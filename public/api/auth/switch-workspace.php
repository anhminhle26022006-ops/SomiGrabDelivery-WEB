<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

somi_api_require_post();
$input = somi_api_input(true);
require_once __DIR__.'/../../../app/Services/ShipperApplicationService.php';
$userId = somi_api_current_user_id();
$target = is_string($input['target'] ?? null) ? strtoupper(trim($input['target'])) : '';
if (!in_array($target, ['CUSTOMER', 'SHIPPER'], true)) {
    somi_api_json(422, ['error' => 'Choose a valid workspace.']);
}

try {
    require_once __DIR__ . '/../../../app/Services/SessionGuard.php';
    if (!somi_require_active_session()) somi_api_json(401, ['error' => 'Please sign in again.']);
    $db = somi_db();
    $db->beginTransaction();

    $userQuery = $db->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND account_status = 'ACTIVE' FOR UPDATE");
    $userQuery->execute(['user_id' => $userId]);
    if (!$userQuery->fetchColumn()) {
        throw new RuntimeException('This account is not active.');
    }

    $roleQuery = $db->prepare('SELECT role_id FROM roles WHERE role_code = :role_code');
    $roleQuery->execute(['role_code' => $target]);
    $roleId = $roleQuery->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('The selected workspace is not available.');
    }

    if ($target === 'CUSTOMER') {
        $grant = $db->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)
             ON CONFLICT (user_id, role_id) DO NOTHING'
        );
        $grant->execute(['user_id' => $userId, 'role_id' => $roleId]);
        $profile = $db->prepare('INSERT INTO customer_profiles (user_id) VALUES (:user_id) ON CONFLICT (user_id) DO NOTHING');
        $profile->execute(['user_id' => $userId]);
        $db->commit();
        $_SESSION['somi_role'] = 'customer';
        somi_api_json(200, ['status' => 'WORKSPACE_READY', 'role' => 'customer']);
    }

    $existingShipper = $db->prepare('SELECT verification_status,application_details FROM shipper_profiles WHERE user_id = :user_id FOR UPDATE');
    $existingShipper->execute(['user_id' => $userId]);
    $existingProfile = $existingShipper->fetch();
    $existingShipperStatus = $existingProfile['verification_status'] ?? false;
    $isSubmission = isset($input['documents']);
    if ($existingShipperStatus !== false && (!$isSubmission || $existingShipperStatus === 'APPROVED' || ($existingShipperStatus === 'PENDING' && $existingProfile['application_details'] !== null))) {
        $grantExisting = $db->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)
             ON CONFLICT (user_id, role_id) DO NOTHING'
        );
        $grantExisting->execute(['user_id' => $userId, 'role_id' => $roleId]);
        $db->commit();
        $_SESSION['somi_role'] = 'shipper';
        somi_api_json(200, ['status' => 'WORKSPACE_READY', 'role' => 'shipper', 'verification_status' => $existingShipperStatus]);
    }

    $applicationService = new ShipperApplicationService($db);
    $applicationService->assertApplicant($userId);
    $application = ShipperApplicationService::validate($input);
    $vehicleType = $application['vehicle_type'];
    $vehicleRegistration = $application['vehicle_registration'] ?? 'BICYCLE-'.$userId;

    $identity = $db->prepare('SELECT review_status FROM user_identity_documents WHERE user_id = :user_id FOR UPDATE');
    $identity->execute(['user_id' => $userId]);
    $identityStatus = $identity->fetchColumn();
    if ($identityStatus === false) {
        throw new RuntimeException('An identity document is required before applying as a shipper.');
    }
    if (in_array($identityStatus, ['NOT_REQUIRED','REJECTED','NEEDS_INFO'], true)) {
        $markIdentityPending = $db->prepare(
            "UPDATE user_identity_documents SET review_status = 'PENDING', rejection_reason=NULL, reviewed_at=NULL, reviewed_by=NULL, updated_at = now() WHERE user_id = :user_id"
        );
        $markIdentityPending->execute(['user_id' => $userId]);
    }

    $grant = $db->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)
         ON CONFLICT (user_id, role_id) DO NOTHING'
    );
    $grant->execute(['user_id' => $userId, 'role_id' => $roleId]);
    $profile = $db->prepare(
        "INSERT INTO shipper_profiles (user_id, vehicle_type, vehicle_registration, verification_status)
         VALUES (:user_id, :vehicle_type, :vehicle_registration, 'PENDING')
         ON CONFLICT (user_id) DO UPDATE SET
             vehicle_type = EXCLUDED.vehicle_type,
             vehicle_registration = EXCLUDED.vehicle_registration,
             verification_status = 'PENDING', availability = FALSE,
             approved_at = NULL, approved_by = NULL, updated_at = now()
         WHERE shipper_profiles.verification_status IN ('REJECTED', 'NEEDS_INFO') OR (shipper_profiles.verification_status='PENDING' AND shipper_profiles.application_details IS NULL)"
    );
    $profile->execute([
        'user_id' => $userId,
        'vehicle_type' => $vehicleType,
        'vehicle_registration' => $vehicleRegistration,
    ]);
    $applicationService->save($userId, $application);
    $statusQuery = $db->prepare('SELECT verification_status FROM shipper_profiles WHERE user_id = :user_id');
    $statusQuery->execute(['user_id' => $userId]);
    $verificationStatus = (string) $statusQuery->fetchColumn();
    $db->commit();
    $_SESSION['somi_role'] = 'shipper';
    somi_api_json(200, ['status' => 'SHIPPER_APPLICATION_SAVED', 'role' => 'shipper', 'verification_status' => $verificationStatus]);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($error instanceof PDOException && $error->getCode() === '23505') {
        somi_api_json(409, ['error' => 'That vehicle registration is already assigned to another Shipper.']);
    }
    somi_api_handle_error($error);
}
