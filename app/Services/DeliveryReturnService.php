<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryService.php';

/**
 * Giao thất bại và hoàn hàng.
 * Đơn chỉ thành RETURNED khi CẢ HAI bên xác nhận: Shipper báo đã trả hàng (kèm ảnh)
 * và Customer xác nhận đã nhận lại. Sự kiện OrderReturned kích hoạt hoàn tiền bên
 * Người 1, nên không được phát khi mới có một phía xác nhận.
 */
final class DeliveryReturnService
{
    public const FAILURE_REASONS = ['NO_ANSWER', 'REFUSED', 'WRONG_ADDRESS', 'RECIPIENT_ABSENT', 'PACKAGE_ISSUE', 'OTHER'];

    public function __construct(private PDO $db) {}

    /** Không giao được: PICKED_UP hoặc IN_DELIVERY -> RETURNING, Shipper mang hàng về điểm gửi. */
    public function fail(int $shipperId, int $orderId, string $reasonCode, ?string $note): array
    {
        if ($reasonCode === 'OTHER' && $note === null) throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'A note is required for reason OTHER.');
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] === 'RETURNING') return (new DeliveryService($this->db))->current($shipperId);
        DeliveryContext::transition($this->db, $ctx['order'], 'RETURNING', $shipperId, 'SHIPPER', $reasonCode);
        $q = $this->db->prepare('UPDATE deliveries SET failure_reason_code = ?, failure_note = ?, return_started_at = now(), updated_at = now() WHERE delivery_id = ?');
        $q->execute([$reasonCode, $note, $ctx['delivery']['delivery_id']]);
        OrderLifecycleService::event($this->db, $ctx['order'], 'OrderReturning', 'returning:' . $orderId,
            ['assignment_id' => (string) $ctx['assignment']['assignment_id'], 'shipper_id' => (string) $shipperId, 'reason_code' => $reasonCode]);
        return (new DeliveryService($this->db))->current($shipperId);
    }

    /** Shipper báo đã trả hàng cho người gửi. Bắt buộc có ảnh hoàn hàng. */
    public function shipperReturned(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] !== 'RETURNING') throw new DeliveryApiError(409, 'INVALID_ORDER_STATE', 'Only a returning order can be handed back.');
        if (DeliveryContext::evidenceCount($this->db, (int) $ctx['delivery']['delivery_id'], 'RETURN_PHOTO') < 1) {
            throw new DeliveryApiError(409, 'EVIDENCE_REQUIRED', 'A return photo is required.');
        }
        $this->db->prepare('UPDATE deliveries SET returned_at = COALESCE(returned_at, now()), updated_at = now() WHERE delivery_id = ?')->execute([$ctx['delivery']['delivery_id']]);
        return ['order_id' => $orderId, 'status' => $this->finishIfBothConfirmed($ctx['order'], (int) $ctx['delivery']['delivery_id'])];
    }

    /** Customer xác nhận đã nhận lại hàng. Chỉ chủ đơn mới gọi được. */
    public function customerConfirmed(int $customerId, int $orderId): array
    {
        if (!$this->db->inTransaction()) throw new LogicException('Return confirmation requires a transaction.');
        AccessControl::requireOrder($this->db, $customerId, 'CUSTOMER', $orderId);
        $order = OrderLifecycleService::lock($this->db, $orderId);
        if ($order['status'] === 'RETURNED') return ['order_id' => $orderId, 'status' => 'RETURNED'];
        if ($order['status'] !== 'RETURNING') throw new DeliveryApiError(409, 'INVALID_ORDER_STATE', 'This order is not being returned.');
        $q = $this->db->prepare("SELECT d.* FROM deliveries d JOIN order_assignments a ON a.assignment_id = d.assignment_id WHERE a.order_id = ? AND a.assignment_status = 'ACTIVE' FOR UPDATE OF d");
        $q->execute([$orderId]);
        $delivery = $q->fetch();
        // Khách chỉ xác nhận sau khi Shipper đã báo trả hàng, tránh xác nhận nhầm khi hàng chưa về.
        if (!$delivery || $delivery['returned_at'] === null) throw new DeliveryApiError(409, 'RETURN_NOT_HANDED_OVER', 'The Shipper has not handed the parcel back yet.');
        $this->db->prepare('UPDATE deliveries SET customer_return_confirmed_at = COALESCE(customer_return_confirmed_at, now()), updated_at = now() WHERE delivery_id = ?')->execute([$delivery['delivery_id']]);
        return ['order_id' => $orderId, 'status' => $this->finishIfBothConfirmed($order, (int) $delivery['delivery_id'])];
    }

    private function finishIfBothConfirmed(array $order, int $deliveryId): string
    {
        $q = $this->db->prepare('SELECT * FROM deliveries WHERE delivery_id = ?');
        $q->execute([$deliveryId]);
        $delivery = $q->fetch();
        if ($delivery['returned_at'] === null || $delivery['customer_return_confirmed_at'] === null) return 'RETURNING';
        $orderId = (int) $order['order_id'];
        DeliveryContext::transition($this->db, $order, 'RETURNED', null, 'SYSTEM', 'Both parties confirmed the return.');
        $this->db->prepare('UPDATE orders SET closed_at = now() WHERE order_id = ?')->execute([$orderId]);
        $q = $this->db->prepare("UPDATE order_assignments SET assignment_status = 'RETURNED', ended_at = now(), ended_reason = 'RETURNED' WHERE assignment_id = ? RETURNING shipper_id");
        $q->execute([$delivery['assignment_id']]);
        OrderLifecycleService::event($this->db, $order, 'OrderReturned', 'returned:' . $orderId,
            ['assignment_id' => (string) $delivery['assignment_id'], 'shipper_id' => (string) $q->fetchColumn()]);
        return 'RETURNED';
    }
}
