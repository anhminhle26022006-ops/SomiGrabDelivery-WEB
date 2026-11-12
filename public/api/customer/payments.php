<?php
declare(strict_types=1);
require_once __DIR__.'/../_delivery.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
delivery_run(['GET','POST'],'order.read_own',$method==='POST',static function(PDO $db,int $id)use($method):array{
    $orderId=DeliveryInput::id($_GET['order_id']??null,'order_id');AccessControl::requireOrder($db,$id,'CUSTOMER',$orderId);$service=new PaymentService($db);
    if($method==='GET')return [200,['payment'=>$service->read($id,$orderId)]];
    $in=delivery_body();DeliveryInput::only($in,['method']);return [202,['payment'=>$service->create($id,$orderId,DeliveryInput::text($in['method']??null,'method',30),DeliveryInput::text($_SERVER['HTTP_IDEMPOTENCY_KEY']??null,'Idempotency-Key',100))]];
});
