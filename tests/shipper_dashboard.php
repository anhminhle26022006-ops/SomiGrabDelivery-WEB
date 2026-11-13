<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Services/ShipperDashboardService.php';
require_once __DIR__ . '/../app/Config/database.php';
function dashboard_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
$now = new DateTimeImmutable('2026-11-12T18:30:00Z');
foreach (['today' => ['2026-11-13', '2026-11-14'], 'yesterday' => ['2026-11-12', '2026-11-13'],
    'week' => ['2026-11-09', '2026-11-16'], 'month' => ['2026-11-01', '2026-12-01']] as $range => [$a, $b]) {
    [$start, $end] = ShipperDashboardService::period($range, $now);
    dashboard_check($start->format('Y-m-d') === $a && $end->format('Y-m-d') === $b, $range . ' uses Vietnam dates');
}
[$start, $end] = ShipperDashboardService::period('month', new DateTimeImmutable('2028-02-29T12:00:00+07:00'));
dashboard_check($end->format('Y-m-d') === '2028-03-01', 'leap month boundary');
try { ShipperDashboardService::period('invalid'); throw new RuntimeException('Invalid range accepted'); }
catch (DeliveryApiError $e) { dashboard_check($e->httpStatus === 422 && $e->errorCode === 'VALIDATION_ERROR', 'range rejected'); }
echo "PASS: dashboard date boundaries and invalid range.\n";

// Only temporary tables are used; application records are never changed.
$db = somi_db();
$db->exec('CREATE TEMP TABLE order_assignments (assignment_id bigint, shipper_id bigint, assignment_status text, assigned_at timestamptz, ended_at timestamptz)');
$db->exec('CREATE TEMP TABLE deliveries (assignment_id bigint, delivered_at timestamptz)');
$db->exec('SET search_path TO pg_temp');
[$start, $end] = ShipperDashboardService::period('yesterday');
$at = $start->modify('+1 hour');
$q = $db->prepare("INSERT INTO order_assignments VALUES (?, ?, ?, CAST(? AS timestamptz), CAST(? AS timestamptz))");
$d = $db->prepare('INSERT INTO deliveries VALUES (?, CAST(? AS timestamptz))');
for ($i = 1; $i <= 61; $i++) {
    $q->execute([$i, $i === 61 ? 4 : 3, 'COMPLETED', $at->format(DATE_ATOM), $at->modify('+1 hour')->format(DATE_ATOM)]);
    $d->execute([$i, $at->modify('+1 hour')->format(DATE_ATOM)]);
}
// Active assignment crossing both boundaries contributes exactly one day.
$q->execute([62, 3, 'ACTIVE', $start->modify('-1 hour')->format(DATE_ATOM), null]);
$result = (new ShipperDashboardService($db))->summary(3, 'yesterday');
dashboard_check($result['completed_deliveries'] === 60, 'all completions counted and other shipper excluded');
dashboard_check($result['delivery_seconds'] === 60 * 3600 + 86400, 'duration clipped to selected period');
dashboard_check((new ShipperDashboardService($db))->summary(99, 'yesterday')['completed_deliveries'] === 0, 'empty shipper');
echo "PASS: dashboard PostgreSQL aggregation, owner isolation, >50 deliveries, duration clipping and empty state.\n";
