<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/DemoDatasetService.php';
require_once __DIR__.'/../app/Services/DistanceWeightPricingRelease.php';
require_once __DIR__.'/../app/Services/ApprovedShipperPolicyRelease.php';
require_once __DIR__.'/../app/Services/OfferAcceptService.php';
require_once __DIR__.'/../app/Services/DeliveryCompletionService.php';
require_once __DIR__.'/../app/Services/ShipperFinanceService.php';
require_once __DIR__.'/../app/Services/Person1Worker.php';
require_once __DIR__.'/../app/Services/ShipperBankService.php';
try{
 $db=shipper_test_db();putenv('SEPAY_ENVIRONMENT=sandbox');putenv('SEPAY_MERCHANT_ID=');putenv('SEPAY_SECRET_KEY=');putenv('SEPAY_IPN_SECRET=');
 foreach(['20261105_approved_pricing','20261105_distance_weight_pricing'] as $v){$sql=file_get_contents(__DIR__.'/../database/migrations/'.$v.'.sql');$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));}
 tx($db,fn()=>DistanceWeightPricingRelease::apply($db));tx($db,fn()=>ApprovedShipperPolicyRelease::apply($db));
 putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));putenv('SOMI_PAYOUT_MODE=manual');
 $uploads=0;$store=static function($asset)use(&$uploads){$uploads++;$path=__DIR__.'/../resources/demo/'.$asset.'.png';return ['object_key'=>bin2hex(random_bytes(32)),'bucket'=>'demo-fixture','byte_size'=>filesize($path),'checksum_sha256'=>hash_file('sha256',$path)];};
 $service=new DemoDatasetService($db,$store);$password='DemoFixture!123456789';$result=tx($db,fn()=>$service->seed('20261113',$password));
 check(count($result['users'])===8&&count($result['orders'])===33,'dataset has 8 accounts and 33 orders');
 check((int)value($db,"SELECT count(*) FROM user_roles ur JOIN roles r USING(role_id) JOIN users u USING(user_id) WHERE u.email LIKE '%@demo.somi.invalid' AND r.role_code='ADMIN'")===0,'no demo Admin created');
 check((int)value($db,"SELECT count(DISTINCT status) FROM orders WHERE order_code LIKE 'DEMO-%'")===8,'eight status views populated');
 check((int)value($db,"SELECT count(*) FROM private_files WHERE original_name LIKE 'DEMO-%'")===30,'actual image metadata exists for KYC and evidence');
 check((int)value($db,"SELECT count(*) FROM payments WHERE provider='SEPAY'")===0,'no real payment provider used');
 $before=$uploads;tx($db,fn()=>$service->seed('20261113',$password));check($uploads===$before&&(int)value($db,"SELECT count(*) FROM orders WHERE order_code LIKE 'DEMO-%'")===33,'repeat import creates no duplicate rows or objects');
 $customer=$result['users']['customer']['id'];$shipper=$result['users']['an']['id'];
 tx($db,fn()=>EmailService::enqueue($db,$customer,'VERIFY_EMAIL','demo-should-not-email',['code'=>'123456']));check((int)value($db,'SELECT count(*) FROM email_messages')===0,'demo email never enters provider queue');
 $live=tx($db,fn()=>$service->freshOffers('20261113'));check($live['offers_created']===3,'demo offers generated with actual matching');
 check((int)value($db,"SELECT count(*) FROM order_match_offers f JOIN orders o USING(order_id) WHERE o.order_code LIKE 'DEMO-%' AND f.shipper_id NOT IN (SELECT user_id FROM users WHERE email LIKE '%@demo.somi.invalid')")===0,'demo never offers real shippers');
 $id=$live['orders'][0]['order_id'];$offer=value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=?',[$id,$shipper]);
 tx($db,fn()=>(new OfferAcceptService($db))->accept($shipper,(int)$offer));check(value($db,'SELECT status FROM orders WHERE order_id=?',[$id])==='SHIPPER_ASSIGNED','demo shipper accepts real API-service offer');
 $again=tx($db,fn()=>$service->freshOffers('20261113'));check($again['active_order_id']===$id&&$again['offers_created']===0,'refresh never resets an active delivery');
 $delivery=new DeliveryService($db);tx($db,fn()=>$delivery->pickup($shipper,$id));tx($db,fn()=>$delivery->startDelivery($shipper,$id));$file=storage_file($db,$shipper,'EVIDENCE',$id);$complete=new DeliveryCompletionService($db);tx($db,fn()=>$complete->addEvidence($shipper,$id,'DELIVERY_PHOTO',$file,null,null,null));$done=tx($db,fn()=>$complete->delivered($shipper,$id));
 check($done['settlement']['earning_id']!==null&&$done['settlement']['payout_id']===null,'completion accrues demo earnings without transfer reservation');
 $payout=value($db,"SELECT p.payout_id FROM shipper_payouts p JOIN users u ON u.user_id=p.shipper_id WHERE u.email LIKE '%@demo.somi.invalid' LIMIT 1");
 rejects(fn()=>tx($db,fn()=>(new ShipperFinanceService($db))->destination(ADMIN,(int)$payout)),'DEMO_TRANSFER_DISABLED');
 rejects(fn()=>tx($db,fn()=>(new ShipperFinanceService($db))->reconcile(ADMIN,['payout_id'=>$payout])),'DEMO_TRANSFER_DISABLED');
 $rid=$result['orders'][20]['id'];$refund=value($db,'SELECT refund_id FROM refunds WHERE order_id=?',[$rid]);$db->prepare("UPDATE refunds SET status='PENDING' WHERE refund_id=?")->execute([$refund]);(new Person1Worker($db))->execute(['kind'=>'REFUND','payload'=>['refund_id'=>$refund]]);
 check(value($db,'SELECT failure_code FROM refunds WHERE refund_id=?',[$refund])==='DEMO_NO_MONEY_MOVED','demo refund is manual simulation without provider calls');
 $normal=paid_order($db);$db->prepare("UPDATE shipper_profiles SET availability=TRUE,last_latitude=?,last_longitude=?,last_location_at=now() WHERE user_id=?")->execute([PICKUP[0],PICKUP[1],$shipper]);tx($db,fn()=>(new MatchingService($db))->dispatch($normal));
 check((int)value($db,'SELECT count(*) FROM order_match_offers WHERE order_id=? AND shipper_id=?',[$normal,$shipper])===0,'demo shipper cannot receive normal orders');
 $fake=$db->prepare("INSERT INTO order_match_offers(order_id,shipper_id,distance_to_pickup_km,match_score,expires_at) VALUES(?,?,0.1,1,now()+interval '60 seconds') RETURNING offer_id");$fake->execute([$normal,$shipper]);$bad=(int)$fake->fetchColumn();
 rejects(fn()=>tx($db,fn()=>(new OfferAcceptService($db))->accept($shipper,$bad)),'ORDER_NOT_AVAILABLE');
 rejects(fn()=>tx($db,fn()=>(new ShipperBankService($db))->request($shipper,['bank_code'=>'TEST','bank_name'=>'Test','account_holder'=>'Test','account_number'=>'123456789','current_password'=>$password,'client_key'=>'demo-bank-blocked'])),'DEMO_TRANSFER_DISABLED');
 while(Person1EventConsumer::consumeOne($db)){}
 check((int)value($db,"SELECT count(*) FROM background_jobs WHERE kind IN ('PAYMENT_CHECKOUT','PAYMENT_RECONCILE','EMAIL')")===0,'no outbound payment or email jobs for live demo transitions');
 check((int)value($db,"SELECT count(*) FROM notifications n JOIN users u USING(user_id) WHERE u.email LIKE '%@demo.somi.invalid'")>0,'in-app demo notifications populated');
 echo "PASS: $checks demo dataset checks; TEMP tables; no provider calls, email or funds.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage().' @ '.$e->getLine()."\n");exit(1);}
