<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'payment.reconcile',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query('SELECT payment_id,order_id,amount_minor,currency_code,method,provider,status,provider_payment_ref,paid_at,created_at,failure_code FROM payments ORDER BY payment_id DESC LIMIT 100')->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['payment_id']);$id=DeliveryInput::id($in['payment_id']??null);$q=$db->prepare('SELECT * FROM payments WHERE payment_id=?');$q->execute([$id]);$payment=$q->fetch();if(!$payment)throw new DeliveryApiError(404,'PAYMENT_NOT_FOUND','Payment not found.');
    if($payment['provider']==='MANUAL_BANK')throw new DeliveryApiError(422,'BANK_EVIDENCE_REQUIRED','Use the bank reconciliation endpoint with statement evidence.');
    (new SePayPaymentProvider())->assertConfigured();
    JobQueue::enqueue($db,'PAYMENT_RECONCILE','admin-reconcile:'.$id.':'.bin2hex(random_bytes(8)),['payment_id'=>(string)$id]);AuditService::record($db,$admin,'ADMIN','PAYMENT_RECONCILIATION_REQUESTED','PAYMENT',$id);return [202,['queued'=>true]];
});
