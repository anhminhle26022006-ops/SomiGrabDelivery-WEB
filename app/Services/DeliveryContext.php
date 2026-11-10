<?php
declare(strict_types=1);
require_once __DIR__ . '/AccessControl.php';
require_once __DIR__ . '/OrderLifecycleService.php';

/** Phần dùng chung của các service giao nhận: khóa đúng đơn của đúng Shipper và chuyển trạng thái. */
final class DeliveryContext
{
    /**
     * Khóa đơn mà Shipper này đang được phân công rồi trả về đơn, assignment và bản ghi giao nhận.
     * Đơn của người khác hoặc đơn không tồn tại đều trả 404 giống nhau để không lộ thông tin.
     */
    public static function lockActive(PDO $db, int $shipperId, int $orderId): array
    {
        if (!$db->inTransaction()) throw new LogicException('Delivery updates require a transaction.');
        AccessControl::requireOrder($db, $shipperId, 'SHIPPER', $orderId);
        $order = OrderLifecycleService::lock($db, $orderId);
        // Đọc lại assignment sau khi khóa đơn: Admin có thể vừa hủy phân công.
        $q = $db->prepare("SELECT * FROM order_assignments WHERE order_id = ? AND shipper_id = ? AND assignment_status = 'ACTIVE' FOR UPDATE");
        $q->execute([$orderId, $shipperId]);
        $assignment = $q->fetch();
        if (!$assignment) throw new DeliveryApiError(404, 'ORDER_NOT_FOUND', 'Order not found.');
        $q = $db->prepare('SELECT * FROM deliveries WHERE assignment_id = ? FOR UPDATE');
        $q->execute([$assignment['assignment_id']]);
        $delivery = $q->fetch();
        if (!$delivery) throw new LogicException('Active assignment without a delivery record.');
        return ['order' => $order, 'assignment' => $assignment, 'delivery' => $delivery];
    }

    /** Chuyển trạng thái theo bảng TRANSITIONS dùng chung của dự án và ghi lịch sử. */
    public static function transition(PDO $db, array $order, string $to, ?int $actorId, string $actorKind, ?string $note = null): void
    {
        $from = $order['status'];
        if (!in_array($to, OrderLifecycleService::TRANSITIONS[$from] ?? [], true)) {
            throw new DeliveryApiError(409, 'INVALID_ORDER_STATE', "Order cannot move from $from to $to.");
        }
        $db->prepare('UPDATE orders SET status = ?, updated_at = now() WHERE order_id = ?')->execute([$to, $order['order_id']]);
        OrderLifecycleService::history($db, (int) $order['order_id'], $from, $to, $actorId, $actorKind, $note);
    }

    public static function evidenceCount(PDO $db, int $deliveryId, string $type): int
    {
        $q = $db->prepare('SELECT count(*) FROM delivery_evidence WHERE delivery_id = ? AND evidence_type = ? AND deleted_at IS NULL');
        $q->execute([$deliveryId, $type]);
        return (int) $q->fetchColumn();
    }
}
