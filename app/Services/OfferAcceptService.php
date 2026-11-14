<?php
declare(strict_types=1);
require_once __DIR__ . '/AccessControl.php';
require_once __DIR__ . '/OrderLifecycleService.php';
require_once __DIR__ . '/ShipperEligibility.php';
require_once __DIR__ . '/MatchingService.php';

/**
 * Nhận đơn "nguyên tử": trong một transaction, hoặc Shipper nhận được đơn trọn vẹn
 * (offer ACCEPTED, có assignment, đơn sang SHIPPER_ASSIGNED), hoặc không có gì thay đổi.
 *
 * Hai Shipper bấm nhận cùng lúc sẽ xếp hàng tại khóa dòng của đơn (SELECT ... FOR UPDATE).
 * Người vào sau đọc lại trạng thái mới nên thấy đơn đã có người nhận. Unique index
 * ux_order_single_active_assignment là lớp chặn cuối cùng ở database.
 */
final class OfferAcceptService
{
    public function __construct(private PDO $db) {}

    public function accept(int $shipperId, int $offerId): array
    {
        if (!$this->db->inTransaction()) throw new LogicException('Accepting an offer requires a transaction.');
        $offer = AccessControl::requireOffer($this->db, $shipperId, $offerId);
        $orderId = (int) $offer['order_id'];

        // 1) Khóa đơn. Hủy đơn của Customer/Admin cũng khóa dòng này nên không thể chen ngang.
        $order = OrderLifecycleService::lock($this->db, $orderId);
        // Lock the shipper before individual offers: parallel accepts of different orders cannot deadlock while releasing other invitations.
        $profile = ShipperEligibility::profile($this->db, $shipperId, true);
        // 2) Khóa offer và đọc lại: dữ liệu đọc trước khi khóa có thể đã cũ.
        $q = $this->db->prepare('SELECT * FROM order_match_offers WHERE offer_id = ? FOR UPDATE');
        $q->execute([$offerId]);
        $offer = $q->fetch();

        // Gọi lại sau khi đã nhận thành công (mất mạng, bấm hai lần) trả về đúng kết quả cũ.
        if ($offer['status'] === 'ACCEPTED') return $this->result($orderId, $shipperId, false);
        if ($offer['status'] === 'LOST_RACE') throw new DeliveryApiError(409, 'OFFER_LOST_RACE', 'Another Shipper accepted this order first.');
        if ($offer['status'] !== 'PENDING') throw new DeliveryApiError(409, 'OFFER_NOT_PENDING', 'This offer is no longer open.');

        // 3) Trạng thái đơn và thanh toán, kiểm tra khi đang giữ khóa.
        if ($order['status'] !== 'SEARCHING_SHIPPER') throw new DeliveryApiError(409, 'ORDER_NOT_AVAILABLE', 'This order is no longer looking for a Shipper.');
        if ((new MatchingService($this->db))->searchExpired($orderId)) throw new DeliveryApiError(409,'ORDER_SEARCH_EXPIRED','No shipper accepted within the five-minute matching window.');
        $q = $this->db->prepare('SELECT status FROM payments WHERE order_id = ?');
        $q->execute([$orderId]);
        if ($order['paid_at'] === null || $q->fetchColumn() !== 'PAID') throw new DeliveryApiError(409, 'PAYMENT_NOT_CONFIRMED', 'The order payment is not confirmed.');

        $q=$this->db->prepare("SELECT (c.email LIKE '%@demo.somi.invalid')=(s.email LIKE '%@demo.somi.invalid') FROM users c CROSS JOIN users s WHERE c.user_id=? AND s.user_id=?");$q->execute([$order['customer_id'],$shipperId]);
        if(!in_array($q->fetchColumn(),[true,'t',1,'1'],true))throw new DeliveryApiError(409,'ORDER_NOT_AVAILABLE','Demo and live deliveries cannot be mixed.');

        // 4) Điều kiện của Shipper tại đúng thời điểm nhận, không tin trạng thái lúc gửi offer.
        ShipperEligibility::assertCanWork($this->db, $profile);
        if (!$profile['availability']) throw new DeliveryApiError(409, 'AVAILABILITY_OFF', 'Turn on availability before accepting orders.');
        if ((int) $order['customer_id'] === $shipperId) throw new DeliveryApiError(409, 'ORDER_NOT_AVAILABLE', 'You cannot deliver your own order.');
        $q = $this->db->prepare("SELECT 1 FROM order_assignments WHERE shipper_id = ? AND assignment_status = 'ACTIVE'");
        $q->execute([$shipperId]);
        if ($q->fetchColumn()) throw new DeliveryApiError(409, 'ACTIVE_DELIVERY_EXISTS', 'Finish the current delivery first.');

        // 5) Ghi kết quả. Mọi câu lệnh dưới đây cùng commit hoặc cùng rollback.
        $q = $this->db->prepare('INSERT INTO order_assignments (order_id, shipper_id, offer_id) VALUES (?, ?, ?) RETURNING assignment_id');
        $q->execute([$orderId, $shipperId, $offerId]);
        $assignmentId = (int) $q->fetchColumn();
        $this->db->prepare('INSERT INTO deliveries (order_id, assignment_id) VALUES (?, ?)')->execute([$orderId, $assignmentId]);
        $this->db->prepare("UPDATE order_match_offers SET status = 'ACCEPTED', responded_at = now() WHERE offer_id = ?")->execute([$offerId]);
        // Offer của những Shipper khác cho đơn này thua cuộc.
        $this->db->prepare("UPDATE order_match_offers SET status = 'LOST_RACE', responded_at = now() WHERE order_id = ? AND offer_id <> ? AND status = 'PENDING'")->execute([$orderId, $offerId]);
        // Shipper đã bận nên các offer khác của chính họ được trả lại cho hệ thống ghép tiếp.
        $this->db->prepare("UPDATE order_match_offers SET status = 'CANCELLED', responded_at = now(), response_note = 'SHIPPER_BUSY' WHERE shipper_id = ? AND offer_id <> ? AND status = 'PENDING'")->execute([$shipperId, $offerId]);
        $this->db->prepare("UPDATE orders SET status = 'SHIPPER_ASSIGNED', updated_at = now() WHERE order_id = ?")->execute([$orderId]);
        $this->db->prepare('UPDATE shipper_profiles SET last_active_at = now() WHERE user_id = ?')->execute([$shipperId]);
        OrderLifecycleService::history($this->db, $orderId, 'SEARCHING_SHIPPER', 'SHIPPER_ASSIGNED', $shipperId, 'SHIPPER');
        OrderLifecycleService::event($this->db, $order, 'ShipperAssigned', 'assigned:' . $assignmentId, ['assignment_id' => (string) $assignmentId, 'shipper_id' => (string) $shipperId]);
        return $this->result($orderId, $shipperId, true);
    }

    private function result(int $orderId, int $shipperId, bool $created): array
    {
        $q = $this->db->prepare("SELECT assignment_id, order_id, assigned_at FROM order_assignments WHERE order_id = ? AND shipper_id = ? AND assignment_status = 'ACTIVE'");
        $q->execute([$orderId, $shipperId]);
        $assignment = $q->fetch();
        // Đã nhận nhưng assignment không còn hoạt động (đơn đã xong hoặc bị Admin hủy).
        if (!$assignment) throw new DeliveryApiError(409, 'OFFER_NOT_PENDING', 'This offer is no longer open.');
        return ['created' => $created, 'assignment' => $assignment];
    }
}
