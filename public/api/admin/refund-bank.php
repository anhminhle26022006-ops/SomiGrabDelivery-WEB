<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/EncryptedPayload.php';
require_once __DIR__.'/../../../app/Services/AuditService.php';
somi_run_api(['GET'],['ADMIN'],'payment.reconcile',true,static function(PDO $db,int $admin):array{
    $id=DeliveryInput::id($_GET['refund_id']??null,'refund_id');
    $q=$db->prepare('SELECT r.refund_id,r.status AS refund_status,r.refund_amount_minor,r.deducted_fee_minor,r.reason,p.provider_payment_ref,o.order_code,o.status AS order_status,u.full_name,b.* FROM refunds r JOIN payments p USING(payment_id) JOIN orders o ON o.order_id=r.order_id JOIN users u ON u.user_id=o.customer_id LEFT JOIN LATERAL (SELECT * FROM customer_bank_accounts a WHERE a.customer_id=o.customer_id AND (r.refund_bank_account_id IS NULL OR a.bank_account_id=r.refund_bank_account_id) ORDER BY a.bank_account_id DESC LIMIT 1) b ON true WHERE r.refund_id=?');$q->execute([$id]);$r=$q->fetch();
    if(!$r)throw new DeliveryApiError(404,'REFUND_NOT_FOUND','Refund not found.');
    if($r['encrypted_payload'])$r=array_replace($r,EncryptedPayload::open($r['encrypted_payload']));unset($r['encrypted_payload']);
    AuditService::record($db,$admin,'ADMIN','REFUND_BANK_VIEWED','REFUND',$id);
    return [200,['destination'=>$r]];
});
