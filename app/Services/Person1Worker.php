<?php
declare(strict_types=1);
require_once __DIR__.'/Person1EventConsumer.php';
require_once __DIR__.'/ResendMailer.php';

final class Person1Worker
{
    private PDO $db;private PaymentProvider $provider;private $mailer;
    public function __construct(PDO $db,?PaymentProvider $provider=null,?callable $mailer=null){$this->db=$db;$this->provider=$provider??new SePayPaymentProvider();$this->mailer=$mailer??static fn($m,$key)=>ResendMailer::send($m['to'],$m['subject'],$m['html'],$m['text'],$key);}
    public function once(): bool
    {
        $job=JobQueue::claim($this->db);if(!$job)return false;
        try{$this->execute($job);JobQueue::finish($this->db,$job);}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();JobQueue::finish($this->db,$job,$e instanceof DeliveryApiError?$e->errorCode:get_class($e));}
        return true;
    }
    public function execute(array $job): void
    {
        $data=$job['payload'];$kind=$job['kind'];
        if($kind==='EMAIL'){$this->email((int)$data['email_id']);return;}
        if(in_array($kind,['PAYMENT_CHECKOUT','PAYMENT_RECONCILE'],true)){
            $q=$this->db->prepare('SELECT * FROM payments WHERE payment_id=?');$q->execute([$data['payment_id']]);$p=$q->fetch();if(!$p)throw new RuntimeException('Payment missing.');if($p['provider']==='DEMO')return;
            if(in_array($p['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true))return;
            if($kind==='PAYMENT_RECONCILE'){
                $event=$this->provider->reconcile($p);
                if(!in_array($event['status']??null,['PAID','FAILED'],true))throw new DeliveryApiError(503,'PAYMENT_STILL_PENDING','Payment confirmation is still pending.');
                $this->db->beginTransaction();(new PaymentService($this->db,$this->provider))->applyVerified($event,$this->provider->name());$this->db->commit();return;
            }
            $q=$this->db->prepare('SELECT status FROM orders WHERE order_id=?');$q->execute([$p['order_id']]);if($q->fetchColumn()==='CANCELLED')return;
            $result=$this->provider->checkout($p,'checkout:'.$p['payment_id']);$ref=DeliveryInput::text($result['provider_payment_ref']??null,'provider_payment_ref',180);$url=DeliveryInput::text($result['checkout_url']??null,'checkout_url',2000);
            if(parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_USER)!==null)throw new DeliveryApiError(503,'INVALID_PROVIDER_RESULT','Provider returned an invalid checkout URL.');
            $this->db->beginTransaction();OrderLifecycleService::lock($this->db,(int)$p['order_id']);$q=$this->db->prepare('SELECT * FROM payments WHERE payment_id=? FOR UPDATE');$q->execute([$p['payment_id']]);$current=$q->fetch();
            if(!in_array($current['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)){$this->db->prepare("UPDATE payments SET provider_payment_ref=?,checkout_url=?,status='PROCESSING',updated_at=now() WHERE payment_id=?")->execute([$ref,$url,$p['payment_id']]);}
            elseif($current['provider_payment_ref']!==$ref)throw new DeliveryApiError(409,'PAYMENT_REFERENCE_CONFLICT','Provider checkout reference conflict.');
            JobQueue::enqueue($this->db,'PAYMENT_RECONCILE','reconcile:'.$p['payment_id'],['payment_id'=>(string)$p['payment_id']]);$this->db->commit();return;
        }
        if($kind==='REFUND'){
            $q=$this->db->prepare('SELECT * FROM refunds WHERE refund_id=?');$q->execute([$data['refund_id']]);$r=$q->fetch();if(!$r)throw new RuntimeException('Refund missing.');if($r['status']==='SUCCEEDED')return;
            $q=$this->db->prepare('SELECT * FROM payments WHERE payment_id=?');$q->execute([$r['payment_id']]);$p=$q->fetch();
            if($p['provider']==='DEMO'){$this->db->prepare("UPDATE refunds SET status='PROCESSING',failure_code='DEMO_NO_MONEY_MOVED' WHERE refund_id=? AND status<>'SUCCEEDED'")->execute([$r['refund_id']]);return;}
            if($p['provider']==='MANUAL_BANK'){$this->db->prepare("UPDATE refunds SET status='PROCESSING',failure_code='MANUAL_ORIGINAL_METHOD_REQUIRED' WHERE refund_id=? AND status<>'SUCCEEDED'")->execute([$r['refund_id']]);return;}
            if($p['provider']==='SEPAY'){$this->db->prepare("UPDATE refunds SET status='PROCESSING',failure_code='SEPAY_REFUND_REVIEW_REQUIRED' WHERE refund_id=? AND status<>'SUCCEEDED'")->execute([$r['refund_id']]);return;}
            $event=$this->provider->refund($r,$p,'refund:'.$r['refund_id']);
            if(!in_array($event['status']??null,['SUCCEEDED','FAILED'],true))throw new DeliveryApiError(503,'REFUND_STILL_PENDING','Refund is awaiting provider confirmation.');
            $this->db->beginTransaction();(new PaymentService($this->db,$this->provider))->applyVerified($event,$this->provider->name());$this->db->commit();return;
        }
        throw new RuntimeException('Unsupported job type.');
    }
    private function email(int $id): void
    {
        $q=$this->db->prepare('SELECT *,expires_at>clock_timestamp() AS valid FROM email_messages WHERE email_id=?');$q->execute([$id]);$m=$q->fetch();if(!$m||$m['status']==='ACCEPTED')return;
        if(!$m['valid']){
            $this->db->prepare("UPDATE email_messages SET status='EXPIRED',encrypted_payload=NULL WHERE email_id=? AND status<>'ACCEPTED'")->execute([$id]);$this->db->prepare("INSERT INTO email_delivery_attempts(email_id,result) VALUES (?,'EXPIRED')")->execute([$id]);return;
        }
        try{
            $payload=EncryptedPayload::open($m['encrypted_payload']);$providerId=($this->mailer)($payload,'somi-email/'.$id.'/'.hash('sha256',$m['encrypted_payload']));
            $this->db->beginTransaction();$this->db->prepare("UPDATE email_messages SET status='ACCEPTED',provider_message_id=?,accepted_at=now(),encrypted_payload=NULL WHERE email_id=?")->execute([$providerId,$id]);$this->db->prepare("INSERT INTO email_delivery_attempts(email_id,result) VALUES (?,'ACCEPTED')")->execute([$id]);$this->db->commit();
        }catch(Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            $this->db->prepare("UPDATE email_messages SET status='FAILED' WHERE email_id=? AND status<>'ACCEPTED'")->execute([$id]);$this->db->prepare("INSERT INTO email_delivery_attempts(email_id,result,error_code) VALUES (?,'FAILED',?)")->execute([$id,get_class($e)]);throw $e;
        }
    }
}
