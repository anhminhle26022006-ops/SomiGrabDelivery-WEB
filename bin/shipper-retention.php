<?php
declare(strict_types=1);
// Dọn dữ liệu GPS quá hạn lưu. Chỉ chạy bằng CLI, lên lịch mỗi ngày một lần:
//   php bin/shipper-retention.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/ShipperLocationService.php';

$service = new ShipperLocationService(somi_db());
$deleted = 0;
// Xóa theo từng lô nhỏ, tối đa 100 lô mỗi lần chạy để một lần dọn không chiếm database quá lâu.
for ($i = 0; $i < 100; $i++) {
    $count = $service->purgeExpired();
    $deleted += $count;
    if ($count === 0) break;
}
echo json_encode(['deleted_locations' => $deleted]) . "\n";
