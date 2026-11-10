<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperAvailabilityService.php';

// GET: trạng thái nhận đơn và lý do nếu chưa đủ điều kiện.
// POST {available: true, latitude, longitude} bật nhận đơn / cập nhật vị trí.
// POST {available: false} tắt nhận đơn.
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'POST'], $method === 'POST' ? 'offer.respond_own' : 'offer.read_own', $method === 'POST',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new ShipperAvailabilityService($db);
        if ($method === 'GET') return [200, $service->status($shipperId)];
        $input = shipper_body(['available', 'latitude', 'longitude']);
        if (ShipperInput::boolean($input['available'] ?? null, 'available')) {
            return [200, $service->turnOn($shipperId, $input['latitude'] ?? null, $input['longitude'] ?? null)];
        }
        return [200, $service->turnOff($shipperId)];
    });
