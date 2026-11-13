<?php
declare(strict_types=1);
require_once __DIR__ . '/ShipperProfileService.php';
require_once __DIR__ . '/ShipperInput.php';
require_once __DIR__ . '/IdentityDocumentVault.php';
require_once __DIR__ . '/PrivateStorageService.php';

/**
 * Nộp lại hồ sơ KYC và xem trạng thái duyệt.
 * Người 2 chỉ lo phần NỘP; việc duyệt thuộc API admin/review-shipper.php của Người 1.
 */
final class ShipperKycService
{
    private const DOCUMENT_TYPES = ['IDENTITY_FRONT', 'IDENTITY_BACK', 'DRIVER_LICENSE', 'VEHICLE_REGISTRATION', 'PORTRAIT', 'DRIVER_LICENSE_BACK', 'VEHICLE_INSURANCE', 'VEHICLE_AUTHORIZATION'];
    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private PDO $db) {}

    /** Trạng thái duyệt hiện tại, lý do bị trả về và các tài liệu đã nộp. */
    public function status(int $shipperId): array
    {
        $profile = ShipperEligibility::profile($this->db, $shipperId);
        $q = $this->db->prepare(
            'SELECT document_id, document_type, original_name, mime_type, byte_size, review_status, rejection_reason, uploaded_at
             FROM shipper_documents WHERE shipper_id = ? AND deleted_at IS NULL ORDER BY document_id DESC LIMIT 50'
        );
        $q->execute([$shipperId]);
        return [
            'verification_status' => $profile['verification_status'],
            'identity_review_status' => $profile['identity_review_status'],
            'rejection_reason' => $profile['identity_rejection_reason'],
            'can_resubmit' => in_array($profile['verification_status'], ['NEEDS_INFO', 'REJECTED'], true),
            'documents' => $q->fetchAll(),
        ];
    }

    /**
     * Nộp lại hồ sơ sau khi Admin trả về (NEEDS_INFO) hoặc từ chối (REJECTED).
     * $identity = null nghĩa là giữ nguyên giấy tờ định danh, chỉ bổ sung tài liệu.
     * $documents contains private Storage file IDs, validated for owner and purpose.
     */
    public function resubmit(int $shipperId, ?array $identity, array $documents): array
    {
        $profile = ShipperEligibility::profile($this->db, $shipperId, true);
        if (!in_array($profile['verification_status'], ['NEEDS_INFO', 'REJECTED'], true)) {
            throw new DeliveryApiError(409, 'KYC_NOT_RESUBMITTABLE', 'The application can only be resubmitted after Admin asks for changes.');
        }
        if ($identity === null && $profile['identity_review_status'] === null) {
            throw new DeliveryApiError(422, 'KYC_IDENTITY_REQUIRED', 'Identity data is required.');
        }
        if ($identity === null && !$documents) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'Submit updated identity data or at least one document.');
        }
        if (count($documents) > 10) throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'At most 10 documents per submission.');

        if ($identity !== null) $this->storeIdentity($shipperId, $identity);
        else $this->db->prepare("UPDATE user_identity_documents SET review_status = 'PENDING', rejection_reason = NULL, reviewed_by = NULL, reviewed_at = NULL, updated_at = now() WHERE user_id = ?")->execute([$shipperId]);
        foreach ($documents as $document) $this->storeDocument($shipperId, $document);

        $this->db->prepare("UPDATE shipper_profiles SET verification_status = 'PENDING', availability = FALSE, approved_at = NULL, approved_by = NULL, updated_at = now() WHERE user_id = ?")->execute([$shipperId]);
        AuditService::record($this->db, $shipperId, 'SHIPPER', 'SHIPPER_KYC_RESUBMITTED', 'SHIPPER_PROFILE', $shipperId,
            ['verification_status' => $profile['verification_status']],
            ['verification_status' => 'PENDING', 'identity_updated' => $identity !== null, 'documents' => count($documents)]);
        return $this->status($shipperId);
    }

    /** Số giấy tờ được mã hóa bằng IdentityDocumentVault trước khi lưu; không ghi vào audit hay log. */
    private function storeIdentity(int $shipperId, array $identity): void
    {
        DeliveryInput::only($identity, ['document_type', 'document_country_code', 'identity_number']);
        $type = ShipperInput::choice($identity['document_type'] ?? null, 'document_type', ['NATIONAL_ID', 'PASSPORT', 'FOREIGN_ID']);
        $country = strtoupper(DeliveryInput::text($identity['document_country_code'] ?? null, 'document_country_code', 2));
        $number = DeliveryInput::text($identity['identity_number'] ?? null, 'identity_number', 40);
        if (!preg_match('/^[A-Z]{2}$/D', $country)) DeliveryInput::invalid('document_country_code');
        if (!preg_match('/^[A-Za-z0-9]{4,40}$/D', $number)) DeliveryInput::invalid('identity_number');
        $q = $this->db->prepare(
            "INSERT INTO user_identity_documents (user_id, document_type, document_country_code, identity_number_ciphertext, review_status)
             VALUES (?, ?, ?, ?, 'PENDING')
             ON CONFLICT (user_id) DO UPDATE SET document_type = EXCLUDED.document_type,
                 document_country_code = EXCLUDED.document_country_code,
                 identity_number_ciphertext = EXCLUDED.identity_number_ciphertext,
                 review_status = 'PENDING', rejection_reason = NULL, reviewed_by = NULL, reviewed_at = NULL, updated_at = now()"
        );
        $q->execute([$shipperId, $type, $country, IdentityDocumentVault::encrypt($number)]);
    }

    public function storeDocument(int $shipperId, mixed $document): void
    {
        if (!is_array($document)) DeliveryInput::invalid('documents');
        DeliveryInput::only($document, ['document_type', 'reference_file_id']);
        $type = ShipperInput::choice($document['document_type'] ?? null, 'document_type', self::DOCUMENT_TYPES);
        $file=(new PrivateStorageService($this->db))->reference($shipperId,'SHIPPER',['reference_file_id'=>$document['reference_file_id']??null,'purpose'=>'KYC'])['file'];
        $reference=$file['object_key'];$mime=$file['mime_type'];$size=(int)$file['byte_size'];
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) DeliveryInput::invalid('byte_size');
        $checksum = $file['checksum_sha256'];
        if ($checksum !== null && !preg_match('/^[a-f0-9]{64}$/D', $checksum)) DeliveryInput::invalid('checksum_sha256');
        $q = $this->db->prepare('INSERT INTO shipper_documents (shipper_id, document_type, object_key, original_name, mime_type, byte_size, checksum_sha256) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $q->execute([$shipperId, $type, $reference, $file['original_name'], $mime, $size, $checksum]);
    }

    /** Tham chiếu file do tầng Storage cấp. Chặn URL và đường dẫn "leo thư mục". */
    public static function fileReference(mixed $value): string
    {
        $reference = DeliveryInput::text($value, 'file_reference', 200);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{7,199}$/D', $reference) || str_contains($reference, '..') || str_contains($reference, '//')) {
            DeliveryInput::invalid('file_reference');
        }
        return $reference;
    }
}
