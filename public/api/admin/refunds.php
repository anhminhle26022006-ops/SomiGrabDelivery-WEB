<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'payment.refund',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query('SELECT * FROM refunds ORDER BY refund_id DESC LIMIT 100')->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['order_id','reason']);$orderId=DeliveryInput::id($in['order_id']??null);$reason=DeliveryInput::text($in['reason']??null,'reason',40);return [202,['refund'=>(new PaymentService($db))->requestRefund($orderId,$reason,'event-refund:'.$orderId.':'.$reason,$admin)]];
});
