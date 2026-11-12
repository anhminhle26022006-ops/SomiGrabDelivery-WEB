<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Services/SupabaseStorage.php';
$checks=0;function check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function rejects(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){check($e->errorCode===$code,$code);return;}throw new RuntimeException('Expected '.$code);}
$vars=['SUPABASE_URL','SUPABASE_SECRET_KEY','SUPABASE_SERVICE_ROLE_KEY','SUPABASE_STORAGE_BUCKET'];$saved=[];foreach($vars as $v)$saved[$v]=getenv($v);
$path=tempnam(sys_get_temp_dir(),'somi-supabase-');file_put_contents($path,'%PDF-fixture');
try{
 putenv('SUPABASE_URL=https://fixtureproject.supabase.co');putenv('SUPABASE_SECRET_KEY=sb_secret_fixture_no_external_requests');putenv('SUPABASE_STORAGE_BUCKET=somi-private');
 $objects=[];$calls=[];$exists=false;$public=false;
 $http=static function($method,$url,$body,$headers,$max)use(&$objects,&$calls,&$exists,&$public):array {
  $calls[]=[$method,$url,$headers];
  if($url==='/bucket/somi-private')return ['status'=>$exists?200:400,'body'=>$exists?json_encode(['id'=>'somi-private','public'=>$public]):'{"statusCode":"404","error":"Bucket not found"}'];
  if($url==='/bucket'&&$method==='POST'){$b=json_decode($body,true);check($b['public']===false&&$b['file_size_limit']===5242880,'private bucket limits');$exists=true;return ['status'=>200,'body'=>'{}'];}
  $key=basename($url);if($method==='POST'){$objects[$key]=$body;return ['status'=>200,'body'=>'{}'];}
  if($method==='GET')return ['status'=>isset($objects[$key])?200:404,'body'=>$objects[$key]??''];
  if($method==='DELETE'){unset($objects[$key]);return ['status'=>200,'body'=>'{}'];}throw new RuntimeException('Unexpected request');
 };
 $storage=new SupabaseStorage(null,$http);rejects(fn()=>$storage->checkBucket(),'SUPABASE_STORAGE_UNAVAILABLE');
 check($storage->checkBucket(true)['private'],'explicit private bucket initialization');
 $key=str_repeat('a',64);$storage->put($key,$path,'application/pdf');check($storage->get($key)==='%PDF-fixture','remote upload and authenticated download');
 check(str_contains($calls[count($calls)-1][1],'/object/authenticated/'),'download endpoint is authenticated');
 check(!array_filter($calls[0][2],fn($h)=>str_starts_with($h,'Authorization:')),'new secret keys are not Bearer JWTs');
 $storage->delete($key);check(!isset($objects[$key]),'remote object deletion');check(!array_filter(end($calls)[2],fn($h)=>str_starts_with($h,'Content-Type:')),'bodyless DELETE has no JSON content type');rejects(fn()=>$storage->get($key),'FILE_NOT_FOUND');
 rejects(fn()=>$storage->put('../unsafe',$path,'application/pdf'),'OBJECT_KEY_INVALID');
 $public=true;rejects(fn()=>$storage->get($key),'PRIVATE_BUCKET_REQUIRED');$public=false;
 putenv('SUPABASE_SECRET_KEY=sb_publishable_fixture');rejects(fn()=>new SupabaseStorage(null,$http),'SUPABASE_SERVER_KEY_REQUIRED');
 putenv('SUPABASE_SECRET_KEY=');$payload=rtrim(strtr(base64_encode(json_encode(['role'=>'service_role'])),'+/','-_'),'=');putenv('SUPABASE_SERVICE_ROLE_KEY=header.'.$payload.'.fixture');
 $legacy=new SupabaseStorage(null,$http);$legacy->checkBucket();check(count(array_filter(end($calls)[2],fn($h)=>str_starts_with($h,'Authorization: Bearer ')))===1,'legacy service JWT uses Bearer');
 putenv('SUPABASE_URL=https://example.invalid');rejects(fn()=>new SupabaseStorage(null,$http),'SUPABASE_STORAGE_NOT_CONFIGURED');
 echo "Supabase Storage: $checks checks passed; zero external requests.\n";
}finally{unlink($path);foreach($saved as $v=>$value)putenv($value===false?$v:$v.'='.$value);}
