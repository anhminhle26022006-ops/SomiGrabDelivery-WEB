<?php
declare(strict_types=1);
require_once __DIR__.'/ShipperKycService.php';

/** A complete application is validated and saved in the workspace-switch transaction. */
final class ShipperApplicationService
{
    public const CONSENT_VERSION = 'shipper-onboarding-v1';
    public function __construct(private PDO $db) {}

    public static function validate(array $in, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $out = [];
        foreach (['residential_address'=>255,'operating_area'=>120,'emergency_contact_name'=>100,
                  'emergency_contact_relationship'=>60,'vehicle_brand'=>80,'vehicle_model'=>80,
                  'vehicle_color'=>40] as $key=>$max) {
            $out[$key] = DeliveryInput::text($in[$key] ?? null, $key, $max);
        }
        $out['emergency_contact_phone'] = DeliveryInput::phone($in['emergency_contact_phone'] ?? null);
        $out['vehicle_type'] = ShipperInput::choice($in['vehicle_type'] ?? null, 'vehicle_type', ['MOTORBIKE','CAR','BICYCLE']);
        $year = $in['vehicle_year'] ?? null;
        if (!is_string($year) || !preg_match('/^\d{4}$/D', $year) || (int)$year < 1980 || (int)$year > (int)$today->format('Y') + 1) DeliveryInput::invalid('vehicle_year');
        $out['vehicle_year'] = (int)$year;
        $out['vehicle_owner_name'] = DeliveryInput::text($in['vehicle_owner_name'] ?? null, 'vehicle_owner_name', 100);
        $out['vehicle_ownership'] = ShipperInput::choice($in['vehicle_ownership'] ?? null, 'vehicle_ownership', ['OWNED','BORROWED']);
        $motorized = $out['vehicle_type'] !== 'BICYCLE';
        if ($motorized) {
            $plate = strtoupper(preg_replace('/\s+/', '', DeliveryInput::text($in['vehicle_registration'] ?? null, 'vehicle_registration', 40)));
            if (!preg_match('/^[A-Z0-9][A-Z0-9.\-]{3,39}$/D', $plate)) DeliveryInput::invalid('vehicle_registration');
            $out['vehicle_registration'] = $plate;
            $number = DeliveryInput::text($in['license_number'] ?? null, 'license_number', 40);
            if (!preg_match('/^[A-Za-z0-9\-]{4,40}$/D', $number)) DeliveryInput::invalid('license_number');
            $out['license_number'] = $number;
            // Class-to-vehicle entitlement is checked by the reviewer against the uploaded licence.
            $out['license_class'] = DeliveryInput::text($in['license_class'] ?? null, 'license_class', 20);
            $out['license_issued_on'] = self::date($in['license_issued_on'] ?? null, 'license_issued_on');
            if ($out['license_issued_on'] > $today->format('Y-m-d')) DeliveryInput::invalid('license_issued_on');
            if (!is_bool($in['license_no_expiry'] ?? null)) DeliveryInput::invalid('license_no_expiry');
            $out['license_no_expiry'] = $in['license_no_expiry'];
            $out['license_expires_on'] = $out['license_no_expiry'] ? null : self::date($in['license_expires_on'] ?? null, 'license_expires_on');
            if ($out['license_expires_on'] !== null && ($out['license_expires_on'] <= $today->format('Y-m-d') || $out['license_expires_on'] <= $out['license_issued_on'])) DeliveryInput::invalid('license_expires_on');
            $out['insurance_expires_on'] = self::date($in['insurance_expires_on'] ?? null, 'insurance_expires_on');
            if ($out['insurance_expires_on'] <= $today->format('Y-m-d')) DeliveryInput::invalid('insurance_expires_on');
        }
        if (($in['consent'] ?? null) !== true) DeliveryInput::invalid('consent');
        $out['consent_version'] = self::CONSENT_VERSION;
        $documents = $in['documents'] ?? null;
        if (!is_array($documents) || array_values($documents) !== $documents || count($documents) > 8) DeliveryInput::invalid('documents');
        $required = ['IDENTITY_FRONT','IDENTITY_BACK','PORTRAIT'];
        if ($motorized) array_push($required, 'DRIVER_LICENSE','DRIVER_LICENSE_BACK','VEHICLE_REGISTRATION','VEHICLE_INSURANCE');
        if ($out['vehicle_ownership'] === 'BORROWED') $required[] = 'VEHICLE_AUTHORIZATION';
        $seen = []; $ids = [];
        foreach ($documents as $doc) {
            if (!is_array($doc)) DeliveryInput::invalid('documents');
            DeliveryInput::only($doc, ['document_type','reference_file_id']);
            $type = $doc['document_type'] ?? '';
            if (!is_string($type) || !in_array($type, $required, true) || isset($seen[$type])) DeliveryInput::invalid('document_type');
            $id = DeliveryInput::id($doc['reference_file_id'] ?? null);
            if (in_array($id, $ids, true)) DeliveryInput::invalid('documents: duplicate file');
            $ids[] = $id; $seen[$type] = true;
        }
        if (array_diff($required, array_keys($seen))) DeliveryInput::invalid('documents: '.implode(', ', array_diff($required, array_keys($seen))));
        $out['documents'] = $documents;
        return $out;
    }

