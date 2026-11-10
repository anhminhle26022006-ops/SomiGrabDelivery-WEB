<?php
declare(strict_types=1);
require_once __DIR__ . '/ShipperProfileService.php';
require_once __DIR__ . '/ShipperInput.php';

/** Bật/tắt trạng thái nhận đơn. Trạng thái thật nằm ở database, không nằm ở trình duyệt. */
final class ShipperAvailabilityService
{
    public function __construct(private PDO $db) {}

    public function status(int $shipperId): array
    {
        $profile = ShipperEligibility::profile($this->db, $shipperId);
        $blockers = ShipperEligibility::blockers($this->db, $profile);
        return [
            'availability' => (bool) $profile['availability'],
            'can_work' => !$blockers,
            'blockers' => $blockers,
            'last_location_at' => $profile['last_location_at'],
        ];
    }

    /**
     * Bật nhận đơn: phải đủ điều kiện hoạt động và gửi kèm vị trí hiện tại,
     * vì matching chọn Shipper theo khoảng cách tới điểm lấy hàng.
     * Gọi lại khi đang bật có tác dụng như "heartbeat" cập nhật vị trí.
     */
    public function turnOn(int $shipperId, mixed $latitude, mixed $longitude): array
    {
        $latitude = ShipperInput::coordinate($latitude, 'latitude', 90);
        $longitude = ShipperInput::coordinate($longitude, 'longitude', 180);
        $profile = ShipperEligibility::profile($this->db, $shipperId, true);
        ShipperEligibility::assertCanWork($this->db, $profile);
        $q = $this->db->prepare('UPDATE shipper_profiles SET availability = TRUE, last_latitude = ?, last_longitude = ?, last_location_at = now(), last_active_at = now(), updated_at = now() WHERE user_id = ?');
        $q->execute([$latitude, $longitude, $shipperId]);
        if (!$profile['availability']) {
            AuditService::record($this->db, $shipperId, 'SHIPPER', 'SHIPPER_AVAILABILITY_ON', 'SHIPPER_PROFILE', $shipperId, ['availability' => false], ['availability' => true]);
        }
        return $this->status($shipperId);
    }

    /** Tắt nhận đơn: luôn được phép. Offer đang chờ bị hủy để đơn được chuyển cho người khác. */
    public function turnOff(int $shipperId): array
    {
        // Thứ tự khóa: offer trước, hồ sơ sau (giống lúc nhận đơn) để tránh deadlock.
        (new ShipperProfileService($this->db))->cancelPendingOffers($shipperId, 'AVAILABILITY_OFF');
        $profile = ShipperEligibility::profile($this->db, $shipperId, true);
        $this->db->prepare('UPDATE shipper_profiles SET availability = FALSE, updated_at = now() WHERE user_id = ?')->execute([$shipperId]);
        if ($profile['availability']) {
            AuditService::record($this->db, $shipperId, 'SHIPPER', 'SHIPPER_AVAILABILITY_OFF', 'SHIPPER_PROFILE', $shipperId, ['availability' => true], ['availability' => false]);
        }
        return $this->status($shipperId);
    }
}
