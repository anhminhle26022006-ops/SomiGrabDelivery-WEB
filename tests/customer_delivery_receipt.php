<?php
declare(strict_types=1);
require __DIR__.'/shipper_fixtures.php';
require __DIR__.'/../app/Services/CustomerDeliveryReceiptService.php';
require __DIR__.'/../app/Services/PrivateStorageService.php';
try{
 $db=shipper_test_db();$id=paid_order($db);$q=$db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status) VALUES(?,3,'COMPLETED') RETURNING assignment_id");$q->execute([$id]);$assignment=(int)$q->fetchColumn();
 $q=$db->prepare("INSERT INTO deliveries(order_id,assignment_id,picked_up_at,arrived_at,delivered_at) VALUES(?,?,now()-interval '1 hour',now()-interval '5 minutes',now()) RETURNING delivery_id");$q->execute([$id,$assignment]);$delivery=(int)$q->fetchColumn();
 $db->prepare("UPDATE orders SET status='DELIVERED' WHERE order_id=?")->execute([$id]);
 $file=storage_file($db,SHIPPER_A,'EVIDENCE',$id);
 $q=$db->prepare("INSERT INTO delivery_evidence(delivery_id,evidence_type,object_key,mime_type,byte_size,uploaded_by,delete_after) SELECT ?,'DELIVERY_PHOTO',object_key,mime_type,byte_size,owner_id,now()+interval '90 days' FROM private_files WHERE file_id=?");$q->execute([$delivery,$file]);
 $service=new CustomerDeliveryReceiptService($db);$receipt=$service->detail(CUSTOMER,$id);
 check(count($receipt['deliveries'])===1&&$receipt['deliveries'][0]['arrived_at']!==null,'customer sees actual delivery milestones');
 check((int)$receipt['evidence'][0]['file_id']===$file&&!isset($receipt['evidence'][0]['object_key']),'customer gets a private photo ID without storage keys');
 rejects(fn()=>$service->detail(ADMIN,$id),'ORDER_NOT_FOUND');
 rejects(fn()=>$service->detail(CUSTOMER,999999),'ORDER_NOT_FOUND');
 putenv('SOMI_STORAGE_SIGNING_KEY='.str_repeat('c',32));$storage=new PrivateStorageService($db);
 check($storage->signed($file,CUSTOMER,'CUSTOMER')['file_id']===(string)$file,'order owner can read another uploader delivery proof after completion');
 rejects(fn()=>$storage->signed($file,ADMIN,'CUSTOMER'),'FILE_NOT_FOUND');
 $unlinked=storage_file($db,SHIPPER_A,'EVIDENCE',$id);
 rejects(fn()=>$storage->signed($unlinked,CUSTOMER,'CUSTOMER'),'FILE_NOT_FOUND');
 $kyc=storage_file($db,SHIPPER_A,'KYC');rejects(fn()=>$storage->signed($kyc,CUSTOMER,'CUSTOMER'),'FILE_NOT_FOUND');
 check($storage->signed($file,SHIPPER_A,'SHIPPER')['file_id']===(string)$file,'shipper ownership access remains available');
 $db->prepare("UPDATE delivery_evidence SET deleted_at=now() WHERE delivery_id=?")->execute([$delivery]);
 check($service->detail(CUSTOMER,$id)['evidence']===[],'removed evidence is not listed');
 rejects(fn()=>$storage->signed($file,CUSTOMER,'CUSTOMER'),'FILE_NOT_FOUND');
 echo "Customer delivery receipt checks passed: $checks\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().' @ '.$e->getLine()."\n");exit(1);}
