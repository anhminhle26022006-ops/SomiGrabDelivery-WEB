<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

final class ShipperDashboardService
{
    public function __construct(private PDO $db) {}

    public static function period(string $range, ?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        $today = $now->setTime(0, 0);
        [$start, $end] = match ($range) {
            'today' => [$today, $today->modify('+1 day')],
            'yesterday' => [$today->modify('-1 day'), $today],
            'week' => [$today->modify('monday this week'), $today->modify('monday next week')],
            'month' => [$today->modify('first day of this month'), $today->modify('first day of next month')],
            default => throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'Invalid dashboard range.'),
        };
        return [$start, $end];
    }

    public function summary(int $shipperId, string $range): array
    {
        [$start, $end] = self::period($range);
        // Count every completed assignment, without the history endpoint's 50-row limit.
        // Duration measures assigned delivery time overlapping the selected period.
        $q = $this->db->prepare("SELECT
            COUNT(*) FILTER (WHERE a.assignment_status = 'COMPLETED'
                AND d.delivered_at >= CAST(? AS timestamptz) AND d.delivered_at < CAST(? AS timestamptz)) AS completed,
            COALESCE(SUM(GREATEST(0, EXTRACT(EPOCH FROM (
                LEAST(COALESCE(a.ended_at, CURRENT_TIMESTAMP), CAST(? AS timestamptz), CURRENT_TIMESTAMP)
                - GREATEST(a.assigned_at, CAST(? AS timestamptz))
            )))), 0) AS delivery_seconds
            FROM order_assignments a LEFT JOIN deliveries d USING (assignment_id)
            WHERE a.shipper_id = ?");
        $q->execute([$start->format(DATE_ATOM), $end->format(DATE_ATOM),
            $end->format(DATE_ATOM), $start->format(DATE_ATOM), $shipperId]);
        $row = $q->fetch();
        return ['range' => $range, 'timezone' => 'Asia/Ho_Chi_Minh',
            'start' => $start->format(DATE_ATOM), 'end' => $end->format(DATE_ATOM),
            'completed_deliveries' => (int) $row['completed'],
            'delivery_seconds' => (int) $row['delivery_seconds']];
    }
}
