<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/CustomerOrderService.php';
require_once __DIR__.'/../app/Services/AdminService.php';
require_once __DIR__.'/../app/Services/AdminConsoleService.php';
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
    foreach(['20261105_customer_orders','20261105_approved_pricing','20261105_distance_weight_pricing','20261106_person1_backend'] as $migration){
        $sql=file_get_contents(__DIR__.'/../database/migrations/'.$migration.'.sql');$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
    }
    check(value($db,"SELECT current_setting('search_path')")==='pg_temp, pg_catalog','isolated schema');
    check((int)value($db,"SELECT count(*) FROM pg_class WHERE relnamespace=pg_my_temp_schema() AND relname IN ('users','orders','payments','refunds','email_messages','background_jobs','audit_logs')")===7,'all writes use temporary tables');
    putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
    $db->exec("INSERT INTO users(full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status,email_verified_at,phone_verified_at) VALUES ('Admin','admin@example.invalid','0900000001','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Customer','customer@example.invalid','0900000002','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Other','other@example.invalid','0900000003','test','US','2000-01-01','F','en','ACTIVE',now(),now()),('Shipper','shipper@example.invalid','0900000004','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()); INSERT INTO customer_profiles(user_id) VALUES (2),(3); INSERT INTO shipper_profiles(user_id,vehicle_type,vehicle_registration) VALUES (4,'TEST','TEST'); INSERT INTO user_roles(user_id,role_id) SELECT v.id,r.role_id FROM (VALUES(1,'ADMIN'),(2,'CUSTOMER'),(3,'CUSTOMER'),(4,'SHIPPER'))v(id,role) JOIN roles r ON r.role_code=v.role; INSERT INTO parcel_categories(category_code,name_vi,name_en,restriction_type) VALUES('TEST','Test','Test','ALLOWED');");
