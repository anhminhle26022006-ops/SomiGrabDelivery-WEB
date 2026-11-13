<?php
declare(strict_types=1);
header('Cache-Control: no-store');
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/EncryptedPayload.php';
require_once __DIR__.'/../../../app/Services/AuditService.php';
require_once __DIR__.'/../../../app/Services/DemoMode.php';
somi_run_api(['GET','POST'],['CUSTOMER'],'order.read_own',true,static function(PDO $db,int $id):array{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $in=somi_json_body();DeliveryInput::only($in,['bank_name','account_holder','account_number','current_password']);
        $bank=['bank_name'=>DeliveryInput::text($in['bank_name']??null,'bank_name',120),'account_holder'=>DeliveryInput::text($in['account_holder']??null,'account_holder',150),'account_number'=>DeliveryInput::text($in['account_number']??null,'account_number',34)];
        if(!preg_match('/^[0-9]{6,34}$/D',$bank['account_number']))DeliveryInput::invalid('account_number');
        $q=$db->prepare('SELECT password_hash,email FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch();
        if(DemoMode::email($u['email']))DemoMode::denyTransfer();
        $q=$db->prepare("INSERT INTO auth_rate_limits(bucket_hash,attempts) VALUES(?,1) ON CONFLICT(bucket_hash) DO UPDATE SET attempts=CASE WHEN auth_rate_limits.window_started_at<now()-interval '15 minutes' THEN 1 ELSE auth_rate_limits.attempts+1 END,window_started_at=CASE WHEN auth_rate_limits.window_started_at<now()-interval '15 minutes' THEN now() ELSE auth_rate_limits.window_started_at END RETURNING attempts");$q->execute([hash('sha256','customer-bank:'.$id)]);
        if((int)$q->fetchColumn()>10)return [429,['error'=>'Too many attempts.']];
        if(!is_string($in['current_password']??null)||!password_verify($in['current_password'],$u['password_hash']))return [422,['error'=>'Current password is incorrect.']];
        $q=$db->prepare("INSERT INTO customer_bank_accounts(customer_id,bank_name,account_holder,encrypted_payload,review_status) VALUES(?,?,?,?,'PENDING') RETURNING bank_account_id");$q->execute([$id,$bank['bank_name'],$bank['account_holder'],EncryptedPayload::seal($bank)]);
        AuditService::record($db,$id,'CUSTOMER','REFUND_BANK_SAVED','CUSTOMER_BANK_ACCOUNT',(int)$q->fetchColumn());
    }
    $q=$db->prepare('SELECT * FROM customer_bank_accounts WHERE customer_id=? ORDER BY bank_account_id DESC LIMIT 1');$q->execute([$id]);$b=$q->fetch();
    if($b){try{$payload=EncryptedPayload::open($b['encrypted_payload']);}catch(Throwable $e){$payload=['account_number'=>''];$b['needs_reentry']=true;}unset($b['encrypted_payload']);$b['account_number']=$payload['account_number'];$b['account_number_masked']='••••'.substr($payload['account_number'],-4);}
    return [200,['bank_account'=>$b?:null]];
});
