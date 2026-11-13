<?php
declare(strict_types=1);
// Kiểm thử số liệu trang Tổng quan của Shipper. Chạy: php tests/shipper_dashboard.php
// Dùng TEMP TABLE như các bài kiểm thử Shipper khác nên không ghi vào dữ liệu thật.
require_once __DIR__ . '/shipper_fixtures.php';
require_once __DIR__ . '/../app/Services/ShipperDashboardService.php';

/** Tạo một đơn đã kết thúc của Shipper tại thời điểm cho trước (giờ Việt Nam). */
function finished_order(PDO $db, int $shipper, string $endedAt, string $assignmentStatus = 'COMPLETED', string $distance = '3.2'): int
{
    $order = paid_order($db);
    $db->prepare('UPDATE orders SET status = ?, distance_km = ? WHERE order_id = ?')->execute([$assignmentStatus === 'COMPLETED' ? 'DELIVERED' : 'RETURNED', $distance, $order]);
    $db->prepare("INSERT INTO order_assignments (order_id, shipper_id, assignment_status, assigned_at, ended_at, ended_reason)
                  VALUES (?, ?, ?, CAST(? AS timestamptz) - interval '30 minutes', CAST(? AS timestamptz), 'TEST')")
       ->execute([$order, $shipper, $assignmentStatus, $endedAt, $endedAt]);
    return $order;
}

/** Tạo một offer đã có kết quả, gửi tại thời điểm cho trước. */
function decided_offer(PDO $db, int $shipper, string $sentAt, string $status): void
{
    $order = paid_order($db);
    $db->prepare("INSERT INTO order_match_offers (order_id, shipper_id, status, sent_at, expires_at, responded_at)
                  VALUES (?, ?, ?, CAST(? AS timestamptz), CAST(? AS timestamptz) + interval '60 seconds', CAST(? AS timestamptz))")
       ->execute([$order, $shipper, $status, $sentAt, $sentAt, $sentAt]);
}

try {
    $db = shipper_test_db();
    $service = new ShipperDashboardService($db);
    // "Bây giờ" cố định: thứ Tư 11/11/2026, 10:00 giờ Việt Nam.
    $now = new DateTimeImmutable('2026-11-11T10:00:00+07:00');

    // --- Ranh giới kỳ theo giờ Việt Nam ---
    [$start, $end, $previous] = ShipperDashboardService::bounds('today', $now);
    check($start->format(DATE_ATOM) === '2026-11-11T00:00:00+07:00' && $end->format(DATE_ATOM) === '2026-11-12T00:00:00+07:00', 'today runs midnight to midnight in Vietnam time');
    check($previous->format('Y-m-d') === '2026-11-10', 'today compares with yesterday');
    [$start, $end, $previous] = ShipperDashboardService::bounds('week', $now);
    check($start->format('Y-m-d D') === '2026-11-09 Mon' && $end->format('Y-m-d') === '2026-11-16' && $previous->format('Y-m-d') === '2026-11-02', 'week starts on Monday');
    [$start, $end, $previous] = ShipperDashboardService::bounds('month', $now);
    check($start->format('Y-m-d') === '2026-11-01' && $end->format('Y-m-d') === '2026-12-01' && $previous->format('Y-m-d') === '2026-10-01', 'month covers the calendar month');
    // 01:00 sáng giờ Việt Nam vẫn là ngày hôm trước theo UTC: phải tính theo ngày Việt Nam.
    [$start] = ShipperDashboardService::bounds('today', new DateTimeImmutable('2026-11-10T18:30:00+00:00'));
    check($start->format('Y-m-d') === '2026-11-11', 'late UTC evening already counts as the next day in Vietnam');
    [$start, , $previous] = ShipperDashboardService::bounds('month', new DateTimeImmutable('2026-03-31T12:00:00+07:00'));
    check($start->format('Y-m-d') === '2026-03-01' && $previous->format('Y-m-d') === '2026-02-01', 'previous month is correct on the 31st');

    // --- Chưa có dữ liệu ---
    $empty = $service->summary(SHIPPER_A, 'today', $now);
    check($empty['current']['deliveries'] === 0 && $empty['current']['acceptance_rate_percent'] === null, 'no activity gives zero deliveries and no acceptance rate');
    check($empty['deliveries_change_percent'] === null && $empty['rating']['average'] === null && $empty['rating']['count'] === 0, 'no baseline and no ratings are reported as null');
    rejects(fn() => $service->summary(SHIPPER_A, 'year', $now), 'VALIDATION_ERROR');
    rejects(fn() => $service->summary(CUSTOMER, 'today', $now), 'SHIPPER_PROFILE_REQUIRED');

    // --- Dữ liệu mẫu của Shipper A ---
    finished_order($db, SHIPPER_A, '2026-11-11T08:15:00+07:00', 'COMPLETED', '3.2');
    finished_order($db, SHIPPER_A, '2026-11-11T09:40:00+07:00', 'COMPLETED', '4.5');
    finished_order($db, SHIPPER_A, '2026-11-11T00:00:00+07:00', 'COMPLETED', '1.0');   // đúng 00:00 thuộc về hôm nay
    finished_order($db, SHIPPER_A, '2026-11-11T09:00:00+07:00', 'RETURNED', '9.9');    // hoàn hàng không tính là giao thành công
    finished_order($db, SHIPPER_A, '2026-11-10T23:59:59+07:00', 'COMPLETED', '2.0');   // hôm qua
    finished_order($db, SHIPPER_A, '2026-11-10T14:00:00+07:00', 'COMPLETED', '2.0');   // hôm qua
    finished_order($db, SHIPPER_A, '2026-11-08T14:00:00+07:00', 'COMPLETED', '5.0');   // Chủ nhật tuần trước
    finished_order($db, SHIPPER_A, '2026-10-20T14:00:00+07:00', 'COMPLETED', '5.0');   // tháng trước
    finished_order($db, SHIPPER_B, '2026-11-11T09:00:00+07:00', 'COMPLETED', '7.0');   // của Shipper khác
    foreach (['ACCEPTED', 'ACCEPTED', 'ACCEPTED', 'REJECTED', 'EXPIRED', 'PENDING'] as $status) decided_offer($db, SHIPPER_A, '2026-11-11T07:00:00+07:00', $status);
    decided_offer($db, SHIPPER_A, '2026-11-10T07:00:00+07:00', 'LOST_RACE');
    $db->exec('UPDATE shipper_profiles SET customer_rating_average = 4.75, customer_rating_count = 12, reliability_score = 96 WHERE user_id = 3');

    // --- Hôm nay ---
    $today = $service->summary(SHIPPER_A, 'today', $now);
    check($today['current']['deliveries'] === 3 && $today['current']['returns'] === 1, 'today counts completed and returned orders separately');
    check($today['current']['distance_km'] === '8.7', 'distance only sums completed deliveries');
    check($today['previous']['deliveries'] === 2 && $today['deliveries_change_percent'] === 50, 'change is measured against yesterday');
    check($today['current']['offers_decided'] === 5 && $today['current']['offers_accepted'] === 3 && $today['current']['acceptance_rate_percent'] === 60, 'pending offers are left out of the acceptance rate');
    check($today['rating'] === ['average' => '4.8', 'count' => 12] && $today['reliability_score'] === '96', 'rating and reliability come from the profile');

    // --- Hôm qua, tuần, tháng ---
    $yesterday = $service->summary(SHIPPER_A, 'yesterday', $now);
    check($yesterday['current']['deliveries'] === 2 && $yesterday['previous']['deliveries'] === 0 && $yesterday['deliveries_change_percent'] === null, 'yesterday has no baseline the day before');
    check($yesterday['current']['acceptance_rate_percent'] === 0, 'a lost offer counts as decided but not accepted');
    $week = $service->summary(SHIPPER_A, 'week', $now);
    check($week['current']['deliveries'] === 5 && $week['previous']['deliveries'] === 1 && $week['deliveries_change_percent'] === 400, 'week runs Monday to Sunday');
    $month = $service->summary(SHIPPER_A, 'month', $now);
    check($month['current']['deliveries'] === 6 && $month['previous']['deliveries'] === 1, 'month includes every delivery since the 1st');

    // --- Tách biệt giữa các Shipper ---
    $other = $service->summary(SHIPPER_B, 'today', $now);
    check($other['current']['deliveries'] === 1 && $other['current']['distance_km'] === '7.0' && $other['current']['offers_decided'] === 0, 'each shipper only sees their own numbers');

    echo "Shipper dashboard checks passed: $checks\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
