<?php
declare(strict_types=1);
require_once __DIR__.'/DemoMode.php';
require_once __DIR__.'/InteractionService.php';
require_once __DIR__.'/AuditService.php';
require_once __DIR__.'/EncryptedPayload.php';

final class ShipperFinanceService
{
    public function __construct(private PDO $db) {}
    private function rows(string $sql,array $args=[]):array {$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();}
    private function refundHold(int $orderId):bool {
        $o=$this->rows('SELECT o.status,o.currency_code,o.total_charge_minor,o.return_fee_minor,p.status AS payment_status FROM orders o LEFT JOIN payments p USING(order_id) WHERE o.order_id=?',[$orderId])[0];
        if(!in_array($o['status'],['DELIVERED','RETURNED'],true))return true;
        if($this->rows("SELECT 1 FROM cancellation_requests WHERE order_id=? AND requester_role='SHIPPER' AND decision='PENDING' LIMIT 1",[$orderId]))return true;
        if($o['status']==='DELIVERED'&&$o['payment_status']!=='PAID')return true;
        if($o['status']==='RETURNED'){
            if($this->rows("SELECT 1 FROM shipper_score_events WHERE order_id=? AND event_type='POST_PICKUP_VIOLATION' LIMIT 1",[$orderId]))return true;
            if(!in_array($o['payment_status'],['PARTIALLY_REFUNDED','REFUNDED'],true))return true;
            $r=$this->rows("SELECT refund_id FROM refunds WHERE order_id=? AND reason='RETURN_FEE_DEDUCTION' AND status='SUCCEEDED' AND currency_code=? AND gross_amount_minor=? AND deducted_fee_minor=?",[$orderId,$o['currency_code'],$o['total_charge_minor'],$o['return_fee_minor']])[0]??null;
            if(!$r)return true;
            return (bool)$this->rows("SELECT 1 FROM refunds WHERE order_id=? AND status<>'FAILED' AND refund_id<>?",[$orderId,$r['refund_id']]);
        }
        return (bool)$this->rows("SELECT 1 FROM refunds WHERE order_id=? AND status<>'FAILED'",[$orderId]);
    }
    /** Money calculation stays in PostgreSQL NUMERIC, with one final ceiling. */
    public function accrue(int $orderId):?array {
        $o=$this->rows('SELECT * FROM orders WHERE order_id=? FOR UPDATE',[$orderId])[0]??null;
        if(!$o||!in_array($o['status'],['DELIVERED','RETURNED'],true))return null;
        $p=$this->rows('SELECT * FROM payments WHERE order_id=? FOR UPDATE',[$orderId])[0]??null;
        if(!$p||!in_array($p['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)||$p['currency_code']!==$o['currency_code']||(string)$p['amount_minor']!==(string)$o['total_charge_minor'])return null;
        $a=$this->rows("SELECT a.*,d.delivered_at,d.returned_at,d.customer_return_confirmed_at FROM order_assignments a JOIN deliveries d USING(assignment_id) WHERE a.order_id=? AND ((a.assignment_status='COMPLETED' AND d.delivered_at IS NOT NULL) OR (a.assignment_status='RETURNED' AND d.returned_at IS NOT NULL AND d.customer_return_confirmed_at IS NOT NULL)) ORDER BY assignment_id DESC LIMIT 1",[$orderId])[0]??null;
        if(!$a||($o['status']==='DELIVERED'&&$a['assignment_status']!=='COMPLETED')||($o['status']==='RETURNED'&&$a['assignment_status']!=='RETURNED'))return null;
        // Unresolved refunds freeze accrual. No guessed compensation policy.
        if($this->refundHold($orderId))return null;
        $kind=$o['status']==='DELIVERED'?'SUCCESSFUL_DELIVERY':'FAILED_RETURN';
        $at=$kind==='SUCCESSFUL_DELIVERY'?$a['delivered_at']:$a['customer_return_confirmed_at'];
        $policy=$this->rows('SELECT *,CASE WHEN policy_type=\'SUCCESSFUL_DELIVERY_PERCENT\' THEN ceil(?::numeric*percentage_rate/100)::bigint ELSE fixed_amount_minor END AS amount FROM shipper_earning_policies WHERE policy_type=? AND currency_code=? AND effective_from<=? AND (effective_until IS NULL OR effective_until>?) ORDER BY effective_from DESC LIMIT 1',[$o['total_charge_minor'],$kind==='SUCCESSFUL_DELIVERY'?'SUCCESSFUL_DELIVERY_PERCENT':'FAILED_RETURN_FIXED',$o['currency_code'],$at,$at])[0]??null;
        if(!$policy)return null;
        return $this->rows('INSERT INTO shipper_earnings(order_id,shipper_id,earning_type,basis_amount_minor,percentage_rate,gross_amount_minor,currency_code,policy_snapshot) VALUES(?,?,?,?,?,?,?,?::jsonb) ON CONFLICT(order_id) DO UPDATE SET order_id=EXCLUDED.order_id RETURNING *',[$orderId,$a['shipper_id'],$kind,$o['total_charge_minor'],$policy['percentage_rate'],$policy['amount'],$o['currency_code'],json_encode($policy,JSON_THROW_ON_ERROR)])[0];
    }
    /** Called in the completion/refund transaction; worker settlement remains a retry fallback. */
    public function settleOrder(int $order):?array {
        if(!$this->db->inTransaction())throw new LogicException('Settlement requires a transaction.');
        $earning=$this->accrue($order);if(!$earning)return null;
        $payout=getenv('SOMI_PAYOUT_MODE')==='manual'?$this->reserve((int)$earning['earning_id']):null;
        return ['earning_id'=>$earning['earning_id'],'amount_minor'=>(string)$earning['gross_amount_minor'],'currency_code'=>$earning['currency_code'],'payout_id'=>$payout['payout_id']??null,'transfer_status'=>$payout['status']??null,'manual_transfer'=>true];
    }
    public function listing(int $user,array $in):array {
        $before=isset($in['before_id'])?DeliveryInput::id($in['before_id']):PHP_INT_MAX;
        $items=$this->rows('SELECT e.*,o.order_code,p.payout_id,p.status AS transfer_status,p.payout_amount_minor,p.payout_currency,p.failure_reason FROM shipper_earnings e JOIN orders o USING(order_id) LEFT JOIN shipper_payouts p USING(earning_id) WHERE e.shipper_id=? AND e.earning_id<? ORDER BY e.earning_id DESC LIMIT 50',[$user,$before]);
        return ['items'=>$items,'totals'=>$this->rows('SELECT currency_code,payout_status,sum(gross_amount_minor)::text AS amount_minor FROM shipper_earnings WHERE shipper_id=? GROUP BY currency_code,payout_status',[$user]),'next_cursor'=>count($items)===50?(string)end($items)['earning_id']:null];
    }
    public function destination(int $admin,int $payoutId):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');
        $bank=$this->rows("SELECT p.payout_id,p.status,p.payout_amount_minor,p.bank_account_id,b.bank_code,b.bank_name,b.account_holder,convert_from(b.account_number_enc,'UTF8') AS encrypted FROM shipper_payouts p JOIN shipper_bank_accounts b USING(bank_account_id) WHERE payout_id=?",[$payoutId])[0]??null;
        if(!$bank)throw new DeliveryApiError(404,'PAYOUT_NOT_FOUND','Payout not found.');
        if(DemoMode::payout($this->db,$payoutId))DemoMode::denyTransfer();
        try{$payload=EncryptedPayload::open($bank['encrypted']);$number=$payload['account_number']??'';if(!is_string($number)||!preg_match('/^[0-9]{6,34}$/D',$number))throw new RuntimeException('Invalid bank envelope.');}catch(Throwable $e){throw new DeliveryApiError(503,'BANK_ENCRYPTION_UNSUPPORTED','Verified bank account must use the documented encrypted envelope.');}
        unset($bank['encrypted']);$bank['account_number']=$number;
        AuditService::record($this->db,$admin,'ADMIN','PAYOUT_BANK_READ','SHIPPER_PAYOUT',$payoutId,[],['bank_account_id'=>$bank['bank_account_id']]);return ['destination'=>$bank];
    }
    public function fxQuote(int $admin,array $in):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');DeliveryInput::only($in,['earning_id','rate','provider','reference','evidence_reference']);
        $id=DeliveryInput::id($in['earning_id']??null);$rate=DeliveryInput::decimal($in['rate']??null,'rate',8,'0.00000001','999999999.99999999');
        $provider=DeliveryInput::text($in['provider']??null,'provider',100);$reference=DeliveryInput::text($in['reference']??null,'reference',180);$evidence=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',1000);
        $e=$this->rows('SELECT order_id FROM shipper_earnings WHERE earning_id=?',[$id])[0]??null;if(!$e)throw new DeliveryApiError(404,'EARNING_NOT_FOUND','Earning not found.');
        $this->rows('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE',[$e['order_id']]);$this->rows('SELECT payment_id FROM payments WHERE order_id=? FOR UPDATE',[$e['order_id']]);
        $e=$this->rows('SELECT * FROM shipper_earnings WHERE earning_id=? FOR UPDATE',[$id])[0];
        if($e['currency_code']!=='USD')throw new DeliveryApiError(422,'FX_NOT_REQUIRED','Only USD earnings require FX.');
        $converted=$this->rows('SELECT ceil(?::numeric*?::numeric/100)::text AS amount',[$e['gross_amount_minor'],$rate])[0]['amount'];if(bccomp($converted,'9223372036854775807',0)>0)DeliveryInput::invalid('rate');
        $old=$this->rows('SELECT * FROM payout_fx_quotes WHERE earning_id=?',[$id])[0]??null;
        if($old){if(bccomp($old['rate'],$rate,8)!==0||$old['provider']!==$provider||$old['reference']!==$reference||$old['evidence_reference']!==$evidence)throw new DeliveryApiError(409,'FX_IMMUTABLE','FX quote is already frozen.');return ['fx_quote'=>$old];}
        if($this->rows('SELECT 1 FROM shipper_payouts WHERE earning_id=?',[$id]))throw new DeliveryApiError(409,'PAYOUT_EXISTS','Payout already exists.');
        $quote=$this->rows('INSERT INTO payout_fx_quotes(earning_id,rate,provider,reference,evidence_reference,created_by) VALUES(?,?,?,?,?,?) RETURNING *',[$id,$rate,$provider,$reference,$evidence,$admin])[0];
        AuditService::record($this->db,$admin,'ADMIN','PAYOUT_FX_RECORDED','SHIPPER_EARNING',$id,[],['fx_quote_id'=>$quote['fx_quote_id']]);return ['fx_quote'=>$quote];
    }
    public function transition(int $admin,string $action,array $in):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');DeliveryInput::only($in,['payout_id','client_key','reason','evidence_reference','transfer_not_completed']);
        if(!in_array($action,['FAILED','RETRY'],true))DeliveryInput::invalid('action');
        $id=DeliveryInput::id($in['payout_id']??null);if(DemoMode::payout($this->db,$id))DemoMode::denyTransfer();$key=InteractionService::key($in);$reason=DeliveryInput::text($in['reason']??null,'reason',2000);$evidence=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',1000);
        if(($in['transfer_not_completed']??null)!==true)throw new DeliveryApiError(422,'TRANSFER_REVIEW_REQUIRED','Confirm from bank evidence that no completed transfer exists.');
        $row=$this->rows('SELECT e.order_id,e.earning_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE payout_id=?',[$id])[0]??null;if(!$row)throw new DeliveryApiError(404,'PAYOUT_NOT_FOUND','Payout not found.');
        $this->rows('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE',[$row['order_id']]);$this->rows('SELECT payment_id FROM payments WHERE order_id=? FOR UPDATE',[$row['order_id']]);$this->rows('SELECT earning_id FROM shipper_earnings WHERE earning_id=? FOR UPDATE',[$row['earning_id']]);
        $p=$this->rows('SELECT * FROM shipper_payouts WHERE payout_id=? FOR UPDATE',[$id])[0];$hash=hash('sha256',json_encode([$id,$action,$reason,$evidence],JSON_THROW_ON_ERROR));
        $old=$this->rows('SELECT * FROM payout_actions WHERE actor_id=? AND client_key=?',[$admin,$key])[0]??null;
        if($old){if(!hash_equals($old['payload_hash'],$hash))throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return ['payout_id'=>$id,'status'=>$action==='FAILED'?'FAILED':'PROCESSING','duplicate'=>true];}
        if(($action==='FAILED'&&$p['status']!=='PROCESSING')||($action==='RETRY'&&$p['status']!=='FAILED'))throw new DeliveryApiError(409,'PAYOUT_CONFLICT','Refresh payout status.');
        if($action==='RETRY'&&$this->refundHold((int)$row['order_id']))throw new DeliveryApiError(409,'REFUND_REVIEW_REQUIRED','Resolve refund before retry.');
        $status=$action==='FAILED'?'FAILED':'PROCESSING';$this->db->prepare('UPDATE shipper_payouts SET status=?,failure_reason=? WHERE payout_id=?')->execute([$status,$action==='FAILED'?$reason:'MANUAL_TRANSFER_REVIEW_REQUIRED',$id]);
        $this->db->prepare('UPDATE shipper_earnings SET payout_status=? WHERE earning_id=?')->execute([$action==='FAILED'?'FAILED':'PENDING',$row['earning_id']]);
        $this->db->prepare('INSERT INTO payout_actions(payout_id,actor_id,action,client_key,reason,evidence_reference,payload_hash) VALUES(?,?,?,?,?,?,?)')->execute([$id,$admin,$action,$key,$reason,$evidence,$hash]);
        AuditService::record($this->db,$admin,'ADMIN','PAYOUT_'.$action,'SHIPPER_PAYOUT',$id,[],['evidence_reference'=>$evidence,'reason'=>$reason]);return ['payout_id'=>$id,'status'=>$status];
    }
    /** Manual adapter reserves one VND transfer, using frozen evidenced FX for USD sources. No bank I/O. */
    public function reserve(int $earningId):?array {
        $e=$this->rows('SELECT order_id FROM shipper_earnings WHERE earning_id=?',[$earningId])[0]??null;if(!$e)return null;
        $this->rows('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE',[$e['order_id']]);
        $this->rows('SELECT payment_id FROM payments WHERE order_id=? FOR UPDATE',[$e['order_id']]);
        $e=$this->rows('SELECT * FROM shipper_earnings WHERE earning_id=? FOR UPDATE',[$earningId])[0];
        if($e['payout_status']!=='PENDING')return null;
        if(DemoMode::user($this->db,(int)$e['shipper_id']))return null; // Demo earnings never reserve a bank transfer.
        if($this->refundHold((int)$e['order_id']))return null;
        $bank=$this->rows("SELECT bank_account_id FROM shipper_bank_accounts WHERE shipper_id=? AND replaced_at IS NULL AND verified_at IS NOT NULL AND COALESCE(to_jsonb(shipper_bank_accounts)->>'review_status','APPROVED')='APPROVED' FOR SHARE",[$e['shipper_id']])[0]??null;if(!$bank)return null;
        $fx=$e['currency_code']==='USD'?($this->rows('SELECT * FROM payout_fx_quotes WHERE earning_id=?',[$earningId])[0]??null):null;
        if($e['currency_code']==='USD'&&!$fx)return null;
        return $this->rows("INSERT INTO shipper_payouts(earning_id,shipper_id,bank_account_id,source_amount_minor,source_currency,payout_amount_minor,fx_rate,fx_provider,fx_reference,status,failure_reason) VALUES(?,?,?,?,?,CASE WHEN ?='USD' THEN ceil(?::numeric*?::numeric/100)::bigint ELSE ?::bigint END,?,?,?,'PROCESSING','MANUAL_TRANSFER_REVIEW_REQUIRED') ON CONFLICT(earning_id) DO UPDATE SET earning_id=EXCLUDED.earning_id RETURNING payout_id,status",[$earningId,$e['shipper_id'],$bank['bank_account_id'],$e['gross_amount_minor'],$e['currency_code'],$e['currency_code'],$e['gross_amount_minor'],$fx['rate']??'1',$e['gross_amount_minor'],$fx['rate']??null,$fx['provider']??null,$fx['reference']??null])[0];
    }
    public function reconcile(int $admin,array $in):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');
        DeliveryInput::only($in,['payout_id','provider_payout_ref','evidence_reference','amount_minor','currency_code']);
        $id=DeliveryInput::id($in['payout_id']??null);if(DemoMode::payout($this->db,$id))DemoMode::denyTransfer();$ref=DeliveryInput::text($in['provider_payout_ref']??null,'provider_payout_ref',180);$proof=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',1000);
        $row=$this->rows('SELECT e.order_id,e.earning_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE payout_id=?',[$id])[0]??null;
        if(!$row)throw new DeliveryApiError(404,'PAYOUT_NOT_FOUND','Payout not found.');
        $this->rows('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE',[$row['order_id']]);$this->rows('SELECT payment_id FROM payments WHERE order_id=? FOR UPDATE',[$row['order_id']]);
        $this->rows('SELECT earning_id FROM shipper_earnings WHERE earning_id=? FOR UPDATE',[$row['earning_id']]);
        $p=$this->rows('SELECT * FROM shipper_payouts WHERE payout_id=? FOR UPDATE',[$id])[0];
        if(($in['currency_code']??null)!=='VND'||!is_string($in['amount_minor']??null)||$in['amount_minor']!==(string)$p['payout_amount_minor'])DeliveryInput::invalid('amount_minor');
        if($p['status']==='SUCCEEDED'){if($p['provider_payout_ref']!==$ref||$p['evidence_reference']!==$proof)throw new DeliveryApiError(409,'PAYOUT_CONFLICT','Payout already reconciled.');return ['payout_id'=>$id,'status'=>'SUCCEEDED'];}
        $bank=$this->rows("SELECT COALESCE(to_jsonb(b)->>'review_status','APPROVED') AS review_status FROM shipper_bank_accounts b WHERE bank_account_id=?",[$p['bank_account_id']])[0]??null;if(!$bank||$bank['review_status']!=='APPROVED')throw new DeliveryApiError(409,'BANK_REVIEW_REQUIRED','Approve the shipper bank account before payout.');
        if($p['status']!=='PROCESSING')throw new DeliveryApiError(409,'PAYOUT_CONFLICT','Payout is not processing.');
        if($this->refundHold((int)$row['order_id']))throw new DeliveryApiError(409,'REFUND_REVIEW_REQUIRED','Review refund before reconciliation.');
        $this->db->prepare("UPDATE shipper_payouts SET status='SUCCEEDED',provider_payout_ref=?,evidence_reference=?,failure_reason=NULL,completed_at=now() WHERE payout_id=?")->execute([$ref,$proof,$id]);
        $this->db->prepare("UPDATE shipper_earnings SET payout_status='PAID' WHERE earning_id=?")->execute([$p['earning_id']]);
        AuditService::record($this->db,$admin,'ADMIN','PAYOUT_RECONCILED','SHIPPER_PAYOUT',$id,[],['provider_payout_ref'=>$ref,'evidence_reference'=>$proof]);
        InteractionService::notify($this->db,(int)$p['shipper_id'],(int)$row['order_id'],'PayoutSucceeded','Đã đối soát chi trả','Payout reconciled','Khoản chi trả đã có chứng từ xác nhận.','The payout has confirmed evidence.','payout:'.$id);
        return ['payout_id'=>$id,'status'=>'SUCCEEDED'];
    }
}
