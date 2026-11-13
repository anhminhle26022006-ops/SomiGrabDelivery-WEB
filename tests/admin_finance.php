<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'&&(PHP_SAPI!=='cli-server'||getenv('SOMI_TEST_ADMIN_FINANCE')!=='1'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1')){http_response_code(404);exit;}
require __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/AdminFinanceService.php';
putenv('SOMI_BANK_NAME=Fixture Bank');putenv('SOMI_BANK_ACCOUNT=123456789');putenv('SOMI_BANK_ACCOUNT_NAME=ADMIN');putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
try{
 $db=shipper_test_db();foreach(['20261112_no_shipper_refund','20261113_manual_escrow','20261113_admin_finance'] as $m){$sql=preg_replace('/^SET LOCAL search_path[^;]+;\s*/m','',file_get_contents(__DIR__.'/../database/migrations/'.$m.'.sql'));$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));}
 $svc=new AdminFinanceService($db);$order=paid_order($db);
 $db->prepare('DELETE FROM payments WHERE order_id=?')->execute([$order]);$db->prepare("UPDATE orders SET status='PENDING_PAYMENT',paid_at=NULL WHERE order_id=?")->execute([$order]);
 $p=tx($db,fn()=>(new PaymentService($db))->create(CUSTOMER,$order,'BANK_TRANSFER','admin-finance-fixture'));
 $q=$db->prepare("INSERT INTO customer_bank_accounts(customer_id,bank_name,account_holder,encrypted_payload,review_status) VALUES(2,'Fixture Bank','Customer',?,'PENDING') RETURNING bank_account_id");$q->execute([EncryptedPayload::seal(['account_number'=>'000123456789'])]);$bank=(int)$q->fetchColumn();
 $banks=tx($db,fn()=>$svc->banks(ADMIN,[]));check($banks['items'][0]['account_number']==='000123456789','admin bank list retains leading zeros');check(!isset($banks['items'][0]['encrypted_payload']),'bank list excludes ciphertext');
 rejects(fn()=>tx($db,fn()=>$svc->banks(CUSTOMER,[])),'PERMISSION_DENIED');
 $list=$svc->listing(ADMIN,['q'=>'SOMI-TEST']);check(count($list['items'])===1,'order finance search');
 $d=tx($db,fn()=>$svc->detail(ADMIN,$order));check($d['payment']['payment_id']===$p['payment_id']&&$d['order']['status']==='PENDING_PAYMENT','payment linked to correct order');
 check($d['customer']['bank']['account_number']==='000123456789','customer bank visible before a refund exists');check($d['admin_bank']['account_number']==='123456789','admin receiving bank is included');
 $assigned=paid_order($db);$db->prepare('INSERT INTO order_assignments(order_id,shipper_id) VALUES(?,3)')->execute([$assigned]);$db->prepare("INSERT INTO shipper_bank_accounts(shipper_id,bank_code,bank_name,account_holder,account_number_enc,verified_at) VALUES(3,'TEST','Shipper Bank','Shipper',?,now())")->execute([EncryptedPayload::seal(['account_number'=>'000333222111'])]);
 $assignedDetail=tx($db,fn()=>$svc->detail(ADMIN,$assigned));check($assignedDetail['shippers'][0]['bank']['account_number']==='000333222111'&&$assignedDetail['payouts']===[],'assigned shipper bank visible before payout creation');
 tx($db,fn()=>$svc->review(ADMIN,['role'=>'CUSTOMER','bank_account_id'=>$bank,'decision'=>'APPROVED','note'=>'Matched holder and account']));check(value($db,'SELECT review_status FROM customer_bank_accounts WHERE bank_account_id=?',[$bank])==='APPROVED','bank review persisted');
 rejects(fn()=>tx($db,fn()=>$svc->confirm(ADMIN,['action'=>'PAYMENT','target_id'=>$p['payment_id'],'confirmed'=>'yes'],[])),'RECEIPT_REQUIRED');
 check((int)value($db,'SELECT count(*) FROM finance_evidence')===0,'missing evidence does not change ledger');
 if(PHP_SAPI==='cli-server'){
  $file=$_FILES['receipt']??[];
  $receipt=tx($db,fn()=>$svc->confirm(ADMIN,['action'=>'PAYMENT','target_id'=>$p['payment_id'],'confirmed'=>'yes'],$file));
  check(value($db,'SELECT status FROM orders WHERE order_id=?',[$order])==='SEARCHING_SHIPPER','receipt confirms incoming funds then starts matching');
  $duplicate=tx($db,fn()=>$svc->confirm(ADMIN,['action'=>'PAYMENT','target_id'=>$p['payment_id'],'confirmed'=>'yes'],$file));check($duplicate['duplicate']===true,'receipt confirmation is idempotent');
  $db->prepare("UPDATE orders SET status='CANCELLED',cancellation_fee_minor=0 WHERE order_id=?")->execute([$order]);$db->prepare("INSERT INTO order_status_history(order_id,old_status,new_status,actor_kind) VALUES(?,'NO_SHIPPER_FOUND','CANCELLED','SYSTEM')")->execute([$order]);
  $r=tx($db,fn()=>(new PaymentService($db))->requestRefund($order,'NO_SHIPPER_FOUND','admin-finance-refund'));
  $db->prepare("UPDATE customer_bank_accounts SET review_status='PENDING' WHERE bank_account_id=?")->execute([$bank]);
  rejects(fn()=>tx($db,fn()=>$svc->confirm(ADMIN,['action'=>'REFUND','target_id'=>$r['refund_id'],'bank_account_id'=>$bank,'confirmed'=>'yes'],$file)),'BANK_REVIEW_REQUIRED');
  check((int)value($db,'SELECT count(*) FROM finance_evidence')===1,'blocked refund rolls receipt back');
  tx($db,fn()=>$svc->review(ADMIN,['role'=>'CUSTOMER','bank_account_id'=>$bank,'decision'=>'APPROVED','note'=>'Approved for refund']));
  tx($db,fn()=>$svc->confirm(ADMIN,['action'=>'REFUND','target_id'=>$r['refund_id'],'bank_account_id'=>$bank,'confirmed'=>'yes'],$file));
  $d=tx($db,fn()=>$svc->detail(ADMIN,$order));check($d['payment']['status']==='REFUNDED'&&$d['refunds'][0]['status']==='SUCCEEDED','refund and payment statuses agree');check(count($d['evidence'])===2,'incoming and outgoing receipts linked to one order');
  check(tx($db,fn()=>$svc->receipt(ADMIN,(int)$receipt['evidence_id']))['mime_type']==='image/png','admin can read encrypted receipt');
 }
 $db->exec('INSERT INTO customer_profiles(user_id) VALUES(5)');
 $q=$db->prepare("INSERT INTO customer_bank_accounts(customer_id,bank_name,account_holder,encrypted_payload,review_status) VALUES(5,'Old Bank','Old account',?,'PENDING') RETURNING bank_account_id");$q->execute(['v1:unreadable-legacy-key']);$broken=(int)$q->fetchColumn();
 $banks=tx($db,fn()=>$svc->banks(ADMIN,[]));$brokenBank=array_values(array_filter($banks['items'],fn($b)=>(int)$b['user_id']===5))[0];check($brokenBank['needs_reentry']===true,'one unreadable legacy bank does not break bank list');
 rejects(fn()=>tx($db,fn()=>$svc->review(ADMIN,['role'=>'CUSTOMER','bank_account_id'=>$broken,'decision'=>'APPROVED','note'=>'Cannot verify number'])),'BANK_REENTRY_REQUIRED');
 echo json_encode(['result'=>'PASS','checks'=>$checks,'persistent_writes'=>0]);
}catch(Throwable $e){http_response_code(500);echo get_class($e).': '.$e->getMessage();exit(1);}
