<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperProfileService.php';

// GET: xem hồ sơ, phương tiện, trạng thái duyệt và điều kiện hoạt động.
// PUT {vehicle_type, vehicle_registration}: đổi phương tiện (hồ sơ đã duyệt sẽ chờ duyệt lại).
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'PUT'], $method === 'PUT' ? 'profile.update_own' : 'profile.read_own', $method === 'PUT',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new ShipperProfileService($db);
        if ($method === 'GET') return [200, $service->detail($shipperId)];
        $input = shipper_body(['vehicle_type', 'vehicle_registration']);
        return [200, $service->updateVehicle($shipperId, $input['vehicle_type'] ?? null, $input['vehicle_registration'] ?? null)];
    });
