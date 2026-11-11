<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/CustomerOrderService.php';
require_once __DIR__.'/../app/Services/Person3Worker.php';
require_once __DIR__.'/../app/Services/PrivateStorageService.php';
require_once __DIR__.'/../app/Services/ShipperBankService.php';
require_once __DIR__.'/../app/Services/AdminService.php';
require_once __DIR__.'/../app/Services/AdminPolicyService.php';
require_once __DIR__.'/../app/Services/Person1Worker.php';
require_once __DIR__.'/../app/Services/AuthRateLimiter.php';
$checks=0;
function check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
function value(PDO $db,string $sql,array $args=[]){$q=$db->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function tx(PDO $db,callable $fn){$db->beginTransaction();try{$r=$fn();$db->commit();return $r;}catch(Throwable $e){$db->rollBack();throw $e;}}
function rejects(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){check($e->errorCode===$code,$code.' got '.$e->errorCode);return;}throw new RuntimeException('Expected '.$code);}
final class TestGateway implements PaymentProvider{
    public function name():string{return 'TEST';}public function assertConfigured():void{}
    public function checkout(array $p,string $key):array{return ['provider_payment_ref'=>'TEST-'.$p['payment_id'],'checkout_url'=>'https://example.invalid/pay'];}
    public function reconcile(array $p):array{return ['status'=>'PENDING'];}
    public function refund(array $r,array $p,string $key):array{return ['kind'=>'REFUND','payment_id'=>$p['payment_id'],'event_id'=>'refund-'.$r['refund_id'],'refund_id'=>$r['refund_id'],'provider_payment_ref'=>$p['provider_payment_ref'],'provider_refund_id'=>'test-refund-'.$r['refund_id'],'amount_minor'=>$r['refund_amount_minor'],'currency_code'=>$r['currency_code'],'status'=>'SUCCEEDED'];}
    public function verifyCallback(string $raw,array $headers):array{throw new LogicException('No external callback in fixtures.');}
}
try{
    $db=somi_db();$db->exec('SET search_path TO pg_temp, pg_catalog');
    $sql=file_get_contents(__DIR__.'/../database/SOMI_database.sql');
    $sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^CREATE SCHEMA[^;]+;\s*|^SET search_path[^;]+;\s*/m','',$sql);
    $sql=preg_replace('/CREATE FUNCTION erase_identity_document_on_account_close\(\).*?(?=CREATE TABLE shipper_score_policies)/s','',$sql);
    $db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
    foreach(['20261105_customer_orders','20261105_approved_pricing','20261105_distance_weight_pricing','20261106_person1_backend','20261111_person3','20261111_person3_complete'] as $migration){
        $sql=file_get_contents(__DIR__.'/../database/migrations/'.$migration.'.sql');$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
    }
    check(value($db,"SELECT current_setting('search_path')")==='pg_temp, pg_catalog','isolated schema');
    check((int)value($db,"SELECT count(*) FROM pg_class WHERE relnamespace=pg_my_temp_schema() AND relname IN ('users','orders','payments','refunds','email_messages','background_jobs','audit_logs')")===7,'all writes use temporary tables');
    putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
    $db->exec("INSERT INTO users(full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status,email_verified_at,phone_verified_at) VALUES ('Admin','admin@example.invalid','0900000001','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Customer','customer@example.invalid','0900000002','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Other','other@example.invalid','0900000003','test','US','2000-01-01','F','en','ACTIVE',now(),now()),('Shipper','shipper@example.invalid','0900000004','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()); INSERT INTO customer_profiles(user_id) VALUES (2),(3); INSERT INTO shipper_profiles(user_id,vehicle_type,vehicle_registration) VALUES (4,'TEST','TEST'); INSERT INTO user_roles(user_id,role_id) SELECT v.id,r.role_id FROM (VALUES(1,'ADMIN'),(2,'CUSTOMER'),(3,'CUSTOMER'),(4,'SHIPPER'))v(id,role) JOIN roles r ON r.role_code=v.role; INSERT INTO parcel_categories(category_code,name_vi,name_en,restriction_type) VALUES('TEST','Test','Test','ALLOWED');");
    function newOrder(PDO $db,string $currency='VND'):int{
        $q=$db->prepare("INSERT INTO orders(order_code,customer_id,currency_code,app_language_at_order,distance_km,pickup_address,pickup_latitude,pickup_longitude,sender_name,sender_phone,delivery_address,delivery_latitude,delivery_longitude,receiver_name,receiver_phone,category_id,estimated_weight_kg,distance_fee_minor,time_multiplier,weather_multiplier,total_charge_minor,return_fee_minor) VALUES (?,2,?,?,4,'TEST',10,106,'S','0900000002','TEST',10,106,'R','0900000003',1,1,100,1,1,100,10) RETURNING order_id");$q->execute(['TEST-'.bin2hex(random_bytes(6)),$currency,$currency==='VND'?'vi':'en']);return (int)$q->fetchColumn();
    }
    $svc=new InteractionService($db);$id=newOrder($db);
    AccessControl::requirePermission($db,2,'CUSTOMER','person3.use');check(true,'shared RBAC');
    rejects(fn()=>AccessControl::requirePermission($db,2,'ADMIN','payout.manage'),'PERMISSION_DENIED');
    $db->prepare("UPDATE orders SET status='SHIPPER_ASSIGNED' WHERE order_id=?")->execute([$id]);
    $db->prepare('INSERT INTO order_assignments(order_id,shipper_id) VALUES(?,4)')->execute([$id]);
    $body=['order_id'=>$id,'body'=>'<img src=x onerror=alert(1)>','client_key'=>'chat-test-0001'];
    $m=tx($db,fn()=>$svc->chat(2,'CUSTOMER',$body,true))['message'];
    $replay=tx($db,fn()=>$svc->chat(2,'CUSTOMER',$body,true));check((string)$replay['message']['message_id']===(string)$m['message_id'],'chat retry dedup');
    rejects(fn()=>tx($db,fn()=>$svc->chat(2,'CUSTOMER',array_replace($body,['body'=>'changed']),true)),'IDEMPOTENCY_CONFLICT');
    rejects(fn()=>tx($db,fn()=>$svc->chat(3,'CUSTOMER',['order_id'=>$id],false)),'ORDER_NOT_FOUND');
    check(count(tx($db,fn()=>$svc->chat(4,'SHIPPER',['order_id'=>$id],false))['items'])===1,'assigned shipper reads chat');
    tx($db,fn()=>$svc->chat(4,'SHIPPER',['order_id'=>$id,'read_through_id'=>$m['message_id']],true));check(value($db,'SELECT read_at IS NOT NULL FROM chat_messages WHERE message_id=?',[$m['message_id']])===true,'peer read receipt');
    check($svc->notifications(4,[],false)['unread_count']===1,'chat notification one time');
    $notification=$svc->notifications(4,[],false)['items'][0]['notification_id'];
    rejects(fn()=>tx($db,fn()=>$svc->notifications(2,['notification_id'=>$notification,'read'=>true],true)),'NOTIFICATION_NOT_FOUND');
    tx($db,fn()=>$svc->notifications(4,['notification_id'=>$notification,'read'=>true],true));check($svc->notifications(4,[],false)['unread_count']===0,'mark read');
    tx($db,fn()=>$svc->notifications(4,['notification_id'=>$notification,'read'=>false],true));check($svc->notifications(4,[],false)['unread_count']===1,'mark unread');
    $ticketBody=['order_id'=>$id,'category'=>'DELIVERY','subject'=>'Test','description'=>'Need help','client_key'=>'ticket-test-0001'];
    $ticket=tx($db,fn()=>$svc->tickets(2,'CUSTOMER',$ticketBody,true))['ticket'];tx($db,fn()=>$svc->tickets(2,'CUSTOMER',$ticketBody,true));check((int)value($db,'SELECT count(*) FROM support_tickets')===1,'ticket retry dedup');
    rejects(fn()=>$svc->tickets(3,'CUSTOMER',['id'=>$ticket['ticket_id']],false),'TICKET_NOT_FOUND');
    check(count($svc->tickets(2,'CUSTOMER',['id'=>$ticket['ticket_id']],false)['events'])===1,'ticket creation history');
    tx($db,fn()=>$svc->tickets(3,'CUSTOMER',['category'=>'ACCOUNT','subject'=>'Account','description'=>'Test','client_key'=>'account-test-0001'],true));check((int)value($db,'SELECT count(*) FROM support_tickets WHERE order_id IS NULL')===1,'account ticket');
    $finance=new ShipperFinanceService($db);check(tx($db,fn()=>$finance->accrue($id))===null,'no earnings before delivery');
    $db->prepare("UPDATE orders SET status='DELIVERED',closed_at=now(),completed_at=now() WHERE order_id=?")->execute([$id]);
    $db->prepare("UPDATE order_assignments SET assignment_status='COMPLETED',ended_at=now() WHERE order_id=?")->execute([$id]);
    check(count(tx($db,fn()=>$svc->chat(4,'SHIPPER',['order_id'=>$id],false))['items'])===1,'completed shipper retains chat history');
    rejects(fn()=>tx($db,fn()=>$svc->chat(2,'CUSTOMER',array_replace($body,['client_key'=>'chat-test-closed']),true)),'CHAT_CLOSED');
    check(tx($db,fn()=>$finance->accrue($id))===null,'unpaid delivery cannot earn');
    $db->prepare("INSERT INTO payments(order_id,amount_minor,currency_code,method,status,paid_at) VALUES(?,100,'VND','GATEWAY','PAID',now())")->execute([$id]);
    $db->prepare('INSERT INTO deliveries(order_id,assignment_id,delivered_at) SELECT order_id,assignment_id,now() FROM order_assignments WHERE order_id=?')->execute([$id]);
    check(tx($db,fn()=>$finance->accrue($id))===null,'missing policy cannot invent percentage');
    $db->exec("INSERT INTO shipper_earning_policies(policy_type,currency_code,percentage_rate,effective_from) VALUES('SUCCESSFUL_DELIVERY_PERCENT','VND',80,now()-interval '1 day')");
    $earning=tx($db,fn()=>$finance->accrue($id));check((string)$earning['gross_amount_minor']==='80','policy-based earnings');
    tx($db,fn()=>$finance->accrue($id));check((int)value($db,'SELECT count(*) FROM shipper_earnings')===1,'one earning per order');
    check(count($finance->listing(3,[])['items'])===0,'foreign earnings hidden');
    check(tx($db,fn()=>$finance->reserve((int)$earning['earning_id']))===null,'no payout without verified bank');
    $db->exec("INSERT INTO shipper_bank_accounts(shipper_id,bank_code,account_number_enc,account_holder,verified_at) VALUES(4,'TEST','TEST','TEST',now())");
    $payout=tx($db,fn()=>$finance->reserve((int)$earning['earning_id']));check($payout['status']==='PROCESSING','manual reservation does not claim transfer');
    $db->prepare('UPDATE shipper_bank_accounts SET account_number_enc=?')->execute([EncryptedPayload::seal(['account_number'=>'000000123456'])]);
    $destination=tx($db,fn()=>$finance->destination(1,(int)$payout['payout_id']));check($destination['destination']['account_number']==='000000123456','audited bank destination decrypts encrypted envelope');
    rejects(fn()=>tx($db,fn()=>$finance->destination(2,(int)$payout['payout_id'])),'PERMISSION_DENIED');
    check((int)value($db,"SELECT count(*) FROM audit_logs WHERE action='PAYOUT_BANK_READ'")===1,'bank access audited without logging account number');
    tx($db,fn()=>$finance->reserve((int)$earning['earning_id']));check((int)value($db,'SELECT count(*) FROM shipper_payouts')===1,'reservation retry no duplicate transfer');
    $proof=['payout_id'=>$payout['payout_id'],'provider_payout_ref'=>'TEST-TRANSFER-1','evidence_reference'=>'TEST-EVIDENCE','amount_minor'=>'80','currency_code'=>'VND'];
    rejects(fn()=>tx($db,fn()=>$finance->reconcile(2,$proof)),'PERMISSION_DENIED');
    rejects(fn()=>tx($db,fn()=>$finance->reconcile(1,array_replace($proof,['amount_minor'=>'81']))),'VALIDATION_ERROR');
    tx($db,fn()=>$finance->reconcile(1,$proof));tx($db,fn()=>$finance->reconcile(1,$proof));check(value($db,'SELECT payout_status FROM shipper_earnings')==='PAID','proof reconciles exactly once');
    rejects(fn()=>tx($db,fn()=>$finance->reconcile(1,array_replace($proof,['provider_payout_ref'=>'different']))),'PAYOUT_CONFLICT');
    check((int)value($db,"SELECT count(*) FROM audit_logs WHERE action='PAYOUT_RECONCILED'")===1,'payout audit once');
    $db->prepare("INSERT INTO order_events(order_id,event_type,deduplication_key,payload) VALUES(?,'OrderDelivered','test-delivered','{}')")->execute([$id]);
    $w=new Person3Worker($db);check($w->consumeOne(),'event consumed');check(!$w->consumeOne(),'event receipt prevents replay');
    check((int)value($db,"SELECT count(*) FROM notifications WHERE notification_type='OrderDelivered'")===2,'event notifies both parties');
    $db->prepare("UPDATE orders SET closed_at=now()-interval '2 months' WHERE order_id=?")->execute([$id]);$w->retain();
    check(value($db,'SELECT body IS NULL FROM chat_messages WHERE message_id=?',[$m['message_id']])===true,'chat purged after retention');
    putenv('SOMI_STORAGE_ROOT=');rejects(fn()=>(new PrivateStorageService($db))->upload(2,'CUSTOMER',['purpose'=>'TICKET','ticket_id'=>$ticket['ticket_id']],[]),'STORAGE_NOT_CONFIGURED');
    $storage=new PrivateStorageService($db);putenv('SOMI_STORAGE_SIGNING_KEY='.str_repeat('t',40));
    // Keep retention/orphan cleanup inside a dedicated fixture directory. Resolve
    // Windows short temp paths before comparing with the adapter's realpath.
    $storageRoot=sys_get_temp_dir().DIRECTORY_SEPARATOR.'somi-person3-test-'.bin2hex(random_bytes(8));
    if(!mkdir($storageRoot,0700))throw new RuntimeException('Cannot create isolated storage fixture.');
    $storageRoot=realpath($storageRoot);putenv('SOMI_STORAGE_ROOT='.$storageRoot);
    $object=bin2hex(random_bytes(32));$path=$storageRoot.DIRECTORY_SEPARATOR.$object;file_put_contents($path,'fixture');
    $q=$db->prepare("INSERT INTO private_files(owner_id,ticket_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256) VALUES(2,?,'TICKET',?,'fixture.pdf','application/pdf',7,?) RETURNING file_id");$q->execute([$ticket['ticket_id'],$object,hash('sha256','fixture')]);$fileId=(int)$q->fetchColumn();
    $signed=$storage->signed($fileId,2,'CUSTOMER');parse_str($signed['download_query'],$query);
    check($storage->download($fileId,2,'CUSTOMER',$query)['path']===$path,'signed owner download');
    check(count(tx($db,fn()=>$storage->listing(1,['ticket_id'=>$ticket['ticket_id']]))['items'])===1,'admin lists related support files');
    check(isset(tx($db,fn()=>$storage->signed($fileId,1,'ADMIN'))['download_query']),'admin signed ticket file audited');
    rejects(fn()=>tx($db,fn()=>$storage->listing(3,['ticket_id'=>$ticket['ticket_id']])),'PERMISSION_DENIED');
    rejects(fn()=>$storage->signed($fileId,3,'CUSTOMER'),'FILE_NOT_FOUND');
    rejects(fn()=>$storage->download($fileId,3,'CUSTOMER',$query),'LINK_EXPIRED');
    rejects(fn()=>$storage->download($fileId,2,'CUSTOMER',array_replace($query,['signature'=>'tampered'])),'LINK_EXPIRED');
    rejects(fn()=>$storage->download($fileId,2,'CUSTOMER',array_replace($query,['expires'=>(string)(time()-10)])),'LINK_EXPIRED');
    check(count($storage->listing(3,[])['items'])===0,'private file listing owner only');
    $db->prepare("UPDATE private_files SET delete_after=now()-interval '1 day' WHERE file_id=?")->execute([$fileId]);
    tx($db,fn()=>$storage->retain());check(!is_file($path),'expired private object removed');rejects(fn()=>$storage->signed($fileId,2,'CUSTOMER'),'FILE_NOT_FOUND');
    putenv('SOMI_STORAGE_ROOT=');
    check(count($svc->orders(3,'CUSTOMER',[])['items'])===0,'order chooser excludes foreign orders');
    check(count($svc->orders(4,'SHIPPER',[])['items'])===1,'shipper chooser includes completed assignments for tickets');
    tx($db,fn()=>$svc->tickets(4,'SHIPPER',array_replace($ticketBody,['client_key'=>'shipper-completed-ticket']),true));check(true,'completed shipper may open support ticket');
    $blocked=newOrder($db);$ready=newOrder($db);
    foreach([$blocked,$ready] as $oid){$db->prepare("UPDATE orders SET status='DELIVERED',total_charge_minor=101 WHERE order_id=?")->execute([$oid]);}
    $db->prepare("INSERT INTO payments(order_id,amount_minor,currency_code,method,status,paid_at) VALUES(?,101,'VND','GATEWAY','PAID',now())")->execute([$ready]);
    $q=$db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status,ended_at) VALUES(?,4,'COMPLETED',now()) RETURNING assignment_id");$q->execute([$ready]);$assignment=$q->fetchColumn();
    $db->prepare('INSERT INTO deliveries(order_id,assignment_id,delivered_at) VALUES(?,?,now())')->execute([$ready,$assignment]);
    putenv('SOMI_PAYOUT_MODE=manual');check($w->settle(1)===0,'incomplete order waits');check($w->settle(1)===1,'cursor moves past blocked order');
    check((string)value($db,'SELECT gross_amount_minor FROM shipper_earnings WHERE order_id=?',[$ready])==='81','fractional earnings ceil once');
    $pending=value($db,'SELECT payout_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE e.order_id=?',[$ready]);check($pending!==false,'manual worker reserves payout');
    $db->prepare("INSERT INTO refunds(payment_id,order_id,reason,gross_amount_minor,deducted_fee_minor,refund_amount_minor,currency_code,status) SELECT payment_id,order_id,'ADMIN_ADJUSTMENT',101,0,101,'VND','PROCESSING' FROM payments WHERE order_id=?")->execute([$ready]);
    rejects(fn()=>tx($db,fn()=>$finance->reconcile(1,['payout_id'=>$pending,'provider_payout_ref'=>'TEST-REFUND-HOLD','evidence_reference'=>'TEST','amount_minor'=>'81','currency_code'=>'VND'])),'REFUND_REVIEW_REQUIRED');
    $db->prepare("INSERT INTO order_events(order_id,event_type,deduplication_key,payload) VALUES(?,'RefundSucceeded','test-refunded','{}')")->execute([$id]);$w->consumeOne();check((int)value($db,'SELECT count(*) FROM shipper_finance_reviews')===1,'refund records finance review without inventing debit');
    $returned=newOrder($db);$db->prepare("UPDATE orders SET status='RETURNED' WHERE order_id=?")->execute([$returned]);
    $q=$db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status) VALUES(?,4,'RETURNED') RETURNING assignment_id");$q->execute([$returned]);$ra=$q->fetchColumn();
    $db->prepare('INSERT INTO deliveries(order_id,assignment_id,returned_at,customer_return_confirmed_at) VALUES(?,?,now(),now())')->execute([$returned,$ra]);
    $db->prepare("INSERT INTO payments(order_id,amount_minor,currency_code,method,status) VALUES(?,100,'VND','GATEWAY','PAID')")->execute([$returned]);
    $db->exec("INSERT INTO shipper_earning_policies(policy_type,currency_code,fixed_amount_minor,effective_from) VALUES('FAILED_RETURN_FIXED','VND',5,now()-interval '1 day')");
    check(tx($db,fn()=>$finance->accrue($returned))===null,'returned earning waits for refund completion');
    $db->prepare("INSERT INTO refunds(payment_id,order_id,reason,gross_amount_minor,deducted_fee_minor,refund_amount_minor,currency_code,status) SELECT payment_id,order_id,'RETURN_FEE_DEDUCTION',100,10,90,'VND','SUCCEEDED' FROM payments WHERE order_id=?")->execute([$returned]);
    $db->prepare("UPDATE payments SET status='PARTIALLY_REFUNDED' WHERE order_id=?")->execute([$returned]);
    $re=tx($db,fn()=>$finance->accrue($returned));check((string)$re['gross_amount_minor']==='5','configured return compensation after expected refund');
    check(tx($db,fn()=>$finance->reserve((int)$re['earning_id']))['status']==='PROCESSING','returned payout only after refund settled');
    // API completion: bound attachments, support replies, bank OTP, manual retry and evidenced FX.
    $chatOrder=newOrder($db);$db->prepare("UPDATE orders SET status='SHIPPER_ASSIGNED' WHERE order_id=?")->execute([$chatOrder]);$db->prepare('INSERT INTO order_assignments(order_id,shipper_id) VALUES(?,4)')->execute([$chatOrder]);
    $q=$db->prepare("INSERT INTO private_files(owner_id,order_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256) VALUES(2,?,'CHAT',?,'photo.png','image/png',7,?) RETURNING file_id");$q->execute([$chatOrder,bin2hex(random_bytes(32)),hash('sha256','fixture')]);$chatFile=(int)$q->fetchColumn();
    rejects(fn()=>(new PrivateStorageService($db))->signed($chatFile,4,'SHIPPER'),'FILE_NOT_FOUND');
    $attachment=['order_id'=>$chatOrder,'body'=>'','file_ids'=>[(string)$chatFile],'client_key'=>'attachment-test-1'];
    $attached=tx($db,fn()=>$svc->chat(2,'CUSTOMER',$attachment,true));check(isset($attached['message']['message_id']),'attachment-only message');
    tx($db,fn()=>$svc->chat(2,'CUSTOMER',$attachment,true));check(true,'attachment retry preserves same message');
    check(count(tx($db,fn()=>$svc->chat(4,'SHIPPER',['order_id'=>$chatOrder],false))['items'][0]['attachments'])===1,'peer receives bound attachment metadata');
    check(isset(tx($db,fn()=>(new PrivateStorageService($db))->signed($chatFile,4,'SHIPPER'))['download_query']),'peer can sign shared attachment');
    rejects(fn()=>tx($db,fn()=>$svc->chat(2,'CUSTOMER',array_replace($attachment,['client_key'=>'attachment-test-2']),true)),'FILE_NOT_ATTACHABLE');
    rejects(fn()=>tx($db,fn()=>(new PrivateStorageService($db))->discard(2,$chatFile)),'FILE_IN_USE');
    $replyBody=['ticket_id'=>$ticket['ticket_id'],'body'=>'Customer followup','client_key'=>'reply-test-customer'];
    tx($db,fn()=>$svc->replies(2,'CUSTOMER',$replyBody,true));tx($db,fn()=>$svc->replies(2,'CUSTOMER',$replyBody,true));
    check(count(tx($db,fn()=>$svc->replies(2,'CUSTOMER',['ticket_id'=>$ticket['ticket_id']],false))['items'])===1,'ticket reply dedup');
    rejects(fn()=>tx($db,fn()=>$svc->replies(3,'CUSTOMER',['ticket_id'=>$ticket['ticket_id']],false)),'TICKET_NOT_FOUND');
    tx($db,fn()=>$svc->replies(1,'ADMIN',array_replace($replyBody,['body'=>'Support response','client_key'=>'reply-test-admin']),true));
    check(count(tx($db,fn()=>$svc->replies(2,'CUSTOMER',['ticket_id'=>$ticket['ticket_id']],false))['items'])===2,'two-way ticket conversation');
    $db->prepare("UPDATE support_tickets SET status='CLOSED',closed_at=now() WHERE ticket_id=?")->execute([$ticket['ticket_id']]);
    rejects(fn()=>tx($db,fn()=>$svc->replies(2,'CUSTOMER',array_replace($replyBody,['client_key'=>'reply-test-closed']),true)),'TICKET_CLOSED');
    $latest=value($db,'SELECT max(notification_id) FROM notifications WHERE user_id=2');tx($db,fn()=>$svc->notifications(2,['all'=>true,'read'=>true,'through_id'=>$latest],true));check($svc->notifications(2,[],false)['unread_count']===0,'mark-all respects owner and high-water ID');
    $bankSvc=new ShipperBankService($db);$db->prepare('UPDATE users SET password_hash=? WHERE user_id=4')->execute([password_hash('FixturePassword!234',PASSWORD_DEFAULT)]);
    $bankInput=['bank_code'=>'TEST','bank_name'=>'Fixture bank','account_holder'=>'Fixture Shipper','account_number'=>'000009876543','current_password'=>'FixturePassword!234','client_key'=>'bank-change-test-1'];
    check(tx($db,fn()=>$bankSvc->request(4,array_replace($bankInput,['current_password'=>'wrong'])))['code']==='PASSWORD_INCORRECT','bank request requires current password');
    $change=tx($db,fn()=>$bankSvc->request(4,$bankInput));check(!isset($change['code'])&&$change['delivery_mode']==='EMAIL_QUEUE','bank OTP queued, never exposed');
    check(tx($db,fn()=>$bankSvc->request(4,$bankInput))['request_id']===$change['request_id'],'bank request idempotent');
    rejects(fn()=>tx($db,fn()=>$bankSvc->request(4,array_replace($bankInput,['account_number'=>'000001111111']))),'IDEMPOTENCY_CONFLICT');
    $email=value($db,"SELECT encrypted_payload FROM email_messages WHERE deduplication_key=?",['bank-change:'.$change['request_id']]);$mail=EncryptedPayload::open($email);preg_match('/: ([0-9]{6})/',$mail['text'],$codeMatch);$otp=$codeMatch[1];
    check(!str_contains($email,$otp)&&!str_contains($mail['text'],$bankInput['account_number']),'bank OTP encrypted and account masked in email');
    $wrong=$otp==='123456'?'654321':'123456';check(tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$change['request_id'],'code'=>$wrong]))['code']==='OTP_INCORRECT','wrong OTP rejected');
    check((int)value($db,'SELECT attempts FROM bank_account_changes WHERE request_id=?',[$change['request_id']])===1,'failed OTP attempt committed');
    rejects(fn()=>tx($db,fn()=>$bankSvc->confirm(3,['request_id'=>$change['request_id'],'code'=>$otp])),'REQUEST_NOT_FOUND');
    $confirmed=tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$change['request_id'],'code'=>$otp]));check($confirmed['confirmed'],'bank OTP confirms replacement');
    check(tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$change['request_id'],'code'=>$otp]))['bank_account_id']===$confirmed['bank_account_id'],'OTP confirmation replay no new account');
    check((int)value($db,'SELECT count(*) FROM shipper_bank_accounts WHERE shipper_id=4 AND replaced_at IS NULL')===1,'exactly one current bank account');
    check($bankSvc->current(4)['bank_account']['account_number_masked']==='••••6543','own bank API masks number');
    check(value($db,'SELECT encrypted_payload FROM bank_account_changes WHERE request_id=?',[$change['request_id']])===null,'consumed bank change payload purged');
    $db->exec("UPDATE bank_account_changes SET created_at=now()-interval '2 minutes'");$expiredInput=array_replace($bankInput,['client_key'=>'bank-change-expired']);$expiredRequest=tx($db,fn()=>$bankSvc->request(4,$expiredInput));
    $db->prepare("UPDATE bank_account_changes SET expires_at=now()-interval '1 second' WHERE request_id=?")->execute([$expiredRequest['request_id']]);
    rejects(fn()=>tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$expiredRequest['request_id'],'code'=>'123456'])),'OTP_EXPIRED');
    $db->exec("UPDATE bank_account_changes SET created_at=now()-interval '2 minutes'");$limitRequest=tx($db,fn()=>$bankSvc->request(4,array_replace($bankInput,['client_key'=>'bank-change-attempt-limit'])));
    $db->prepare('UPDATE bank_account_changes SET token_hash=? WHERE request_id=?')->execute([password_hash('654321',PASSWORD_DEFAULT),$limitRequest['request_id']]);
    for($i=0;$i<5;$i++)tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$limitRequest['request_id'],'code'=>'123456']));
    rejects(fn()=>tx($db,fn()=>$bankSvc->confirm(4,['request_id'=>$limitRequest['request_id'],'code'=>'654321'])),'OTP_EXPIRED');
    check((int)value($db,'SELECT count(*) FROM shipper_bank_accounts WHERE replaced_at IS NULL')===1,'expired/blocked OTP never replaces bank');
    $failure=['payout_id'=>$pending,'client_key'=>'payout-fail-test-1','reason'=>'Transfer rejected by bank','evidence_reference'=>'FIXTURE-DECLINED','transfer_not_completed'=>true];
    check(tx($db,fn()=>$finance->transition(1,'FAILED',$failure))['status']==='FAILED','failed manual transfer recorded');
    check(tx($db,fn()=>$finance->transition(1,'FAILED',$failure))['duplicate'],'failed record retry idempotent');
    rejects(fn()=>tx($db,fn()=>$finance->transition(1,'RETRY',array_replace($failure,['client_key'=>'payout-retry-hold']))),'REFUND_REVIEW_REQUIRED');
    $db->prepare("UPDATE refunds SET status='FAILED' WHERE order_id=?")->execute([$ready]);
    $retry=array_replace($failure,['client_key'=>'payout-retry-test-1','reason'=>'Bank confirmed no debit']);
    check(tx($db,fn()=>$finance->transition(1,'RETRY',$retry))['status']==='PROCESSING','retry reopens same reservation');
    check(tx($db,fn()=>$finance->transition(1,'RETRY',$retry))['duplicate'],'retry idempotency');
    check((int)value($db,'SELECT count(*) FROM shipper_payouts WHERE payout_id=?',[$pending])===1,'retry never creates a second payout');
    rejects(fn()=>tx($db,fn()=>$finance->transition(1,'FAILED',array_replace($failure,['client_key'=>'payout-unconfirmed','transfer_not_completed'=>false]))),'TRANSFER_REVIEW_REQUIRED');
    $usd=newOrder($db,'USD');$db->prepare("UPDATE orders SET status='DELIVERED' WHERE order_id=?")->execute([$usd]);
    $db->prepare("INSERT INTO payments(order_id,amount_minor,currency_code,method,status) VALUES(?,100,'USD','GATEWAY','PAID')")->execute([$usd]);
    $q=$db->prepare("INSERT INTO order_assignments(order_id,shipper_id,assignment_status) VALUES(?,4,'COMPLETED') RETURNING assignment_id");$q->execute([$usd]);$ua=$q->fetchColumn();$db->prepare('INSERT INTO deliveries(order_id,assignment_id,delivered_at) VALUES(?,?,now())')->execute([$usd,$ua]);
    $db->exec("INSERT INTO shipper_earning_policies(policy_type,currency_code,percentage_rate,effective_from) VALUES('SUCCESSFUL_DELIVERY_PERCENT','USD',80,now()-interval '1 day')");
    $ue=tx($db,fn()=>$finance->accrue($usd));check(tx($db,fn()=>$finance->reserve((int)$ue['earning_id']))===null,'USD waits for evidenced FX');
    $fx=['earning_id'=>$ue['earning_id'],'rate'=>'25000.125','provider'=>'FIXTURE-NOT-LIVE','reference'=>'FX-TEST-1','evidence_reference'=>'FIXTURE-FX-STATEMENT'];
    tx($db,fn()=>$finance->fxQuote(1,$fx));tx($db,fn()=>$finance->fxQuote(1,$fx));rejects(fn()=>tx($db,fn()=>$finance->fxQuote(1,array_replace($fx,['rate'=>'26000']))),'FX_IMMUTABLE');
    $up=tx($db,fn()=>$finance->reserve((int)$ue['earning_id']));check((string)value($db,'SELECT payout_amount_minor FROM shipper_payouts WHERE payout_id=?',[$up['payout_id']])==='20001','USD cents converted with exact NUMERIC and one ceiling');
    check(value($db,'SELECT fx_reference FROM shipper_payouts WHERE payout_id=?',[$up['payout_id']])==='FX-TEST-1','FX source reference snapshotted');
    $q=$db->prepare("INSERT INTO private_files(owner_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256,created_at) VALUES(4,'KYC',?,'kyc.pdf','application/pdf',7,?,now()-interval '1 minute') RETURNING file_id");$q->execute([bin2hex(random_bytes(32)),hash('sha256','fixture')]);$kycFile=$q->fetchColumn();
    tx($db,fn()=>AuditService::record($db,1,'ADMIN','SHIPPER_APPLICATION_APPROVED','SHIPPER_PROFILE',4));$db->exec("UPDATE shipper_profiles SET updated_at=now()+interval '1 day' WHERE user_id=4");$w->retain();
    check(value($db,"SELECT f.delete_after=(SELECT min(a.created_at)+interval '6 months' FROM audit_logs a WHERE a.entity_type='SHIPPER_PROFILE' AND a.entity_id::text='4' AND a.action='SHIPPER_APPLICATION_APPROVED') FROM private_files f WHERE file_id=?",[$kycFile])===true,'KYC retention uses decision audit, not later profile changes');
    $db->prepare("INSERT INTO order_events(order_id,event_type,deduplication_key,payload) VALUES(?,'OrderCreated','fixture-poison','{}')")->execute([$chatOrder]);$poison=(int)value($db,"SELECT event_id FROM order_events WHERE deduplication_key='fixture-poison'");
    $db->exec("CREATE FUNCTION pg_temp.p3_fail_notice() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'fixture-only'; END $$; CREATE TRIGGER p3_fixture_fail BEFORE INSERT ON notifications FOR EACH ROW EXECUTE FUNCTION pg_temp.p3_fail_notice()");
    check($w->consumeOne(),'failed event recorded without stopping worker');check((int)value($db,'SELECT attempts FROM person3_event_failures WHERE event_id=?',[$poison])===1,'failed event counts attempts');
    check(!$w->consumeOne(),'backoff skips failed event');
    for($i=1;$i<8;$i++){$db->prepare("UPDATE person3_event_failures SET next_attempt_at=now()-interval '1 second' WHERE event_id=?")->execute([$poison]);$w->consumeOne();}
    check(value($db,'SELECT status FROM person3_event_failures WHERE event_id=?',[$poison])==='FAILED','bounded retries reach dead letter');
    $db->exec('DROP TRIGGER p3_fixture_fail ON notifications; DROP FUNCTION pg_temp.p3_fail_notice()');
    check(!$w->consumeOne(),'dead-letter event does not busy loop');
    $db->prepare("UPDATE person3_event_failures SET status='PENDING',attempts=0,next_attempt_at=now() WHERE event_id=?")->execute([$poison]);check($w->consumeOne(),'repaired event may be retried');
    check((int)value($db,"SELECT count(*) FROM event_consumer_receipts WHERE consumer='person3' AND event_id=?",[$poison])===1,'retried event is consumed once');
    check((int)value($db,'SELECT count(*) FROM person3_event_failures WHERE event_id=?',[$poison])===0,'successful retry clears failure');
    echo "Person3 backend: $checks checks passed (isolated TEMP tables).\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
finally{if(isset($path)&&is_file($path))unlink($path);if(isset($storageRoot)&&is_dir($storageRoot))rmdir($storageRoot);}
