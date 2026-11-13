<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__.'/../app/Services/ShipperEventConsumer.php';
require_once __DIR__.'/../app/Services/OfferService.php';
require_once __DIR__.'/../app/Services/OfferAcceptService.php';
require_once __DIR__.'/../app/Services/DeliveryService.php';
require_once __DIR__.'/../app/Services/CustomerOrderService.php';
function appointment(PDO $db,int $id,int $pickupOffset,int $historyOffset=0):array{
    $schedule=['mode'=>'later','at'=>gmdate(DATE_ATOM,time()+$pickupOffset),
        'dispatch_at'=>gmdate(DATE_ATOM,time()+$pickupOffset-900),'timezone'=>'Asia/Ho_Chi_Minh'];
    $db->prepare('UPDATE orders SET pricing_snapshot=CAST(? AS jsonb),paid_at=now()+make_interval(secs=>?) WHERE order_id=?')
        ->execute([json_encode(['schedule'=>$schedule]),$historyOffset,$id]);
    $db->prepare("UPDATE order_status_history SET created_at=now()+make_interval(secs=>?) WHERE order_id=? AND new_status='SEARCHING_SHIPPER'")
        ->execute([$historyOffset,$id]);
    return $schedule;
}
try{
    $db=shipper_test_db();
    $matching=new MatchingService($db);$offers=new OfferService($db);$accept=new OfferAcceptService($db);$delivery=new DeliveryService($db);
    tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(SHIPPER_A,NEAR[0],NEAR[1]));
    $future=paid_order($db);appointment($db,$future,3600,-86400);
    check(tx($db,fn()=>$matching->dispatch($future))['result']==='SCHEDULED','future appointment waits without exhausting search timeout');
    check(!in_array($future,$matching->ordersNeedingDispatch(),true),'sweep excludes future appointments');
    check(tx($db,fn()=>$matching->dispatchNearby(SHIPPER_A))===[],'poll excludes future appointments');
    check(!ShipperEventConsumer::consumeOne($db),'event consumer leaves future appointment queued');
    check((int)value($db,'SELECT count(*) FROM event_consumer_receipts')===0,'future event not acknowledged prematurely');
    $immediate=paid_order($db);
    $due=paid_order($db);$dueSchedule=appointment($db,$due,600,-86400);
    $queue=$matching->ordersNeedingDispatch();
    check($queue[0]===$due && in_array($immediate,$queue,true),'due appointment takes priority over older immediate order');
    check(ShipperEventConsumer::consumeOne($db),'worker dispatches due appointment');
    check((int)value($db,'SELECT count(*) FROM order_match_offers WHERE order_id=?',[$due])===1,'appointment paid yesterday still receives first offer');
    check((int)value($db,'SELECT count(*) FROM order_match_offers WHERE order_id=?',[$immediate])===0,'worker handles appointment before immediate event');
    $list=$offers->pending(SHIPPER_A)['items'];
    check($list[0]['schedule']['at']===$dueSchedule['at'],'shipper offer includes exact pickup appointment');
    $offerId=(int)$list[0]['offer_id'];
    tx($db,fn()=>$accept->accept(SHIPPER_A,$offerId));
    $current=$delivery->current(SHIPPER_A)['delivery'];
    check($current['schedule']['mode']==='later'&&!in_array('PICKUP',$current['next_actions'],true),'early pickup action hidden');
    rejects(fn()=>tx($db,fn()=>$delivery->pickup(SHIPPER_A,$due)),'PICKUP_TOO_EARLY');
    // A crafted early offer is rejected by accept too, independent of queue filtering.
    $db->prepare("INSERT INTO order_match_offers(order_id,shipper_id,expires_at) VALUES (?,4,now()+interval '60 seconds')")->execute([$future]);
    $futureOffer=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=?',[$future]);
    rejects(fn()=>tx($db,fn()=>$accept->accept(SHIPPER_B,$futureOffer)),'SCHEDULE_NOT_DUE');
    $db->prepare('DELETE FROM order_match_offers WHERE offer_id=?')->execute([$futureOffer]);
    tx($db,fn()=>$matching->dispatch($immediate));
    $immediateOffer=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=3',[$immediate]);
    // Manually invite the busy driver to exercise the acceptance guard, not only candidate filtering.
    if(!$immediateOffer){$db->prepare("INSERT INTO order_match_offers(order_id,shipper_id,expires_at) VALUES (?,3,now()+interval '60 seconds')")->execute([$immediate]);$immediateOffer=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=3',[$immediate]);}
    rejects(fn()=>tx($db,fn()=>$accept->accept(SHIPPER_A,$immediateOffer)),'ACTIVE_DELIVERY_EXISTS');
    appointment($db,$due,-1);
    tx($db,fn()=>$delivery->pickup(SHIPPER_A,$due));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$due])==='PICKED_UP','pickup succeeds at or after appointment');
    $missed=paid_order($db);appointment($db,$missed,-1000,-7200);
    check(tx($db,fn()=>$matching->dispatch($missed))['result']==='NO_SHIPPER_FOUND','missed appointment exits through existing no-shipper flow');
    $cancelled=paid_order($db);appointment($db,$cancelled,7200);
    tx($db,fn()=>(new CustomerOrderService($db))->cancel(CUSTOMER,$cancelled,'Cancel future appointment'));
    check(tx($db,fn()=>$matching->dispatch($cancelled))['result']==='SKIPPED','cancelled appointment never dispatches');
    echo "Scheduled pickup backend: $checks checks passed; TEMP tables only; no real payments or notifications.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);}
