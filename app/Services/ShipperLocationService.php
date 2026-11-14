<?php
declare(strict_types=1);
require_once __DIR__ . '/AccessControl.php';
require_once __DIR__ . '/ShipperInput.php';
require_once __DIR__ . '/DeliveryService.php';

/** GPS theo đơn đang giao: ghi điểm, đọc điểm mới nhất và xóa dữ liệu hết hạn lưu. */
final class ShipperLocationService
{
    /** Chỉ theo dõi vị trí khi đơn đang được thực hiện. */
    private const TRACKED_STATUSES = ['SHIPPER_ASSIGNED', 'PICKED_UP', 'IN_DELIVERY', 'RETURNING'];

    public function __construct(private PDO $db) {}

    /**
     * Ghi một điểm GPS. Shipper phải có assignment đang hoạt động; hệ thống tự gắn điểm
     * vào đơn đó, trình duyệt không được chọn order_id. Gửi dày hơn mức cho phép bị trả 429.
     */
    public function record(int $shipperId, mixed $latitude, mixed $longitude, mixed $accuracy): array
    {
        $latitude = ShipperInput::coordinate($latitude, 'latitude', 90);
        $longitude = ShipperInput::coordinate($longitude, 'longitude', 180);
        if ($accuracy !== null && (!is_numeric($accuracy) || is_string($accuracy) || $accuracy < 0 || $accuracy > 100000)) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'Invalid field: accuracy_meters.');
        }
        $q = $this->db->prepare(
            "SELECT a.order_id FROM order_assignments a JOIN orders o ON o.order_id = a.order_id
             WHERE a.shipper_id = ? AND a.assignment_status = 'ACTIVE' AND o.status IN ('SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING')"
        );
        $q->execute([$shipperId]);
        $orderId = $q->fetchColumn();
        if ($orderId === false) throw new DeliveryApiError(409, 'NO_ACTIVE_DELIVERY', 'Location is only recorded during an active delivery.');
        $ctx=DeliveryContext::lockActive($this->db,$shipperId,(int)$orderId);

        $q = $this->db->prepare('SELECT 1 FROM shipper_locations WHERE shipper_id = ? AND recorded_at > now() - make_interval(secs => ?) LIMIT 1');
        $q->execute([$shipperId, ShipperInput::config('gps_min_interval_seconds')]);
        if ($q->fetchColumn()) throw new DeliveryApiError(429, 'LOCATION_RATE_LIMITED', 'Location updates are too frequent.');

        $q = $this->db->prepare(
            'INSERT INTO shipper_locations (shipper_id, order_id, latitude, longitude, accuracy_meters, delete_after)
             VALUES (?, ?, ?, ?, ?, now() + make_interval(days => ?)) RETURNING recorded_at'
        );
        $q->execute([$shipperId, $orderId, $latitude, $longitude, $accuracy === null ? null : round((float) $accuracy, 2), ShipperInput::config('gps_retention_days')]);
        $recordedAt = $q->fetchColumn();
        $this->db->prepare('UPDATE shipper_profiles SET last_latitude = ?, last_longitude = ?, last_location_at = now(), last_active_at = now() WHERE user_id = ?')->execute([$latitude, $longitude, $shipperId]);
        $arrivedAt=$ctx['delivery']['arrived_at'];
        if ($ctx['order']['status']==='IN_DELIVERY' && !$arrivedAt && $accuracy!==null && $accuracy<=200
            && DeliveryService::distanceMeters((float)$latitude,(float)$longitude,(float)$ctx['order']['delivery_latitude'],(float)$ctx['order']['delivery_longitude'])<=200) {
            $result=(new DeliveryService($this->db))->arrive($shipperId,(int)$orderId);
            $arrivedAt=$result['delivery']['arrived_at'];
        }
        return ['order_id' => (int) $orderId, 'recorded_at' => $recordedAt,'arrived_at'=>$arrivedAt];
    }

    /**
     * Vị trí mới nhất của Shipper trên một đơn. Customer chỉ xem được đơn của mình, Shipper chỉ
     * xem được đơn đang được phân công (AccessControl::requireOrder). Khi đơn kết thúc thì không trả vị trí nữa.
     */
    public function latest(int $userId, string $role, int $orderId): array
    {
        $order = AccessControl::requireOrder($this->db, $userId, $role, $orderId);
        if (!in_array($order['status'], self::TRACKED_STATUSES, true)) return ['order_id' => $orderId, 'status' => $order['status'], 'location' => null];
        $q = $this->db->prepare(
            "SELECT l.latitude, l.longitude, l.accuracy_meters, l.recorded_at
             FROM shipper_locations l
             JOIN order_assignments a ON a.order_id = l.order_id AND a.shipper_id = l.shipper_id AND a.assignment_status = 'ACTIVE'
             WHERE l.order_id = ? AND l.deleted_at IS NULL AND l.delete_after > now()
             ORDER BY l.recorded_at DESC LIMIT 1"
        );
        $q->execute([$orderId]);
        $location=$q->fetch()?:null;
        $q=$this->db->prepare("SELECT d.arrived_at FROM deliveries d JOIN order_assignments a USING(assignment_id) WHERE a.order_id=? AND a.assignment_status='ACTIVE'");$q->execute([$orderId]);
        return ['order_id' => $orderId, 'status' => $order['status'], 'location' => $location,'arrived_at'=>$q->fetchColumn()?:null];
    }

    /** Xóa hẳn các điểm GPS quá hạn lưu, theo lô để không khóa bảng lâu. Trả về số dòng đã xóa. */
    public function purgeExpired(int $batch = 5000): int
    {
        $q = $this->db->prepare('DELETE FROM shipper_locations WHERE location_id IN (SELECT location_id FROM shipper_locations WHERE delete_after <= now() ORDER BY location_id LIMIT ?)');
        $q->execute([$batch]);
        return $q->rowCount();
    }
}
