<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperDashboardService.php';

shipper_run(['GET'], 'delivery.update_assigned', false,
    static function (PDO $db, int $shipperId): array {
        $range = ShipperInput::choice($_GET['range'] ?? 'today', 'range', ['today', 'yesterday', 'week', 'month']);
        return [200, (new ShipperDashboardService($db))->summary($shipperId, $range)];
    });
