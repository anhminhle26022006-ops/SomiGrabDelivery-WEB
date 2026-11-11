<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryInput.php';

/** Server-only Supabase Storage client. No keys or provider URLs reach the browser. */
final class SupabaseObjectStorage
{
    private string $url;
    private string $key;
    private string $bucket;
    private bool $verified=false;
    public function __construct(private mixed $transport=null)
    {
        $this->url=rtrim(getenv('SUPABASE_URL')?:'','/');
        $this->key=getenv('SUPABASE_SECRET_KEY')?:'';
        $this->bucket=getenv('SUPABASE_STORAGE_BUCKET')?:'somi-private';
        $parts=parse_url($this->url);
        if(!$parts||($parts['scheme']??'')!=='https'||!preg_match('/^[a-z0-9-]+\.supabase\.co$/D',$parts['host']??'')||isset($parts['port'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||($parts['path']??'')!==''||!preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/D',$this->bucket))
            throw new DeliveryApiError(503,'STORAGE_NOT_CONFIGURED','Set the Supabase project HTTPS URL and private bucket name.');
        $legacy=false;
        $segments=explode('.',$this->key);
        if(count($segments)===3){$claims=json_decode(base64_decode(strtr($segments[1],'-_','+/')),true);$legacy=($claims['role']??null)==='service_role';}
        if(preg_match('/[\x00-\x20\x7f]/',$this->key)||(!str_starts_with($this->key,'sb_secret_')&&!$legacy)||strlen($this->key)<32)
            throw new DeliveryApiError(503,'STORAGE_NOT_CONFIGURED','Set a server-only Supabase secret key or legacy service_role key.');
    }
    private function request(string $method,string $path,?string $body=null,string $mime='application/json',int $maximum=5242880):array
    {
        $headers=['apikey: '.$this->key,'Content-Type: '.$mime,'x-upsert: false'];
        if(!str_starts_with($this->key,'sb_secret_'))$headers[]='Authorization: Bearer '.$this->key;
        if($this->transport)return ($this->transport)($method,$path,$body,$headers);
        $result='';$curl=curl_init($this->url.'/storage/v1'.$path);
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$result,$maximum):int{if(strlen($result)+strlen($chunk)>$maximum)return 0;$result.=$chunk;return strlen($chunk);}]);
        if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$body);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($ok===false)throw new DeliveryApiError(503,'STORAGE_UNAVAILABLE','Private storage request failed. Please retry.');
        return ['status'=>$status,'body'=>$result];
    }
    private function success(array $response):void
    {
        if($response['status']<200||$response['status']>=300)throw new DeliveryApiError(503,'STORAGE_UNAVAILABLE','Private storage request was rejected. Check server configuration.');
    }
    public function check():void
    {
        if($this->verified)return;
        $r=$this->request('GET','/bucket/'.rawurlencode($this->bucket));$this->success($r);
        $bucket=json_decode($r['body'],true);
        if(!is_array($bucket)||($bucket['public']??null)!==false||($bucket['id']??null)!==$this->bucket)throw new DeliveryApiError(503,'STORAGE_NOT_PRIVATE','The SOMI storage bucket must be private.');
        $this->verified=true;
    }
    public function createBucket():void
    {
        $r=$this->request('POST','/bucket',json_encode(['id'=>$this->bucket,'name'=>$this->bucket,'public'=>false,'file_size_limit'=>5242880,'allowed_mime_types'=>['image/jpeg','image/png','image/webp','application/pdf']],JSON_THROW_ON_ERROR));
        if(!in_array($r['status'],[200,201,400,409],true))$this->success($r);
        $this->check();
    }
    private function objectPath(string $key):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DeliveryApiError(503,'STORAGE_OBJECT_INVALID','Invalid private object reference.');
        return '/'.rawurlencode($this->bucket).'/'.$key;
    }
    public function put(string $key,string $bytes,string $mime):void
    {
        $path=$this->objectPath($key);$this->check();
        if(strlen($bytes)<1||strlen($bytes)>5242880)throw new DeliveryApiError(422,'FILE_SIZE_INVALID','Maximum file size is 5 MiB.');
        $this->success($this->request('POST','/object'.$path,$bytes,$mime));
    }
    public function get(string $key):string
    {
        $path=$this->objectPath($key);$this->check();$r=$this->request('GET','/object/authenticated'.$path);
        $error=json_decode($r['body'],true);
        if($r['status']===404||($error['code']??null)==='NoSuchKey'||($error['statusCode']??null)==='404')throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');
        $this->success($r);return $r['body'];
    }
    public function delete(string $key):void
    {
        $this->objectPath($key);$this->check();
        $this->success($this->request('DELETE','/object/'.rawurlencode($this->bucket),json_encode(['prefixes'=>[$key]],JSON_THROW_ON_ERROR)));
    }
    /** Bounded list used only for optional cleanup of old, unreferenced random keys. */
    public function objects(int $offset):array
    {
        $this->check();$r=$this->request('POST','/object/list/'.rawurlencode($this->bucket),json_encode(['prefix'=>'','limit'=>100,'offset'=>$offset,'sortBy'=>['column'=>'name','order'=>'asc']],JSON_THROW_ON_ERROR));$this->success($r);
        $items=json_decode($r['body'],true);if(!is_array($items)||($items!==[]&&array_keys($items)!==range(0,count($items)-1)))throw new DeliveryApiError(503,'STORAGE_UNAVAILABLE','Invalid storage list response.');return $items;
    }
}
