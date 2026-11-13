<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/InteractionService.php';
require_once __DIR__.'/../app/Services/PrivateStorageService.php';
require_once __DIR__.'/../app/Services/Person3Worker.php';
function workspace_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$db=somi_db();$db->exec('SET search_path TO pg_temp,pg_catalog');
// All fixtures and migration writes stay in this connection's temporary tables.
$db->exec("CREATE TEMP TABLE roles(role_id bigint,role_code text);INSERT INTO roles VALUES(1,'CUSTOMER'),(2,'SHIPPER'),(3,'ADMIN');
 CREATE TEMP TABLE user_roles(user_id bigint,role_id bigint);INSERT INTO user_roles VALUES(42,1),(42,2),(99,1);
 CREATE TEMP TABLE orders(order_id bigint,customer_id bigint);INSERT INTO orders VALUES(10,42),(11,99);
 CREATE TEMP TABLE order_assignments(order_id bigint,shipper_id bigint,assignment_status text DEFAULT 'ACTIVE');INSERT INTO order_assignments(order_id,shipper_id) VALUES(11,42);
 CREATE TEMP TABLE support_tickets(ticket_id bigint GENERATED ALWAYS AS IDENTITY,ticket_code text,order_id bigint,opened_by bigint,category text,subject text,description text,status text DEFAULT 'NEW',client_key text,created_at timestamptz DEFAULT now());
 INSERT INTO support_tickets(ticket_code,order_id,opened_by) VALUES('LEGACY-C',10,42),('LEGACY-S',11,42),('UNKNOWN',NULL,42);
 CREATE TEMP TABLE support_ticket_events(ticket_id bigint,new_status text,note text,changed_by bigint);
 CREATE TEMP TABLE support_replies(reply_id bigint,ticket_id bigint);
 CREATE TEMP TABLE notifications(notification_id bigint GENERATED ALWAYS AS IDENTITY,user_id bigint,order_id bigint,notification_type text,title_vi text,title_en text,message_vi text,message_en text,in_app_status text,email_status text,dedup_key text,read_at timestamptz,created_at timestamptz DEFAULT now(),UNIQUE(user_id,dedup_key));
 INSERT INTO notifications(user_id,order_id,notification_type,dedup_key) VALUES(42,10,'OrderCancelled','legacy-c'),(42,10,'NoShipperFound','legacy-no-shipper'),(42,10,'PaymentConfirmed','legacy-payment'),(42,11,'OrderDelivered','legacy-s'),(42,NULL,'Unknown','legacy-unknown'),(99,11,'OrderCreated','legacy-other');");
$migration=preg_replace('/^SET LOCAL search_path[^;]+;\s*/m','',file_get_contents(__DIR__.'/../database/migrations/20261113_workspace_notifications.sql'));
$db->exec($migration);
$svc=new InteractionService($db);
$customer=$svc->notifications(42,'CUSTOMER',[],false);$shipper=$svc->notifications(42,'SHIPPER',[],false);
workspace_check(count($customer['items'])===3&&count($shipper['items'])===1,'legacy customer and shipper feeds split for same user');
workspace_check($customer['unread_count']===3&&$shipper['unread_count']===1,'badges are workspace scoped');
workspace_check($shipper['items'][0]['notification_type']==='OrderDelivered','checkout/matching events stay customer only');
foreach(['CUSTOMER','SHIPPER'] as $role){
 InteractionService::notify($db,42,null,'Test','Test','Test','Test','Test','same-event',$role);
 InteractionService::notify($db,42,null,'Test','Test','Test','Test','Test','same-event',$role);
}
workspace_check((int)$db->query("SELECT count(*) FROM notifications WHERE notification_type='Test'")->fetchColumn()===2,'dedup is independent by workspace');
$customerId=$customer['items'][0]['notification_id'];
try{$svc->notifications(42,'SHIPPER',['notification_id'=>$customerId,'read'=>true],true);throw new RuntimeException('cross-workspace mutation allowed');}
catch(DeliveryApiError $e){workspace_check($e->errorCode==='NOTIFICATION_NOT_FOUND','read rejects other workspace IDs');}
$svc->notifications(42,'SHIPPER',['all'=>true,'read'=>true,'through_id'=>'99999'],true);
workspace_check($svc->notifications(42,'SHIPPER',[],false)['unread_count']===0,'shipper mark all');
workspace_check($svc->notifications(42,'CUSTOMER',[],false)['unread_count']===4,'shipper mark all leaves customer unread');
$db->exec("INSERT INTO notifications(user_id,recipient_role,notification_type) SELECT 42,'SHIPPER','Paged' FROM generate_series(1,51)");
$page=$svc->notifications(42,'SHIPPER',[],false);$older=$svc->notifications(42,'SHIPPER',['before_id'=>$page['next_cursor']],false);
workspace_check(count($page['items'])===50&&count($older['items'])===3,'pagination stays within workspace');
workspace_check(!array_intersect(array_column($customer['items'],'notification_id'),array_column($older['items'],'notification_id')),'pagination cannot expose customer notifications');
workspace_check(count($svc->tickets(42,'CUSTOMER',[],false)['items'])===1&&count($svc->tickets(42,'SHIPPER',[],false)['items'])===1,'support requests split by workspace');
$new=$svc->tickets(42,'SHIPPER',['category'=>'ACCOUNT','subject'=>'Shipper profile','description'=>'Help','client_key'=>'workspace-ticket-001'],true)['ticket'];
try{$svc->tickets(42,'CUSTOMER',['id'=>$new['ticket_id']],false);throw new RuntimeException('cross-workspace ticket read allowed');}
catch(DeliveryApiError $e){workspace_check($e->errorCode==='TICKET_NOT_FOUND','ticket detail rejects other workspace');}
try{$svc->replies(42,'CUSTOMER',['ticket_id'=>$new['ticket_id']],false);throw new RuntimeException('cross-workspace replies read allowed');}
catch(DeliveryApiError $e){workspace_check($e->errorCode==='TICKET_NOT_FOUND','ticket replies reject other workspace');}
$db->exec($migration);
workspace_check($svc->notifications(42,'CUSTOMER',[],false)['unread_count']===4,'migration safely repeats');
$db->exec("CREATE TEMP TABLE private_files(file_id bigint GENERATED ALWAYS AS IDENTITY,owner_id bigint,order_id bigint,ticket_id bigint,purpose text,original_name text,mime_type text,byte_size bigint,created_at timestamptz DEFAULT now(),deleted_at timestamptz,delete_after timestamptz DEFAULT 'infinity',message_id bigint)");
$q=$db->prepare("INSERT INTO private_files(owner_id,ticket_id,purpose) VALUES(42,1,'TICKET'),(42,?,'TICKET'),(42,NULL,'KYC')");$q->execute([$new['ticket_id']]);
$storage=new PrivateStorageService($db);
workspace_check(count($storage->listing(42,[],'CUSTOMER')['items'])===1,'customer file list excludes shipper ticket and KYC');
workspace_check(count($storage->listing(42,[],'SHIPPER')['items'])===2,'shipper file list excludes customer ticket');
try{$storage->signed(2,42,'CUSTOMER');throw new RuntimeException('cross-workspace ticket file allowed');}
catch(DeliveryApiError $e){workspace_check($e->errorCode==='FILE_NOT_FOUND','signed file route rejects other workspace');}
$db->exec("ALTER TABLE orders ADD COLUMN order_code text DEFAULT 'FIXTURE';
 CREATE TEMP TABLE order_events(event_id bigint GENERATED ALWAYS AS IDENTITY,order_id bigint,event_type text);
 CREATE TEMP TABLE event_consumer_receipts(consumer text,event_id bigint);
 CREATE TEMP TABLE person3_event_failures(event_id bigint,status text,next_attempt_at timestamptz);
 INSERT INTO order_events(order_id,event_type) VALUES(11,'PaymentConfirmed'),(11,'NoShipperFound'),(11,'ShipperAssigned')");
$worker=new Person3Worker($db);for($i=0;$i<3;$i++)workspace_check($worker->consumeOne(),'worker consumes fixture event');
workspace_check(!$worker->consumeOne(),'worker does not repeat notifications');
workspace_check((int)$db->query("SELECT count(*) FROM notifications WHERE order_id=11 AND notification_type IN ('PaymentConfirmed','NoShipperFound') AND recipient_role='SHIPPER'")->fetchColumn()===0,'worker excludes customer payment and matching events from shipper');
workspace_check((int)$db->query("SELECT count(*) FROM notifications WHERE order_id=11 AND notification_type='ShipperAssigned'")->fetchColumn()===2,'worker sends separate assignment notifications to customer and shipper');
echo "PASS: dual-role legacy backfill, feeds, badges, read/write isolation, dedup, pagination, support tickets and private file isolation; temporary PostgreSQL tables only.\n";
