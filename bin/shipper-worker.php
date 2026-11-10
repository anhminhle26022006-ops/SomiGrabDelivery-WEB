<?php
declare(strict_types=1);
// Worker ghép đơn của Người 2. Chỉ chạy bằng CLI, không mở qua HTTP.
// Lên lịch mỗi phút: php bin/shipper-worker.php --seconds=55
// Trong thời gian đó worker lặp vài giây một lần để offer 60 giây được xử lý kịp.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/ShipperEventConsumer.php';

$seconds = 0;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--seconds=([0-9]{1,3})$/D', $argument, $m)) $seconds = min(300, (int) $m[1]);
    else { fwrite(STDERR, "Usage: php bin/shipper-worker.php [--seconds=55]\n"); exit(2); }
}

$db = somi_db();
$deadline = time() + $seconds;
$totals = ['events' => 0, 'expired' => 0, 'dispatched' => 0];
do {
    // 1) Đơn vừa thanh toán xong (OrderReadyForMatching) được gửi vòng offer đầu tiên.
    for ($i = 0; $i < 50 && ShipperEventConsumer::consumeOne($db); $i++) $totals['events']++;
    // 2) Offer hết hạn được đóng và đơn được gửi vòng kế tiếp hoặc báo không có Shipper.
    $sweep = ShipperEventConsumer::sweep($db);
    $totals['expired'] += $sweep['expired'];
    $totals['dispatched'] += count($sweep['dispatched']);
    if (time() >= $deadline) break;
    sleep(5);
} while (true);

echo json_encode($totals) . "\n";
