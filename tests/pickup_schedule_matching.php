<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__.'/../app/Services/MatchingService.php';
require_once __DIR__.'/../app/Services/OfferService.php';
require_once __DIR__.'/../app/Services/OfferAcceptService.php';
require_once __DIR__.'/../app/Services/EmailService.php';
try {
    $db=shipper_test_db();
    $matching=new MatchingService($db); $offers=new OfferService($db);
    tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(SHIPPER_A,NEAR[0],NEAR[1]));
    $id=paid_order($db);
    $db->exec("UPDATE orders SET status='PENDING_PAYMENT',paid_at=NULL WHERE order_id=$id");
    $db->exec("UPDATE payments SET status='PROCESSING',paid_at=NULL WHERE order_id=$id");
    check(tx($db,fn()=>$matching->dispatch($id))['result']==='SKIPPED','unconfirmed payment never reaches shipper');
    $db->exec("UPDATE payments SET status='PAID',paid_at=now() WHERE order_id=$id");
    $payment=(int)value($db,'SELECT payment_id FROM payments WHERE order_id=?',[$id]);
    tx($db,fn()=>OrderLifecycleService::paymentConfirmed($db,$id,$payment));
    check(tx($db,fn()=>$matching->dispatchNearby(SHIPPER_A))[$id]['result']==='OFFERED','confirmed immediate order appears through polling without worker');
    $pending=$offers->pending(SHIPPER_A)['items'];
    $offer=(int)$pending[0]['offer_id'];
    $accept=new OfferAcceptService($db);
    check(tx($db,fn()=>$accept->accept(SHIPPER_A,$offer))['created'],'shipper accepts paid customer order');
    check(!tx($db,fn()=>$accept->accept(SHIPPER_A,$offer))['created'],'accept retry is idempotent');
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$id])==='SHIPPER_ASSIGNED','customer order status reflects accepted delivery');
    $db->exec("UPDATE order_assignments SET assignment_status='CANCELLED_BEFORE_PICKUP' WHERE order_id=$id");

    $scheduled=paid_order($db);
    $db->exec("UPDATE orders SET paid_at=now()-interval '2 hours', pricing_snapshot=jsonb_build_object('schedule',jsonb_build_object('mode','later','at',now()+interval '2 hours')) WHERE order_id=$scheduled");
    $db->exec("UPDATE order_status_history SET created_at=now()-interval '2 hours' WHERE order_id=$scheduled");
    check(tx($db,fn()=>$matching->dispatch($scheduled))['result']==='SCHEDULED','future paid appointment does not match or time out early');
    check(!in_array($scheduled,$matching->ordersNeedingDispatch(),true),'worker excludes appointments outside matching window');
    check(!array_key_exists($scheduled,tx($db,fn()=>$matching->dispatchNearby(SHIPPER_A))),'shipper polling excludes future appointments');
    $db->exec("UPDATE orders SET pricing_snapshot=jsonb_set(pricing_snapshot,'{schedule,at}',to_jsonb(now()+interval '13 minutes')) WHERE order_id=$scheduled");
    check(in_array($scheduled,$matching->ordersNeedingDispatch(),true),'worker finds due appointment');
    check(tx($db,fn()=>$matching->dispatchNearby(SHIPPER_A))[$scheduled]['result']==='OFFERED','due appointment matches despite payment two hours ago');
    $pending=$offers->pending(SHIPPER_A)['items'];
    check($pending[0]['pickup_schedule']['mode']==='later','shipper receives scheduled pickup time');
    check(tx($db,fn()=>$accept->accept(SHIPPER_A,(int)$pending[0]['offer_id']))['created'],'scheduled delivery can be accepted');
    $mail=EmailService::render('OrderCancelled','vi',['order_code'=>'TEST','reason'=>'NO_SHIPPER_FOUND']);
    check(str_contains($mail[1],'Không tìm được shipper')&&str_contains($mail[1],'chưa phải xác nhận'),'cancellation email explains reason without claiming a completed refund');
    foreach ([['mode'=>'later','at'=>'2026-02-30T12:00:00Z'],['mode'=>'later','at'=>'tomorrow'],['mode'=>'unknown']] as $bad) rejects(fn()=>PickupSchedule::validate($bad),'VALIDATION_ERROR');
    echo "Pickup schedule and customer-to-shipper checks passed: $checks\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");exit(1);}
