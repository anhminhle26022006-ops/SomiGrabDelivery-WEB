<?php
declare(strict_types=1);
require_once __DIR__.'/AccessControl.php';
require_once __DIR__.'/OrderLifecycleService.php';
/** Only trusted business transitions choose event types and deltas. */
final class ShipperScoreService
{
    public function __construct(private PDO $db) {}
    public function apply(int $shipper,int $order,string $type,string $key,?int $actor,string $reason,?string $evidence=null,?string $at=null):?array
    {
        if(!$this->db->inTransaction())throw new LogicException('Score updates require a transaction.');
        $fields=['CANCEL_BEFORE_PICKUP'=>'deduction_points','POST_PICKUP_VIOLATION'=>'post_pickup_deduction_points','RECOVERY'=>'recovery_points'];
        if(!isset($fields[$type]))throw new LogicException('Unsupported score event.');
        $q=$this->db->prepare('SELECT reliability_score FROM shipper_profiles WHERE user_id=? FOR UPDATE');$q->execute([$shipper]);$score=$q->fetchColumn();
        if($score===false)throw new DeliveryApiError(404,'SHIPPER_NOT_FOUND','Shipper not found.');
        $q=$this->db->prepare('SELECT * FROM shipper_score_events WHERE source_key=?');$q->execute([$key]);if($old=$q->fetch())return $old;
        $q=$this->db->prepare('SELECT * FROM shipper_score_policies WHERE effective_from<=COALESCE(?::timestamptz,now()) AND (effective_until IS NULL OR effective_until>COALESCE(?::timestamptz,now())) ORDER BY effective_from DESC LIMIT 1');$q->execute([$at,$at]);$policy=$q->fetch();
        if(!$policy)return null;
        $delta=(string)$policy[$fields[$type]];if($type!=='RECOVERY')$delta=bcsub('0',$delta,2);
        $after=bcadd((string)$score,$delta,2);if(bccomp($after,'0',2)<0)$after='0.00';if(bccomp($after,(string)$policy['max_score'],2)>0)$after=(string)$policy['max_score'];
        $delta=bcsub($after,(string)$score,2);
        $this->db->prepare('UPDATE shipper_profiles SET reliability_score=?,availability=CASE WHEN ?::numeric<?::numeric THEN FALSE ELSE availability END,updated_at=now() WHERE user_id=?')->execute([$after,$after,$policy['lock_threshold'],$shipper]);
        $q=$this->db->prepare('INSERT INTO shipper_score_events(shipper_id,order_id,event_type,points_delta,score_after,reason,actor_id,source_key,policy_snapshot,evidence_reference) VALUES(?,?,?,?,?,?,?,?,?::jsonb,?) RETURNING *');
        $q->execute([$shipper,$order,$type,$delta,$after,$reason,$actor,$key,json_encode($policy,JSON_THROW_ON_ERROR),$evidence]);$event=$q->fetch();
        AuditService::record($this->db,$actor,$actor?'ADMIN':'SYSTEM','SHIPPER_SCORE_APPLIED','SHIPPER_PROFILE',$shipper,[],['score_event_id'=>$event['score_event_id'],'event_type'=>$type,'points_delta'=>$delta,'score_after'=>$after]);return $event;
    }
    public function recovery(int $order):?array
    {
        OrderLifecycleService::lock($this->db,$order);
        $q=$this->db->prepare("SELECT 1 FROM cancellation_requests WHERE order_id=? AND requester_role='SHIPPER' AND decision='PENDING' UNION ALL SELECT 1 FROM shipper_score_events WHERE order_id=? AND event_type='POST_PICKUP_VIOLATION' LIMIT 1");$q->execute([$order,$order]);if($q->fetchColumn())return null;
        $q=$this->db->prepare("SELECT a.assignment_id,a.shipper_id,d.delivered_at FROM order_assignments a JOIN orders o USING(order_id) JOIN deliveries d USING(assignment_id) WHERE o.order_id=? AND o.status='DELIVERED' AND a.assignment_status='COMPLETED' AND d.delivered_at IS NOT NULL");$q->execute([$order]);$a=$q->fetch();
        return $a?$this->apply((int)$a['shipper_id'],$order,'RECOVERY','delivery-recovery:'.$a['assignment_id'],null,'Successful delivery.',null,$a['delivered_at']):null;
    }
    public function violation(int $admin,array $in):array
    {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','shipper.verify');
        DeliveryInput::only($in,['assignment_id','reason','evidence_reference','shipper_at_fault']);
        if(($in['shipper_at_fault']??null)!==true)throw new DeliveryApiError(422,'FAULT_CONFIRMATION_REQUIRED','Admin must verify Shipper fault.');
        $id=DeliveryInput::id($in['assignment_id']??null);$reason=DeliveryInput::text($in['reason']??null,'reason',2000);$proof=DeliveryInput::text($in['evidence_reference']??null,'evidence_reference',1000);
        $q=$this->db->prepare('SELECT order_id FROM order_assignments WHERE assignment_id=?');$q->execute([$id]);$order=$q->fetchColumn();if(!$order)throw new DeliveryApiError(404,'ASSIGNMENT_NOT_FOUND','Assignment not found.');
        OrderLifecycleService::lock($this->db,(int)$order);
        $q=$this->db->prepare('SELECT a.shipper_id FROM order_assignments a JOIN deliveries d USING(assignment_id) WHERE a.assignment_id=? AND d.picked_up_at IS NOT NULL');$q->execute([$id]);$shipper=$q->fetchColumn();if(!$shipper)throw new DeliveryApiError(409,'PICKUP_REQUIRED','A recorded pickup is required.');
        $event=$this->apply((int)$shipper,(int)$order,'POST_PICKUP_VIOLATION','pickup-violation:'.$id,$admin,$reason,$proof);
        if(!$event)throw new DeliveryApiError(409,'SCORE_POLICY_REQUIRED','Publish a score policy first.');
        $q=$this->db->prepare('SELECT * FROM orders WHERE order_id=?');$q->execute([$order]);$orderRow=$q->fetch();
        OrderLifecycleService::event($this->db,$orderRow,'ShipperViolationConfirmed','shipper-violation:'.$id,['assignment_id'=>(string)$id,'shipper_id'=>(string)$shipper]);
        return ['score_event'=>$event];
    }
}
