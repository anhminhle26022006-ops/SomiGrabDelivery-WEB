<?php
declare(strict_types=1);
require_once __DIR__.'/ShipperFinanceService.php';
require_once __DIR__.'/PrivateStorageService.php';
require_once __DIR__.'/ShipperInput.php';
require_once __DIR__.'/ShipperScoreService.php';

final class Person3Worker
{
    public function __construct(private PDO $db) {}
    private function scan(string $kind,int $limit):array {
        $db=$this->db;$db->beginTransaction();try{
            $q=$db->prepare('SELECT last_id FROM person3_worker_cursors WHERE name=? FOR UPDATE');$q->execute([$kind]);$after=(int)$q->fetchColumn();
            $sql=$kind==='earnings'?"SELECT order_id AS id FROM orders o WHERE status IN ('DELIVERED','RETURNED') AND NOT EXISTS(SELECT 1 FROM shipper_earnings e WHERE e.order_id=o.order_id) ORDER BY CASE WHEN order_id>? THEN 0 ELSE 1 END,order_id LIMIT ?":"SELECT earning_id AS id FROM shipper_earnings e WHERE payout_status='PENDING' AND NOT EXISTS(SELECT 1 FROM shipper_payouts p WHERE p.earning_id=e.earning_id) ORDER BY CASE WHEN earning_id>? THEN 0 ELSE 1 END,earning_id LIMIT ?";
            $q=$db->prepare($sql);$q->bindValue(1,$after,PDO::PARAM_INT);$q->bindValue(2,$limit,PDO::PARAM_INT);$q->execute();$items=$q->fetchAll();
            if($items)$db->prepare('UPDATE person3_worker_cursors SET last_id=? WHERE name=?')->execute([end($items)['id'],$kind]);$db->commit();return $items;
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public function consumeOne():bool {
        $db=$this->db;$db->beginTransaction();
        try {
            $event=$db->query("SELECT e.* FROM order_events e WHERE NOT EXISTS(SELECT 1 FROM event_consumer_receipts r WHERE r.consumer='person3' AND r.event_id=e.event_id) AND NOT EXISTS(SELECT 1 FROM person3_event_failures f WHERE f.event_id=e.event_id AND (f.status='FAILED' OR f.next_attempt_at>now())) ORDER BY e.event_id FOR UPDATE OF e SKIP LOCKED LIMIT 1")->fetch();
            if(!$event){$db->commit();return false;}
            $q=$db->prepare('SELECT customer_id,order_code FROM orders WHERE order_id=?');$q->execute([$event['order_id']]);$o=$q->fetch();
            $templates=[
                'OrderCreated'=>['Đã tạo đơn','Order created'], 'PaymentConfirmed'=>['Đã thanh toán','Payment confirmed'],
                'OrderCancelled'=>['Đơn đã hủy','Order cancelled'], 'OrderDelivered'=>['Giao thành công','Order delivered'],
                'OrderReturning'=>['Đang hoàn hàng','Order returning'], 'OrderReturned'=>['Đã hoàn hàng','Order returned'],
                'RefundSucceeded'=>['Đã hoàn tiền','Refund completed'], 'ShipperAssigned'=>['Shipper đã nhận đơn','Shipper accepted your order'],
                'OrderArrived'=>['Shipper đã đến điểm giao','Shipper arrived at drop-off'], 'OrderPickedUp'=>['Đã lấy hàng','Order picked up'], 'OrderDelivering'=>['Đang giao hàng','Order delivering']
                ,'NoShipperFound'=>['Chưa tìm được người giao','No shipper found']
            ];
            if($o&&isset($templates[$event['event_type']])){
                [$vi,$en]=$templates[$event['event_type']];
                InteractionService::notify($db,(int)$o['customer_id'],(int)$event['order_id'],$event['event_type'],$vi,$en,$o['order_code'].' · '.$vi,$o['order_code'].' · '.$en,'event:'.$event['event_id'],'CUSTOMER');
                // Customer checkout/payment/matching events never become shipper alerts.
                if(in_array($event['event_type'],['ShipperAssigned','OrderCancelled','OrderArrived','OrderPickedUp','OrderDelivering','OrderDelivered','OrderReturning','OrderReturned'],true)){
                    $q=$db->prepare("SELECT DISTINCT shipper_id FROM order_assignments WHERE order_id=? AND assignment_status IN ('ACTIVE','COMPLETED','RETURNED')");$q->execute([$event['order_id']]);
                    if($event['event_type']==='ShipperAssigned'){ $vi='Bạn đã nhận đơn'; $en='You accepted the order'; }
                    foreach($q->fetchAll() as $a)InteractionService::notify($db,(int)$a['shipper_id'],(int)$event['order_id'],$event['event_type'],$vi,$en,$o['order_code'].' · '.$vi,$o['order_code'].' · '.$en,'event:'.$event['event_id'],'SHIPPER');
                }
            }
            // Offers are addressed from persisted offers, never a user ID in untrusted event payloads.
            if(in_array($event['event_type'],['RefundRequested','RefundSucceeded','ShipperViolationConfirmed'],true)){
                $db->prepare('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE')->execute([$event['order_id']]);
                $db->prepare('SELECT payment_id FROM payments WHERE order_id=? FOR UPDATE')->execute([$event['order_id']]);
                $db->prepare('INSERT INTO shipper_finance_reviews(event_id,order_id,reason) VALUES(?,?,?) ON CONFLICT(event_id) DO NOTHING')->execute([$event['event_id'],$event['order_id'],$event['event_type']]);
                $db->prepare("UPDATE shipper_payouts p SET failure_reason=? FROM shipper_earnings e WHERE e.earning_id=p.earning_id AND e.order_id=? AND p.status<>'SUCCEEDED'")->execute([$event['event_type']==='ShipperViolationConfirmed'?'SHIPPER_FAULT_REVIEW_REQUIRED':'REFUND_REVIEW_REQUIRED',$event['order_id']]);
            }
            if($o&&$event['event_type']==='OfferCreated'){
                $q=$db->prepare("SELECT offer_id,shipper_id FROM order_match_offers WHERE order_id=? AND status='PENDING' AND expires_at>now()");$q->execute([$event['order_id']]);
                foreach($q->fetchAll() as $a)InteractionService::notify($db,(int)$a['shipper_id'],(int)$event['order_id'],'OfferCreated','Có đơn mới','New delivery offer',$o['order_code'],$o['order_code'],'offer:'.$a['offer_id'],'SHIPPER');
            }
            if(in_array($event['event_type'],['OrderDelivered','RefundSucceeded'],true)){
                if($event['event_type']==='OrderDelivered')(new ShipperScoreService($db))->recovery((int)$event['order_id']);
                (new ShipperFinanceService($db))->settleOrder((int)$event['order_id']);
            }
            $db->prepare("INSERT INTO event_consumer_receipts(consumer,event_id) VALUES('person3',?)")->execute([$event['event_id']]);$db->prepare('DELETE FROM person3_event_failures WHERE event_id=?')->execute([$event['event_id']]);$db->commit();return true;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(empty($event))throw $e;
            $code=$e instanceof DeliveryApiError?$e->errorCode:($e instanceof PDOException?'DATABASE_ERROR':'EVENT_ERROR');
            $db->prepare("INSERT INTO person3_event_failures(event_id,attempts,next_attempt_at,last_error_code,status) VALUES(?,1,now()+interval '30 seconds',?,'PENDING') ON CONFLICT(event_id) DO UPDATE SET attempts=person3_event_failures.attempts+1,next_attempt_at=now()+make_interval(secs=>LEAST(3600,30*power(2,LEAST(7,person3_event_failures.attempts)))::int),last_error_code=EXCLUDED.last_error_code,status=CASE WHEN person3_event_failures.attempts+1>=8 THEN 'FAILED' ELSE 'PENDING' END")->execute([$event['event_id'],$code]);return true;
        }
    }
    public function settle(int $limit):int {
        $count=0;
        foreach($this->scan('earnings',$limit) as $o){$this->db->beginTransaction();try{if((new ShipperFinanceService($this->db))->accrue((int)$o['id']))$count++;$this->db->commit();}catch(Throwable $e){$this->db->rollBack();throw $e;}}
        if(getenv('SOMI_PAYOUT_MODE')==='manual'){
            foreach($this->scan('payouts',$limit) as $e){$this->db->beginTransaction();try{(new ShipperFinanceService($this->db))->reserve((int)$e['id']);$this->db->commit();}catch(Throwable $ex){$this->db->rollBack();throw $ex;}}
        }return $count;
    }
    public function retain():void {
        $db=$this->db;$db->beginTransaction();try{
            $db->exec("UPDATE chat_messages m SET delete_after=o.closed_at+interval '1 month' FROM orders o WHERE o.order_id=m.order_id AND o.closed_at IS NOT NULL AND m.delete_after='infinity'");
            $db->exec("UPDATE chat_messages SET body=NULL,message_type='SYSTEM',deleted_at=now() WHERE deleted_at IS NULL AND delete_after<=now()");
            $days=(int)ShipperInput::config('evidence_retention_days');
            $db->exec("UPDATE private_files f SET delete_after=o.closed_at+CASE WHEN f.purpose='CHAT' THEN interval '1 month' ELSE make_interval(days=>$days) END FROM orders o WHERE f.order_id=o.order_id AND o.closed_at IS NOT NULL AND f.purpose IN ('CHAT','EVIDENCE') AND (f.delete_after='infinity' OR (f.purpose='EVIDENCE' AND f.delete_after<o.closed_at+make_interval(days=>$days)))");
            $db->exec("UPDATE delivery_evidence e SET delete_after=o.closed_at+make_interval(days=>$days) FROM deliveries d JOIN orders o ON o.order_id=d.order_id WHERE e.delivery_id=d.delivery_id AND o.closed_at IS NOT NULL AND (e.delete_after='infinity' OR e.delete_after<o.closed_at+make_interval(days=>$days))");
            $db->exec("UPDATE private_files f SET delete_after=t.closed_at+interval '3 months' FROM support_tickets t WHERE f.ticket_id=t.ticket_id AND t.closed_at IS NOT NULL AND f.delete_after='infinity'");
            $db->exec("UPDATE private_files f SET delete_after=(SELECT min(a.created_at)+interval '6 months' FROM audit_logs a WHERE a.entity_type='SHIPPER_PROFILE' AND a.entity_id::text=f.owner_id::text AND a.action IN ('SHIPPER_APPLICATION_APPROVED','SHIPPER_APPLICATION_REJECTED') AND a.created_at>=f.created_at) WHERE f.purpose='KYC' AND f.delete_after='infinity' AND EXISTS(SELECT 1 FROM audit_logs a WHERE a.entity_type='SHIPPER_PROFILE' AND a.entity_id::text=f.owner_id::text AND a.action IN ('SHIPPER_APPLICATION_APPROVED','SHIPPER_APPLICATION_REJECTED') AND a.created_at>=f.created_at)");
            $db->exec('UPDATE bank_account_changes SET encrypted_payload=NULL WHERE encrypted_payload IS NOT NULL AND (consumed_at IS NOT NULL OR expires_at<=now())');
            if(PrivateStorageService::driver()==='supabase'||getenv('SOMI_STORAGE_ROOT')){$storage=new PrivateStorageService($db);$storage->retain();if(PrivateStorageService::driver()==='local')$storage->pruneOrphans();}
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
}
