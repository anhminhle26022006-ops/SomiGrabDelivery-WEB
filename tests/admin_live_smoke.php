<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/AdminConsoleService.php';
try {
 $db=somi_db();$q=$db->query("SELECT u.user_id FROM users u JOIN user_roles ur USING(user_id) JOIN roles r USING(role_id) WHERE r.role_code='ADMIN' AND u.account_status='ACTIVE' ORDER BY u.user_id LIMIT 1");$id=$q->fetchColumn();
 if(!$id){echo "NO_ACTIVE_ADMIN; provision via the existing protected admin procedure.\n";exit(1);}
 $s=new AdminConsoleService($db);
 foreach(['overview'=>fn()=>$s->overview((int)$id),'accounts'=>fn()=>$s->accounts((int)$id,[]),'account'=>fn()=>$s->account((int)$id,(int)$id),'tickets'=>fn()=>$s->tickets((int)$id,[]),'reviews'=>fn()=>$s->reviews((int)$id,[]),'notifications'=>fn()=>$s->notifications((int)$id,[])]as $name=>$read){
  $db->beginTransaction();try{$read();$db->rollBack();echo $name."=PASS\n";}catch(Throwable $e){$db->rollBack();echo $name.'=FAIL; '.($e instanceof DeliveryApiError?$e->errorCode:somi_database_diagnostic($e)).PHP_EOL;exit(1);}
 }
 echo "Live Supabase reads passed; all transactions rolled back; no PII printed.\n";
}catch(Throwable $e){echo 'LIVE_CHECK_FAILED; '.somi_database_diagnostic($e).PHP_EOL;exit(1);}
