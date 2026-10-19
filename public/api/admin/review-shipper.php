<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

somi_api_require_post();
$adminId = somi_api_current_user_id();
if (($_SESSION['somi_role'] ?? null) !== 'admin') {
    somi_api_json(403, ['error' => 'Admin access is required.']);
}
$input = somi_api_input();
$shipperId = filter_var($input['shipper_id'] ?? null, FILTER_VALIDATE_INT);
$decision = strtoupper(trim((string) ($input['decision'] ?? '')));
$note = trim((string) ($input['note'] ?? ''));
if (!$shipperId || !in_array($decision, ['APPROVED', 'REJECTED'], true)) {
    somi_api_json(422, ['error' => 'Choose a valid Shipper application and decision.']);
}
if ($decision === 'REJECTED' && $note === '') {
    somi_api_json(422, ['error' => 'Add a short reason when rejecting an application.']);
}

try {
    $db = somi_db();
    $adminCheck = $db->prepare(
        "SELECT 1 FROM user_roles ur JOIN roles r ON r.role_id = ur.role_id
         WHERE ur.user_id = :user_id AND r.role_code = 'ADMIN'"
    );
    $adminCheck->execute(['user_id' => $adminId]);
    if (!$adminCheck->fetchColumn()) {
        somi_api_json(403, ['error' => 'Admin access is required.']);
    }

    $db->beginTransaction();
    $profile = $db->prepare(
        "SELECT verification_status FROM shipper_profiles WHERE user_id = :user_id FOR UPDATE"
    );
    $profile->execute(['user_id' => $shipperId]);
    $oldStatus = $profile->fetchColumn();
    if (!in_array($oldStatus, ['PENDING', 'NEEDS_INFO'], true)) {
        throw new RuntimeException('This application is no longer waiting for review.');
    }

    $approved = $decision === 'APPROVED';
    if ($approved) {
        $updateProfile = $db->prepare(
            "UPDATE shipper_profiles SET verification_status = 'APPROVED', availability = FALSE,
             approved_at = now(), approved_by = :admin_id, updated_at = now() WHERE user_id = :user_id"
        );
        $updateProfile->execute(['admin_id' => $adminId, 'user_id' => $shipperId]);
    } else {
        $updateProfile = $db->prepare(
            "UPDATE shipper_profiles SET verification_status = 'REJECTED', availability = FALSE,
             approved_at = NULL, approved_by = NULL, updated_at = now() WHERE user_id = :user_id"
        );
        $updateProfile->execute(['user_id' => $shipperId]);
    }

    $updateIdentity = $db->prepare(
        'UPDATE user_identity_documents
         SET review_status = :status, rejection_reason = :reason,
             reviewed_by = :admin_id, reviewed_at = now(), updated_at = now()
         WHERE user_id = :user_id'
    );
    $updateIdentity->execute([
        'status' => $decision,
        'reason' => $approved ? null : $note,
        'admin_id' => $adminId,
        'user_id' => $shipperId,
    ]);

    $audit = $db->prepare(
        "INSERT INTO audit_logs (actor_id, actor_kind, action, entity_type, entity_id, old_value, new_value)
         VALUES (:admin_id, 'ADMIN', :action, 'SHIPPER_PROFILE', :shipper_id,
                 CAST(:old_value AS jsonb), CAST(:new_value AS jsonb))"
    );
    $audit->execute([
        'admin_id' => $adminId,
        'action' => 'SHIPPER_APPLICATION_' . $decision,
        'shipper_id' => $shipperId,
        'old_value' => json_encode(['verification_status' => $oldStatus], JSON_THROW_ON_ERROR),
        'new_value' => json_encode(['verification_status' => $decision, 'note' => $note], JSON_THROW_ON_ERROR),
    ]);
    $db->commit();
    somi_api_json(200, ['status' => $decision]);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    somi_api_handle_error($error);
}
