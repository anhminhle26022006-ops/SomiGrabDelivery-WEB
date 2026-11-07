<?php
declare(strict_types=1);
require_once __DIR__.'/SePayPaymentProvider.php';
require_once __DIR__.'/OrderLifecycleService.php';
require_once __DIR__.'/AuditService.php';
require_once __DIR__.'/JobQueue.php';
require_once __DIR__.'/AccessControl.php';

final class PaymentService
{
    private PDO $db; private PaymentProvider $provider;
    public function __construct(PDO $db,?PaymentProvider $provider=null){$this->db=$db;$this->provider=$provider??new SePayPaymentProvider();}
    private function key(string $key): void {if(!preg_match('/^[A-Za-z0-9._:-]{8,100}$/D',$key))DeliveryInput::invalid('Idempotency-Key');}
    public static function money($value): string
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^(0|[1-9][0-9]{0,18})$/D',(string)$value)||bccomp((string)$value,'9223372036854775807')>0)DeliveryInput::invalid('amount_minor');return (string)$value;
    }
    public function create(int $customerId,int $orderId,string $method,string $key): array
    {
        $this->key($key);if(!in_array($method,['GATEWAY','CARD','BANK_TRANSFER'],true))DeliveryInput::invalid('method');
        $order=OrderLifecycleService::lock($this->db,$orderId);
        if((int)$order['customer_id']!==$customerId)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        $q=$this->db->prepare('SELECT * FROM payments WHERE order_id=? FOR UPDATE');$q->execute([$orderId]);$existing=$q->fetch();
        if($existing){if($existing['request_key']!==$key||$existing['method']!==$method)throw new DeliveryApiError(409,'PAYMENT_ALREADY_EXISTS','Use the original payment and idempotency key.');return $this->publicPayment($existing);}
        if($order['status']!=='PENDING_PAYMENT')throw new DeliveryApiError(409,'ORDER_NOT_PAYABLE','Order cannot be paid in its current state.');
        $provider=$this->provider->name();
        if($provider==='SEPAY'&&$order['currency_code']!=='VND')throw new DeliveryApiError(422,'PAYMENT_CURRENCY_UNSUPPORTED','SePay only supports VND.');
        $this->provider->assertConfigured();
        if(bccomp((string)$order['total_charge_minor'],'0')<=0)throw new DeliveryApiError(422,'PAYMENT_AMOUNT_UNSUPPORTED','A positive payment amount is required.');
        $q=$this->db->prepare('INSERT INTO payments(order_id,amount_minor,currency_code,method,provider,request_key) VALUES (?,?,?,?,?,?) RETURNING *');$q->execute([$orderId,$order['total_charge_minor'],$order['currency_code'],$method,$provider,$key]);$payment=$q->fetch();
        if($provider==='SEPAY'){
            $checkout=$this->provider->checkout($payment,'checkout:'.$payment['payment_id']);
            $this->db->prepare("UPDATE payments SET provider_payment_ref=?,checkout_url=?,status='PROCESSING' WHERE payment_id=?")->execute([$checkout['provider_payment_ref'],$checkout['checkout_url'],$payment['payment_id']]);
            $payment=array_replace($payment,$checkout,['status'=>'PROCESSING']);
            JobQueue::enqueue($this->db,'PAYMENT_RECONCILE','reconcile:'.$payment['payment_id'],['payment_id'=>(string)$payment['payment_id']]);
        }else JobQueue::enqueue($this->db,'PAYMENT_CHECKOUT','checkout:'.$payment['payment_id'],['payment_id'=>(string)$payment['payment_id']]);
        return $this->publicPayment($payment);
    }
    public static function bankInstructions(): array
    {
        $out=[];foreach(['bank'=>'SOMI_BANK_NAME','account_number'=>'SOMI_BANK_ACCOUNT','account_name'=>'SOMI_BANK_ACCOUNT_NAME'] as $field=>$env){$v=getenv($env);if(!$v)throw new DeliveryApiError(503,'BANK_NOT_CONFIGURED','Bank transfer is not configured.');$out[$field]=$v;}return $out;
    }
    private function publicPayment(array $p): array
    {
        unset($p['request_key']);if($p['provider']==='MANUAL_BANK')$p['bank_instructions']=self::bankInstructions()+['transfer_reference'=>'SOMI-PAY-'.$p['payment_id']];return $p;
    }
    public function read(int $customerId,int $orderId): ?array
    {
        $q=$this->db->prepare('SELECT p.* FROM payments p JOIN orders o USING(order_id) WHERE o.order_id=? AND o.customer_id=?');$q->execute([$orderId,$customerId]);$p=$q->fetch();return $p?$this->publicPayment($p):null;
    }
    /** Called only after signature verification or audited bank reconciliation. */
    public function applyVerified(array $e,string $provider): array
    {
        $id=DeliveryInput::id($e['payment_id']??null,'payment_id');$external=DeliveryInput::text($e['event_id']??null,'event_id',180);$kind=DeliveryInput::text($e['kind']??null,'kind',20);
        if(!in_array($kind,['PAYMENT','REFUND'],true))DeliveryInput::invalid('kind');
        $q=$this->db->prepare('SELECT order_id FROM payments WHERE payment_id=?');$q->execute([$id]);$orderId=$q->fetchColumn();if(!$orderId)throw new DeliveryApiError(404,'PAYMENT_NOT_FOUND','Payment not found.');
        $order=OrderLifecycleService::lock($this->db,(int)$orderId);
        $q=$this->db->prepare('SELECT * FROM payments WHERE payment_id=? FOR UPDATE');$q->execute([$id]);$p=$q->fetch();
        if($p['provider']!==$provider)throw new DeliveryApiError(409,'PAYMENT_PROVIDER_MISMATCH','Payment provider mismatch.');
        $amount=self::money($e['amount_minor']??null);$currency=$e['currency_code']??null;$status=$e['status']??null;
        $ref=DeliveryInput::text($e['provider_payment_ref']??null,'provider_payment_ref',180);
        if($currency!==$p['currency_code']||($p['provider_payment_ref']!==null&&$p['provider_payment_ref']!==$ref))throw new DeliveryApiError(409,'PAYMENT_MISMATCH','Payment currency or reference mismatch.');
        $canonical=['kind'=>$kind,'payment_id'=>(string)$id,'amount_minor'=>$amount,'currency_code'=>$currency,'status'=>$status,'provider_payment_ref'=>$ref,'refund_id'=>$e['refund_id']??null,'provider_refund_id'=>$e['provider_refund_id']??null];
        $hash=hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR));
        $q=$this->db->prepare('SELECT payload_hash FROM payment_receipts WHERE provider=? AND external_id=?');$q->execute([$provider,$external]);$old=$q->fetchColumn();
        if($old!==false){if(!hash_equals($old,$hash))throw new DeliveryApiError(409,'CALLBACK_CONFLICT','Callback event was reused with different data.');return ['duplicate'=>true,'payment_id'=>$id];}
        if($kind==='PAYMENT'){
            if(!in_array($status,['PAID','FAILED'],true)||bccomp($amount,(string)$p['amount_minor'])!==0)throw new DeliveryApiError(409,'PAYMENT_MISMATCH','Payment status or full amount mismatch.');
            if($status==='PAID'){
                if(!in_array($p['status'],['PARTIALLY_REFUNDED','REFUNDED'],true)){
                    $q=$this->db->prepare("UPDATE payments SET status='PAID',paid_at=COALESCE(paid_at,now()),provider_payment_ref=?,failure_code=NULL,updated_at=now() WHERE payment_id=?");$q->execute([$ref,$id]);
                    OrderLifecycleService::paymentConfirmed($this->db,(int)$orderId,$id);
                }
                OrderLifecycleService::event($this->db,$order,'PaymentConfirmed','payment-confirmed:'.$id,['payment_id'=>(string)$id]);
            }elseif(!in_array($p['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)){$this->db->prepare("UPDATE payments SET status='FAILED',failure_code='PROVIDER_DECLINED',updated_at=now() WHERE payment_id=?")->execute([$id]);}
        }else{
            $refundId=DeliveryInput::id($e['refund_id']??null,'refund_id');$q=$this->db->prepare('SELECT * FROM refunds WHERE refund_id=? AND payment_id=? FOR UPDATE');$q->execute([$refundId,$id]);$refund=$q->fetch();
            if(!$refund||!in_array($status,['SUCCEEDED','FAILED'],true)||bccomp($amount,(string)$refund['refund_amount_minor'])!==0)throw new DeliveryApiError(409,'REFUND_MISMATCH','Refund data mismatch.');
            $refundRef=DeliveryInput::text($e['provider_refund_id']??null,'provider_refund_id',180);
            if($refund['provider_refund_id']!==null&&$refund['provider_refund_id']!==$refundRef)throw new DeliveryApiError(409,'REFUND_MISMATCH','Refund reference mismatch.');
            if($refund['status']!=='SUCCEEDED'){
                $q=$this->db->prepare('UPDATE refunds SET status=?,provider_refund_id=?,completed_at=CASE WHEN ? THEN now() ELSE NULL END,failure_code=? WHERE refund_id=?');$q->execute([$status,$refundRef,$status==='SUCCEEDED'?'true':'false',$status==='FAILED'?'PROVIDER_DECLINED':null,$refundId]);
                if($status==='SUCCEEDED'){
                    $q=$this->db->prepare("SELECT COALESCE(sum(refund_amount_minor),0) FROM refunds WHERE payment_id=? AND status='SUCCEEDED'");$q->execute([$id]);$sum=(string)$q->fetchColumn();
                    $this->db->prepare('UPDATE payments SET status=?,updated_at=now() WHERE payment_id=?')->execute([bccomp($sum,(string)$p['amount_minor'])===0?'REFUNDED':'PARTIALLY_REFUNDED',$id]);
                    OrderLifecycleService::event($this->db,$order,'RefundSucceeded','refund-succeeded:'.$refundId,['payment_id'=>(string)$id,'refund_id'=>(string)$refundId,'refund_amount_minor'=>$amount]);
                }
            }
        }
        $q=$this->db->prepare('INSERT INTO payment_receipts(provider,external_id,payload_hash,payment_id,receipt_kind) VALUES (?,?,?,?,?)');$q->execute([$provider,$external,$hash,$id,$kind]);
        $q=$this->db->prepare("INSERT INTO payment_transactions(payment_id,provider,provider_transaction_id,idempotency_key,transaction_type,amount_minor,currency_code,status) VALUES (?,?,?,?,'CALLBACK',?,?,?)");$q->execute([$id,$provider,$external,$provider.':'.$external,$amount,$currency,$kind.':'.$status]);
        return ['duplicate'=>false,'payment_id'=>$id];
    }
    public function reconcileBank(int $adminId,int $paymentId,array $input): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','payment.reconcile');
        $reference=DeliveryInput::text($input['bank_reference']??null,'bank_reference',180);$evidence=DeliveryInput::text($input['evidence_reference']??null,'evidence_reference',500);
        $q=$this->db->prepare('SELECT p.*,o.customer_id FROM payments p JOIN orders o USING(order_id) WHERE payment_id=?');$q->execute([$paymentId]);$p=$q->fetch();
        if(!$p||$p['method']!=='BANK_TRANSFER')throw new DeliveryApiError(404,'PAYMENT_NOT_FOUND','Bank payment not found.');
        OrderLifecycleService::lock($this->db,(int)$p['order_id']);
        $result=$this->applyVerified(['payment_id'=>$paymentId,'event_id'=>'bank:'.$reference,'kind'=>'PAYMENT','provider_payment_ref'=>$reference,'amount_minor'=>$input['amount_minor']??null,'currency_code'=>$input['currency_code']??null,'status'=>'PAID'],'MANUAL_BANK');
        $q=$this->db->prepare('INSERT INTO bank_reconciliations(payment_id,bank_reference,evidence_reference,reconciled_by) VALUES (?,?,?,?) ON CONFLICT(bank_reference) DO NOTHING');$q->execute([$paymentId,$reference,$evidence,$adminId]);
        AuditService::record($this->db,$adminId,'ADMIN','BANK_PAYMENT_RECONCILED','PAYMENT',$paymentId,[],['bank_reference'=>$reference,'evidence_reference'=>$evidence]);return $result;
    }
    public function requestRefund(int $orderId,string $reason,string $key,?int $adminId=null): array
    {
        if($adminId!==null)AccessControl::requirePermission($this->db,$adminId,'ADMIN','payment.refund');
        if(!in_array($reason,['CUSTOMER_CANCELLATION','RETURN_FEE_DEDUCTION','ADMIN_ADJUSTMENT'],true))DeliveryInput::invalid('reason');
        if($reason==='ADMIN_ADJUSTMENT')throw new DeliveryApiError(422,'REFUND_POLICY_REQUIRED','An explicit adjustment policy is required.');
        $order=OrderLifecycleService::lock($this->db,$orderId);$q=$this->db->prepare('SELECT * FROM payments WHERE order_id=? FOR UPDATE');$q->execute([$orderId]);$p=$q->fetch();
        if(!$p||!in_array($p['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true))throw new DeliveryApiError(409,'PAYMENT_NOT_REFUNDABLE','No confirmed payment is refundable.');
        $q=$this->db->prepare('SELECT * FROM refunds WHERE request_key=?');$q->execute([$key]);$old=$q->fetch();
        if($old){if((int)$old['order_id']!==$orderId||$old['reason']!==$reason)throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Refund key conflict.');return $old;}
        if(($reason==='CUSTOMER_CANCELLATION'&&$order['status']!=='CANCELLED')||($reason==='RETURN_FEE_DEDUCTION'&&$order['status']!=='RETURNED'))throw new DeliveryApiError(409,'REFUND_NOT_ELIGIBLE','Order is not eligible for this refund.');
        $q=$this->db->prepare('SELECT COALESCE(sum(gross_amount_minor),0) FROM refunds WHERE payment_id=?');$q->execute([$p['payment_id']]);$reserved=(string)$q->fetchColumn();
        if(bccomp($reserved,'0')>0)throw new DeliveryApiError(409,'REFUND_ALREADY_RESERVED','A refund is already reserved. Retry the existing refund.');
        $fee=$reason==='RETURN_FEE_DEDUCTION'?(string)$order['return_fee_minor']:(string)$order['cancellation_fee_minor'];
        if(bccomp($fee,(string)$p['amount_minor'])>0)throw new DeliveryApiError(409,'REFUND_POLICY_INVALID','Deduction exceeds captured amount.');
        $amount=bcsub((string)$p['amount_minor'],$fee,0);
        $q=$this->db->prepare('INSERT INTO refunds(payment_id,order_id,reason,gross_amount_minor,deducted_fee_minor,refund_amount_minor,currency_code,provider,request_key) VALUES (?,?,?,?,?,?,?,?,?) RETURNING *');$q->execute([$p['payment_id'],$orderId,$reason,$p['amount_minor'],$fee,$amount,$p['currency_code'],$p['provider'],$key]);$refund=$q->fetch();
        JobQueue::enqueue($this->db,'REFUND','refund:'.$refund['refund_id'],['refund_id'=>(string)$refund['refund_id']]);
        AuditService::record($this->db,$adminId,$adminId?'ADMIN':'SYSTEM','REFUND_REQUESTED','REFUND',(int)$refund['refund_id'],[],['reason'=>$reason,'amount_minor'=>$amount]);
        return $refund;
    }
    public function reconcileBankRefund(int $adminId,int $refundId,array $in): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','payment.reconcile');
        $reference=DeliveryInput::text($in['bank_reference']??null,'bank_reference',180);$evidence=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',500);$original=DeliveryInput::text($in['original_payment_reference']??null,'original_payment_reference',180);
        $q=$this->db->prepare('SELECT r.*,p.provider_payment_ref FROM refunds r JOIN payments p USING(payment_id) WHERE refund_id=?');$q->execute([$refundId]);$r=$q->fetch();
        if(!$r||$r['provider']!=='MANUAL_BANK')throw new DeliveryApiError(404,'REFUND_NOT_FOUND','Bank refund not found.');
        if($original!==$r['provider_payment_ref'])throw new DeliveryApiError(409,'ORIGINAL_METHOD_REQUIRED','Refund must reference the original reconciled payment.');
        $result=$this->applyVerified(['kind'=>'REFUND','event_id'=>'bank-refund:'.$reference,'payment_id'=>$r['payment_id'],'provider_payment_ref'=>$original,'refund_id'=>$refundId,'provider_refund_id'=>$reference,'amount_minor'=>$in['amount_minor']??null,'currency_code'=>$in['currency_code']??null,'status'=>'SUCCEEDED'],'MANUAL_BANK');
        $this->db->prepare('UPDATE refunds SET reconciliation_reference=?,reconciled_by=?,evidence_reference=? WHERE refund_id=?')->execute([$reference,$adminId,$evidence,$refundId]);
        AuditService::record($this->db,$adminId,'ADMIN','BANK_REFUND_RECONCILED','REFUND',$refundId,[],['bank_reference'=>$reference,'original_payment_reference'=>$original,'evidence_reference'=>$evidence]);return $result;
    }

    /** Records an original-method refund already completed by the gateway operator.
     * This endpoint does not send money, select a bank account, or initiate a refund. */
    public function reconcileProviderRefund(int $adminId,int $refundId,array $in): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','payment.reconcile');
        $reference=DeliveryInput::text($in['provider_refund_id']??null,'provider_refund_id',180);
        $evidence=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',500);
        $original=DeliveryInput::text($in['original_payment_reference']??null,'original_payment_reference',180);
        if(($in['original_method_confirmed']??null)!==true)throw new DeliveryApiError(422,'ORIGINAL_METHOD_REQUIRED','Confirm the refund was completed to the original payment method.');
        $q=$this->db->prepare('SELECT r.*,p.provider_payment_ref FROM refunds r JOIN payments p USING(payment_id) WHERE refund_id=?');$q->execute([$refundId]);$r=$q->fetch();
        if(!$r||$r['provider']!=='SEPAY')throw new DeliveryApiError(404,'REFUND_NOT_FOUND','SePay refund not found.');
        OrderLifecycleService::lock($this->db,(int)$r['order_id']);
        if($original!==$r['provider_payment_ref'])throw new DeliveryApiError(409,'ORIGINAL_METHOD_REQUIRED','Original payment reference mismatch.');
        $result=$this->applyVerified(['kind'=>'REFUND','event_id'=>'operator-refund:'.$reference,'payment_id'=>$r['payment_id'],'provider_payment_ref'=>$original,'refund_id'=>$refundId,'provider_refund_id'=>$reference,'amount_minor'=>$in['amount_minor']??null,'currency_code'=>$in['currency_code']??null,'status'=>'SUCCEEDED'],'SEPAY');
        $this->db->prepare('UPDATE refunds SET reconciliation_reference=?,reconciled_by=?,evidence_reference=?,failure_code=NULL WHERE refund_id=?')->execute([$reference,$adminId,$evidence,$refundId]);
        AuditService::record($this->db,$adminId,'ADMIN','PROVIDER_REFUND_RECONCILED','REFUND',$refundId,[],['provider_refund_id'=>$reference,'original_payment_reference'=>$original,'evidence_reference'=>$evidence,'original_method_confirmed'=>true]);
        return $result;
    }
}
