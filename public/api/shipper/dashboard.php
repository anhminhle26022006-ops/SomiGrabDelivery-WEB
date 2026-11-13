<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperDashboardService.php';

// GET ?range=today|yesterday|week|month: số liệu trang Tổng quan của Shipper đang đăng nhập.
// Thiếu range thì mặc định là hôm nay. Chỉ đọc dữ liệu, không ghi gì.
shipper_run(['GET'], 'profile.read_own', false, static function (PDO $db, int $shipperId): array {
    $range = ShipperInput::choice($_GET['range'] ?? 'today', 'range', ShipperDashboardService::RANGES);
    return [200, (new ShipperDashboardService($db))->summary($shipperId, $range)];
});
