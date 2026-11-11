<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/SupabaseObjectStorage.php';
$checks=0;
function check(bool $ok,string $label):void {global $checks;$checks++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
function rejects(callable $fn,string $code):void {try{$fn();}catch(DeliveryApiError $e){check($e->errorCode===$code,'reject '.$code);return;}throw new RuntimeException('Expected '.$code);}
putenv('SUPABASE_URL=https://fixture.supabase.co');putenv('SUPABASE_SECRET_KEY=sb_secret_'.str_repeat('x',40));putenv('SUPABASE_STORAGE_BUCKET=somi-private');
$requests=[];$objects=[];$public=false;$fail=false;$missing=false;
$client=new SupabaseObjectStorage(function($method,$path,$body,$headers)use(&$requests,&$objects,&$public,&$fail,&$missing):array {
    $requests[]=[$method,$path,$body,$headers];
    if($fail)return ['status'=>500,'body'=>'secret-provider-detail'];
    if($path==='/bucket/somi-private')return ['status'=>200,'body'=>json_encode(['id'=>'somi-private','public'=>$public])];
    if($path==='/bucket')return ['status'=>201,'body'=>'{}'];
    if($method==='POST'&&str_starts_with($path,'/object/somi-private/')){$objects[basename($path)]=$body;return ['status'=>200,'body'=>'{}'];}
    if($method==='GET'&&str_starts_with($path,'/object/authenticated/'))return $missing?['status'=>400,'body'=>'{"code":"NoSuchKey"}']:['status'=>200,'body'=>$objects[basename($path)]??''];
    if($method==='DELETE'){foreach(json_decode($body,true)['prefixes'] as $key)unset($objects[$key]);return ['status'=>200,'body'=>'[]'];}
    if($path==='/object/list/somi-private')return ['status'=>200,'body'=>'[]'];
    throw new RuntimeException('Unexpected request.');
});
try{
    $key=str_repeat('a',64);$client->put($key,'private fixture','application/pdf');
    check($client->get($key)==='private fixture','authenticated object roundtrip');
    check($requests[1][0]==='POST'&&$requests[1][1]==='/object/somi-private/'.$key,'upload URL');
    check(in_array('x-upsert: false',$requests[1][3],true),'never overwrite existing objects');
    check(in_array('Content-Type: application/pdf',$requests[1][3],true),'preserve mime');
    check(in_array('apikey: '.getenv('SUPABASE_SECRET_KEY'),$requests[1][3],true),'secret uses apikey header');
    check(count(array_filter($requests[1][3],fn($h)=>str_starts_with($h,'Authorization:')))===0,'opaque secret is not a JWT');
    check(count(array_filter($requests,fn($r)=>$r[1]==='/bucket/somi-private'))===1,'verify privacy once per client');
    rejects(fn()=>$client->get('../escape'),'STORAGE_OBJECT_INVALID');
    rejects(fn()=>$client->put($key,str_repeat('x',5242881),'application/pdf'),'FILE_SIZE_INVALID');
    $missing=true;rejects(fn()=>$client->get($key),'FILE_NOT_FOUND');$missing=false;
    $client->delete($key);check(!$objects,'delete objects through Storage API');
    check($client->objects(100)===[],'empty object list');
    check(json_decode(end($requests)[2],true)['offset']===100,'listing pagination');
    $fail=true;rejects(fn()=>$client->delete($key),'STORAGE_UNAVAILABLE');$fail=false;
    $public=true;
    $privateClient=new SupabaseObjectStorage(fn()=>['status'=>200,'body'=>'{"id":"somi-private","public":true}']);
    rejects(fn()=>$privateClient->check(),'STORAGE_NOT_PRIVATE');$public=false;
    $client->createBucket();check(json_decode($requests[count($requests)-1][2],true)['public']===false,'bucket creation private');
    putenv('SUPABASE_SECRET_KEY=sb_publishable_'.str_repeat('p',40));rejects(fn()=>new SupabaseObjectStorage(),'STORAGE_NOT_CONFIGURED');
    putenv('SUPABASE_SECRET_KEY=sb_secret_'.str_repeat('x',40));
    foreach(['http://fixture.supabase.co','https://fixture.supabase.co/evil','https://fixture.supabase.co@evil.test','https://127.0.0.1','https://fixture.supabase.co?x=1'] as $url){putenv('SUPABASE_URL='.$url);rejects(fn()=>new SupabaseObjectStorage(),'STORAGE_NOT_CONFIGURED');}
    putenv('SUPABASE_URL=https://fixture.supabase.co');
    $claims=rtrim(strtr(base64_encode('{"role":"service_role"}'),'+/','-_'),'=');$legacy='eyJhbGciOiJIUzI1NiJ9.'.$claims.'.'.str_repeat('s',43);putenv('SUPABASE_SECRET_KEY='.$legacy);
    $legacyClient=new SupabaseObjectStorage(function($method,$path,$body,$headers)use($legacy){check(in_array('Authorization: Bearer '.$legacy,$headers,true),'legacy JWT bearer');return ['status'=>200,'body'=>'{"id":"somi-private","public":false}'];});$legacyClient->check();
    echo "Supabase storage: $checks checks passed; external requests=0.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
