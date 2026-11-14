<?php
declare(strict_types=1);
require_once __DIR__ . '/OrderLifecycleService.php';
require_once __DIR__ . '/ShipperInput.php';
require_once __DIR__ . '/ShipperEligibility.php';
require_once __DIR__ . '/PickupSchedule.php';

/** Open dispatch: all eligible nearby shippers can receive an order; no individual offer timeout, with a five-minute customer search window. */
final class MatchingService
{
    public function __construct(private PDO $db) {}

    /** Invite all currently eligible nearby shippers; existing invitations are never duplicated. */
    public function dispatch(int $orderId): array
    {
        // Khóa đơn trước tiên: hủy đơn, thanh toán và nhận đơn đều khóa cùng dòng này.
        $order = OrderLifecycleService::lock($this->db, $orderId);
        if ($order['status'] !== 'SEARCHING_SHIPPER' || $order['paid_at'] === null) return ['result' => 'SKIPPED', 'offers' => 0];
        $matchingAt = PickupSchedule::matchingAt($order);
        if ($matchingAt && $matchingAt > new DateTimeImmutable('now')) return ['result' => 'SCHEDULED', 'offers' => 0];

        if ($this->searchExpired($orderId)) return $this->noShipperFound($order);

        $this->db->prepare('UPDATE orders SET matching_checked_at=now() WHERE order_id=?')->execute([$orderId]);
        $candidates = $this->candidates($order);
        if (!$candidates) return ['result' => 'WAITING', 'offers' => 0];

        $q = $this->db->prepare(
            'INSERT INTO order_match_offers (order_id, shipper_id, distance_to_pickup_km, match_score, sent_at, expires_at)
             VALUES (?, ?, ?, ?, now(), NULL) RETURNING offer_id'
        );
        foreach ($candidates as $candidate) {
            $q->execute([$orderId, $candidate['user_id'], $candidate['distance_km'], $candidate['match_score']]);
            $offerId=(int)$q->fetchColumn();
            OrderLifecycleService::event($this->db,$order,'OfferCreated','offer-created:'.$offerId,['offer_id'=>(string)$offerId]);
        }
        return ['result' => 'OFFERED', 'offers' => count($candidates)];
    }

    /** Bounded fallback when an online shipper polls; the worker remains the primary dispatcher. */
    public function dispatchNearby(int $shipperId): array
    {
        $profile = ShipperEligibility::profile($this->db, $shipperId);
        ShipperEligibility::assertCanWork($this->db, $profile);
        if (!\Somi\Domain\Shipper::fromRow($profile)->isAvailable()) return [];
        $q = $this->db->prepare(
            "SELECT o.order_id FROM orders o
             JOIN shipper_profiles sp ON sp.user_id = ?
             WHERE o.status = 'SEARCHING_SHIPPER' AND o.paid_at IS NOT NULL AND o.customer_id <> sp.user_id
               AND " . PickupSchedule::dueSql() . "
               AND sp.last_location_at > now() - make_interval(secs => ?)
               AND 6371 * 2 * asin(sqrt(least(1.0,
                 power(sin(radians(sp.last_latitude - o.pickup_latitude) / 2), 2)
                 + cos(radians(o.pickup_latitude)) * cos(radians(sp.last_latitude))
                   * power(sin(radians(sp.last_longitude - o.pickup_longitude) / 2), 2)))) <= ?
               AND NOT EXISTS (SELECT 1 FROM order_assignments a WHERE a.shipper_id=sp.user_id AND a.assignment_status='ACTIVE')
               AND NOT EXISTS (SELECT 1 FROM order_match_offers f WHERE f.order_id=o.order_id AND f.shipper_id=sp.user_id AND f.status IN ('PENDING','REJECTED','ACCEPTED'))
             ORDER BY o.matching_checked_at NULLS FIRST,o.paid_at,o.order_id LIMIT 20"
        );
        $q->execute([$shipperId, ShipperInput::config('location_max_age_seconds'), ShipperInput::config('radius_km')]);
        $orders = $q->fetchAll(PDO::FETCH_COLUMN);
        $results = [];
        foreach ($orders as $id) $results[(int)$id] = $this->dispatch((int)$id);
        return $results;
    }

