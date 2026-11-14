<?php
declare(strict_types=1);
require_once __DIR__ . '/AccessControl.php';
require_once __DIR__ . '/MatchingService.php';

/** Danh sách offer của Shipper và thao tác từ chối. Nhận đơn nằm ở OfferAcceptService. */
final class OfferService
{
    public function __construct(private PDO $db) {}

    /**
     * Offer còn hiệu lực gửi cho Shipper này. Trước khi nhận đơn chỉ trả thông tin đủ để
     * quyết định (tuyến đường, hàng hóa, khoảng cách); tên và số điện thoại người gửi,
     * người nhận chỉ được trả sau khi đã có assignment.
     */
    public function pending(int $shipperId): array
    {
        $q = $this->db->prepare(
            "SELECT f.offer_id, f.order_id, o.order_code, f.distance_to_pickup_km, f.sent_at, f.expires_at,
                    NULL AS seconds_left,
                    o.pickup_label, o.pickup_address, o.pickup_latitude, o.pickup_longitude,
                    o.delivery_label, o.delivery_address, o.delivery_latitude, o.delivery_longitude,
                    o.distance_km, o.estimated_duration_minutes, o.estimated_weight_kg, o.parcel_description,
                    c.name_vi AS category_name_vi, c.name_en AS category_name_en,
                    o.currency_code, o.total_charge_minor, o.pricing_snapshot->'schedule' AS pickup_schedule
             FROM order_match_offers f
             JOIN orders o ON o.order_id = f.order_id
             JOIN parcel_categories c ON c.category_id = o.category_id
             WHERE f.shipper_id = ? AND f.status = 'PENDING' AND o.status = 'SEARCHING_SHIPPER' AND clock_timestamp()<" . MatchingService::deadlineSql() . "
             ORDER BY f.sent_at, f.offer_id"
        );
        $q->execute([$shipperId]);
        $offers = $q->fetchAll();
        foreach ($offers as &$offer) {
            $offer['estimated_earning_minor'] = $this->estimatedEarning($offer);
            $offer['pickup_schedule'] = $offer['pickup_schedule'] ? json_decode($offer['pickup_schedule'], true) : null;
        }
        unset($offer);
        $notice = null;
        if (!$offers) {
            $q = $this->db->prepare("SELECT EXISTS(SELECT 1 FROM orders WHERE customer_id=? AND status IN ('PENDING_PAYMENT','SEARCHING_SHIPPER'))");
            $q->execute([$shipperId]);
            if (in_array($q->fetchColumn(), [true,'t',1,'1'], true)) $notice = 'OWN_ORDER_EXCLUDED';
        }
        $q = $this->db->prepare("SELECT f.offer_id,o.order_code,
            CASE WHEN f.status <> 'PENDING' THEN f.status
                 WHEN o.status <> 'SEARCHING_SHIPPER' THEN 'ORDER_UNAVAILABLE'
                 ELSE f.status END AS status,
            f.response_note
            FROM order_match_offers f JOIN orders o ON o.order_id=f.order_id
            WHERE f.shipper_id=? AND (f.status<>'PENDING' OR o.status<>'SEARCHING_SHIPPER')
              AND COALESCE(f.responded_at,f.expires_at)>now()-interval '5 minutes'
            ORDER BY COALESCE(f.responded_at,f.expires_at) DESC,f.offer_id DESC LIMIT 5");
        $q->execute([$shipperId]);
        return ['items' => $offers, 'notice' => $notice, 'recent_updates' => $q->fetchAll()];
    }

    /**
     * Thu nhập dự kiến theo chính sách đang hiệu lực của Admin (chỉ đọc).
     * Chưa có chính sách thì trả null; giao diện không được tự bịa tỷ lệ.
     * Số tiền thật do module thu nhập của Người 3 ghi nhận sau khi giao xong.
     */
    private function estimatedEarning(array $offer): ?string
    {
        $q = $this->db->prepare(
            "SELECT percentage_rate FROM shipper_earning_policies
             WHERE policy_type = 'SUCCESSFUL_DELIVERY_PERCENT' AND currency_code = ?
               AND effective_from <= now() AND (effective_until IS NULL OR effective_until > now())
             ORDER BY effective_from DESC LIMIT 1"
        );
        $q->execute([$offer['currency_code']]);
        $rate = $q->fetchColumn();
        if ($rate === false) return null;
        return (string) intdiv((int) $offer['total_charge_minor'] * (int) round((float) $rate * 10000), 1000000);
    }

    /** Từ chối offer. Nếu đơn không còn offer nào chờ thì gửi ngay vòng kế tiếp. */
    public function reject(int $shipperId, int $offerId, ?string $note): array
    {
        // requireOffer trả 404 cho cả offer không tồn tại lẫn offer của người khác.
        $offer = AccessControl::requireOffer($this->db, $shipperId, $offerId);
        // Thứ tự khóa của dự án: order trước, offer sau.
        OrderLifecycleService::lock($this->db, (int) $offer['order_id']);
        $q = $this->db->prepare('SELECT * FROM order_match_offers WHERE offer_id = ? FOR UPDATE');
        $q->execute([$offerId]);
        $offer = $q->fetch();
        if ($offer['status'] === 'REJECTED') return ['offer_id' => $offerId, 'status' => 'REJECTED'];
        if ($offer['status'] !== 'PENDING') throw new DeliveryApiError(409, 'OFFER_NOT_PENDING', 'This offer is no longer open.');
        $q = $this->db->prepare("UPDATE order_match_offers SET status = 'REJECTED', responded_at = now(), response_note = ? WHERE offer_id = ?");
        $q->execute([$note, $offerId]);
        (new MatchingService($this->db))->dispatch((int) $offer['order_id']);
        return ['offer_id' => $offerId, 'status' => 'REJECTED'];
    }
}
