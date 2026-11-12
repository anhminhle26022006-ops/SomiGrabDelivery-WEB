<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryService.php';
require_once __DIR__ . '/ShipperKycService.php';
require_once __DIR__ . '/PrivateStorageService.php';
require_once __DIR__.'/ShipperScoreService.php';
require_once __DIR__.'/ShipperFinanceService.php';

/** Bằng chứng giao nhận và xác nhận giao thành công. */
final class DeliveryCompletionService
{
    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PER_TYPE = 5;

    public function __construct(private PDO $db) {}

    /**
     * Link a private Storage file ID. Client-supplied file metadata is never trusted.
     * Ảnh giao hàng chỉ nhận khi đang giao, ảnh hoàn hàng chỉ nhận khi đang hoàn.
     */
    public function addEvidence(int $shipperId, int $orderId, string $type, mixed $reference, mixed $mime, mixed $size, mixed $checksum): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        $requiredStatus = $type === 'DELIVERY_PHOTO' ? 'IN_DELIVERY' : 'RETURNING';
        if ($ctx['order']['status'] !== $requiredStatus) {
            throw new DeliveryApiError(409, 'INVALID_ORDER_STATE', "$type can only be added while the order is $requiredStatus.");
        }
        $file=(new PrivateStorageService($this->db))->reference($shipperId,'SHIPPER',['reference_file_id'=>$reference,'purpose'=>'EVIDENCE','order_id'=>$orderId])['file'];
        $reference=$file['object_key'];$mime=$file['mime_type'];$size=(int)$file['byte_size'];$checksum=$file['checksum_sha256'];
        $mime = ShipperInput::choice($mime, 'mime_type', self::MIME_TYPES);
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) DeliveryInput::invalid('byte_size');
        $checksum = DeliveryInput::text($checksum, 'checksum_sha256', 64, false);
        if ($checksum !== null && !preg_match('/^[a-f0-9]{64}$/D', $checksum)) DeliveryInput::invalid('checksum_sha256');
        $deliveryId = (int) $ctx['delivery']['delivery_id'];
        $existing=$this->db->prepare('SELECT 1 FROM delivery_evidence WHERE delivery_id=? AND evidence_type=? AND object_key=?');$existing->execute([$deliveryId,$type,$reference]);
        if($existing->fetchColumn())return (new DeliveryService($this->db))->current($shipperId);
        if (DeliveryContext::evidenceCount($this->db, $deliveryId, $type) >= self::MAX_PER_TYPE) {
            throw new DeliveryApiError(409, 'EVIDENCE_LIMIT_REACHED', 'Too many evidence files for this delivery.');
        }
        $q = $this->db->prepare(
            'INSERT INTO delivery_evidence (delivery_id, evidence_type, object_key, mime_type, byte_size, checksum_sha256, uploaded_by, delete_after)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'infinity\'::timestamptz)'
        );
        $q->execute([$deliveryId, $type, $reference, $mime, $size, $checksum, $shipperId]);
        return (new DeliveryService($this->db))->current($shipperId);
    }

    /**
     * Giao thành công: IN_DELIVERY -> DELIVERED. Bắt buộc đã có ảnh giao hàng.
     * Sự kiện OrderDelivered là điểm bàn giao cho email (Người 1) và thu nhập (Người 3).
     */
    public function delivered(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        $deliveryId = (int) $ctx['delivery']['delivery_id'];
        if ($ctx['order']['status'] !== 'IN_DELIVERY') {
            throw new DeliveryApiError(409, 'INVALID_ORDER_STATE', 'Only an order in delivery can be marked delivered.');
        }
        if (DeliveryContext::evidenceCount($this->db, $deliveryId, 'DELIVERY_PHOTO') < 1) {
            throw new DeliveryApiError(409, 'EVIDENCE_REQUIRED', 'A delivery photo is required before completing the order.');
        }
        DeliveryContext::transition($this->db, $ctx['order'], 'DELIVERED', $shipperId, 'SHIPPER');
        $this->db->prepare('UPDATE orders SET completed_at = now(), closed_at = now() WHERE order_id = ?')->execute([$orderId]);
        $this->db->prepare('UPDATE deliveries SET delivered_at = now(), updated_at = now() WHERE delivery_id = ?')->execute([$deliveryId]);
        $this->db->prepare("UPDATE order_assignments SET assignment_status = 'COMPLETED', ended_at = now(), ended_reason = 'DELIVERED' WHERE assignment_id = ?")->execute([$ctx['assignment']['assignment_id']]);
        OrderLifecycleService::event($this->db, $ctx['order'], 'OrderDelivered', 'delivered:' . $orderId,
            ['assignment_id' => (string) $ctx['assignment']['assignment_id'], 'shipper_id' => (string) $shipperId]);
        (new ShipperScoreService($this->db))->recovery($orderId);
        $settlement=(new ShipperFinanceService($this->db))->settleOrder($orderId);
        return ['order_id' => $orderId, 'status' => 'DELIVERED','settlement'=>$settlement];
    }
}
