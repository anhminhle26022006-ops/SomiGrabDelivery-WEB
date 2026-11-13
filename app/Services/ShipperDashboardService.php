<?php
declare(strict_types=1);
require_once __DIR__ . '/ShipperEligibility.php';

/**
 * Số liệu trang Tổng quan của Shipper, tính từ dữ liệu thật trong database.
 * Thay cho các con số minh họa viết cứng trong giao diện (5 đơn, 4h 30m, 4.9 sao).
 *
 * Mỗi kỳ (hôm nay, hôm qua, tuần này, tháng này) được so với kỳ liền trước cùng loại.
 * Ranh giới ngày tính theo giờ Việt Nam vì Shipper làm việc theo ngày ở Việt Nam,
 * trong khi database lưu thời gian theo UTC.
 */
final class ShipperDashboardService
{
    public const RANGES = ['today', 'yesterday', 'week', 'month'];
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function __construct(private PDO $db) {}

    /** $now chỉ truyền khi kiểm thử, để cố định "hôm nay". */
    public function summary(int $shipperId, string $range, ?DateTimeImmutable $now = null): array
    {
        if (!in_array($range, self::RANGES, true)) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'Invalid field: range.');
        }
        $profile = ShipperEligibility::profile($this->db, $shipperId);
        [$start, $end, $previousStart] = self::bounds($range, $now ?? new DateTimeImmutable('now'));
        $current = $this->stats($shipperId, $start, $end);
        $previous = $this->stats($shipperId, $previousStart, $start);

        return [
            'range' => $range,
            'timezone' => self::TIMEZONE,
            'start' => $start->format(DATE_ATOM),
            'end' => $end->format(DATE_ATOM),
            'completed_deliveries' => $current['deliveries'],
            'delivery_seconds' => $current['delivery_seconds'],
            'period' => ['start' => $start->format(DATE_ATOM), 'end' => $end->format(DATE_ATOM)],
            'current' => $current,
            'previous' => $previous,
            // null khi kỳ trước không có đơn: không thể tính phần trăm tăng giảm từ số 0.
            'deliveries_change_percent' => $previous['deliveries'] > 0
                ? (int) round(($current['deliveries'] - $previous['deliveries']) * 100 / $previous['deliveries'])
                : null,
            'rating' => [
                // Chưa có lượt đánh giá nào thì trả null, không hiển thị 0.0 như một điểm thấp.
                'average' => (int) $profile['customer_rating_count'] > 0 ? number_format((float) $profile['customer_rating_average'], 1, '.', '') : null,
                'count' => (int) $profile['customer_rating_count'],
            ],
            'reliability_score' => number_format((float) $profile['reliability_score'], 0, '.', ''),
        ];
    }

    /**
     * Trả về [đầu kỳ, cuối kỳ, đầu kỳ trước]. Khoảng thời gian là nửa mở [đầu, cuối)
     * nên một đơn kết thúc đúng 00:00 chỉ thuộc về một ngày.
     */
    public static function bounds(string $range, DateTimeImmutable $now): array
    {
        $today = $now->setTimezone(new DateTimeZone(self::TIMEZONE))->setTime(0, 0);
        return match ($range) {
            'today' => [$today, $today->modify('+1 day'), $today->modify('-1 day')],
            'yesterday' => [$today->modify('-1 day'), $today, $today->modify('-2 days')],
            'week' => (static function () use ($today) {
                $monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
                return [$monday, $monday->modify('+7 days'), $monday->modify('-7 days')];
            })(),
            'month' => (static function () use ($today) {
                $first = $today->modify('first day of this month');
                return [$first, $first->modify('first day of next month'), $first->modify('first day of last month')];
            })(),
        };
    }

    /** Thống kê của một Shipper trong khoảng [$from, $to). */
    private function stats(int $shipperId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $params = [$shipperId, $from->format(DATE_ATOM), $to->format(DATE_ATOM)];
        // Đơn kết thúc trong kỳ: COMPLETED là giao thành công, RETURNED là đã hoàn hàng.
        $q = $this->db->prepare(
            "SELECT count(*) FILTER (WHERE a.assignment_status = 'COMPLETED') AS deliveries,
                    count(*) FILTER (WHERE a.assignment_status = 'RETURNED') AS returns,
                    COALESCE(sum(o.distance_km) FILTER (WHERE a.assignment_status = 'COMPLETED'), 0) AS distance_km
             FROM order_assignments a JOIN orders o ON o.order_id = a.order_id
             WHERE a.shipper_id = ? AND a.ended_at >= CAST(? AS timestamptz) AND a.ended_at < CAST(? AS timestamptz)"
        );
        $q->execute($params);
        $done = $q->fetch();
        // Offer đã có kết quả trong kỳ. Offer còn PENDING chưa tính vì Shipper chưa quyết định.
        $q = $this->db->prepare(
            "SELECT count(*) FILTER (WHERE status <> 'PENDING') AS decided,
                    count(*) FILTER (WHERE status = 'ACCEPTED') AS accepted
             FROM order_match_offers
             WHERE shipper_id = ? AND sent_at >= CAST(? AS timestamptz) AND sent_at < CAST(? AS timestamptz)"
        );
        $q->execute($params);
        $offers = $q->fetch();
        $decided = (int) $offers['decided'];

        $q = $this->db->prepare("SELECT COALESCE(SUM(GREATEST(0, EXTRACT(EPOCH FROM (
            LEAST(COALESCE(ended_at, CURRENT_TIMESTAMP), CAST(? AS timestamptz), CURRENT_TIMESTAMP)
            - GREATEST(assigned_at, CAST(? AS timestamptz))
        )))), 0) FROM order_assignments WHERE shipper_id = ?");
        $q->execute([$to->format(DATE_ATOM), $from->format(DATE_ATOM), $shipperId]);
        $seconds = (int) $q->fetchColumn();
        return [
            'delivery_seconds' => $seconds,
            'deliveries' => (int) $done['deliveries'],
            'returns' => (int) $done['returns'],
            'distance_km' => number_format((float) $done['distance_km'], 1, '.', ''),
            'offers_decided' => $decided,
            'offers_accepted' => (int) $offers['accepted'],
            // null khi chưa có offer nào: tỷ lệ 0% sẽ gây hiểu lầm là Shipper từ chối hết.
            'acceptance_rate_percent' => $decided > 0 ? (int) round((int) $offers['accepted'] * 100 / $decided) : null,
        ];
    }
}
