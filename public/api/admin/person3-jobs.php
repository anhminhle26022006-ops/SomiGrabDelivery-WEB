<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/DeliveryInput.php';
somi_run_api(['GET','POST'],['ADMIN'],'person3.manage_jobs',true,function(PDO $db,int $user):array {
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $before=isset($_GET['before_id'])?DeliveryInput::id($_GET['before_id']):PHP_INT_MAX;
        $q=$db->prepare('SELECT f.*,e.order_id,e.event_type FROM person3_event_failures f JOIN order_events e USING(event_id) WHERE f.event_id<? ORDER BY f.event_id DESC LIMIT 50');$q->execute([$before]);$items=$q->fetchAll();return [200,['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['event_id']:null]];
    }
    $in=somi_json_body(false);DeliveryInput::only($in,['event_id','reason']);$id=DeliveryInput::id($in['event_id']??null);$reason=DeliveryInput::text($in['reason']??null,'reason',2000);
    $q=$db->prepare("SELECT 1 FROM event_consumer_receipts WHERE consumer='person3' AND event_id=?");$q->execute([$id]);if($q->fetchColumn())return [200,['event_id'=>(string)$id,'status'=>'DONE']];
    $q=$db->prepare('SELECT status FROM person3_event_failures WHERE event_id=? FOR UPDATE');$q->execute([$id]);$status=$q->fetchColumn();if(!$status)throw new DeliveryApiError(404,'JOB_NOT_FOUND','Event failure not found.');
    if($status==='FAILED'){$db->prepare("UPDATE person3_event_failures SET status='PENDING',attempts=0,next_attempt_at=now() WHERE event_id=?")->execute([$id]);AuditService::record($db,$user,'ADMIN','PERSON3_EVENT_RETRY','ORDER_EVENT',$id,[],['reason'=>$reason]);}
    return [200,['event_id'=>(string)$id,'status'=>'PENDING']];
});
