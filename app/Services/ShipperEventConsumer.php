<?php
declare(strict_types=1);
require_once __DIR__ . '/MatchingService.php';

/**
 * Consumer của Người 2 trên bảng order_events.
 * Dùng biên nhận riêng (consumer = 'shipper-matching') nên không ảnh hưởng tới
 * consumer email/refund của Người 1: mỗi module tự ghi nhận sự kiện mình đã xử lý.
 */
final class ShipperEventConsumer
{
    private const CONSUMER = 'shipper-matching';

    /** Xử lý tối đa một sự kiện. Trả về false khi không còn sự kiện nào chờ. */
    public static function consumeOne(PDO $db): bool
    {
        if ($db->inTransaction()) throw new LogicException('Event worker owns its transaction.');
        $db->beginTransaction();
        try {
            $due = OrderSchedule::dueSql(); $priority = OrderSchedule::prioritySql();
            // SKIP LOCKED cho phép chạy nhiều worker song song mà không xử lý trùng một sự kiện.
            $q = $db->prepare(
                "SELECT e.event_id, e.order_id FROM order_events e JOIN orders o ON o.order_id=e.order_id
                 WHERE e.event_type = 'OrderReadyForMatching'
                   AND ($due OR o.status <> 'SEARCHING_SHIPPER')
                   AND NOT EXISTS (SELECT 1 FROM event_consumer_receipts r WHERE r.consumer = ? AND r.event_id = e.event_id)
                 ORDER BY $priority, e.event_id FOR UPDATE OF e SKIP LOCKED LIMIT 1"
            );
            $q->execute([self::CONSUMER]);
            $event = $q->fetch();
            if (!$event) { $db->commit(); return false; }
            (new MatchingService($db))->dispatch((int) $event['order_id']);
            // Biên nhận và offer nằm chung một transaction: hoặc cả hai được ghi, hoặc không gì cả.
            $db->prepare('INSERT INTO event_consumer_receipts (consumer, event_id) VALUES (?, ?)')->execute([self::CONSUMER, $event['event_id']]);
            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Dọn offer hết hạn rồi gửi vòng kế tiếp cho các đơn còn đang tìm Shipper. */
    public static function sweep(PDO $db): array
    {
        $matching = new MatchingService($db);
        $db->beginTransaction();
        try { $expired = $matching->expireOffers(); $db->commit(); }
        catch (Throwable $e) { $db->rollBack(); throw $e; }

        $results = [];
        foreach ($matching->ordersNeedingDispatch() as $orderId) {
            // Mỗi đơn một transaction riêng: một đơn lỗi không kéo các đơn khác rollback theo.
            $db->beginTransaction();
            try { $results[$orderId] = $matching->dispatch($orderId)['result']; $db->commit(); }
            catch (Throwable $e) { $db->rollBack(); $results[$orderId] = 'ERROR'; error_log('[SOMI matching] order ' . $orderId . ' ' . get_class($e)); }
        }
        return ['expired' => $expired, 'dispatched' => $results];
    }
}
