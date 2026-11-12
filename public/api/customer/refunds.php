<?php
declare(strict_types=1);
require_once __DIR__.'/../_delivery.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
delivery_run(['GET','POST'],'order.read_own',$method==='POST',static function(PDO $db,int $id)use($method):array{
    $orderId=DeliveryInput::id($_GET['order_id']??null,'order_id');$order=AccessControl::requireOrder($db,$id,'CUSTOMER',$orderId);
    if($method==='GET'){$q=$db->prepare('SELECT refund_id,payment_id,order_id,reason,gross_amount_minor,deducted_fee_minor,refund_amount_minor,currency_code,status,created_at,completed_at FROM refunds WHERE order_id=? ORDER BY refund_id');$q->execute([$orderId]);return [200,['items'=>$q->fetchAll()]];}
    $in=delivery_body();DeliveryInput::only($in,['reason']);$reason=DeliveryInput::text($in['reason']??null,'reason',40);
    if(!in_array($reason,['CUSTOMER_CANCELLATION','RETURN_FEE_DEDUCTION'],true))DeliveryInput::invalid('reason');
    // Same business key as the event consumer prevents duplicate reservations.
    return [202,['refund'=>(new PaymentService($db))->requestRefund($orderId,$reason,'event-refund:'.$orderId.':'.$reason)]];
});
