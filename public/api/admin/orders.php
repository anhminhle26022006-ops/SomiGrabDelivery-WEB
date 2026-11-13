<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/CustomerOrderService.php';
require_once __DIR__.'/../../../app/Services/AdminWorkQueue.php';
somi_run_api(['GET'],['ADMIN'],'order.read_all',true,static function(PDO $db,int $admin):array{
    if(isset($_GET['order_id']))$_GET['id']=$_GET['order_id'];
    if(isset($_GET['id'])){
        $id=DeliveryInput::id($_GET['id']);$q=$db->prepare('SELECT customer_id FROM orders WHERE order_id=?');$q->execute([$id]);$owner=$q->fetchColumn();if(!$owner)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        $order=(new CustomerOrderService($db))->detail((int)$owner,$id);
        $q=$db->prepare('SELECT a.assignment_id,a.shipper_id,a.assignment_status,d.picked_up_at FROM order_assignments a LEFT JOIN deliveries d USING(assignment_id) WHERE a.order_id=? ORDER BY a.assignment_id DESC LIMIT 50');$q->execute([$id]);$order['assignments']=$q->fetchAll();
        AuditService::record($db,$admin,'ADMIN','ORDER_READ','ORDER',$id);return [200,['order'=>$order]];
    }
    $params=[];$where='TRUE';if(isset($_GET['customer_id'])){$where.=' AND customer_id=?';$params[]=DeliveryInput::id($_GET['customer_id']);}
    if(!empty($_GET['q'])){$term='%'.DeliveryInput::text($_GET['q'],'q',100).'%';$where.=' AND (order_code ILIKE ? OR sender_name ILIKE ? OR receiver_name ILIKE ? OR pickup_address ILIKE ? OR delivery_address ILIKE ?)';array_push($params,$term,$term,$term,$term,$term);}
    if(isset($_GET['status'])){$status=DeliveryInput::text($_GET['status'],'status',30);if(!isset(OrderLifecycleService::TRANSITIONS[$status]))DeliveryInput::invalid('status');$where.=' AND status=?';$params[]=$status;}
    $page=AdminWorkQueue::page($db,'SELECT '.AdminWorkQueue::rank(AdminWorkQueue::ORDER_WORK).' AS work_priority,o.order_id,o.order_code,o.customer_id,o.status,o.currency_code,o.total_charge_minor,o.created_at FROM orders o WHERE '.$where,$params,'order_id',$_GET);
    AuditService::record($db,$admin,'ADMIN','ORDERS_SEARCH','ORDER',null);return [200,$page];
});
