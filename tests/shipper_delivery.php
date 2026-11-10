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

/** Tạo đơn đã thanh toán và cho Shipper A nhận, trả về order_id. */
function assigned_order(PDO $db): int
{
    $order = paid_order($db);
    tx($db, fn() => (new MatchingService($db))->dispatch($order));
    $offer = (int) value($db, 'SELECT offer_id FROM order_match_offers WHERE order_id = ? AND shipper_id = 3', [$order]);
    tx($db, fn() => (new OfferAcceptService($db))->accept(SHIPPER_A, $offer));
    return $order;
}
function photo(string $name): array { return ['pending-upload/evidence/' . $name . '.jpg', 'image/jpeg', 512000, hash('sha256', $name)]; }

try {
    $db = shipper_test_db();
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
    rejects(fn() => tx($db, fn() => $completion->delivered(SHIPPER_A, $order)), 'EVIDENCE_REQUIRED');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'RETURN_PHOTO', ...photo('wrong'))), 'INVALID_ORDER_STATE');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', '../../etc/passwd', 'image/jpeg', 10, null)), 'VALIDATION_ERROR');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', 'pending-upload/evidence/x.exe', 'application/x-msdownload', 10, null)), 'VALIDATION_ERROR');
    rejects(fn() => tx($db, fn() => $completion->addEvidence(SHIPPER_B, $order, 'DELIVERY_PHOTO', ...photo('foreign'))), 'ORDER_NOT_FOUND');
    $after = tx($db, fn() => $completion->addEvidence(SHIPPER_A, $order, 'DELIVERY_PHOTO', ...photo('door')));
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

    echo "Shipper delivery checks passed: $checks\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
