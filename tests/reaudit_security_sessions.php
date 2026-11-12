<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../app/Config/database.php';
$dir=__DIR__.'/.runtime';ini_set('session.save_path',$dir);
$q=somi_db()->query("SELECT u.user_id FROM users u WHERE account_status='ACTIVE' AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id AND r.role_code='CUSTOMER') AND NOT EXISTS(SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id AND r.role_code='ADMIN') LIMIT 1");
$id=$q->fetchColumn();if (!$id) exit(2);
$fixtures=[];
foreach(['expired','missing_timestamp','forged_admin'] as $case) {
 session_id(bin2hex(random_bytes(24)));session_start();
 $_SESSION=['somi_user_id'=>(int)$id,'somi_role'=>$case==='forged_admin'?'admin':'customer','somi_authenticated_at'=>$case==='missing_timestamp'?0:time(),'somi_last_activity'=>$case==='expired'?time()-1900:time()];
 $fixtures[$case]=session_id();session_write_close();
}
file_put_contents($dir.'/security-fixtures.json',json_encode($fixtures));
