<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'payment.reconcile',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query("SELECT p.payment_id,p.order_id,p.amount_minor,p.currency_code,p.method,p.provider,p.status,p.provider_payment_ref,p.paid_at,p.created_at,p.failure_code,o.status AS order_status,o.order_code,u.full_name AS customer_name,CASE WHEN p.provider='DEMO' THEN 'DEMO_NO_MONEY' WHEN p.status='REFUNDED' THEN 'REFUNDED' WHEN p.status='PARTIALLY_REFUNDED' THEN 'PARTIALLY_REFUNDED' WHEN p.status='PAID' AND EXISTS(SELECT 1 FROM shipper_earnings e WHERE e.order_id=p.order_id AND e.payout_status='PAID') THEN 'SHIPPER_PAID' WHEN p.status='PAID' THEN 'ADMIN_HOLDING' ELSE 'AWAITING_PAYMENT' END AS custody_status FROM payments p JOIN orders o USING(order_id) JOIN users u ON u.user_id=o.customer_id ORDER BY p.payment_id DESC LIMIT 100")->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['payment_id']);$id=DeliveryInput::id($in['payment_id']??null);$q=$db->prepare('SELECT * FROM payments WHERE payment_id=?');$q->execute([$id]);$payment=$q->fetch();if(!$payment)throw new DeliveryApiError(404,'PAYMENT_NOT_FOUND','Payment not found.');
    if($payment['provider']==='MANUAL_BANK')throw new DeliveryApiError(422,'BANK_EVIDENCE_REQUIRED','Use the bank reconciliation endpoint with statement evidence.');
    (new SePayPaymentProvider())->assertConfigured();
    JobQueue::enqueue($db,'PAYMENT_RECONCILE','admin-reconcile:'.$id.':'.bin2hex(random_bytes(8)),['payment_id'=>(string)$id]);AuditService::record($db,$admin,'ADMIN','PAYMENT_RECONCILIATION_REQUESTED','PAYMENT',$id);return [202,['queued'=>true]];
});
