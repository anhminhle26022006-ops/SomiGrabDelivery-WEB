<?php
declare(strict_types=1);
require_once __DIR__ . '/ShipperEligibility.php';
require_once __DIR__ . '/DeliveryInput.php';
require_once __DIR__ . '/AuditService.php';

/** Hồ sơ và phương tiện của Shipper. Dùng lại dữ liệu đã có từ bước đăng ký. */
final class ShipperProfileService
{
    public function __construct(private PDO $db) {}

    /** Hồ sơ hiển thị cho chính Shipper. Không bao giờ trả số giấy tờ. */
    public function detail(int $shipperId): array
    {
        $profile = ShipperEligibility::profile($this->db, $shipperId);
        $blockers = ShipperEligibility::blockers($this->db, $profile);
        $q = $this->db->prepare("SELECT 1 FROM order_assignments WHERE shipper_id = ? AND assignment_status = 'ACTIVE'");
        $q->execute([$shipperId]);
        return [
            'profile' => [
                'full_name' => $profile['full_name'],
                'vehicle_type' => $profile['vehicle_type'],
                'vehicle_registration' => $profile['vehicle_registration'],
                'verification_status' => $profile['verification_status'],
                'identity_review_status' => $profile['identity_review_status'],
                'availability' => (bool) $profile['availability'],
                'reliability_score' => $profile['reliability_score'],
                'customer_rating_average' => $profile['customer_rating_average'],
                'customer_rating_count' => (int) $profile['customer_rating_count'],
                'approved_at' => $profile['approved_at'],
            ],
            'eligibility' => ['can_work' => !$blockers, 'blockers' => $blockers],
            'has_active_delivery' => (bool) $q->fetchColumn(),
            'account' => $this->account($shipperId),
        ];
    }

    /**
     * Thông tin liên hệ của chính Shipper để hiển thị ở trang Tài khoản.
     * Chỉ đọc: đổi email hoặc số điện thoại phải qua luồng xác minh của account/profile.php.
     */
    private function account(int $shipperId): array
    {
        $q = $this->db->prepare('SELECT email, phone, created_at FROM users WHERE user_id = ?');
        $q->execute([$shipperId]);
        $user = $q->fetch();
        return [
            // Mã hiển thị cho Shipper, dựng từ user_id để hỗ trợ tra cứu khi liên hệ Admin.
            'code' => 'SH-' . str_pad((string) $shipperId, 5, '0', STR_PAD_LEFT),
            'email' => $user['email'],
            'phone' => $user['phone'],
            'joined_at' => $user['created_at'],
        ];
    }

    /**
     * Đổi thông tin phương tiện. Phương tiện là một phần của hồ sơ đã duyệt, nên
     * nếu hồ sơ đang APPROVED thì sau khi đổi phải chờ Admin duyệt lại và tạm tắt nhận đơn.
     */
    public function updateVehicle(int $shipperId, mixed $vehicleType, mixed $vehicleRegistration): array
    {
        $vehicleType = DeliveryInput::text($vehicleType, 'vehicle_type', 80);
        $vehicleRegistration = strtoupper(preg_replace('/\s+/', '', DeliveryInput::text($vehicleRegistration, 'vehicle_registration', 40)));
        $profile = ShipperEligibility::profile($this->db, $shipperId, true);
        $this->assertNoActiveDelivery($shipperId);
        if ($profile['vehicle_type'] === $vehicleType && $profile['vehicle_registration'] === $vehicleRegistration) {
            return $this->detail($shipperId);
        }
        $q = $this->db->prepare('SELECT 1 FROM shipper_profiles WHERE vehicle_registration = ? AND user_id <> ?');
        $q->execute([$vehicleRegistration, $shipperId]);
        if ($q->fetchColumn()) throw new DeliveryApiError(409, 'VEHICLE_REGISTRATION_TAKEN', 'That vehicle registration belongs to another Shipper.');

        $newStatus = $profile['verification_status'] === 'APPROVED' ? 'PENDING' : $profile['verification_status'];
        $q = $this->db->prepare(
            'UPDATE shipper_profiles SET vehicle_type = ?, vehicle_registration = ?, verification_status = ?,
                    availability = FALSE, approved_at = CASE WHEN ? THEN approved_at ELSE NULL END,
                    approved_by = CASE WHEN ? THEN approved_by ELSE NULL END, updated_at = now()
             WHERE user_id = ?'
        );
        $keepApproval = $newStatus === 'APPROVED' ? 'true' : 'false';
        $q->execute([$vehicleType, $vehicleRegistration, $newStatus, $keepApproval, $keepApproval, $shipperId]);
        $this->cancelPendingOffers($shipperId, 'VEHICLE_CHANGED');
        AuditService::record($this->db, $shipperId, 'SHIPPER', 'SHIPPER_VEHICLE_UPDATED', 'SHIPPER_PROFILE', $shipperId,
            ['vehicle_type' => $profile['vehicle_type'], 'vehicle_registration' => $profile['vehicle_registration'], 'verification_status' => $profile['verification_status']],
            ['vehicle_type' => $vehicleType, 'vehicle_registration' => $vehicleRegistration, 'verification_status' => $newStatus]);
        return $this->detail($shipperId);
    }

    public function assertNoActiveDelivery(int $shipperId): void
    {
        $q = $this->db->prepare("SELECT 1 FROM order_assignments WHERE shipper_id = ? AND assignment_status = 'ACTIVE'");
        $q->execute([$shipperId]);
        if ($q->fetchColumn()) throw new DeliveryApiError(409, 'ACTIVE_DELIVERY_EXISTS', 'Finish the current delivery first.');
    }

    /** Khi Shipper không còn nhận đơn được nữa thì các offer đang chờ cũng không còn giá trị. */
    public function cancelPendingOffers(int $shipperId, string $note): void
    {
        $q = $this->db->prepare("UPDATE order_match_offers SET status = 'CANCELLED', responded_at = now(), response_note = ? WHERE shipper_id = ? AND status = 'PENDING'");
        $q->execute([$note, $shipperId]);
    }
}
