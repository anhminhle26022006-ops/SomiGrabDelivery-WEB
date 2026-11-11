<?php
declare(strict_types=1);
require_once __DIR__.'/InteractionService.php';
require_once __DIR__.'/EncryptedPayload.php';
require_once __DIR__.'/EmailService.php';

/** Caller owns a transaction. Failed OTP attempts return errors so increments commit. */
final class ShipperBankService
{
    public function __construct(private PDO $db) {}
    private function rows(string $sql,array $args=[]):array {$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();}
    public function current(int $user):array {
        $r=$this->rows("SELECT bank_account_id,bank_code,bank_name,account_holder,verified_at,convert_from(account_number_enc,'UTF8') AS encrypted FROM shipper_bank_accounts WHERE shipper_id=? AND replaced_at IS NULL",[$user])[0]??null;
        if(!$r)return ['bank_account'=>null];$p=EncryptedPayload::open($r['encrypted']);unset($r['encrypted']);$r['account_number_masked']='••••'.substr($p['account_number'],-4);return ['bank_account'=>$r];
    }
    public function request(int $user,array $in):array {
        DeliveryInput::only($in,['bank_code','bank_name','account_holder','account_number','current_password','client_key']);
        $bank=['bank_code'=>DeliveryInput::text($in['bank_code']??null,'bank_code',40),'bank_name'=>DeliveryInput::text($in['bank_name']??null,'bank_name',120),'account_holder'=>DeliveryInput::text($in['account_holder']??null,'account_holder',150),'account_number'=>DeliveryInput::text($in['account_number']??null,'account_number',34)];
        if(!preg_match('/^[0-9]{6,34}$/D',$bank['account_number']))DeliveryInput::invalid('account_number');
        $password=DeliveryInput::text($in['current_password']??null,'current_password',1024);$key=InteractionService::key($in);
        $u=$this->rows('SELECT password_hash,email_verified_at,account_status FROM users WHERE user_id=? FOR UPDATE',[$user])[0]??null;
        if(!$u||$u['account_status']!=='ACTIVE'||!$u['email_verified_at'])throw new DeliveryApiError(403,'VERIFIED_ACCOUNT_REQUIRED','Verified active account required.');
        // This counter commits even when credentials fail; the API returns the error object.
        $bucket=hash('sha256','bank-change:'.$user);
        $rate=$this->rows("INSERT INTO auth_rate_limits(bucket_hash,attempts) VALUES(?,1) ON CONFLICT(bucket_hash) DO UPDATE SET attempts=CASE WHEN auth_rate_limits.window_started_at<now()-interval '15 minutes' THEN 1 ELSE auth_rate_limits.attempts+1 END,window_started_at=CASE WHEN auth_rate_limits.window_started_at<now()-interval '15 minutes' THEN now() ELSE auth_rate_limits.window_started_at END RETURNING attempts",[$bucket])[0];
        if((int)$rate['attempts']>10)return ['code'=>'RATE_LIMITED','error'=>'Too many attempts.'];
        if(!password_verify($password,$u['password_hash']))return ['code'=>'PASSWORD_INCORRECT','error'=>'Current password is incorrect.'];
        $hash=hash('sha256',json_encode($bank,JSON_THROW_ON_ERROR));
        $old=$this->rows('SELECT request_id,request_hash,expires_at FROM bank_account_changes WHERE shipper_id=? AND client_key=?',[$user,$key])[0]??null;
        if($old){if(!hash_equals($old['request_hash'],$hash))throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return ['request_id'=>$old['request_id'],'expires_at'=>$old['expires_at'],'delivery_mode'=>'EMAIL_QUEUE'];}
        if($this->rows("SELECT 1 FROM bank_account_changes WHERE shipper_id=? AND created_at>now()-interval '60 seconds'",[$user]))return ['code'=>'OTP_COOLDOWN','error'=>'Wait before requesting another code.'];
        if((int)$this->rows("SELECT count(*) AS n FROM bank_account_changes WHERE shipper_id=? AND created_at>now()-interval '1 hour'",[$user])[0]['n']>=5)return ['code'=>'OTP_LIMIT','error'=>'Hourly OTP request limit reached.'];
        $this->db->prepare('UPDATE bank_account_changes SET consumed_at=now(),encrypted_payload=NULL WHERE shipper_id=? AND consumed_at IS NULL')->execute([$user]);
        $code=(string)random_int(100000,999999);
        $r=$this->rows("INSERT INTO bank_account_changes(shipper_id,client_key,request_hash,encrypted_payload,token_hash,expires_at) VALUES(?,?,?,?,?,now()+interval '5 minutes') RETURNING request_id,expires_at",[$user,$key,$hash,EncryptedPayload::seal($bank),password_hash($code,PASSWORD_DEFAULT)])[0];
        EmailService::enqueue($this->db,$user,'BANK_ACCOUNT_CHANGE','bank-change:'.$r['request_id'],['code'=>$code,'last_four'=>substr($bank['account_number'],-4)],null,300);
        return $r+['delivery_mode'=>'EMAIL_QUEUE'];
    }
    public function confirm(int $user,array $in):array {
        DeliveryInput::only($in,['request_id','code']);$id=DeliveryInput::id($in['request_id']??null);$code=DeliveryInput::text($in['code']??null,'code',6);if(!preg_match('/^[0-9]{6}$/D',$code))DeliveryInput::invalid('code');
        $this->rows('SELECT user_id FROM users WHERE user_id=? FOR UPDATE',[$user]);
        $r=$this->rows('SELECT *,expires_at<=now() AS expired FROM bank_account_changes WHERE request_id=? AND shipper_id=? FOR UPDATE',[$id,$user])[0]??null;
        if(!$r)throw new DeliveryApiError(404,'REQUEST_NOT_FOUND','Bank change request not found.');
        if($r['consumed_at']){if($r['bank_account_id'])return ['bank_account_id'=>$r['bank_account_id'],'confirmed'=>true];throw new DeliveryApiError(409,'OTP_REPLACED','Request a new code.');}
        if($r['expired']||(int)$r['attempts']>=5)throw new DeliveryApiError(409,'OTP_EXPIRED','Code expired or attempt limit reached.');
        if(!password_verify($code,$r['token_hash'])){$this->db->prepare('UPDATE bank_account_changes SET attempts=attempts+1 WHERE request_id=?')->execute([$id]);return ['code'=>'OTP_INCORRECT','error'=>'Code is incorrect.'];}
        $bank=EncryptedPayload::open($r['encrypted_payload']);
        $this->db->prepare('UPDATE shipper_bank_accounts SET replaced_at=now() WHERE shipper_id=? AND replaced_at IS NULL')->execute([$user]);
        $account=$this->rows('INSERT INTO shipper_bank_accounts(shipper_id,bank_code,bank_name,account_number_enc,account_holder,verified_at) VALUES(?,?,?,?,?,now()) RETURNING bank_account_id',[$user,$bank['bank_code'],$bank['bank_name'],EncryptedPayload::seal(['account_number'=>$bank['account_number']]),$bank['account_holder']])[0];
        $this->db->prepare('UPDATE bank_account_changes SET consumed_at=now(),encrypted_payload=NULL,bank_account_id=? WHERE request_id=?')->execute([$account['bank_account_id'],$id]);
        AuditService::record($this->db,$user,'SHIPPER','BANK_ACCOUNT_CHANGED','SHIPPER_BANK_ACCOUNT',(int)$account['bank_account_id']);
        return $account+['confirmed'=>true];
    }
}
