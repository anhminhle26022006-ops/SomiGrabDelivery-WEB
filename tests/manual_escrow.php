<?php
declare(strict_types=1);
require __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/PaymentService.php';
require_once __DIR__.'/../app/Services/EncryptedPayload.php';
putenv('SOMI_BANK_NAME=Fixture Bank');putenv('SOMI_BANK_ACCOUNT=123456789');putenv('SOMI_BANK_ACCOUNT_NAME=SOMI ADMIN');
putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
try {
 $db=shipper_test_db();
 foreach(['20261112_no_shipper_refund','20261113_manual_escrow'] as $m){$sql=file_get_contents(__DIR__.'/../database/migrations/'.$m.'.sql');$sql=preg_replace('/^SET LOCAL search_path[^;]+;\s*/m','',$sql);$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));}
 $order=paid_order($db);
 $db->prepare('DELETE FROM payments WHERE order_id=?')->execute([$order]);
 $db->prepare("UPDATE orders SET status='PENDING_PAYMENT',paid_at=NULL WHERE order_id=?")->execute([$order]);
 $service=new PaymentService($db);
 $p=tx($db,fn()=>$service->create(CUSTOMER,$order,'BANK_TRANSFER','manual-test-001'));
 check($p['provider']==='MANUAL_BANK'&&$p['status']==='PENDING','manual transfer requires no gateway');
 check($p['bank_instructions']['transfer_reference']==='SOMI-PAY-'.$p['payment_id'],'unique transfer reference');
 check(value($db,'SELECT status FROM orders WHERE order_id=?',[$order])==='PENDING_PAYMENT','matching waits for evidence');
 $again=tx($db,fn()=>$service->create(CUSTOMER,$order,'BANK_TRANSFER','manual-test-001'));check($again['payment_id']===$p['payment_id'],'payment retry idempotent');
 $input=['bank_reference'=>'incoming-test-001','evidence_reference'=>'statement-fixture','amount_minor'=>'30000','currency_code'=>'VND'];
 tx($db,fn()=>$service->reconcileBank(ADMIN,(int)$p['payment_id'],$input));
 check(value($db,'SELECT status FROM orders WHERE order_id=?',[$order])==='SEARCHING_SHIPPER','matching starts after admin verifies');
 $db->prepare("UPDATE orders SET status='CANCELLED',cancellation_fee_minor=0 WHERE order_id=?")->execute([$order]);
 $db->prepare("INSERT INTO order_status_history(order_id,old_status,new_status,actor_kind) VALUES(?,'NO_SHIPPER_FOUND','CANCELLED','SYSTEM')")->execute([$order]);
 $r=tx($db,fn()=>$service->requestRefund($order,'NO_SHIPPER_FOUND','refund-test-001'));
 check((string)$r['refund_amount_minor']==='30000'&&(string)$r['deducted_fee_minor']==='0','no shipper refunds full amount');
 $q=$db->prepare('INSERT INTO customer_bank_accounts(customer_id,bank_name,account_holder,encrypted_payload) VALUES(?,?,?,?) RETURNING bank_account_id');$q->execute([CUSTOMER,'Fixture Bank','Customer',EncryptedPayload::seal(['account_number'=>'123456789'])]);$bank=(int)$q->fetchColumn();
 $out=['bank_reference'=>'outgoing-test-001','original_payment_reference'=>'incoming-test-001','evidence_reference'=>'outgoing-statement','amount_minor'=>'30000','currency_code'=>'VND','bank_account_id'=>$bank];
 rejects(fn()=>tx($db,fn()=>$service->reconcileBankRefund(ADMIN,(int)$r['refund_id'],array_replace($out,['amount_minor'=>'1']))),'REFUND_MISMATCH');
 tx($db,fn()=>$service->reconcileBankRefund(ADMIN,(int)$r['refund_id'],$out));
 check(value($db,'SELECT status FROM payments WHERE payment_id=?',[$p['payment_id']])==='REFUNDED','refund completed only after evidence');
 check((int)value($db,'SELECT refund_bank_account_id FROM refunds WHERE refund_id=?',[$r['refund_id']])===$bank,'destination frozen');
 $duplicate=tx($db,fn()=>$service->reconcileBankRefund(ADMIN,(int)$r['refund_id'],$out));check($duplicate['duplicate']===true,'refund retry does not duplicate transfer');
 echo json_encode(['result'=>'PASS','checks'=>$checks,'persistent_writes'=>0]).PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
