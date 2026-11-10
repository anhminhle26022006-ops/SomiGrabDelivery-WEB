<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperLocationService.php';

// POST {latitude, longitude, accuracy_meters?}: gửi vị trí trong lúc đang giao đơn.
// GET ?order_id=: đọc lại vị trí mới nhất của chính mình trên đơn đang giao.
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'POST'], 'delivery.update_assigned', $method === 'POST',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new ShipperLocationService($db);
        if ($method === 'GET') return [200, $service->latest($shipperId, 'SHIPPER', DeliveryInput::id($_GET['order_id'] ?? null, 'order_id'))];
        $input = shipper_body(['latitude', 'longitude', 'accuracy_meters']);
        return [201, $service->record($shipperId, $input['latitude'] ?? null, $input['longitude'] ?? null, $input['accuracy_meters'] ?? null)];
    });