    private static function date(mixed $value, string $field): string
    {
        if (!is_string($value)) DeliveryInput::invalid($field);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) DeliveryInput::invalid($field);
        return $value;
    }

    public function assertApplicant(int $user): void
    {
        $q = $this->db->prepare('SELECT date_of_birth, email_verified_at, phone_verified_at FROM users WHERE user_id=?');
        $q->execute([$user]); $row = $q->fetch();
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh'));
        if (!$row || !$row['date_of_birth'] || new DateTimeImmutable($row['date_of_birth']) > $today->modify('-18 years')) {
            throw new DeliveryApiError(422, 'APPLICANT_AGE_REQUIRED', 'Bạn cần đủ 18 tuổi để đăng ký Shipper.');
        }
        if (!$row['email_verified_at'] || !$row['phone_verified_at']) throw new DeliveryApiError(422, 'CONTACT_VERIFICATION_REQUIRED', 'Vui lòng xác thực email và số điện thoại trước khi đăng ký.');
    }

    public function save(int $user, array $details): void
    {
        if (!$this->db->inTransaction()) throw new LogicException('Application requires a transaction.');
        $documents = $details['documents']; unset($details['documents']);
        $hashes = [];
        foreach ($documents as $document) {
            $file = (new PrivateStorageService($this->db))->reference($user, 'SHIPPER', ['reference_file_id'=>$document['reference_file_id'], 'purpose'=>'KYC'])['file'];
            if ($document['document_type'] === 'PORTRAIT' && !str_starts_with($file['mime_type'], 'image/')) DeliveryInput::invalid('PORTRAIT: image required');
            if (in_array($file['checksum_sha256'], $hashes, true)) DeliveryInput::invalid('documents: duplicate content');
            $hashes[] = $file['checksum_sha256'];
        }
        if (isset($details['license_number'])) {
            $details['license_number_ciphertext'] = IdentityDocumentVault::encrypt($details['license_number']);
            unset($details['license_number']);
        }
        $details['consented_at'] = gmdate(DATE_ATOM);
        $this->db->prepare('UPDATE shipper_profiles SET application_details=CAST(? AS jsonb),application_submitted_at=now() WHERE user_id=?')->execute([json_encode($details, JSON_THROW_ON_ERROR), $user]);
        // Superseded documents remain in audit history; current review sees only this submission.
        $this->db->prepare('UPDATE shipper_documents SET deleted_at=now() WHERE shipper_id=? AND deleted_at IS NULL')->execute([$user]);
        $kyc = new ShipperKycService($this->db);
        foreach ($documents as $document) $kyc->storeDocument($user, $document);
        AuditService::record($this->db, $user, 'SHIPPER', 'SHIPPER_APPLICATION_SUBMITTED', 'SHIPPER_PROFILE', $user, [], ['document_count'=>count($documents),'consent_version'=>self::CONSENT_VERSION]);
    }

    public function assertReviewable(int $user): void
    {
        $q = $this->db->prepare('SELECT application_details,vehicle_type,vehicle_registration FROM shipper_profiles WHERE user_id=?'); $q->execute([$user]);
        $profile = $q->fetch();
        $details = json_decode($profile['application_details'] ?? 'null', true);
        if (!is_array($details)) throw new DeliveryApiError(409, 'APPLICATION_INCOMPLETE', 'Hồ sơ chưa đủ thông tin. Yêu cầu Shipper bổ sung hồ sơ mới.');
        if (($details['vehicle_type'] ?? null) !== $profile['vehicle_type'] || ($details['vehicle_type'] !== 'BICYCLE' && ($details['vehicle_registration'] ?? null) !== $profile['vehicle_registration'])) {
            throw new DeliveryApiError(409, 'APPLICATION_VEHICLE_CHANGED', 'Phương tiện đã thay đổi. Yêu cầu nộp lại hồ sơ đúng phương tiện hiện tại.');
        }
        $q = $this->db->prepare("SELECT d.document_type, f.file_id FROM shipper_documents d JOIN private_files f ON f.object_key=d.object_key AND f.owner_id=d.shipper_id WHERE d.shipper_id=? AND d.deleted_at IS NULL AND (d.delete_after IS NULL OR d.delete_after>now()) AND f.purpose='KYC' AND f.deleted_at IS NULL AND f.delete_after>now() FOR UPDATE OF d,f");
        $q->execute([$user]);
        $details['documents'] = array_map(static fn($r)=>['document_type'=>$r['document_type'],'reference_file_id'=>$r['file_id']], $q->fetchAll());
        // Validation of licence number was completed at submission; encrypted value stays private.
        $details['license_number'] = isset($details['license_number_ciphertext']) ? 'ENCRYPTED' : '';
        $details['vehicle_year'] = (string)($details['vehicle_year'] ?? '');
        $details['consent'] = ($details['consent_version'] ?? null) === self::CONSENT_VERSION;
        self::validate($details);
        $this->assertApplicant($user);
    }

    public function forAdmin(int $admin, int $user): array
    {
        AccessControl::requirePermission($this->db, $admin, 'ADMIN', 'shipper.verify');
        $q=$this->db->prepare('SELECT application_details,application_submitted_at FROM shipper_profiles WHERE user_id=?');$q->execute([$user]);$row=$q->fetch();
        $details=json_decode($row['application_details'] ?? 'null',true);
        if ($details) unset($details['license_number_ciphertext']);
        AuditService::record($this->db,$admin,'ADMIN','SHIPPER_APPLICATION_READ','SHIPPER_PROFILE',$user);
        return ['details'=>$details,'submitted_at'=>$row['application_submitted_at'] ?? null];
    }
}
