<?php
declare(strict_types=1);
require_once __DIR__.'/EmailService.php';
require_once __DIR__.'/PaymentService.php';

final class Person1EventConsumer
{
    public static function consumeOne(PDO $db): bool
    {
        if($db->inTransaction())throw new LogicException('Event worker owns its transaction.');$db->beginTransaction();
        try{
            $q=$db->query("SELECT e.* FROM order_events e WHERE NOT EXISTS(SELECT 1 FROM event_consumer_receipts r WHERE r.consumer='person1' AND r.event_id=e.event_id) ORDER BY e.event_id FOR UPDATE OF e SKIP LOCKED LIMIT 1");$event=$q->fetch();
            if(!$event){$db->commit();return false;}
            self::handle($db,$event);
            $db->prepare("INSERT INTO event_consumer_receipts(consumer,event_id) VALUES ('person1',?)")->execute([$event['event_id']]);$db->commit();return true;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function handle(PDO $db,array $event): void
    {
        $q=$db->prepare('SELECT * FROM orders WHERE order_id=?');$q->execute([$event['order_id']]);$order=$q->fetch();if(!$order)throw new RuntimeException('Event order missing.');
        $type=$event['event_type'];
        if($type==='NoShipperFound'){self::resolveNoShipper($db,(int)$event['order_id']);return;}
        if(in_array($type,['OrderCreated','PaymentConfirmed','OrderCancelled','OrderDelivered','OrderReturning','OrderReturned','RefundSucceeded'],true)){
            EmailService::enqueue($db,(int)$order['customer_id'],$type,'order-event:'.$event['event_id'],['order_code'=>$order['order_code']],(int)$order['order_id']);
        }
        if($type==='RefundRequested'){
            $payload=is_array($event['payload'])?$event['payload']:json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
            $reason=$payload['reason']??'CUSTOMER_CANCELLATION';
            (new PaymentService($db))->requestRefund((int)$order['order_id'],$reason,'event-refund:'.$order['order_id'].':'.$reason);
        }
        // Person 2 publishes OrderReturned only after both parties confirm.
        if($type==='OrderReturned'){
            $q=$db->prepare('SELECT status FROM payments WHERE order_id=?');$q->execute([$order['order_id']]);
            if(in_array($q->fetchColumn(),['PAID','PARTIALLY_REFUNDED'],true))(new PaymentService($db))->requestRefund((int)$order['order_id'],'RETURN_FEE_DEDUCTION','event-refund:'.$order['order_id'].':RETURN_FEE_DEDUCTION');
        }
    }
    /** Internal worker entry point; also resolves exhausted orders with older event receipts. */
    public static function resolveNoShipper(PDO $db,int $orderId): bool
    {
        $order=OrderLifecycleService::lock($db,(int)$orderId);
        if($order['status']!=='NO_SHIPPER_FOUND')return false;
        $q=$db->prepare("SELECT 1 FROM order_assignments WHERE order_id=? AND assignment_status='ACTIVE'");$q->execute([$order['order_id']]);
        if($q->fetchColumn())throw new LogicException('Cannot cancel an assigned order.');
        $db->prepare("UPDATE orders SET status='CANCELLED',cancellation_fee_minor=0,cancelled_at=now(),closed_at=now(),updated_at=now() WHERE order_id=?")->execute([$order['order_id']]);
        $db->prepare("UPDATE order_match_offers SET status='CANCELLED',responded_at=now() WHERE order_id=? AND status='PENDING'")->execute([$order['order_id']]);
        OrderLifecycleService::history($db,(int)$order['order_id'],'NO_SHIPPER_FOUND','CANCELLED',null,'SYSTEM','Matching exhausted; full refund requested.');
        OrderLifecycleService::event($db,$order,'OrderCancelled','cancelled:'.$order['order_id'],['reason'=>'NO_SHIPPER_FOUND','refund_required_hint'=>true]);
        (new PaymentService($db))->requestRefund((int)$order['order_id'],'NO_SHIPPER_FOUND','event-refund:'.$order['order_id'].':NO_SHIPPER_FOUND');
        AuditService::record($db,null,'SYSTEM','NO_SHIPPER_AUTO_CANCELLED','ORDER',(int)$order['order_id']);
        return true;
    }

}
