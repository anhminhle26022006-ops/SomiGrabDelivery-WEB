<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryService.php';
require_once __DIR__.'/ShipperScoreService.php';
require_once __DIR__.'/ShipperFinanceService.php';
final class ShipperCancellationService
{
    public function __construct(private PDO $db) {}
    private function row(int $id):array {
        $q=$this->db->prepare('SELECT cancellation_request_id,order_id,assignment_id,reason_code,reason,requires_admin_decision,decision,decision_note,requested_at,decided_at FROM cancellation_requests WHERE cancellation_request_id=?');$q->execute([$id]);return ['request'=>$q->fetch()];
    }
    public function request(int $shipper,array $in):array {
        DeliveryInput::only($in,['order_id','reason_code','reason','client_key']);
        $orderId=DeliveryInput::id($in['order_id']??null);$reason=DeliveryInput::text($in['reason']??null,'reason',1000);
        $code=ShipperInput::choice($in['reason_code']??null,'reason_code',['SHIPPER_FAULT','EXTERNAL_ISSUE']);
        $key=DeliveryInput::text($in['client_key']??null,'client_key',100);if(!preg_match('/^[A-Za-z0-9._:-]{8,100}$/D',$key))DeliveryInput::invalid('client_key');
        $hash=hash('sha256',json_encode([$orderId,$code,$reason],JSON_THROW_ON_ERROR));
        // Reject foreign orders before taking a lock, including after the assignment ends.
        $q=$this->db->prepare('SELECT 1 FROM order_assignments WHERE order_id=? AND shipper_id=?');$q->execute([$orderId,$shipper]);if(!$q->fetchColumn())throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        OrderLifecycleService::lock($this->db,$orderId);
        $q=$this->db->prepare('SELECT cancellation_request_id,payload_hash FROM cancellation_requests WHERE requested_by=? AND request_key=?');$q->execute([$shipper,$key]);
        if($old=$q->fetch()){if(!hash_equals($old['payload_hash'],$hash))throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return $this->row((int)$old['cancellation_request_id']);}
        $ctx=DeliveryContext::lockActive($this->db,$shipper,$orderId);
        if(!in_array($ctx['order']['status'],['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY'],true))throw new DeliveryApiError(409,'INVALID_ORDER_STATE','Cannot request cancellation in this state.');
        $q=$this->db->prepare("SELECT 1 FROM cancellation_requests WHERE assignment_id=? AND decision='PENDING'");$q->execute([$ctx['assignment']['assignment_id']]);if($q->fetchColumn())throw new DeliveryApiError(409,'CANCELLATION_PENDING','An Admin review is already pending.');
        $immediate=$ctx['order']['status']==='SHIPPER_ASSIGNED'&&$code==='SHIPPER_FAULT';
        $q=$this->db->prepare("INSERT INTO cancellation_requests(order_id,requested_by,requester_role,reason_code,reason,requires_admin_decision,decision,assignment_id,request_key,payload_hash,shipper_at_fault) VALUES(?,?,'SHIPPER',?,?,?, ?,?,?,?,?) RETURNING cancellation_request_id");
        $q->execute([$orderId,$shipper,$code,$reason,$immediate?'false':'true',$immediate?'NOT_REQUIRED':'PENDING',$ctx['assignment']['assignment_id'],$key,$hash,$immediate?'true':null]);$id=(int)$q->fetchColumn();
        if($immediate)$this->releaseBeforePickup($ctx,null,true,$reason);
        AuditService::record($this->db,$shipper,'SHIPPER','SHIPPER_CANCELLATION_REQUESTED','CANCELLATION_REQUEST',$id,[],['requires_admin_decision'=>!$immediate]);
        return $this->row($id);
    }
    private function releaseBeforePickup(array $ctx,?int $admin,bool $fault,string $note):void {
        $order=$ctx['order'];$id=(int)$order['order_id'];
        if($ctx['delivery']['picked_up_at']!==null)throw new DeliveryApiError(409,'PICKUP_ALREADY_RECORDED','Pickup already recorded.');
        $this->db->prepare("UPDATE order_assignments SET assignment_status='CANCELLED_BEFORE_PICKUP',ended_at=now(),ended_reason=? WHERE assignment_id=?")->execute([$note,$ctx['assignment']['assignment_id']]);
        $this->db->prepare("UPDATE order_match_offers SET status='CANCELLED',responded_at=now() WHERE order_id=? AND status='PENDING'")->execute([$id]);
        DeliveryContext::transition($this->db,$order,'SEARCHING_SHIPPER',$admin??(int)$ctx['assignment']['shipper_id'],$admin?'ADMIN':'SHIPPER',$note);
        if($fault&&!((new ShipperScoreService($this->db))->apply((int)$ctx['assignment']['shipper_id'],$id,'CANCEL_BEFORE_PICKUP','cancel-before:'.$ctx['assignment']['assignment_id'],$admin,$note)))throw new DeliveryApiError(409,'SCORE_POLICY_REQUIRED','Publish a score policy first.');
        OrderLifecycleService::event($this->db,$order,'OrderReadyForMatching','matching:cancel:'.$ctx['assignment']['assignment_id']);
    }
    public function review(int $admin,array $in):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','order.cancel_admin');
        DeliveryInput::only($in,['request_id','decision','shipper_at_fault','note','evidence_reference']);
        $id=DeliveryInput::id($in['request_id']??null);$decision=ShipperInput::choice($in['decision']??null,'decision',['APPROVED','REJECTED']);
        if(!is_bool($in['shipper_at_fault']??null))DeliveryInput::invalid('shipper_at_fault');$fault=$in['shipper_at_fault'];
        if($fault)AccessControl::requirePermission($this->db,$admin,'ADMIN','shipper.verify');
        $note=DeliveryInput::text($in['note']??null,'note',2000);$proof=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',1000);
        $hash=hash('sha256',json_encode([$decision,$fault,$note,$proof],JSON_THROW_ON_ERROR));
        $q=$this->db->prepare("SELECT order_id FROM cancellation_requests WHERE cancellation_request_id=? AND requester_role='SHIPPER'");$q->execute([$id]);$orderId=$q->fetchColumn();if(!$orderId)throw new DeliveryApiError(404,'REQUEST_NOT_FOUND','Request not found.');
        $order=OrderLifecycleService::lock($this->db,(int)$orderId);
        $q=$this->db->prepare('SELECT * FROM cancellation_requests WHERE cancellation_request_id=? FOR UPDATE');$q->execute([$id]);$request=$q->fetch();
        if($request['decision']!=='PENDING'){if($request['review_hash']!==null&&hash_equals($request['review_hash'],$hash))return $this->row($id);throw new DeliveryApiError(409,'REQUEST_ALREADY_REVIEWED','Request already reviewed.');}
        $terminal=in_array($order['status'],['DELIVERED','RETURNED','CANCELLED'],true);
        if($terminal){
            if($decision!=='REJECTED')throw new DeliveryApiError(409,'INVALID_ORDER_STATE','Close the stale request without changing the completed order.');
            $q=$this->db->prepare('SELECT * FROM order_assignments WHERE assignment_id=? AND shipper_id=? AND order_id=? FOR UPDATE');$q->execute([$request['assignment_id'],$request['requested_by'],$orderId]);$assignment=$q->fetch();
            $q=$this->db->prepare('SELECT * FROM deliveries WHERE assignment_id=? FOR UPDATE');$q->execute([$request['assignment_id']]);$delivery=$q->fetch();
            if(!$assignment||!$delivery)throw new DeliveryApiError(409,'ASSIGNMENT_CHANGED','Assignment changed.');
            $ctx=['assignment'=>$assignment,'delivery'=>$delivery,'order'=>$order];
        }else $ctx=DeliveryContext::lockActive($this->db,(int)$request['requested_by'],(int)$orderId);
        if((int)$ctx['assignment']['assignment_id']!==(int)$request['assignment_id'])throw new DeliveryApiError(409,'ASSIGNMENT_CHANGED','Assignment changed.');
        if(!$terminal&&!in_array($order['status'],['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'],true))throw new DeliveryApiError(409,'INVALID_ORDER_STATE','Refresh the order before reviewing.');
        if($ctx['delivery']['picked_up_at']!==null&&$fault)(new ShipperScoreService($this->db))->violation($admin,['assignment_id'=>$request['assignment_id'],'reason'=>$note,'evidence_reference'=>$proof,'shipper_at_fault'=>true]);
        if($decision==='APPROVED'){
            if($order['status']==='SHIPPER_ASSIGNED')$this->releaseBeforePickup($ctx,$admin,$fault,$note);
            elseif($order['status']!=='RETURNING') {
                DeliveryContext::transition($this->db,$order,'RETURNING',$admin,'ADMIN',$note);
                $this->db->prepare("UPDATE deliveries SET failure_reason_code='OTHER',failure_note=?,return_started_at=now(),updated_at=now() WHERE delivery_id=?")->execute([$note,$ctx['delivery']['delivery_id']]);
                OrderLifecycleService::event($this->db,$order,'OrderReturning','returning:'.$orderId,['assignment_id'=>(string)$request['assignment_id'],'shipper_id'=>(string)$request['requested_by'],'reason_code'=>'ADMIN_APPROVED_RETURN']);
            }
        }
        $this->db->prepare('UPDATE cancellation_requests SET decision=?,decided_by=?,decision_note=?,decided_at=now(),shipper_at_fault=?,evidence_reference=?,review_hash=? WHERE cancellation_request_id=?')->execute([$decision,$admin,$note,$fault?'true':'false',$proof,$hash,$id]);
        if($terminal){(new ShipperScoreService($this->db))->recovery((int)$orderId);(new ShipperFinanceService($this->db))->settleOrder((int)$orderId);}
        AuditService::record($this->db,$admin,'ADMIN','SHIPPER_CANCELLATION_REVIEWED','CANCELLATION_REQUEST',$id,[],['decision'=>$decision,'shipper_at_fault'=>$fault,'evidence_reference'=>$proof]);return $this->row($id);
    }
    public function listing(int $admin,array $in):array {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','order.cancel_admin');$params=[];$where="c.requester_role='SHIPPER'";
        if(isset($in['order_id'])){$where.=' AND c.order_id=?';$params[]=DeliveryInput::id($in['order_id']);}else $where.=" AND c.decision='PENDING'";
        if(isset($in['before_id'])){$where.=' AND c.cancellation_request_id<?';$params[]=DeliveryInput::id($in['before_id']);}
        $q=$this->db->prepare('SELECT c.cancellation_request_id,c.order_id,c.assignment_id,c.requested_by,c.reason_code,c.reason,c.decision,c.requested_at,o.order_code,o.status AS order_status FROM cancellation_requests c JOIN orders o USING(order_id) WHERE '.$where.' ORDER BY c.cancellation_request_id DESC LIMIT 51');$q->execute($params);$items=$q->fetchAll();$more=count($items)>50;if($more)array_pop($items);
        AuditService::record($this->db,$admin,'ADMIN','SHIPPER_CANCELLATIONS_LIST','ORDER',isset($in['order_id'])?(int)$in['order_id']:null);
        return ['items'=>$items,'next_before_id'=>$more?(string)end($items)['cancellation_request_id']:null];
    }
}
