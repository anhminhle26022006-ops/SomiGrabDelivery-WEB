<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryApiError.php';

/** Server-side private Storage. The transport seam is used only by isolated tests. */
final class SupabaseStorage
{
    private string $url;private string $key;private string $bucket;private $transport;
    public function __construct(?string $bucket=null,?callable $transport=null){
        $this->url=rtrim(getenv('SUPABASE_URL')?:'','/');$this->key=getenv('SUPABASE_SECRET_KEY')?:getenv('SUPABASE_SERVICE_ROLE_KEY')?:'';
        $this->bucket=$bucket??(getenv('SUPABASE_STORAGE_BUCKET')?:'somi-private');$this->transport=$transport;
        if(!preg_match('~^https://[a-z0-9-]+\.supabase\.co$~D',$this->url)||$this->key===''||preg_match('/[\r\n]/',$this->key)||!preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/D',$this->bucket))self::unavailable('SUPABASE_STORAGE_NOT_CONFIGURED');
        if(str_starts_with($this->key,'sb_publishable_'))self::unavailable('SUPABASE_SERVER_KEY_REQUIRED');
        if(!str_starts_with($this->key,'sb_secret_')){
            $parts=explode('.',$this->key);$payload=count($parts)===3?json_decode((string)base64_decode(strtr($parts[1],'-_','+/')),true):null;
            if(($payload['role']??null)!=='service_role')self::unavailable('SUPABASE_SERVER_KEY_REQUIRED');
        }
    }
    public function bucket():string{return $this->bucket;}
    private static function unavailable(string $code='SUPABASE_STORAGE_UNAVAILABLE'):void{throw new DeliveryApiError(503,$code,'Private Supabase Storage is unavailable. Check server configuration and bucket access.');}
    private static function missing(array $response):bool {
        if($response['status']===404)return true;
        $error=json_decode($response['body'],true);
        return $response['status']===400&&is_array($error)&&(string)($error['statusCode']??'')==='404';
    }
    private function object(string $key,bool $authenticated=false):string {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DeliveryApiError(422,'OBJECT_KEY_INVALID','Invalid storage reference.');
        return '/object/'.($authenticated?'authenticated/':'').rawurlencode($this->bucket).'/'.$key;
    }
    private function request(string $method,string $path,?string $body=null,string $mime='application/json',int $maxBytes=65536):array {
        $headers=['apikey: '.$this->key,'x-upsert: false'];
        if($body!==null)$headers[]='Content-Type: '.$mime;
        // New sb_secret keys belong only in apikey; legacy service_role JWTs also use Bearer.
        if(!str_starts_with($this->key,'sb_secret_'))$headers[]='Authorization: Bearer '.$this->key;
        try{
            if($this->transport)return ($this->transport)($method,$path,$body,$headers,$maxBytes);
            $bytes='';$curl=curl_init($this->url.'/storage/v1'.$path);
            curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$bytes,$maxBytes):int{if(strlen($bytes)+strlen($chunk)>$maxBytes)return 0;$bytes.=$chunk;return strlen($chunk);}]);
            if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$body);$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);if($ok===false)self::unavailable();return ['status'=>$status,'body'=>$bytes];
        }catch(DeliveryApiError $e){throw $e;}catch(Throwable $e){self::unavailable();}
    }
    public function checkBucket(bool $create=false):array {
        $r=$this->request('GET','/bucket/'.rawurlencode($this->bucket));
        if(self::missing($r)&&$create){$r=$this->request('POST','/bucket',json_encode(['id'=>$this->bucket,'name'=>$this->bucket,'public'=>false,'file_size_limit'=>5242880,'allowed_mime_types'=>['image/jpeg','image/png','image/webp','application/pdf']],JSON_THROW_ON_ERROR));if(!in_array($r['status'],[200,201],true))self::unavailable();$r=$this->request('GET','/bucket/'.rawurlencode($this->bucket));}
        if($r['status']!==200)self::unavailable();$b=json_decode($r['body'],true);
        if(!is_array($b)||($b['public']??null)!==false||($b['id']??$b['name']??null)!==$this->bucket)self::unavailable('PRIVATE_BUCKET_REQUIRED');
        return ['bucket'=>$this->bucket,'private'=>true];
    }
    public function put(string $key,string $path,string $mime):void {
        $this->checkBucket();$body=file_get_contents($path);if($body===false||strlen($body)>5242880)self::unavailable();
        $r=$this->request('POST',$this->object($key),$body,$mime);if(!in_array($r['status'],[200,201],true))self::unavailable();
    }
    public function get(string $key):string {
        $this->checkBucket();$r=$this->request('GET',$this->object($key,true),null,'application/json',5242880);
        if(self::missing($r))throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');if($r['status']!==200)self::unavailable();return $r['body'];
    }
    public function delete(string $key):void {
        $r=$this->request('DELETE',$this->object($key));if(!in_array($r['status'],[200,204],true)&&!self::missing($r))self::unavailable();
    }
}
