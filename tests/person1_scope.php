<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/Person1EventConsumer.php';
require_once __DIR__.'/../app/Services/MatchingService.php';
require_once __DIR__.'/../app/Services/AdminMediaService.php';
require_once __DIR__.'/../app/Services/CustomerOrderService.php';
require_once __DIR__.'/../app/Services/AdminConsoleService.php';
try {
    $db=shipper_test_db();
    $sql=file_get_contents(__DIR__.'/../database/migrations/20261112_no_shipper_refund.sql');
    $db->exec(str_replace('SET LOCAL search_path TO somi, public;','',$sql));
    $order=paid_order($db);
    $db->prepare("UPDATE order_status_history SET created_at=now()-interval '10 minutes' WHERE order_id=?")->execute([$order]);
    check(tx($db,fn()=>(new MatchingService($db))->dispatch($order))['result']==='NO_SHIPPER_FOUND','real matching exhaustion event');
    $event=['order_id'=>$order,'event_type'=>'NoShipperFound'];
    tx($db,fn()=>Person1EventConsumer::handle($db,$event));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$order])==='CANCELLED','exhaustion auto cancels');
    check((string)value($db,'SELECT refund_amount_minor FROM refunds WHERE order_id=?',[$order])==='30000','full refund reserved');
    check((string)value($db,'SELECT deducted_fee_minor FROM refunds WHERE order_id=?',[$order])==='0','no cancellation fee');
    check(value($db,'SELECT reason FROM refunds WHERE order_id=?',[$order])==='NO_SHIPPER_FOUND','refund reason explicit');
    tx($db,fn()=>Person1EventConsumer::handle($db,$event));
    check((int)value($db,'SELECT count(*) FROM refunds WHERE order_id=?',[$order])===1,'event replay does not reserve twice');
    check((int)value($db,"SELECT count(*) FROM background_jobs WHERE kind='REFUND'")===1,'refund job once');
    check((int)value($db,"SELECT count(*) FROM order_events WHERE event_type='OrderCancelled' AND order_id=?",[$order])===1,'cancel event once');
    check(value($db,'SELECT status FROM payments WHERE order_id=?',[$order])==='PAID','refund pending is not transferred');
    $pending=paid_order($db);check(!tx($db,fn()=>Person1EventConsumer::resolveNoShipper($db,$pending)),'active search ignored');
    $db->prepare("UPDATE orders SET status='NO_SHIPPER_FOUND' WHERE order_id=?")->execute([$pending]);
    $db->prepare("UPDATE payments SET status='PROCESSING',paid_at=NULL WHERE order_id=?")->execute([$pending]);
    rejects(fn()=>tx($db,fn()=>Person1EventConsumer::resolveNoShipper($db,$pending)),'PAYMENT_NOT_REFUNDABLE');
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$pending])==='NO_SHIPPER_FOUND','refund failure rolls cancellation back');
    $db->prepare("UPDATE orders SET status='CANCELLED' WHERE order_id=?")->execute([$pending]);
    $db->prepare("UPDATE payments SET status='PAID',paid_at=now() WHERE order_id=?")->execute([$pending]);
    rejects(fn()=>tx($db,fn()=>(new PaymentService($db))->requestRefund($pending,'NO_SHIPPER_FOUND','invalid-reason-fixture')),'REFUND_NOT_ELIGIBLE');

    foreach(['customer','admin'] as $actor){
        $manual=paid_order($db);$db->prepare("UPDATE orders SET status='NO_SHIPPER_FOUND',pricing_snapshot=CAST(? AS jsonb) WHERE order_id=?")->execute([json_encode(['cancellation_fees'=>['NO_SHIPPER_FOUND'=>'700']]),$manual]);
        tx($db,fn()=>$actor==='customer'?(new CustomerOrderService($db))->cancel(CUSTOMER,$manual,'Manual cancellation'):(new AdminConsoleService($db))->cancel(ADMIN,$manual,'Manual cancellation'));
        check((string)value($db,'SELECT cancellation_fee_minor FROM orders WHERE order_id=?',[$manual])==='0','exhausted matching waived for '.$actor.' cancellation');
    }
    $usd=paid_order($db);$db->prepare("UPDATE orders SET status='NO_SHIPPER_FOUND',currency_code='USD',total_charge_minor=1234 WHERE order_id=?")->execute([$usd]);$db->prepare("UPDATE payments SET currency_code='USD',amount_minor=1234 WHERE order_id=?")->execute([$usd]);
    tx($db,fn()=>Person1EventConsumer::resolveNoShipper($db,$usd));
    check(value($db,'SELECT currency_code FROM refunds WHERE order_id=?',[$usd])==='USD'&&(string)value($db,'SELECT refund_amount_minor FROM refunds WHERE order_id=?',[$usd])==='1234','legacy USD refund preserves minor units and currency');
    $media=new AdminMediaService($db);$file=storage_file($db,SHIPPER_A,'KYC');
    $db->prepare("INSERT INTO shipper_documents(shipper_id,document_type,object_key,mime_type,byte_size) SELECT owner_id,'NATIONAL_ID_FRONT',object_key,mime_type,byte_size FROM private_files WHERE file_id=?")->execute([$file]);
    $unlinked=storage_file($db,SHIPPER_A,'KYC');
    $files=tx($db,fn()=>$media->listing(ADMIN,['shipper_id'=>SHIPPER_A]));
    check(count($files['items'])===1&&(int)$files['items'][0]['file_id']===$file,'only submitted KYC files');
    check(!isset($files['items'][0]['object_key']),'no private object keys exposed');
    rejects(fn()=>tx($db,fn()=>$media->listing(CUSTOMER,['shipper_id'=>SHIPPER_A])),'PERMISSION_DENIED');
    rejects(fn()=>tx($db,fn()=>$media->listing(ADMIN,['order_id'=>$order])),'RELATED_TICKET_REQUIRED');
    $db->prepare("INSERT INTO support_tickets(ticket_code,order_id,opened_by,category,subject,description) VALUES('MEDIA-TEST',?,2,'OTHER','Fixture','Fixture')")->execute([$order]);
    $db->prepare("INSERT INTO order_assignments(order_id,shipper_id) VALUES(?,3)")->execute([$order]);
    $db->prepare("INSERT INTO deliveries(order_id,assignment_id) SELECT order_id,assignment_id FROM order_assignments WHERE order_id=?")->execute([$order]);
    $photo=storage_file($db,SHIPPER_A,'EVIDENCE',$order);
    $db->prepare("INSERT INTO delivery_evidence(delivery_id,evidence_type,object_key,mime_type,byte_size,uploaded_by,delete_after) SELECT d.delivery_id,'DELIVERY_PHOTO',f.object_key,f.mime_type,f.byte_size,f.owner_id,now()+interval '90 days' FROM deliveries d JOIN private_files f ON f.order_id=d.order_id WHERE f.file_id=?")->execute([$photo]);
    check(count(tx($db,fn()=>$media->listing(ADMIN,['order_id'=>$order]))['items'])===1,'order evidence linked through delivery');
    $db->prepare("UPDATE private_files SET delete_after=now()-interval '1 second' WHERE file_id IN (?,?)")->execute([$file,$photo]);
    check(count(tx($db,fn()=>$media->listing(ADMIN,['shipper_id'=>SHIPPER_A]))['items'])===0,'expired KYC hidden');
    check(count(tx($db,fn()=>$media->listing(ADMIN,['order_id'=>$order]))['items'])===0,'expired evidence hidden');
    check((int)value($db,"SELECT count(*) FROM audit_logs WHERE action IN ('KYC_FILES_LIST','ORDER_EVIDENCE_LIST')")===4,'sensitive lists audited');
    echo "PASS: $checks Person 1 scope checks; TEMP tables only; no provider calls or funds.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1);}
