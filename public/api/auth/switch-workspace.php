<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

somi_api_require_post();
$input = somi_api_input();
$userId = somi_api_current_user_id();
$target = strtoupper(trim((string) ($input['target'] ?? '')));
if (!in_array($target, ['CUSTOMER', 'SHIPPER'], true)) {
    somi_api_json(422, ['error' => 'Choose a valid workspace.']);
}

try {
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
        $_SESSION['somi_role'] = 'customer';
        $db->commit();
        somi_api_json(200, ['status' => 'WORKSPACE_READY', 'role' => 'customer']);
    }

    $existingShipper = $db->prepare('SELECT verification_status FROM shipper_profiles WHERE user_id = :user_id');
    $existingShipper->execute(['user_id' => $userId]);
    $existingShipperStatus = $existingShipper->fetchColumn();
    if ($existingShipperStatus !== false && !in_array($existingShipperStatus, ['REJECTED', 'NEEDS_INFO'], true)) {
        $grantExisting = $db->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)
             ON CONFLICT (user_id, role_id) DO NOTHING'
        );
        $grantExisting->execute(['user_id' => $userId, 'role_id' => $roleId]);
        $_SESSION['somi_role'] = 'shipper';
        $db->commit();
        somi_api_json(200, ['status' => 'WORKSPACE_READY', 'role' => 'shipper', 'verification_status' => $existingShipperStatus]);
    }

    $vehicleType = trim((string) ($input['vehicle_type'] ?? ''));
    $vehicleRegistration = strtoupper(preg_replace('/\s+/', '', trim((string) ($input['vehicle_registration'] ?? ''))));
    if ($vehicleType === '' || mb_strlen($vehicleType) > 80) {
        throw new InvalidArgumentException('Enter a vehicle type up to 80 characters.');
    }
    if ($vehicleRegistration === '' || strlen($vehicleRegistration) > 40) {
        throw new InvalidArgumentException('Enter a vehicle registration up to 40 characters.');
    }

    $identity = $db->prepare('SELECT review_status FROM user_identity_documents WHERE user_id = :user_id FOR UPDATE');
    $identity->execute(['user_id' => $userId]);
    $identityStatus = $identity->fetchColumn();
    if ($identityStatus === false) {
        throw new RuntimeException('An identity document is required before applying as a shipper.');
    }
    if ($identityStatus === 'REJECTED') {
        throw new RuntimeException('Your identity document needs to be updated before applying as a shipper.');
    }
    if ($identityStatus === 'NOT_REQUIRED') {
        $markIdentityPending = $db->prepare(
            "UPDATE user_identity_documents SET review_status = 'PENDING', updated_at = now() WHERE user_id = :user_id"
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
         WHERE shipper_profiles.verification_status IN ('REJECTED', 'NEEDS_INFO')"
    );
    $profile->execute([
        'user_id' => $userId,
        'vehicle_type' => $vehicleType,
        'vehicle_registration' => $vehicleRegistration,
    ]);
    $statusQuery = $db->prepare('SELECT verification_status FROM shipper_profiles WHERE user_id = :user_id');
    $statusQuery->execute(['user_id' => $userId]);
    $verificationStatus = (string) $statusQuery->fetchColumn();
    $_SESSION['somi_role'] = 'shipper';
    $db->commit();
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
