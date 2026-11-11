<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/PrivateStorageService.php';
$checks=[];
foreach(['pdo_pgsql','mbstring','bcmath','fileinfo','openssl','curl'] as $extension)$checks['extension_'.$extension]=extension_loaded($extension);
$checks['payout_mode_manual']=getenv('SOMI_PAYOUT_MODE')==='manual';
$checks['storage_signing_key']=strlen(getenv('SOMI_STORAGE_SIGNING_KEY')?:'')>=32;
try {
    if(PrivateStorageService::provider()==='supabase'){
        (new SupabaseObjectStorage())->check();$checks['private_storage']=true;
    }else{
        $root=getenv('SOMI_STORAGE_ROOT')?:'';$real=$root!==''?realpath($root):false;$project=realpath(__DIR__.'/..');
        $checks['private_storage']=$real&&is_dir($real)&&is_writable($real)&&!str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower(str_replace('\\','/',$project)).'/');
    }
}catch(Throwable $error){$checks['private_storage']=false;}
$key=base64_decode(getenv('SOMI_MESSAGE_ENCRYPTION_KEY')?:getenv('SOMI_IDENTITY_ENCRYPTION_KEY')?:'',true);$checks['encryption_key']=$key!==false&&strlen($key)===32;
$checks['email_provider']=(getenv('RESEND_API_KEY')?:'')!=='';
try{$db=somi_db();$q=$db->query("SELECT count(*) FROM somi.backend_migrations WHERE version IN ('20261111_person3','20261111_person3_complete')");$checks['migrations_applied']=(int)$q->fetchColumn()===2;}catch(Throwable $e){$checks['migrations_applied']=false;}
echo json_encode(['checks'=>$checks,'ready'=>!in_array(false,$checks,true)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
