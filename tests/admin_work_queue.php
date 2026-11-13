<?php
declare(strict_types=1);
require __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/AdminConsoleService.php';
require_once __DIR__.'/../app/Services/AdminFinanceService.php';
try{
 $db=shipper_test_db();foreach(['20261113_manual_escrow','20261113_admin_finance'] as $m){$sql=preg_replace('/^SET LOCAL search_path[^;]+;\s*/m','',file_get_contents(__DIR__.'/../database/migrations/'.$m.'.sql'));$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));}
 $db->exec("UPDATE shipper_profiles SET verification_status='PENDING' WHERE user_id=5");
 $db->exec("INSERT INTO support_tickets(ticket_code,opened_by,opened_role,category,description,status) SELECT 'QUEUE-'||i,2,'CUSTOMER','OTHER','Fixture',CASE WHEN i=1 THEN 'NEW' ELSE 'CLOSED' END FROM generate_series(1,61) i");
 $db->exec("INSERT INTO customer_bank_accounts(customer_id,bank_name,account_holder,encrypted_payload,review_status) VALUES(2,'Old Bank','Customer','fixture','PENDING'),(2,'New Bank','Customer','fixture','APPROVED'); INSERT INTO shipper_bank_accounts(shipper_id,bank_code,bank_name,account_holder,account_number_enc,verified_at,review_status) VALUES(3,'TEST','Bank','Shipper','fixture',now(),'PENDING')");
 $unpaid=paid_order($db);$newer=paid_order($db);$db->prepare("UPDATE payments SET provider='MANUAL_BANK',method='BANK_TRANSFER',status='PENDING' WHERE order_id=?")->execute([$unpaid]);$db->prepare("UPDATE orders SET status='PENDING_PAYMENT' WHERE order_id=?")->execute([$unpaid]);$db->prepare("UPDATE orders SET status='NO_SHIPPER_FOUND' WHERE order_id=?")->execute([$newer]);
 $db->exec("INSERT INTO background_jobs(kind,deduplication_key,payload,status) VALUES('EMAIL','queue-fixture','{}','FAILED'); INSERT INTO notifications(user_id,recipient_role,notification_type,title_vi,message_vi,title_en,message_en) VALUES(1,'ADMIN','TEST','Test','Test','Test','Test'),(1,'CUSTOMER','TEST','Other','Other','Other','Other')");
 $c=AdminWorkQueue::counts($db,ADMIN)['counts'];check($c['shippers']===1&&$c['users']===1,'KYC badges count pending approvals');check($c['customers']===0,'customer badge excludes unrelated shipper-only reviews');check($c['complaints']===1,'ticket badge excludes closed rows');check($c['payments']===1,'finance badge counts orders with outstanding admin work');check($c['orders']===1,'order badge counts intervention cases');check($c['bank-accounts']===1,'bank badge ignores replaced customer version');check($c['operations']===1,'failed job badge');check($c['notifications']===1,'unread badge is scoped to admin workspace');
 rejects(fn()=>AdminWorkQueue::counts($db,CUSTOMER),'PERMISSION_DENIED');
 $console=new AdminConsoleService($db);$first=$console->tickets(ADMIN,[]);check((int)$first['items'][0]['ticket_id']===1,'old pending ticket precedes newer closed tickets');check(count($first['items'])===50&&$first['next_priority']===1,'priority-aware page boundary');
 $db->prepare("UPDATE support_tickets SET status='NEW' WHERE ticket_id=?")->execute([$first['next_before_id']]);
 $second=$console->tickets(ADMIN,['before_id'=>$first['next_before_id'],'before_priority'=>$first['next_priority']]);$ids=array_merge(array_column($first['items'],'ticket_id'),array_column($second['items'],'ticket_id'));check(count($ids)===61&&count(array_unique($ids))===61,'no skipped or duplicate rows across changed priority boundary');
 $filtered=$console->tickets(ADMIN,['status'=>'CLOSED']);check(count(array_filter($filtered['items'],fn($r)=>$r['status']!=='CLOSED'))===0,'priority preserves explicit filters');
 $finances=(new AdminFinanceService($db))->listing(ADMIN,[]);check((int)$finances['items'][0]['order_id']===$unpaid,'old unpaid order is listed before newer non-actionable order');
 $accounts=$console->accounts(ADMIN,['role'=>'SHIPPER']);check((int)$accounts['items'][0]['user_id']===5,'pending KYC listed before approved shippers');
 $db->exec("UPDATE support_tickets SET status='RESOLVED' WHERE status='NEW'; UPDATE shipper_profiles SET verification_status='APPROVED'; UPDATE shipper_bank_accounts SET review_status='APPROVED'; UPDATE notifications SET read_at=now() WHERE recipient_role='ADMIN'");
 $after=AdminWorkQueue::counts($db,ADMIN)['counts'];check($after['complaints']===0&&$after['shippers']===0&&$after['bank-accounts']===0&&$after['notifications']===0,'badges decrease after work is completed');
 echo json_encode(['result'=>'PASS','checks'=>$checks,'persistent_writes'=>0]).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
