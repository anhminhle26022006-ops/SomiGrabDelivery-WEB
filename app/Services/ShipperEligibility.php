<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryApiError.php';

/**
 * Điều kiện hoạt động của Shipper, kiểm tra tại backend.
 * Mọi chỗ cần biết "Shipper này có được nhận đơn không" đều gọi class này,
 * để availability, matching và accept dùng chung một bộ quy tắc.
 */
final class ShipperEligibility
{
    /** Hồ sơ Shipper kèm trạng thái tài khoản và giấy tờ. $lock = true khi sắp cập nhật hồ sơ. */
    public static function profile(PDO $db, int $shipperId, bool $lock = false): array
    {
        $q = $db->prepare(
            'SELECT sp.*, u.account_status, u.full_name, d.review_status AS identity_review_status,
                    d.rejection_reason AS identity_rejection_reason
             FROM shipper_profiles sp
             JOIN users u ON u.user_id = sp.user_id
             LEFT JOIN user_identity_documents d ON d.user_id = sp.user_id
             WHERE sp.user_id = ?' . ($lock ? ' FOR UPDATE OF sp' : '')
        );
        $q->execute([$shipperId]);
        $profile = $q->fetch();
        if (!$profile) throw new DeliveryApiError(404, 'SHIPPER_PROFILE_REQUIRED', 'Shipper profile not found.');
        return $profile;
    }

    /** Danh sách lý do Shipper chưa được hoạt động. Mảng rỗng nghĩa là đủ điều kiện. */
    public static function blockers(PDO $db, array $profile): array
    {
        $blockers = [];
        if ($profile['account_status'] !== 'ACTIVE') $blockers[] = 'ACCOUNT_NOT_ACTIVE';
        if ($profile['verification_status'] !== 'APPROVED') $blockers[] = 'KYC_NOT_APPROVED';
        if ($profile['identity_review_status'] !== 'APPROVED') $blockers[] = 'IDENTITY_NOT_APPROVED';
        // Chỉ khóa theo điểm khi Admin đã ban hành chính sách điểm; không tự đặt ngưỡng.
        $q = $db->query(
            'SELECT lock_threshold FROM shipper_score_policies
             WHERE effective_from <= now() AND (effective_until IS NULL OR effective_until > now())
             ORDER BY effective_from DESC LIMIT 1'
        );
        $threshold = $q->fetchColumn();
        if ($threshold !== false && (float) $profile['reliability_score'] < (float) $threshold) $blockers[] = 'SCORE_LOCKED';
        return $blockers;
    }

    public static function assertCanWork(PDO $db, array $profile): void
    {
        $blockers = self::blockers($db, $profile);
        if ($blockers) {
            throw new DeliveryApiError(403, 'SHIPPER_NOT_ELIGIBLE', 'Shipper is not eligible to work: ' . implode(', ', $blockers) . '.');
        }
    }
}
