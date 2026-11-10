<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/DeliveryCompletionService.php';

// POST {order_id}: xác nhận giao thành công. 409 EVIDENCE_REQUIRED nếu chưa có ảnh giao hàng.
shipper_run(['POST'], 'delivery.update_assigned', true, static function (PDO $db, int $shipperId): array {
    $input = shipper_body(['order_id']);
    return [200, (new DeliveryCompletionService($db))->delivered($shipperId, DeliveryInput::id($input['order_id'] ?? null, 'order_id'))];
});
