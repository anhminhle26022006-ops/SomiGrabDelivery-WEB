<?php
declare(strict_types=1);
require_once __DIR__ . '/OrderLifecycleService.php';
require_once __DIR__ . '/ShipperInput.php';

/**
 * Ghép đơn: chọn Shipper phù hợp và gửi offer có thời hạn.
 *
 * Mỗi "vòng" gửi offer cho vài Shipper gần điểm lấy hàng nhất. Khi cả vòng hết hạn
 * hoặc bị từ chối, vòng kế tiếp gửi cho những người chưa được mời. Hết số vòng hoặc
 * hết thời gian tìm kiếm thì đơn chuyển sang NO_SHIPPER_FOUND.
 */
final class MatchingService
{
    public function __construct(private PDO $db) {}

    /**
     * Gửi một vòng offer cho đơn. Hàm an toàn khi gọi lặp lại: nếu đơn không còn
     * tìm Shipper hoặc vẫn còn offer đang chờ thì không làm gì.
     * Trả về ['result' => SKIPPED | WAITING | OFFERED | NO_SHIPPER_FOUND, 'offers' => số offer vừa tạo].
     */
    public function dispatch(int $orderId): array
    {
        // Khóa đơn trước tiên: hủy đơn, thanh toán và nhận đơn đều khóa cùng dòng này.
        $order = OrderLifecycleService::lock($this->db, $orderId);
        if ($order['status'] !== 'SEARCHING_SHIPPER' || $order['paid_at'] === null) return ['result' => 'SKIPPED', 'offers' => 0];

        $q = $this->db->prepare("SELECT count(*) FROM order_match_offers WHERE order_id = ? AND status = 'PENDING' AND expires_at > now()");
        $q->execute([$orderId]);
        if ((int) $q->fetchColumn() > 0) return ['result' => 'WAITING', 'offers' => 0];

        // Phiên tìm kiếm hiện tại bắt đầu từ lần gần nhất đơn vào SEARCHING_SHIPPER.
        $q = $this->db->prepare(
            "SELECT COALESCE((SELECT max(created_at) FROM order_status_history WHERE order_id = o.order_id AND new_status = 'SEARCHING_SHIPPER'), o.paid_at) AS started_at
             FROM orders o WHERE o.order_id = ?"
        );
        $q->execute([$orderId]);
        $startedAt = $q->fetchColumn();
        $q = $this->db->prepare(
            'SELECT count(DISTINCT sent_at) AS rounds, extract(epoch FROM now() - CAST(? AS timestamptz)) AS elapsed
             FROM order_match_offers WHERE order_id = ? AND sent_at >= CAST(? AS timestamptz)'
        );
        $q->execute([$startedAt, $orderId, $startedAt]);
        $progress = $q->fetch();
        $timedOut = (float) $progress['elapsed'] > ShipperInput::config('search_timeout_seconds');
        if ((int) $progress['rounds'] >= ShipperInput::config('max_rounds') || $timedOut) return $this->noShipperFound($order);

        $candidates = $this->candidates($order, $startedAt);
        // Chưa có ai phù hợp: giữ đơn ở SEARCHING_SHIPPER để worker thử lại cho tới khi hết thời gian.
        if (!$candidates) return ['result' => 'WAITING', 'offers' => 0];

        $q = $this->db->prepare(
            'INSERT INTO order_match_offers (order_id, shipper_id, distance_to_pickup_km, match_score, sent_at, expires_at)
             VALUES (?, ?, ?, ?, now(), now() + make_interval(secs => ?)) RETURNING offer_id'
        );
        foreach ($candidates as $candidate) {
            $q->execute([$orderId, $candidate['user_id'], $candidate['distance_km'], $candidate['match_score'], ShipperInput::config('offer_ttl_seconds')]);
            $offerId=(int)$q->fetchColumn();
            OrderLifecycleService::event($this->db,$order,'OfferCreated','offer-created:'.$offerId,['offer_id'=>(string)$offerId]);
        }
        return ['result' => 'OFFERED', 'offers' => count($candidates)];
    }

