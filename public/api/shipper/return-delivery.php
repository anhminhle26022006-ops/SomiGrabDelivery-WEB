<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/DeliveryReturnService.php';

// POST {order_id, action: "FAIL", reason_code, note?}: báo giao thất bại, bắt đầu hoàn hàng.
// POST {order_id, action: "RETURNED"}: báo đã trả hàng cho người gửi (cần ảnh hoàn hàng).
shipper_run(['POST'], 'delivery.update_assigned', true, static function (PDO $db, int $shipperId): array {
    $input = shipper_body(['order_id', 'action', 'reason_code', 'note']);
    $orderId = DeliveryInput::id($input['order_id'] ?? null, 'order_id');
    $service = new DeliveryReturnService($db);
    if (ShipperInput::choice($input['action'] ?? null, 'action', ['FAIL', 'RETURNED']) === 'RETURNED') {
        return [200, $service->shipperReturned($shipperId, $orderId)];
    }
    $reason = ShipperInput::choice($input['reason_code'] ?? null, 'reason_code', DeliveryReturnService::FAILURE_REASONS);
    return [200, $service->fail($shipperId, $orderId, $reason, DeliveryInput::text($input['note'] ?? null, 'note', 500, false))];
});