    /**
     * Shipper đủ điều kiện nhận offer của đơn này, gần điểm lấy hàng nhất xếp trước.
     * Điều kiện: đang bật nhận đơn, hồ sơ và giấy tờ đã duyệt, tài khoản ACTIVE, không bị
     * khóa điểm, vị trí còn mới, trong bán kính, đang rảnh, chưa có lời mời mở hoặc chưa chủ động từ chối
     * và không phải chính người đặt đơn (một tài khoản có thể có cả hai vai trò).
     */
    private function candidates(array $order): array
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
                   AND NOT EXISTS (SELECT 1 FROM order_match_offers f WHERE f.order_id = ? AND f.shipper_id = sp.user_id AND f.status IN ('PENDING','REJECTED','ACCEPTED'))
                   AND NOT EXISTS (SELECT 1 FROM order_assignments cancelled WHERE cancelled.order_id=? AND cancelled.shipper_id=sp.user_id AND cancelled.assignment_status='CANCELLED_BEFORE_PICKUP')
                   AND (u.email LIKE '%@demo.somi.invalid') = EXISTS (SELECT 1 FROM users dc WHERE dc.user_id=? AND dc.email LIKE '%@demo.somi.invalid')
                   AND NOT EXISTS (SELECT 1 FROM shipper_score_policies sc
                                   WHERE sc.effective_from <= now() AND (sc.effective_until IS NULL OR sc.effective_until > now())
                                     AND sp.reliability_score < sc.lock_threshold)
             )
             SELECT user_id, round(distance_km::numeric, 3) AS distance_km,
                    round((reliability_score / 100 / (1 + distance_km))::numeric, 5) AS match_score
             FROM nearby WHERE distance_km <= ?
             ORDER BY distance_km, reliability_score DESC, user_id
             "
        );
        $q->execute([
            $order['pickup_latitude'], $order['pickup_longitude'], $order['customer_id'],
            ShipperInput::config('location_max_age_seconds'), $order['order_id'], $order['order_id'], $order['customer_id'],
            ShipperInput::config('radius_km'),
        ]);
        return $q->fetchAll();
    }

    /** The clock starts at matching, never at advance payment for a scheduled pickup. */
    public static function deadlineSql(string $alias='o'): string
    {
        $seconds=(int)ShipperInput::config('search_timeout_seconds');
        return "(GREATEST(COALESCE((SELECT max(h.created_at) FROM order_status_history h WHERE h.order_id=$alias.order_id AND h.new_status='SEARCHING_SHIPPER'),$alias.paid_at),CASE WHEN $alias.pricing_snapshot #>> '{schedule,mode}'='later' THEN CAST($alias.pricing_snapshot #>> '{schedule,at}' AS timestamptz)-interval '30 minutes' ELSE NULL END)+make_interval(secs=>$seconds))";
    }

    public function searchExpired(int $orderId): bool
    {
        $q=$this->db->prepare('SELECT clock_timestamp()>=' . self::deadlineSql() . ' FROM orders o WHERE o.order_id=?');
        $q->execute([$orderId]);
        return in_array($q->fetchColumn(),[true,'t',1,'1'],true);
    }

    private function noShipperFound(array $order): array
    {
        $id=(int)$order['order_id'];
        $this->db->prepare("UPDATE orders SET status='NO_SHIPPER_FOUND',updated_at=now() WHERE order_id=?")->execute([$id]);
        $this->db->prepare("UPDATE order_match_offers SET status='CANCELLED',responded_at=now(),response_note='SEARCH_TIMEOUT' WHERE order_id=? AND status='PENDING'")->execute([$id]);
        OrderLifecycleService::history($this->db,$id,'SEARCHING_SHIPPER','NO_SHIPPER_FOUND',null,'SYSTEM','Không tìm được shipper trong 5 phút.');
        $q=$this->db->prepare("SELECT count(*) FROM order_status_history WHERE order_id=? AND new_status='NO_SHIPPER_FOUND'");$q->execute([$id]);
        OrderLifecycleService::event($this->db,$order,'NoShipperFound','no-shipper:'.$id.':'.$q->fetchColumn());
        return ['result'=>'NO_SHIPPER_FOUND','offers'=>0];
    }

    /** Retained for older worker entry points. Open invitations never expire. */
    public function expireOffers(): int { return 0; }

    /** Fair sweep also includes orders already visible to other shippers, so newcomers can join. */
    public function ordersNeedingDispatch(int $limit = 20): array
    {
        $q = $this->db->prepare(
            "SELECT o.order_id FROM orders o
             WHERE o.status = 'SEARCHING_SHIPPER' AND o.paid_at IS NOT NULL
               AND " . PickupSchedule::dueSql() . "
             ORDER BY o.matching_checked_at NULLS FIRST, o.paid_at, o.order_id LIMIT ?"
        );
        $q->execute([$limit]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }
}
