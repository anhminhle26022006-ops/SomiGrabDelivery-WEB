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
}
