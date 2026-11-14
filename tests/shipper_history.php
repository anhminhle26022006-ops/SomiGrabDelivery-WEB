<?php
declare(strict_types=1);
require __DIR__.'/shipper_fixtures.php';
require __DIR__.'/../app/Services/DeliveryService.php';
require __DIR__.'/../app/Services/PrivateStorageService.php';
try{
 $db=shipper_test_db();$service=new DeliveryService($db);$id=paid_order($db);
 $q=$db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status,assigned_at,ended_at,ended_reason) VALUES(?,3,'COMPLETED',now()-interval '1 hour',now(),'DELIVERED') RETURNING assignment_id");$q->execute([$id]);$assignment=(int)$q->fetchColumn();
 $q=$db->prepare("INSERT INTO deliveries(order_id,assignment_id,picked_up_at,arrived_at,delivered_at) VALUES(?,?,now()-interval '40 minutes',now()-interval '5 minutes',now()) RETURNING delivery_id");$q->execute([$id,$assignment]);$delivery=(int)$q->fetchColumn();
 $db->prepare("UPDATE orders SET status='DELIVERED',completed_at=now(),closed_at=now() WHERE order_id=?")->execute([$id]);
 $file=storage_file($db,SHIPPER_A,'EVIDENCE',$id);
 $db->prepare("INSERT INTO delivery_evidence(delivery_id,evidence_type,object_key,mime_type,byte_size,uploaded_by,delete_after) SELECT ?,'DELIVERY_PHOTO',object_key,mime_type,byte_size,owner_id,now()+interval '90 days' FROM private_files WHERE file_id=?")->execute([$delivery,$file]);
 $db->prepare("INSERT INTO shipper_earnings(order_id,shipper_id,earning_type,basis_amount_minor,percentage_rate,gross_amount_minor,currency_code) VALUES(?,3,'SUCCESSFUL_DELIVERY',30000,80,24000,'VND')")->execute([$id]);
 $r=$service->historyDetail(SHIPPER_A,$assignment)['receipt'];
 check($r['order_code']!==''&&$r['receiver_name']!==null,'own completed receipt includes route and contacts');
 check((string)$r['gross_amount_minor']==='24000'&&$r['payout_status']==='PENDING','receipt preserves actual earning and payout status');
 check(count($r['evidence'])===1&&(int)$r['evidence'][0]['file_id']===$file,'own delivery evidence appears');
 check(!isset($r['evidence'][0]['object_key']),'receipt never exposes storage keys');
 putenv('SOMI_STORAGE_SIGNING_KEY='.str_repeat('h',32));$storage=new PrivateStorageService($db);
 check($storage->signed($file,SHIPPER_A,'SHIPPER')['file_id']===(string)$file,'owner can access proof after assignment ended');
 rejects(fn()=>$storage->signed($file,SHIPPER_B,'SHIPPER'),'FILE_NOT_FOUND');
 rejects(fn()=>$service->historyDetail(SHIPPER_B,$assignment),'ORDER_NOT_FOUND');
 rejects(fn()=>$service->historyDetail(SHIPPER_A,999999),'ORDER_NOT_FOUND');
 $db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status,assigned_at,ended_at) VALUES(?,4,'CANCELLED_BEFORE_PICKUP',now()-interval '3 hours',now()-interval '2 hours') RETURNING assignment_id")->execute([$id]);
 $cancelled=(int)value($db,'SELECT assignment_id FROM order_assignments WHERE order_id=? AND shipper_id=4',[$id]);
 $r=$service->historyDetail(SHIPPER_B,$cancelled)['receipt'];
 check($r['evidence']===[]&&$r['gross_amount_minor']===null&&!isset($r['receiver_phone']),'cancelled assignment cannot see later courier proof, earnings or contacts');
 check($r['timeline']===[],'cancelled assignment cannot read later delivery events');
 $list=$service->history(SHIPPER_A,null,1);check((string)$list['items'][0]['gross_amount_minor']==='24000','history row shows actual earnings');
 check($service->history(SHIPPER_A,(int)$list['next_before_id'],1)['items']===[],'history cursor progresses');
 $db->prepare("UPDATE private_files SET delete_after=now()-interval '1 second' WHERE file_id=?")->execute([$file]);
 check($service->historyDetail(SHIPPER_A,$assignment)['receipt']['evidence']===[],'expired proof is omitted');
 echo "Shipper history checks passed: $checks\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().' @ '.$e->getLine()."\n");exit(1);}
