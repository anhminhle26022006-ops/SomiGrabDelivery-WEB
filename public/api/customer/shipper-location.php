<?php
declare(strict_types=1);
require_once __DIR__ . '/../_kernel.php';
require_once __DIR__ . '/../../../app/Services/DeliveryInput.php';
require_once __DIR__ . '/../../../app/Services/ShipperLocationService.php';

// GET ?order_id=: Customer theo dõi vị trí Shipper của đơn mình. location = null khi chưa có điểm
// hoặc đơn đã kết thúc. Màn hình tracking của Customer gọi định kỳ endpoint này.
somi_run_api(['GET'], ['CUSTOMER'], 'order.read_own', false, static function (PDO $db, int $customerId): array {
    return [200, (new ShipperLocationService($db))->latest($customerId, 'CUSTOMER', DeliveryInput::id($_GET['order_id'] ?? null, 'order_id'))];
});
