<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

final class OrderLifecycleService
{
    public const TRANSITIONS = [
        'PENDING_PAYMENT' => ['SEARCHING_SHIPPER', 'CANCELLED'],
        'SEARCHING_SHIPPER' => ['SHIPPER_ASSIGNED', 'NO_SHIPPER_FOUND', 'CANCELLED'],
        'NO_SHIPPER_FOUND' => ['SEARCHING_SHIPPER', 'CANCELLED'],
        'SHIPPER_ASSIGNED' => ['PICKED_UP', 'SEARCHING_SHIPPER'],
        'PICKED_UP' => ['IN_DELIVERY', 'RETURNING'],
        'IN_DELIVERY' => ['DELIVERED', 'RETURNING'],
        'RETURNING' => ['RETURNED'],
        'DELIVERED' => [], 'RETURNED' => [], 'CANCELLED' => [],
    ];

    public static function lock(PDO $db, int $orderId): array
    {
        if (!$db->inTransaction()) throw new LogicException('Order operations require a transaction.');
        $q = $db->prepare('SELECT * FROM orders WHERE order_id = :id FOR UPDATE');
        $q->execute(['id' => $orderId]);
        $order = $q->fetch();
        if (!$order) throw new DeliveryApiError(404, 'ORDER_NOT_FOUND', 'Order not found.');
        return $order;
    }

    public static function history(PDO $db, int $orderId, ?string $old, string $new, ?int $actorId, string $kind, ?string $note = null): void
    {
        $q = $db->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, actor_id, actor_kind, note) VALUES (:id, :old, :new, :actor, :kind, :note)');
        $q->execute(['id' => $orderId, 'old' => $old, 'new' => $new, 'actor' => $actorId, 'kind' => $kind, 'note' => $note]);
    }

    public static function event(PDO $db, array $order, string $type, string $key, array $extra = []): void
    {
        $q = $db->prepare('INSERT INTO order_events (order_id, event_type, deduplication_key, payload) VALUES (:id, :type, :key, CAST(:payload AS jsonb)) ON CONFLICT (deduplication_key) DO NOTHING');
        $q->execute(['id' => $order['order_id'], 'type' => $type, 'key' => $key,
            'payload' => json_encode(['schema_version' => 1, 'order_id' => (string) $order['order_id'], 'order_code' => $order['order_code'], 'currency_code' => $order['currency_code'], 'total_charge_minor' => (string) $order['total_charge_minor']] + $extra, JSON_THROW_ON_ERROR)]);
    }

    /** Internal only. Caller verifies provider signature and persists PAID in the
     * same transaction, locking order BEFORE payment. No public HTTP callback. */
    public static function paymentConfirmed(PDO $db, int $orderId, int $paymentId): string
    {
        $order = self::lock($db, $orderId);
        $q = $db->prepare('SELECT * FROM payments WHERE payment_id = :payment AND order_id = :order FOR UPDATE');
        $q->execute(['payment' => $paymentId, 'order' => $orderId]);
        $payment = $q->fetch();
        if (!$payment || $payment['status'] !== 'PAID' || $payment['paid_at'] === null
            || $payment['currency_code'] !== $order['currency_code'] || (string) $payment['amount_minor'] !== (string) $order['total_charge_minor']) {
            throw new DeliveryApiError(409, 'PAYMENT_NOT_CONFIRMED', 'A verified full payment in the order currency is required.');
        }
        $q = $db->prepare('UPDATE orders SET paid_at = COALESCE(paid_at, :paid), updated_at = now() WHERE order_id = :id');
        $q->execute(['paid' => $payment['paid_at'], 'id' => $orderId]);
        if ($order['status'] === 'CANCELLED') {
            self::event($db, $order, 'RefundRequested', 'refund:cancel:' . $orderId, ['reason' => 'CUSTOMER_CANCELLATION', 'deducted_fee_minor' => '0']);
            return 'CANCELLED';
        }
        if ($order['status'] !== 'PENDING_PAYMENT') return $order['status'];
        $q = $db->prepare("UPDATE orders SET status = 'SEARCHING_SHIPPER', updated_at = now() WHERE order_id = :id");
        $q->execute(['id' => $orderId]);
        self::history($db, $orderId, 'PENDING_PAYMENT', 'SEARCHING_SHIPPER', null, 'PROVIDER');
        self::event($db, $order, 'OrderReadyForMatching', 'matching:paid:' . $orderId);
        return 'SEARCHING_SHIPPER';
    }
}