    /**
     * Shipper đủ điều kiện nhận offer của đơn này, gần điểm lấy hàng nhất xếp trước.
     * Điều kiện: đang bật nhận đơn, hồ sơ và giấy tờ đã duyệt, tài khoản ACTIVE, không bị
     * khóa điểm, vị trí còn mới, trong bán kính, đang rảnh, chưa được mời trong phiên này
     * và không phải chính người đặt đơn (một tài khoản có thể có cả hai vai trò).
     */
    private function candidates(array $order, string $startedAt): array
    {
        $q = $this->db->prepare(
            "WITH params AS (
                 SELECT CAST(? AS double precision) AS lat, CAST(? AS double precision) AS lng
             ), nearby AS (
                 SELECT sp.user_id, sp.reliability_score,
                        6371 * 2 * asin(sqrt(
                            power(sin(radians(sp.last_latitude - p.lat) / 2), 2)
                            + cos(radians(p.lat)) * cos(radians(sp.last_latitude))
                              * power(sin(radians(sp.last_longitude - p.lng) / 2), 2))) AS distance_km
                 FROM shipper_profiles sp
                 CROSS JOIN params p
                 JOIN users u ON u.user_id = sp.user_id AND u.account_status = 'ACTIVE'
                 JOIN user_identity_documents d ON d.user_id = sp.user_id AND d.review_status = 'APPROVED'
                 WHERE sp.availability AND sp.verification_status = 'APPROVED'
                   AND sp.user_id <> ?
                   AND sp.last_location_at > now() - make_interval(secs => ?)
                   AND NOT EXISTS (SELECT 1 FROM order_assignments a WHERE a.shipper_id = sp.user_id AND a.assignment_status = 'ACTIVE')
                   AND NOT EXISTS (SELECT 1 FROM order_match_offers f WHERE f.order_id = ? AND f.shipper_id = sp.user_id AND f.sent_at >= CAST(? AS timestamptz))
                   AND NOT EXISTS (SELECT 1 FROM order_assignments cancelled WHERE cancelled.order_id=? AND cancelled.shipper_id=sp.user_id AND cancelled.assignment_status='CANCELLED_BEFORE_PICKUP')
                   AND NOT EXISTS (SELECT 1 FROM shipper_score_policies sc
                                   WHERE sc.effective_from <= now() AND (sc.effective_until IS NULL OR sc.effective_until > now())
                                     AND sp.reliability_score < sc.lock_threshold)
             )
             SELECT user_id, round(distance_km::numeric, 3) AS distance_km,
                    round((reliability_score / 100 / (1 + distance_km))::numeric, 5) AS match_score
             FROM nearby WHERE distance_km <= ?
             ORDER BY distance_km, reliability_score DESC, user_id
             LIMIT ?"
        );
        $q->execute([
            $order['pickup_latitude'], $order['pickup_longitude'], $order['customer_id'],
            ShipperInput::config('location_max_age_seconds'), $order['order_id'], $startedAt, $order['order_id'],
            ShipperInput::config('radius_km'), ShipperInput::config('batch_size'),
        ]);
        return $q->fetchAll();
    }

    private function noShipperFound(array $order): array
    {
        $orderId = (int) $order['order_id'];
        $this->db->prepare("UPDATE orders SET status = 'NO_SHIPPER_FOUND', updated_at = now() WHERE order_id = ?")->execute([$orderId]);
        OrderLifecycleService::history($this->db, $orderId, 'SEARCHING_SHIPPER', 'NO_SHIPPER_FOUND', null, 'SYSTEM', 'Matching exhausted.');
        $q = $this->db->prepare("SELECT count(*) FROM order_status_history WHERE order_id = ? AND new_status = 'NO_SHIPPER_FOUND'");
        $q->execute([$orderId]);
        // Khóa chống trùng có số lần, vì khách có thể yêu cầu tìm lại rồi lại không có Shipper.
        OrderLifecycleService::event($this->db, $order, 'NoShipperFound', 'no-shipper:' . $orderId . ':' . $q->fetchColumn());
        return ['result' => 'NO_SHIPPER_FOUND', 'offers' => 0];
    }

    /** Đánh dấu hết hạn các offer quá thời gian. SKIP LOCKED để không chờ giao dịch nhận đơn đang chạy. */
    public function expireOffers(): int
    {
        $q = $this->db->query(
            "UPDATE order_match_offers SET status = 'EXPIRED', responded_at = now()
             WHERE offer_id IN (SELECT offer_id FROM order_match_offers
                                WHERE status = 'PENDING' AND expires_at <= now()
                                ORDER BY offer_id FOR UPDATE SKIP LOCKED LIMIT 200)"
        );
        return $q->rowCount();
    }

    /** Các đơn đang tìm Shipper mà không còn offer nào chờ: cần gửi vòng tiếp theo. */
    public function ordersNeedingDispatch(int $limit = 20): array
    {
        $q = $this->db->prepare(
            "SELECT o.order_id FROM orders o
             WHERE o.status = 'SEARCHING_SHIPPER' AND o.paid_at IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM order_match_offers f WHERE f.order_id = o.order_id AND f.status = 'PENDING' AND f.expires_at > now())
             ORDER BY o.paid_at, o.order_id LIMIT ?"
        );
        $q->execute([$limit]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }
}
