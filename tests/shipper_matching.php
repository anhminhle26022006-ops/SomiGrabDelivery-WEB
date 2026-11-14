<?php
declare(strict_types=1);
// Kiểm thử hồ sơ/KYC, availability, matching, offer và nhận đơn. Chạy: php tests/shipper_matching.php
require_once __DIR__ . '/shipper_fixtures.php';
require_once __DIR__ . '/../app/Services/ShipperKycService.php';
require_once __DIR__ . '/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__ . '/../app/Services/ShipperEventConsumer.php';
require_once __DIR__ . '/../app/Services/OfferService.php';
require_once __DIR__ . '/../app/Services/OfferAcceptService.php';
require_once __DIR__ . '/../app/Services/CustomerOrderService.php';

try {
    $db = shipper_test_db();
    $profiles = new ShipperProfileService($db); $kyc = new ShipperKycService($db); $availability = new ShipperAvailabilityService($db);
    $matching = new MatchingService($db); $offers = new OfferService($db); $accept = new OfferAcceptService($db);

    // --- Hồ sơ và KYC ---
    $detail = $profiles->detail(SHIPPER_A);
    check($detail['eligibility']['can_work'] && $detail['profile']['vehicle_registration'] === '59X1-00003', 'approved shipper can work');
    check(!array_key_exists('identity_number_ciphertext', $detail['profile']), 'profile never exposes identity number');
    check($detail['account']['code'] === 'SH-00003' && $detail['account']['email'] === 'user3@example.invalid' && $detail['account']['phone'] === '0900000003', 'profile returns the shipper own contact details');
    check(!array_key_exists('password_hash', $detail['account']), 'account block never exposes the password hash');
    check($profiles->detail(SHIPPER_PENDING)['eligibility']['blockers'] === ['KYC_NOT_APPROVED', 'IDENTITY_NOT_APPROVED'], 'blockers explain why a shipper cannot work');
    rejects(fn() => $profiles->detail(CUSTOMER), 'SHIPPER_PROFILE_REQUIRED');
    rejects(fn() => tx($db, fn() => $profiles->updateVehicle(SHIPPER_B, 'Xe máy', ' 59x1-00003 ')), 'VEHICLE_REGISTRATION_TAKEN');
    rejects(fn() => tx($db, fn() => $kyc->resubmit(SHIPPER_A, null, [])), 'KYC_NOT_RESUBMITTABLE');
    rejects(fn() => tx($db, fn() => $kyc->resubmit(SHIPPER_PENDING, null, [])), 'VALIDATION_ERROR');
    $document = ['document_type' => 'IDENTITY_FRONT', 'reference_file_id' => storage_file($db,SHIPPER_PENDING,'KYC')];
    rejects(fn() => tx($db, fn() => $kyc->resubmit(SHIPPER_PENDING, null, [array_replace($document, ['reference_file_id' => 'https://evil.example/../x.jpg'])])), 'VALIDATION_ERROR');
    $status = tx($db, fn() => $kyc->resubmit(SHIPPER_PENDING, ['document_type' => 'NATIONAL_ID', 'document_country_code' => 'vn', 'identity_number' => '079200001234'], [$document]));
    check($status['verification_status'] === 'PENDING' && $status['identity_review_status'] === 'PENDING' && $status['rejection_reason'] === null, 'resubmission returns to PENDING review');
    check(count($status['documents']) === 1 && !$status['can_resubmit'], 'document reference stored once');
    check(!str_contains((string) value($db, 'SELECT identity_number_ciphertext FROM user_identity_documents WHERE user_id = 5'), '079200001234'), 'identity number encrypted at rest');
    check(!str_contains((string) value($db, "SELECT new_value FROM audit_logs WHERE action = 'SHIPPER_KYC_RESUBMITTED'"), '0792'), 'audit has no identity number');

    // --- Bật/tắt nhận đơn ---
    rejects(fn() => tx($db, fn() => $availability->turnOn(SHIPPER_PENDING, NEAR[0], NEAR[1])), 'SHIPPER_NOT_ELIGIBLE');
    rejects(fn() => tx($db, fn() => $availability->turnOn(SHIPPER_A, 91, 0)), 'VALIDATION_ERROR');
    rejects(fn() => tx($db, fn() => $availability->turnOn(SHIPPER_A, null, null)), 'VALIDATION_ERROR');
    check(tx($db, fn() => $availability->turnOn(SHIPPER_A, NEAR[0], NEAR[1]))['availability'] === true, 'eligible shipper goes online');
    tx($db, fn() => $availability->turnOn(SHIPPER_B, FARTHER[0], FARTHER[1]));
    $db->exec("UPDATE users SET account_status = 'SUSPENDED' WHERE user_id = 4");
    rejects(fn() => tx($db, fn() => $availability->turnOn(SHIPPER_B, FARTHER[0], FARTHER[1])), 'SHIPPER_NOT_ELIGIBLE');
    $db->exec("UPDATE users SET account_status = 'ACTIVE' WHERE user_id = 4");

    // --- Consumer và vòng offer đầu tiên ---
    $order = paid_order($db);
    check(ShipperEventConsumer::consumeOne($db) && !ShipperEventConsumer::consumeOne($db), 'OrderReadyForMatching consumed exactly once');
    check((int) value($db, "SELECT count(*) FROM event_consumer_receipts WHERE consumer = 'shipper-matching'") === 1, 'own consumer receipt');
    check((int) value($db, "SELECT count(*) FROM order_match_offers WHERE order_id = ? AND status = 'PENDING'", [$order]) === 2, 'both nearby shippers get an offer');
    check((float) value($db, 'SELECT distance_to_pickup_km FROM order_match_offers WHERE order_id = ? AND shipper_id = 3', [$order]) < (float) value($db, 'SELECT distance_to_pickup_km FROM order_match_offers WHERE order_id = ? AND shipper_id = 4', [$order]), 'distance to pickup computed per shipper');
    check(value($db,'SELECT expires_at IS NULL FROM order_match_offers WHERE order_id=? LIMIT 1',[$order])===true,'offer has no deadline');
    check(tx($db, fn() => $matching->dispatch($order))['result'] === 'WAITING', 'no duplicate round while offers are pending');
    $list = $offers->pending(SHIPPER_A)['items'];
    check(count($list) === 1 && $list[0]['seconds_left'] === null && !isset($list[0]['receiver_phone']) && !isset($list[0]['sender_phone']), 'offer list hides contact details before acceptance');
    check($list[0]['estimated_earning_minor'] === null, 'no earning estimate without an Admin policy');
    $db->exec("INSERT INTO shipper_earning_policies (policy_type, currency_code, percentage_rate, effective_from) VALUES ('SUCCESSFUL_DELIVERY_PERCENT', 'VND', 80, now() - interval '1 day')");
    check($offers->pending(SHIPPER_A)['items'][0]['estimated_earning_minor'] === '24000', 'earning estimate reads the active policy');
    $offerA = (int) $list[0]['offer_id']; $offerB = (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ? AND shipper_id = 4', [$order]);
    rejects(fn() => tx($db, fn() => $accept->accept(SHIPPER_B, $offerA)), 'OFFER_NOT_FOUND');

    // --- Hai Shipper cùng nhận một đơn ---
    $won = tx($db, fn() => $accept->accept(SHIPPER_A, $offerA));
    check($won['created'] && value($db, 'SELECT status FROM orders WHERE order_id = ?', [$order]) === 'SHIPPER_ASSIGNED', 'first shipper wins the order');
    rejects(fn() => tx($db, fn() => $accept->accept(SHIPPER_B, $offerB)), 'OFFER_LOST_RACE');
    check(tx($db, fn() => $accept->accept(SHIPPER_A, $offerA))['created'] === false, 'accept replay returns the same assignment');
    check((int) value($db, "SELECT count(*) FROM order_assignments WHERE order_id = ? AND assignment_status = 'ACTIVE'", [$order]) === 1, 'exactly one active assignment');
    check((int) value($db, 'SELECT count(*) FROM deliveries WHERE order_id = ?', [$order]) === 1, 'delivery record created with the assignment');
    check((int) value($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'ShipperAssigned'", [$order]) === 1, 'ShipperAssigned published once');
    try { $db->exec("INSERT INTO order_assignments (order_id, shipper_id) VALUES ($order, 4)"); check(false, 'database must reject a second assignment'); }
    catch (PDOException $e) { check($e->getCode() === '23505', 'unique index blocks a second active assignment'); }

    // --- Shipper đang bận không nhận thêm offer; đơn mới chỉ gửi cho người rảnh ---
    $second = paid_order($db);
    tx($db, fn() => $matching->dispatch($second));
    check(value($db, 'SELECT string_agg(shipper_id::text, \',\') FROM order_match_offers WHERE order_id = ?', [$second]) === '4', 'busy shipper is skipped');
    $offer2 = (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ?', [$second]);

    // --- Nhận đơn đua với hủy đơn: hủy xong thì không nhận được nữa ---
    tx($db, fn() => (new CustomerOrderService($db))->cancel(CUSTOMER, $second, 'Đổi ý'));
    rejects(fn() => tx($db, fn() => $accept->accept(SHIPPER_B, $offer2)), 'OFFER_NOT_PENDING');
    check((int) value($db, 'SELECT count(*) FROM order_assignments WHERE order_id = ?', [$second]) === 0, 'cancelled order never gets an assignment');

    // No 60-second invitation cutoff, but the customer search still ends after five minutes.
    $third=paid_order($db);tx($db,fn()=>$matching->dispatch($third));
    $offer3=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=?',[$third]);
    $db->exec("UPDATE order_status_history SET created_at=now()-interval '2 minutes' WHERE order_id=$third");
    $db->exec("UPDATE order_match_offers SET sent_at=now()-interval '2 minutes' WHERE offer_id=$offer3");
    check($matching->expireOffers()===0&&count($offers->pending(SHIPPER_B)['items'])===1,'invitation remains visible after 60 seconds');
    $db->exec("UPDATE order_status_history SET created_at=now()-interval '6 minutes' WHERE order_id=$third");
    check(count($offers->pending(SHIPPER_B)['items'])===0,'order is unavailable after five-minute search deadline');
    rejects(fn()=>tx($db,fn()=>$accept->accept(SHIPPER_B,$offer3)),'ORDER_SEARCH_EXPIRED');
    check(ShipperEventConsumer::sweep($db)['dispatched'][$third]==='NO_SHIPPER_FOUND','worker closes five-minute search');
    check(value($db,'SELECT status FROM order_match_offers WHERE offer_id=?',[$offer3])==='CANCELLED','parent timeout cancels remaining invitations');
    check(str_contains(value($db,"SELECT note FROM order_status_history WHERE order_id=? AND new_status='NO_SHIPPER_FOUND'",[$third]),'5 phút'),'timeout reason is recorded');

    // --- Từ chối offer và tắt nhận đơn ---
    $fourth = paid_order($db);
    tx($db, fn() => $matching->dispatch($fourth));
    $offer4 = (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ?', [$fourth]);
    check(tx($db, fn() => $offers->reject(SHIPPER_B, $offer4, 'Xa quá'))['status'] === 'REJECTED', 'shipper rejects an offer');
    check((int) value($db, 'SELECT count(*) FROM order_match_offers WHERE order_id = ?', [$fourth]) === 1, 'rejected shipper is not offered the same order again');
    rejects(fn() => tx($db, fn() => $accept->accept(SHIPPER_B, $offer4)), 'OFFER_NOT_PENDING');
    $fifth = paid_order($db);
    tx($db, fn() => $matching->dispatch($fifth));
    check(tx($db, fn() => $availability->turnOff(SHIPPER_B))['availability'] === false, 'shipper goes offline');
    check(value($db, 'SELECT status FROM order_match_offers WHERE order_id = ?', [$fifth]) === 'CANCELLED', 'going offline releases pending offers');
    rejects(fn() => tx($db, fn() => $accept->accept(SHIPPER_B, (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ?', [$fifth]))), 'OFFER_NOT_PENDING');

    // --- Đổi phương tiện khi đã duyệt thì phải duyệt lại ---
    rejects(fn() => tx($db, fn() => $profiles->updateVehicle(SHIPPER_A, 'Xe máy', '59X1-99999')), 'ACTIVE_DELIVERY_EXISTS');
    $changed = tx($db, fn() => $profiles->updateVehicle(SHIPPER_B, 'Xe máy điện', '59X1-88888'));
    check($changed['profile']['verification_status'] === 'PENDING' && !$changed['eligibility']['can_work'], 'vehicle change requires a new review');

    echo "Shipper matching checks passed: $checks\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
