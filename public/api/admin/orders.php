<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/CustomerOrderService.php';
somi_run_api(['GET'],['ADMIN'],'order.read_all',true,static function(PDO $db,int $admin):array{
    if(isset($_GET['id'])){
        $id=DeliveryInput::id($_GET['id']);$q=$db->prepare('SELECT customer_id FROM orders WHERE order_id=?');$q->execute([$id]);$owner=$q->fetchColumn();if(!$owner)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        AuditService::record($db,$admin,'ADMIN','ORDER_READ','ORDER',$id);return [200,['order'=>(new CustomerOrderService($db))->detail((int)$owner,$id)]];
    }
    $params=[];$where='TRUE';foreach(['customer_id','before_id']as $field)if(isset($_GET[$field])){$where.=$field==='before_id'?' AND order_id<?':' AND customer_id=?';$params[]=DeliveryInput::id($_GET[$field]);}
    if(isset($_GET['status'])){$status=DeliveryInput::text($_GET['status'],'status',30);if(!isset(OrderLifecycleService::TRANSITIONS[$status]))DeliveryInput::invalid('status');$where.=' AND status=?';$params[]=$status;}
    $q=$db->prepare('SELECT order_id,order_code,customer_id,status,currency_code,total_charge_minor,created_at FROM orders WHERE '.$where.' ORDER BY order_id DESC LIMIT 100');$q->execute($params);AuditService::record($db,$admin,'ADMIN','ORDERS_SEARCH','ORDER',null);return [200,['items'=>$q->fetchAll()]];
});
