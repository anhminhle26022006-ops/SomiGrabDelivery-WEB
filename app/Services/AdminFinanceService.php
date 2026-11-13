<?php
declare(strict_types=1);
require_once __DIR__.'/PaymentService.php';
require_once __DIR__.'/ShipperFinanceService.php';
require_once __DIR__.'/EncryptedPayload.php';

final class AdminFinanceService {
    public function __construct(private PDO $db){}
    private function rows(string $sql,array $args=[]):array{$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();}
    public function listing(int $admin,array $in):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');
        $args=[];$where='TRUE';
        if(!empty($in['q'])){$term='%'.DeliveryInput::text($in['q'],'q',100).'%';$where.=' AND (o.order_code ILIKE ? OR u.full_name ILIKE ?)';$args=[$term,$term];}
        if(!empty($in['status'])){$where.=' AND o.status=?';$args[]=DeliveryInput::text($in['status'],'status',30);}
        if(isset($in['before_id'])){$where.=' AND o.order_id<?';$args[]=DeliveryInput::id($in['before_id']);}
        $items=$this->rows("SELECT o.order_id,o.order_code,o.status AS order_status,o.currency_code,o.total_charge_minor,u.full_name AS customer_name,p.payment_id,p.status AS payment_status,p.provider,
        (SELECT json_agg(json_build_object('status',r.status,'amount_minor',r.refund_amount_minor::text)) FROM refunds r WHERE r.order_id=o.order_id) AS refunds,
        (SELECT json_agg(json_build_object('status',p2.status,'amount_minor',p2.payout_amount_minor::text)) FROM shipper_earnings e JOIN shipper_payouts p2 USING(earning_id) WHERE e.order_id=o.order_id) AS payouts
        FROM orders o JOIN users u ON u.user_id=o.customer_id LEFT JOIN payments p USING(order_id) WHERE $where ORDER BY o.order_id DESC LIMIT 51",$args);
        $more=count($items)>50;if($more)array_pop($items);foreach($items as &$r){$r['refunds']=json_decode($r['refunds']??'[]',true);$r['payouts']=json_decode($r['payouts']??'[]',true);}unset($r);
        return ['items'=>$items,'next_before_id'=>$more?(string)end($items)['order_id']:null];
    }
    public function detail(int $admin,int $order):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');
        AccessControl::requirePermission($this->db,$admin,'ADMIN','order.read_all');$o=$this->rows('SELECT * FROM orders WHERE order_id=?',[$order])[0]??null;if(!$o)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        $customer=$this->rows('SELECT user_id,full_name FROM users WHERE user_id=?',[$o['customer_id']])[0];
        $customer['bank']=$this->bank($this->rows('SELECT * FROM customer_bank_accounts WHERE customer_id=? ORDER BY bank_account_id DESC LIMIT 1',[$o['customer_id']])[0]??null);
        $shippers=$this->rows("SELECT DISTINCT ON(a.shipper_id) a.shipper_id,u.full_name,a.assignment_status FROM order_assignments a JOIN users u ON u.user_id=a.shipper_id WHERE a.order_id=? ORDER BY a.shipper_id,a.assignment_id DESC",[$order]);
        foreach($shippers as &$shipper){$shipper['bank']=$this->bank($this->rows("SELECT bank_account_id,bank_name,account_holder,review_status,review_note,convert_from(account_number_enc,'UTF8') AS encrypted_payload FROM shipper_bank_accounts WHERE shipper_id=? AND replaced_at IS NULL",[$shipper['shipper_id']])[0]??null);}unset($shipper);
        try{$adminBank=PaymentService::bankInstructions();}catch(DeliveryApiError $e){if($e->errorCode!=='BANK_NOT_CONFIGURED')throw $e;$adminBank=null;}
        $payment=$this->rows('SELECT payment_id,amount_minor,currency_code,method,provider,status,provider_payment_ref,paid_at FROM payments WHERE order_id=?',[$order])[0]??null;
        $refunds=$this->rows('SELECT * FROM refunds WHERE order_id=? ORDER BY refund_id',[$order]);
        $payouts=$this->rows('SELECT p.*,u.full_name AS shipper_name,e.order_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) JOIN users u ON u.user_id=p.shipper_id WHERE e.order_id=? ORDER BY payout_id',[$order]);
        foreach($payouts as &$p){$bank=$this->rows("SELECT bank_account_id,bank_name,account_holder,review_status,convert_from(account_number_enc,'UTF8') AS encrypted_payload FROM shipper_bank_accounts WHERE bank_account_id=?",[$p['bank_account_id']])[0];$p['bank']=$this->bank($bank);}unset($p);
        foreach($refunds as &$r){$bank=$r['refund_bank_account_id']?$this->rows('SELECT * FROM customer_bank_accounts WHERE bank_account_id=?',[$r['refund_bank_account_id']])[0]??null:$this->rows('SELECT * FROM customer_bank_accounts WHERE customer_id=? ORDER BY bank_account_id DESC LIMIT 1',[$o['customer_id']])[0]??null;$r['bank']=$this->bank($bank);}unset($r);
        $earnings=$this->rows('SELECT e.*,u.full_name AS shipper_name FROM shipper_earnings e JOIN users u ON u.user_id=e.shipper_id WHERE order_id=?',[$order]);
        $evidence=$this->rows('SELECT evidence_id,action,target_id,created_at FROM finance_evidence WHERE order_id=? ORDER BY evidence_id',[$order]);
        AuditService::record($this->db,$admin,'ADMIN','ORDER_FINANCE_READ','ORDER',$order);
        return ['order'=>$o,'customer'=>$customer,'shippers'=>$shippers,'admin_bank'=>$adminBank,'payment'=>$payment,'refunds'=>$refunds,'payouts'=>$payouts,'earnings'=>$earnings,'evidence'=>$evidence,'is_demo'=>DemoMode::user($this->db,(int)$o['customer_id'])];
    }
    private function bank(?array $b):?array{if(!$b)return null;try{$b['account_number']=EncryptedPayload::open($b['encrypted_payload'])['account_number'];$b['readable']=true;}catch(Throwable $e){$b['account_number']=null;$b['readable']=false;$b['needs_reentry']=true;}unset($b['encrypted_payload']);return $b;}
    public function banks(int $admin,array $in):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');
        $args=[];$where='TRUE';if(!empty($in['q'])){$where='(u.full_name ILIKE ? OR b.bank_name ILIKE ?)';$term='%'.DeliveryInput::text($in['q'],'q',100).'%';$args=[$term,$term];}
        $sql="WITH b AS (SELECT DISTINCT ON(customer_id) 'CUSTOMER' AS role,customer_id AS user_id,bank_account_id,bank_name,account_holder,encrypted_payload,review_status,review_note FROM customer_bank_accounts ORDER BY customer_id,bank_account_id DESC), all_banks AS (SELECT * FROM b UNION ALL SELECT 'SHIPPER',shipper_id,bank_account_id,bank_name,account_holder,convert_from(account_number_enc,'UTF8'),review_status,review_note FROM shipper_bank_accounts WHERE replaced_at IS NULL) SELECT b.*,u.full_name FROM all_banks b JOIN users u USING(user_id) WHERE $where ORDER BY CASE WHEN b.review_status='PENDING' THEN 0 ELSE 1 END,b.user_id DESC LIMIT 100";
        $items=$this->rows($sql,$args);foreach($items as &$b)$b=$this->bank($b);unset($b);AuditService::record($this->db,$admin,'ADMIN','BANK_REVIEW_LIST','USER',null);return ['items'=>$items];
    }
    public function receipt(int $admin,int $id):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');
        $r=$this->rows('SELECT encrypted_payload FROM finance_evidence WHERE evidence_id=?',[$id])[0]??null;if(!$r)throw new DeliveryApiError(404,'EVIDENCE_NOT_FOUND','Receipt not found.');
        AuditService::record($this->db,$admin,'ADMIN','FINANCE_RECEIPT_READ','FINANCE_EVIDENCE',$id);return EncryptedPayload::open($r['encrypted_payload']);
    }
    public function review(int $admin,array $in):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');AccessControl::requirePermission($this->db,$admin,'ADMIN','payout.manage');
        $role=$in['role']??'';if(!in_array($role,['CUSTOMER','SHIPPER'],true))DeliveryInput::invalid('role');$status=$in['decision']??'';if(!in_array($status,['APPROVED','REJECTED'],true))DeliveryInput::invalid('decision');
        $id=DeliveryInput::id($in['bank_account_id']??null);$note=DeliveryInput::text($in['note']??null,'note',1000);$table=$role==='CUSTOMER'?'customer_bank_accounts':'shipper_bank_accounts';
        if($status==='APPROVED'){
            $column=$role==='CUSTOMER'?'encrypted_payload':"convert_from(account_number_enc,'UTF8')";
            $r=$this->rows("SELECT $column AS encrypted_payload FROM $table WHERE bank_account_id=? FOR UPDATE",[$id])[0]??null;
            if(!$r)throw new DeliveryApiError(404,'BANK_NOT_FOUND','Bank account not found.');
            try{$number=EncryptedPayload::open($r['encrypted_payload'])['account_number']??null;}catch(Throwable $e){$number=null;}
            if(!is_string($number)||!preg_match('/^[0-9]{6,34}$/D',$number))throw new DeliveryApiError(409,'BANK_REENTRY_REQUIRED','The saved bank account cannot be decrypted. Ask the user to save their bank details again before approval.');
        }
        $bank=$this->rows("UPDATE $table SET review_status=?,review_note=?,reviewed_by=?,reviewed_at=now() WHERE bank_account_id=? RETURNING bank_account_id",[$status,$note,$admin,$id]);if(!$bank)throw new DeliveryApiError(404,'BANK_NOT_FOUND','Bank account not found.');
        AuditService::record($this->db,$admin,'ADMIN','BANK_'.$status,$table,$id,[],['note'=>$note]);return ['status'=>$status];
    }
    /** Evidence upload and financial confirmation commit atomically. Never sends money. */
    public function confirm(int $admin,array $in,array $file):array{
        AccessControl::requirePermission($this->db,$admin,'ADMIN','payment.reconcile');$action=$in['action']??'';if(!in_array($action,['PAYMENT','REFUND','PAYOUT'],true))DeliveryInput::invalid('action');
        if(($in['confirmed']??'')!=='yes')DeliveryInput::invalid('confirmed');$id=DeliveryInput::id($in['target_id']??null);
        if($action==='PAYOUT'){$r=$this->rows('SELECT p.*,e.order_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE payout_id=?',[$id])[0]??null;}
        elseif($action==='REFUND')$r=$this->rows('SELECT r.*,p.provider_payment_ref,o.customer_id FROM refunds r JOIN payments p USING(payment_id) JOIN orders o ON o.order_id=r.order_id WHERE refund_id=?',[$id])[0]??null;
        else $r=$this->rows('SELECT * FROM payments WHERE payment_id=?',[$id])[0]??null;
        if(!$r)throw new DeliveryApiError(404,'TRANSACTION_NOT_FOUND','Transaction not found.');OrderLifecycleService::lock($this->db,(int)$r['order_id']);
        $old=$this->rows('SELECT evidence_id FROM finance_evidence WHERE action=? AND target_id=?',[$action,$id]);if($old)return ['duplicate'=>true,'evidence_id'=>$old[0]['evidence_id']];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||(int)$file['size']>5*1024*1024)throw new DeliveryApiError(422,'RECEIPT_REQUIRED','Upload a bank receipt image, maximum 5 MiB.');
        $bytes=file_get_contents($file['tmp_name']);$image=@getimagesizefromstring($bytes);if(!$image||!in_array($image['mime'],['image/png','image/jpeg','image/webp'],true))throw new DeliveryApiError(422,'RECEIPT_INVALID','Upload a PNG, JPEG or WebP bank receipt.');
        $q=$this->db->prepare('INSERT INTO finance_evidence(order_id,actor_id,action,target_id,encrypted_payload) VALUES(?,?,?,?,?) RETURNING evidence_id');$q->execute([$r['order_id'],$admin,$action,$id,EncryptedPayload::seal(['mime_type'=>$image['mime'],'base64'=>base64_encode($bytes)])]);$proof=(int)$q->fetchColumn();
        $ref='SOMI-MANUAL-'.$proof;$evidence='FINANCE-EVIDENCE:'.$proof;$service=new PaymentService($this->db);
        if($action==='PAYMENT'){$service->reconcileBank($admin,$id,['bank_reference'=>$ref,'evidence_reference'=>$evidence,'amount_minor'=>(string)$r['amount_minor'],'currency_code'=>$r['currency_code']]);}
        elseif($action==='REFUND'){
            if($r['provider']==='SEPAY'){
                $service->reconcileProviderRefund($admin,$id,['provider_refund_id'=>$ref,'evidence_reference'=>$evidence,'original_payment_reference'=>$r['provider_payment_ref'],'amount_minor'=>(string)$r['refund_amount_minor'],'currency_code'=>$r['currency_code'],'original_method_confirmed'=>true]);
                return ['confirmed'=>true,'evidence_id'=>$proof];
            }
            if($r['provider']!=='MANUAL_BANK')throw new DeliveryApiError(422,'GATEWAY_REFUND_REQUIRED','This refund provider requires separate review.');
            $bank=$this->rows('SELECT * FROM customer_bank_accounts WHERE customer_id=? ORDER BY bank_account_id DESC LIMIT 1 FOR SHARE',[$r['customer_id']])[0]??null;
            if(!$bank||$bank['review_status']!=='APPROVED'||(string)$bank['bank_account_id']!==(string)($in['bank_account_id']??''))throw new DeliveryApiError(409,'BANK_REVIEW_REQUIRED','Review and approve the displayed refund bank account first.');
            $service->reconcileBankRefund($admin,$id,['bank_account_id'=>$bank['bank_account_id'],'bank_reference'=>$ref,'evidence_reference'=>$evidence,'original_payment_reference'=>$r['provider_payment_ref'],'amount_minor'=>(string)$r['refund_amount_minor'],'currency_code'=>$r['currency_code']]);
        }else{(new ShipperFinanceService($this->db))->reconcile($admin,['payout_id'=>$id,'provider_payout_ref'=>$ref,'evidence_reference'=>$evidence,'amount_minor'=>(string)$r['payout_amount_minor'],'currency_code'=>'VND']);}
        return ['confirmed'=>true,'evidence_id'=>$proof];
    }
}
