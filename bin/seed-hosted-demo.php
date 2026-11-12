<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/DemoDatasetService.php';
require_once __DIR__.'/../app/Services/SupabaseStorage.php';
$batch='20261113';$apply=in_array('--apply',$argv,true);$refresh=in_array('--fresh-offers',$argv,true);$target='';
foreach(array_slice($argv,1) as $arg){if(str_starts_with($arg,'--batch='))$batch=substr($arg,8);elseif(str_starts_with($arg,'--target-url='))$target=rtrim(substr($arg,13),'/');elseif(!in_array($arg,['--apply','--fresh-offers'],true)){fwrite(STDERR,"Unknown argument.\n");exit(2);}}
if(!$apply){echo json_encode(['dry_run'=>true,'batch'=>$batch,'accounts'=>8,'orders'=>33,'admin_accounts_created'=>0,'fictional_payments'=>true,'real_money_moved'=>false,'instructions'=>'--apply --target-url=https://your-host; --fresh-offers refreshes expiring offers.'],JSON_PRETTY_PRINT).PHP_EOL;exit;}
if(!preg_match('/^[0-9]{8}$/D',$batch)||$target===''||rtrim(getenv('SOMI_PUBLIC_URL')?:'','/')!==$target){fwrite(STDERR,"Batch and exact configured --target-url are required.\n");exit(2);}
$runtime=__DIR__.'/../tests/.runtime/hosted-demo-'.$batch;if(!is_dir($runtime))mkdir($runtime,0700,true);$secretFile=$runtime.'/credentials.json';
$secret=is_file($secretFile)?json_decode(file_get_contents($secretFile),true):['password'=>'Demo!'.bin2hex(random_bytes(14))];
file_put_contents($secretFile,json_encode($secret,JSON_PRETTY_PRINT));chmod($secretFile,0600);
$objects=[];$storage=null;$db=null;
try{
 $db=somi_db();$required=['20261110_shipper_delivery','20261112_no_shipper_refund','20261112_shipper_policy_workflows'];$q=$db->query('SELECT version FROM backend_migrations');$applied=$q->fetchAll(PDO::FETCH_COLUMN);foreach($required as $version)if(!in_array($version,$applied,true))throw new RuntimeException('Apply versioned backend migrations first.');
 if(!$refresh){$storage=new SupabaseStorage();if(!$storage->checkBucket()['private'])throw new RuntimeException('Private demo bucket required.');}
 $store=static function(string $asset)use(&$storage,&$objects,$runtime):array{
  $path=__DIR__.'/../resources/demo/'.$asset.'.png';if(!in_array($asset,['kyc','delivery','return'],true)||!is_file($path))throw new RuntimeException('Demo image assets missing.');
  $key=bin2hex(random_bytes(32));$storage->put($key,$path,'image/png');$objects[]=$key;file_put_contents($runtime.'/uploaded-objects.json',json_encode($objects));
  return ['object_key'=>$key,'bucket'=>$storage->bucket(),'byte_size'=>filesize($path),'checksum_sha256'=>hash_file('sha256',$path)];
 };
 $db->beginTransaction();$service=new DemoDatasetService($db,$store);$result=$refresh?$service->freshOffers($batch):$service->seed($batch,$secret['password']);$db->commit();
 file_put_contents($runtime.'/'.($refresh?'fresh-offers':'manifest').'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 if(!$refresh){$secret['users']=$result['users'];$secret['url']=$target;file_put_contents($secretFile,json_encode($secret,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));$lines=['# Tài khoản DEMO hosting','','Chỉ dùng để trình diễn. Không có tài khoản Admin mới.','','Website: '.$target,'','Mật khẩu các tài khoản DEMO: `'.$secret['password'].'`','','| Vai trò | Tài khoản |','|---|---|'];foreach($result['users'] as $u)$lines[]='| '.$u['name'].' ('.$u['role'].') | '.$u['email'].' |';file_put_contents($runtime.'/TAI-KHOAN-DEMO.md',implode("\n",$lines)."\n");}
 echo json_encode(['applied'=>true,'demo'=>true,'batch'=>$batch,'accounts'=>$refresh?null:count($result['users']),'orders'=>$refresh?$result:count($result['orders']),'existing'=>$result['existing']??false,'credentials_file'=>$secretFile,'real_money_moved'=>false,'real_messages_sent'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();foreach($objects as $key){try{$storage->delete($key);}catch(Throwable $ignored){}}fwrite(STDERR,'Demo seed failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
