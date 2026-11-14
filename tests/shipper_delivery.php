<?php
declare(strict_types=1);
// Kiểm thử giao nhận, bằng chứng, hoàn hàng và GPS. Chạy: php tests/shipper_delivery.php
require_once __DIR__ . '/shipper_fixtures.php';
require_once __DIR__ . '/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__ . '/../app/Services/MatchingService.php';
require_once __DIR__ . '/../app/Services/OfferAcceptService.php';
require_once __DIR__ . '/../app/Services/DeliveryCompletionService.php';
require_once __DIR__ . '/../app/Services/DeliveryReturnService.php';
require_once __DIR__ . '/../app/Services/ShipperLocationService.php';
require_once __DIR__ . '/../app/Services/Person3Worker.php';
require_once __DIR__ . '/../app/Services/ShipperBankService.php';
require_once __DIR__ . '/../app/Services/AuthRateLimiter.php';

/** Tạo đơn đã thanh toán và cho Shipper A nhận, trả về order_id. */
function assigned_order(PDO $db): int
{
    $order = paid_order($db);
    tx($db, fn() => (new MatchingService($db))->dispatch($order));
    $offer = (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ? AND shipper_id = 3', [$order]);
    tx($db, fn() => (new OfferAcceptService($db))->accept(SHIPPER_A, $offer));
    return $order;
}
function photo(string $name): array { global $db; $order=(int)value($db,"SELECT order_id FROM order_assignments WHERE shipper_id=3 AND assignment_status='ACTIVE'");return [storage_file($db,SHIPPER_A,'EVIDENCE',$order), 'image/jpeg', 512000, hash('sha256', $name)]; }

try {
    $db = shipper_test_db();
    foreach(['20261113_manual_escrow','20261113_admin_finance'] as $migration){$sql=file_get_contents(__DIR__.'/../database/migrations/'.$migration.'.sql');$db->exec(str_replace(['SET LOCAL search_path TO somi, public;','CREATE TABLE '],['','CREATE TEMP TABLE '],$sql));}
    $delivery = new DeliveryService($db); $completion = new DeliveryCompletionService($db);
    $returns = new DeliveryReturnService($db); $gps = new ShipperLocationService($db);
    tx($db, fn() => (new ShipperAvailabilityService($db))->turnOn(SHIPPER_A, NEAR[0], NEAR[1]));
    tx($db, fn() => (new ShipperAvailabilityService($db))->turnOn(SHIPPER_B, FARTHER[0], FARTHER[1]));
    check($delivery->current(SHIPPER_A)['delivery'] === null, 'no active delivery yet');
    rejects(fn() => tx($db, fn() => $gps->record(SHIPPER_A, NEAR[0], NEAR[1], 12.5)), 'NO_ACTIVE_DELIVERY');

    // --- Luồng giao thành công ---
    $order = assigned_order($db);
    $current = $delivery->current(SHIPPER_A)['delivery'];
    check($current['status'] === 'SHIPPER_ASSIGNED' && $current['receiver_phone'] === '0900000099' && $current['next_actions'] === ['PICKUP'], 'assigned shipper sees contacts and the next step');
    rejects(fn() => tx($db, fn() => $delivery->pickup(SHIPPER_B, $order)), 'ORDER_NOT_FOUND');
    rejects(fn() => tx($db, fn() => $delivery->startDelivery(SHIPPER_A, $order)), 'INVALID_ORDER_STATE');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', ...photo('early'))), 'INVALID_ORDER_STATE');
    tx($db, fn() => $delivery->pickup(SHIPPER_A, $order));
    check(tx($db, fn() => $delivery->pickup(SHIPPER_A, $order))['delivery']['status'] === 'PICKED_UP', 'pickup replay is harmless');
    check(value($db, 'SELECT picked_up_at IS NOT NULL FROM orders WHERE order_id = ?', [$order]) === true, 'pickup time recorded');
    tx($db, fn() => $delivery->startDelivery(SHIPPER_A, $order));
    rejects(fn() => tx($db, fn() => $completion->delivered(SHIPPER_A, $order)), 'ARRIVAL_REQUIRED');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A,$order,'DELIVERY_PHOTO',...photo('before-arrival'))), 'ARRIVAL_REQUIRED');
    rejects(fn()=>tx($db,fn()=>$delivery->arrive(SHIPPER_A,$order)),'ARRIVAL_LOCATION_REQUIRED');
    check($delivery->current(SHIPPER_A)['delivery']['next_actions']===['ARRIVED','FAIL'],'arrival precedes delivery evidence');
    $far=tx($db,fn()=>$gps->record(SHIPPER_A,NEAR[0],NEAR[1],8));
    check($far['arrived_at']===null,'GPS far from destination cannot mark arrival');
    $db->exec('DELETE FROM shipper_locations');
    $inaccurate=tx($db,fn()=>$gps->record(SHIPPER_A,'10.7760000','106.6880000',500));
    check($inaccurate['arrived_at']===null,'inaccurate GPS cannot auto-arrive');
    $db->exec('DELETE FROM shipper_locations');
    $arrival=tx($db,fn()=>$gps->record(SHIPPER_A,'10.7760000','106.6880000',8));
    check($arrival['arrived_at']!==null,'accurate GPS at destination automatically records arrival');
    check(tx($db,fn()=>$delivery->arrive(SHIPPER_A,$order))['delivery']['arrived_at']===$arrival['arrived_at'],'manual arrival retry is idempotent');
    check((int)value($db,"SELECT count(*) FROM order_events WHERE order_id=? AND event_type='OrderArrived'",[$order])===1,'arrival event emitted exactly once');
    check($gps->latest(CUSTOMER,'CUSTOMER',$order)['arrived_at']===$arrival['arrived_at'],'customer sees arrival milestone');
    $db->exec('DELETE FROM shipper_locations');
    rejects(fn() => tx($db, fn() => $completion->delivered(SHIPPER_A, $order)), 'EVIDENCE_REQUIRED');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'RETURN_PHOTO', ...photo('wrong'))), 'INVALID_ORDER_STATE');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', '../../etc/passwd', 'image/jpeg', 10, null)), 'VALIDATION_ERROR');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', 'pending-upload/evidence/x.exe', 'application/x-msdownload', 10, null)), 'VALIDATION_ERROR');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_B, $order, 'DELIVERY_PHOTO', ...photo('foreign'))), 'ORDER_NOT_FOUND');
    $after = tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', ...photo('door')));
    $foreignFile=storage_file($db,SHIPPER_B,'EVIDENCE',$order);
    rejects(fn()=>tx($db,fn()=>$completion->addEvidence(SHIPPER_A,$order,'DELIVERY_PHOTO',$foreignFile,null,null,null)),'FILE_NOT_FOUND');
    $wrongPurpose=storage_file($db,SHIPPER_A,'KYC');
    rejects(fn()=>tx($db,fn()=>$completion->addEvidence(SHIPPER_A,$order,'DELIVERY_PHOTO',$wrongPurpose,null,null,null)),'FILE_NOT_FOUND');
    $usedFile=(int)value($db,"SELECT file_id FROM private_files WHERE order_id=? AND owner_id=3 ORDER BY file_id DESC LIMIT 1",[$order]);
    $prior=(int)value($db,'SELECT count(*) FROM delivery_evidence');
    tx($db,fn()=>$completion->addEvidence(SHIPPER_A,$order,'DELIVERY_PHOTO',$usedFile,'application/pdf',1,null));
    check((int)value($db,'SELECT count(*) FROM delivery_evidence')===$prior,'same file retry does not duplicate evidence and ignores client metadata');
    check(in_array('DELIVERED', $after['delivery']['next_actions'], true) && !isset($after['delivery']['evidence'][0]['object_key']), 'evidence unlocks completion and hides storage keys');

    // --- GPS trong lúc giao ---
    tx($db, fn() => $gps->record(SHIPPER_A, '10.7900000', '106.6850000', 8));
    rejects(fn() => tx($db, fn() => $gps->record(SHIPPER_A, '10.7901000', '106.6851000', 8)), 'LOCATION_RATE_LIMITED');
    rejects(fn() => tx($db, fn() => $gps->record(SHIPPER_A, 'abc', '106.6851000', 8)), 'VALIDATION_ERROR');
    check($gps->latest(CUSTOMER, 'CUSTOMER', $order)['location']['latitude'] === '10.7900000', 'customer reads the latest point of their order');
    check($gps->latest(SHIPPER_A, 'SHIPPER', $order)['location'] !== null, 'assigned shipper reads the point back');
    rejects(fn() => $gps->latest(SHIPPER_B, 'SHIPPER', $order), 'ORDER_NOT_FOUND');
    rejects(fn() => tx($db, fn() => $gps->record(SHIPPER_B, NEAR[0], NEAR[1], null)), 'NO_ACTIVE_DELIVERY');
    check((int) value($db, "SELECT round(extract(epoch FROM delete_after - recorded_at) / 86400) FROM shipper_locations LIMIT 1") === 7, 'gps point carries a 7 day retention deadline');

    check(tx($db, fn() => $completion->delivered(SHIPPER_A, $order))['status'] === 'DELIVERED', 'delivered with evidence');
    check(value($db, 'SELECT assignment_status FROM order_assignments WHERE order_id = ?', [$order]) === 'COMPLETED', 'assignment completed');
    check((int) value($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderDelivered'", [$order]) === 1, 'OrderDelivered published once');
    check($gps->latest(CUSTOMER, 'CUSTOMER', $order)['location'] === null, 'tracking stops after delivery');
    rejects(fn() => tx($db, fn() => $completion->delivered(SHIPPER_A, $order)), 'ORDER_NOT_FOUND');
    check(count($delivery->history(SHIPPER_A, null, 20)['items']) === 1 && $delivery->current(SHIPPER_A)['delivery'] === null, 'finished order moves to history');

    // --- Giao thất bại và hoàn hàng, cần cả hai bên xác nhận ---
    $failed = assigned_order($db);
    rejects(fn() => tx($db, fn() => $returns->fail(SHIPPER_A, $failed, 'NO_ANSWER', null)), 'INVALID_ORDER_STATE');
    tx($db, fn() => $delivery->pickup(SHIPPER_A, $failed));
    tx($db, fn() => $delivery->startDelivery(SHIPPER_A, $failed));
    rejects(fn() => tx($db, fn() => $returns->fail(SHIPPER_A, $failed, 'OTHER', null)), 'VALIDATION_ERROR');
    $state = tx($db, fn() => $returns->fail(SHIPPER_A, $failed, 'NO_ANSWER', 'Gọi 3 lần không nghe máy'));
    check($state['delivery']['status'] === 'RETURNING' && $state['delivery']['next_actions'] === ['ADD_RETURN_PHOTO'], 'failed delivery starts the return');
    check((int) value($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderReturning'", [$failed]) === 1, 'OrderReturning published');
    rejects(fn() => tx($db, fn() => $completion->delivered(SHIPPER_A, $failed)), 'INVALID_ORDER_STATE');
    rejects(fn() => tx($db, fn() => $returns->shipperReturned(SHIPPER_A, $failed)), 'EVIDENCE_REQUIRED');
    rejects(fn() => tx($db, fn() => $returns->customerConfirmed(CUSTOMER, $failed)), 'RETURN_NOT_HANDED_OVER');
    tx($db, fn() => $completion->addEvidence(SHIPPER_A, $failed, 'RETURN_PHOTO', ...photo('return')));
    check(tx($db, fn() => $returns->shipperReturned(SHIPPER_A, $failed))['status'] === 'RETURNING', 'shipper confirmation alone does not close the return');
    check((int) value($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderReturned'", [$failed]) === 0, 'OrderReturned waits for the customer');
    rejects(fn() => tx($db, fn() => $returns->customerConfirmed(SHIPPER_B, $failed)), 'ORDER_NOT_FOUND');
    check(tx($db, fn() => $returns->customerConfirmed(CUSTOMER, $failed))['status'] === 'RETURNED', 'both confirmations close the return');
    check(tx($db, fn() => $returns->customerConfirmed(CUSTOMER, $failed))['status'] === 'RETURNED', 'confirmation replay is harmless');
    check((int) value($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderReturned'", [$failed]) === 1, 'OrderReturned published once');
    check(value($db, 'SELECT assignment_status FROM order_assignments WHERE order_id = ?', [$failed]) === 'RETURNED', 'assignment closed as RETURNED');
    check(value($db, "SELECT string_agg(new_status, '>' ORDER BY status_history_id) FROM order_status_history WHERE order_id = ?", [$failed]) === 'SEARCHING_SHIPPER>SHIPPER_ASSIGNED>PICKED_UP>IN_DELIVERY>RETURNING>RETURNED', 'full status history is recorded');

    // --- Dọn GPS hết hạn lưu ---
    $db->exec("UPDATE shipper_locations SET recorded_at = now() - interval '9 days', delete_after = now() - interval '2 days'");
    check(tx($db, fn() => $gps->purgeExpired()) === 1 && (int) value($db, 'SELECT count(*) FROM shipper_locations') === 0, 'expired gps points are deleted');

    // Consume actual events emitted above by matching/delivery services, not hand-built event fixtures.
    putenv('SOMI_STORAGE_DRIVER=local');putenv('SOMI_STORAGE_ROOT=');
    $worker=new Person3Worker($db);for($i=0;$i<100&&$worker->consumeOne();$i++){}
    check((int)value($db,"SELECT count(*) FROM notifications WHERE notification_type='OrderDelivered' AND user_id=2")===1,'real delivery event notifies customer once');
    check((int)value($db,"SELECT count(*) FROM notifications WHERE notification_type='OrderReturned' AND user_id=3")===1,'real return event notifies shipper once');
    check((int)value($db,"SELECT count(*) FROM notifications WHERE notification_type='OrderDelivering' AND user_id=2")===2,'delivery-start events notify customer for both real deliveries');
    check(!$worker->consumeOne(),'event replay exhausted without duplicate notifications');
    $db->exec("INSERT INTO shipper_earning_policies(policy_type,currency_code,percentage_rate,effective_from) VALUES('SUCCESSFUL_DELIVERY_PERCENT','VND',80,now()-interval '1 day')");
    $earning=tx($db,fn()=>(new ShipperFinanceService($db))->accrue($order));
    check($earning!==null,'completed paid delivery accrues earning through actual assignment');
    // Complete the same earning -> queued encrypted OTP -> verified bank -> manual reconciliation.
    $db->prepare('UPDATE users SET password_hash=? WHERE user_id=3')->execute([password_hash('FixturePassword!234',PASSWORD_DEFAULT)]);
    $bank=new ShipperBankService($db);$finance=new ShipperFinanceService($db);
    check(tx($db,fn()=>$finance->reserve((int)$earning['earning_id']))===null,'payout waits for OTP-verified bank');
    $request=tx($db,fn()=>$bank->request(3,['bank_code'=>'TEST','bank_name'=>'Fixture bank','account_holder'=>'Fixture Shipper','account_number'=>'000009876543','current_password'=>'FixturePassword!234','client_key'=>'delivery-bank-fixture']));
    $encrypted=value($db,'SELECT encrypted_payload FROM email_messages WHERE deduplication_key=?',['bank-change:'.$request['request_id']]);
    $mail=EncryptedPayload::open($encrypted);preg_match('/: ([0-9]{6})/',$mail['text'],$match);
    $confirmed=tx($db,fn()=>$bank->confirm(3,['request_id'=>$request['request_id'],'code'=>$match[1]]));check($confirmed['confirmed'],'OTP confirms bank for delivery earning');
    $db->exec("UPDATE shipper_bank_accounts SET review_status='APPROVED' WHERE shipper_id=3");
    $payout=tx($db,fn()=>$finance->reserve((int)$earning['earning_id']));check($payout['status']==='PROCESSING','manual payout awaits evidence, no bank transfer API');
    $proof=['payout_id'=>$payout['payout_id'],'provider_payout_ref'=>'FIXTURE-TRANSFER-NOT-REAL','evidence_reference'=>'FIXTURE-STATEMENT-NOT-REAL','amount_minor'=>(string)$earning['gross_amount_minor'],'currency_code'=>'VND'];
    check(tx($db,fn()=>$finance->reconcile(1,$proof))['status']==='SUCCEEDED','manual evidence reconciliation marks completed payout');
    tx($db,fn()=>$finance->reconcile(1,$proof));check((int)value($db,'SELECT count(*) FROM shipper_payouts WHERE earning_id=?',[$earning['earning_id']])===1,'reconciliation replay never duplicates payout');
    $db->prepare("UPDATE private_files SET delete_after=now()-interval '1 second' WHERE order_id=? AND purpose='EVIDENCE'")->execute([$order]);
    $worker->retain();
    check((bool)value($db,"SELECT bool_and(f.delete_after>=o.closed_at+interval '90 days') FROM private_files f JOIN orders o USING(order_id) WHERE f.purpose='EVIDENCE' AND o.closed_at IS NOT NULL"),'old seven-day storage deadlines extended to 90 days after closing');
    echo "Shipper delivery checks passed: $checks\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
