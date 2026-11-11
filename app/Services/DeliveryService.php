<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryContext.php';

/** Đơn đang giao của Shipper, lịch sử, và hai mốc đầu: lấy hàng và bắt đầu giao. */
final class DeliveryService
{
    public function __construct(private PDO $db) {}

    /** Đơn Shipper đang chạy (hoặc null). Chỉ sau khi nhận đơn mới thấy thông tin liên hệ. */
    public function current(int $shipperId): array
    {
        $q = $this->db->prepare(
            "SELECT a.assignment_id, a.assigned_at, o.order_id, o.order_code, o.status,
                    o.pickup_label, o.pickup_address, o.pickup_latitude, o.pickup_longitude, o.sender_name, o.sender_phone,
                    o.delivery_label, o.delivery_address, o.delivery_latitude, o.delivery_longitude, o.receiver_name, o.receiver_phone,
                    o.distance_km, o.estimated_duration_minutes, o.estimated_weight_kg, o.parcel_description, o.customer_note,
                    c.name_vi AS category_name_vi, c.name_en AS category_name_en,
                    d.delivery_id, d.picked_up_at, d.delivery_started_at, d.delivered_at, d.failure_reason_code,
                    d.return_started_at, d.returned_at, d.customer_return_confirmed_at
             FROM order_assignments a
             JOIN orders o ON o.order_id = a.order_id
             JOIN parcel_categories c ON c.category_id = o.category_id
             JOIN deliveries d ON d.assignment_id = a.assignment_id
             WHERE a.shipper_id = ? AND a.assignment_status = 'ACTIVE'"
        );
        $q->execute([$shipperId]);
        $delivery = $q->fetch();
        if (!$delivery) return ['delivery' => null];
        $q = $this->db->prepare('SELECT evidence_id, evidence_type, mime_type, byte_size, uploaded_at FROM delivery_evidence WHERE delivery_id = ? AND deleted_at IS NULL ORDER BY evidence_id');
        $q->execute([$delivery['delivery_id']]);
        $delivery['evidence'] = $q->fetchAll();
        $delivery['next_actions'] = self::nextActions($delivery);
        return ['delivery' => $delivery];
    }

    /** Những thao tác hợp lệ ở trạng thái hiện tại. Giao diện chỉ hiển thị nút theo danh sách này. */
    private static function nextActions(array $delivery): array
    {
        $types = array_column($delivery['evidence'], 'evidence_type');
        return match ($delivery['status']) {
            'SHIPPER_ASSIGNED' => ['PICKUP'],
            'PICKED_UP' => ['START_DELIVERY', 'FAIL'],
            'IN_DELIVERY' => in_array('DELIVERY_PHOTO', $types, true) ? ['ADD_DELIVERY_PHOTO', 'DELIVERED', 'FAIL'] : ['ADD_DELIVERY_PHOTO', 'FAIL'],
            'RETURNING' => $delivery['returned_at'] !== null ? []
                : (in_array('RETURN_PHOTO', $types, true) ? ['ADD_RETURN_PHOTO', 'RETURNED'] : ['ADD_RETURN_PHOTO']),
            default => [],
        };
    }

    /** Các đơn đã kết thúc của Shipper, mới nhất trước, phân trang bằng before_id. */
    public function history(int $shipperId, ?int $beforeId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $q = $this->db->prepare(
            "SELECT a.assignment_id, a.assignment_status, a.assigned_at, a.ended_at, o.order_id, o.order_code, o.status,
                    o.pickup_address, o.delivery_address, o.distance_km
             FROM order_assignments a JOIN orders o ON o.order_id = a.order_id
             WHERE a.shipper_id = ? AND a.assignment_status <> 'ACTIVE' AND (CAST(? AS bigint) IS NULL OR a.assignment_id < CAST(? AS bigint))
             ORDER BY a.assignment_id DESC LIMIT ?"
        );
        $q->execute([$shipperId, $beforeId, $beforeId, $limit]);
        return ['items' => $q->fetchAll()];
    }

    /** Shipper xác nhận đã lấy hàng tại điểm gửi: SHIPPER_ASSIGNED -> PICKED_UP. */
    public function pickup(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] === 'PICKED_UP') return $this->current($shipperId);
        DeliveryContext::transition($this->db, $ctx['order'], 'PICKED_UP', $shipperId, 'SHIPPER');
        $this->db->prepare('UPDATE orders SET picked_up_at = now() WHERE order_id = ?')->execute([$orderId]);
        $this->db->prepare('UPDATE deliveries SET picked_up_at = now(), updated_at = now() WHERE delivery_id = ?')->execute([$ctx['delivery']['delivery_id']]);
        OrderLifecycleService::event($this->db, $ctx['order'], 'OrderPickedUp', 'picked-up:' . $ctx['assignment']['assignment_id'], ['shipper_id' => (string) $shipperId]);
        return $this->current($shipperId);
    }

    /** Shipper rời điểm lấy hàng để đi giao: PICKED_UP -> IN_DELIVERY. */
    public function startDelivery(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] === 'IN_DELIVERY') return $this->current($shipperId);
        DeliveryContext::transition($this->db, $ctx['order'], 'IN_DELIVERY', $shipperId, 'SHIPPER');
        $this->db->prepare('UPDATE deliveries SET delivery_started_at = now(), updated_at = now() WHERE delivery_id = ?')->execute([$ctx['delivery']['delivery_id']]);
        return $this->current($shipperId);
    }
}