function newOrder(PDO $db,string $currency='VND'):int{
        $q=$db->prepare("INSERT INTO orders(order_code,customer_id,currency_code,app_language_at_order,distance_km,pickup_address,pickup_latitude,pickup_longitude,sender_name,sender_phone,delivery_address,delivery_latitude,delivery_longitude,receiver_name,receiver_phone,category_id,estimated_weight_kg,distance_fee_minor,time_multiplier,weather_multiplier,total_charge_minor,return_fee_minor) VALUES (?,2,?,?,4,'TEST',10,106,'S','0900000002','TEST',10,106,'R','0900000003',1,1,100,1,1,100,10) RETURNING order_id");$q->execute(['TEST-'.bin2hex(random_bytes(6)),$currency,$currency==='VND'?'vi':'en']);return (int)$q->fetchColumn();
    }

    $console=new AdminConsoleService($db);
    rejects(fn()=>$console->accounts(2,[]),'PERMISSION_DENIED');
    $all=tx($db,fn()=>$console->accounts(1,[]));check(count($all['items'])===4,'accounts from DB');
    check(!str_contains(json_encode($all),'password_hash'),'no password hashes');
    check(count(tx($db,fn()=>$console->accounts(1,['role'=>'SHIPPER']))['items'])===1,'role filter');
    check(count(tx($db,fn()=>$console->accounts(1,['q'=>'no-such-customer']))['items'])===0,'search empty');
    rejects(fn()=>$console->accounts(1,['status'=>'INVALID']),'VALIDATION_ERROR');
    $account=tx($db,fn()=>$console->account(1,4));check($account['account']['shipper']['vehicle_type']==='TEST','shipper detail');
    rejects(fn()=>tx($db,fn()=>$console->account(1,99999)),'ACCOUNT_NOT_FOUND');
    $db->exec("INSERT INTO users(full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status) SELECT 'Fixture '||i,'extra'||i||'@example.invalid','098'||lpad(i::text,7,'0'),'hash','VN','2000-01-01','M','vi','PENDING_VERIFICATION' FROM generate_series(1,52)i");
    $first=$console->accounts(1,[]);$second=$console->accounts(1,['before_id'=>$first['next_before_id']]);check(count($first['items'])===50&&count($second['items'])===6,'cursor pagination');check(count(array_intersect(array_column($first['items'],'user_id'),array_column($second['items'],'user_id')))===0,'no duplicate page rows');
    $id=newOrder($db);$db->prepare("UPDATE orders SET status='SHIPPER_ASSIGNED',pricing_snapshot=CAST(? AS jsonb),paid_at=now() WHERE order_id=?")->execute([json_encode(['cancellation_fees'=>['SHIPPER_ASSIGNED'=>'7']]),$id]);
    $db->prepare('INSERT INTO order_assignments(order_id,shipper_id) VALUES(?,4)')->execute([$id]);
    $db->prepare("INSERT INTO support_tickets(ticket_code,order_id,opened_by,category,subject,description) VALUES('TEST-TICKET',?,2,'DELIVERY','Test support','Fixture description')")->execute([$id]);$ticket=(int)value($db,'SELECT ticket_id FROM support_tickets LIMIT 1');
    check(count(tx($db,fn()=>$console->tickets(1,[]))['items'])===1,'tickets list');
    check(tx($db,fn()=>$console->tickets(1,['id'=>$ticket]))['ticket']['description']==='Fixture description','ticket detail');
    rejects(fn()=>tx($db,fn()=>$console->ticketStatus(1,['ticket_id'=>$ticket,'status'=>'CLOSED','note'=>'skip'])),'TICKET_STATE_CONFLICT');
    foreach(['IN_PROGRESS','RESOLVED','CLOSED']as $state)tx($db,fn()=>$console->ticketStatus(1,['ticket_id'=>$ticket,'status'=>$state,'note'=>'Fixture resolution']));
    check(value($db,'SELECT status FROM support_tickets WHERE ticket_id=?',[$ticket])==='CLOSED','strict ticket lifecycle');
    check((int)value($db,'SELECT count(*) FROM support_ticket_events')===3,'ticket events');check((int)value($db,"SELECT count(*) FROM notifications WHERE user_id=2 AND notification_type='TICKET_STATUS_CHANGED'")===3,'reporter notifications');
    check(value($db,'SELECT resolution FROM support_tickets WHERE ticket_id=?',[$ticket])==='Fixture resolution','resolution persisted');
    $db->prepare("INSERT INTO chat_messages(order_id,sender_id,body,delete_after) VALUES(?,2,'Retained',now()+interval '1 month'),(?,2,'Expired',now()-interval '1 day')")->execute([$id,$id]);
    $chat=tx($db,fn()=>$console->chat(1,$ticket));check(count($chat['items'])===1&&$chat['items'][0]['body']==='Retained','chat respects retention');check((int)value($db,"SELECT count(*) FROM audit_logs WHERE action='CHAT_READ_FOR_TICKET'")===1,'chat access audited');
    rejects(fn()=>tx($db,fn()=>$console->chat(1,9999)),'TICKET_NOT_FOUND');
    $notification=(int)value($db,'SELECT notification_id FROM notifications WHERE user_id=2 LIMIT 1');
    rejects(fn()=>tx($db,fn()=>$console->markRead(1,$notification)),'NOTIFICATION_NOT_FOUND');
    check(count($console->notifications(1,[])['items'])===0,'admin cannot read other notifications');
    tx($db,fn()=>$console->cancel(1,$id,'Fixture cancel'));check(value($db,'SELECT status FROM orders WHERE order_id=?',[$id])==='CANCELLED','admin cancellation');
    check((string)value($db,'SELECT cancellation_fee_minor FROM orders WHERE order_id=?',[$id])==='7','frozen cancellation fee');check(value($db,'SELECT assignment_status FROM order_assignments WHERE order_id=?',[$id])==='ADMIN_CANCELLED','active assignment ended');
    check((int)value($db,"SELECT count(*) FROM order_events WHERE order_id=? AND event_type='RefundRequested'",[$id])===1,'refund event emitted');
    rejects(fn()=>tx($db,fn()=>$console->cancel(1,$id,'duplicate')),'ORDER_NOT_CANCELLABLE');
    $picked=newOrder($db);$db->prepare("UPDATE orders SET status='PICKED_UP' WHERE order_id=?")->execute([$picked]);rejects(fn()=>tx($db,fn()=>$console->cancel(1,$picked,'invalid')),'ORDER_NOT_CANCELLABLE');
    $overview=$console->overview(1);check(count($overview['days'])===7,'seven Vietnam dates');check((int)$overview['stats']['accounts']===56,'real dashboard aggregate');
    check(count($console->reviews(1,[])['items'])===0,'empty reviews are empty');
    $db->exec("INSERT INTO shipper_score_policies(deduction_points,lock_threshold,recovery_points,effective_from) VALUES(5,60,2,now()); UPDATE shipper_profiles SET reliability_score=20 WHERE user_id=4");
    rejects(fn()=>tx($db,fn()=>$console->restoreScore(1,['shipper_id'=>4,'score'=>'50','reason'=>'invalid'])),'SCORE_POLICY_REQUIRED');
    tx($db,fn()=>$console->restoreScore(1,['shipper_id'=>4,'score'=>'100','reason'=>'Fixture restoration']));check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=4')==='100.00','admin score restoration');check((int)value($db,'SELECT count(*) FROM shipper_score_events WHERE shipper_id=4')===1,'score event recorded');
    rejects(fn()=>tx($db,fn()=>$console->restoreScore(1,['shipper_id'=>4,'score'=>'100','reason'=>'duplicate'])),'SCORE_MUST_INCREASE');
    echo "PASS: $checks Admin checks; TEMP tables only; no real email, payments or user changes.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1);}
