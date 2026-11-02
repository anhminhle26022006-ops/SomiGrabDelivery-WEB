<?php
declare(strict_types=1);

require_once __DIR__ . '/../_person1.php';

somi_api_require_post();
$adminId = somi_api_current_user_id();
if (($_SESSION['somi_role'] ?? null) !== 'admin') {
    somi_api_json(403, ['error' => 'Admin access is required.']);
}
$input = somi_api_input();
$shipperId = filter_var($input['shipper_id'] ?? null, FILTER_VALIDATE_INT);
$decision = strtoupper(trim((string) ($input['decision'] ?? '')));
$note = trim((string) ($input['note'] ?? ''));
if (!$shipperId || $shipperId < 1 || !in_array($decision, ['APPROVED', 'REJECTED', 'NEEDS_INFO'], true)) {
    somi_api_json(422, ['error' => 'Choose a valid Shipper application and decision.']);
}
if ($decision !== 'APPROVED' && $note === '') {
    somi_api_json(422, ['error' => 'Add a short reason when rejecting an application.']);
}
if (mb_strlen($note) > 1000) somi_api_json(422, ['error' => 'Review note must be at most 1000 characters.']);

try {
    person1_identity('ADMIN', 'shipper.verify');
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
    // Workspace applications lock the user before identity/profile rows too.
    $lockUser = $db->prepare('SELECT user_id FROM users WHERE user_id = :id FOR UPDATE');
    $lockUser->execute(['id' => $shipperId]);
    $profile = $db->prepare(
        "SELECT verification_status FROM shipper_profiles WHERE user_id = :user_id FOR UPDATE"
    );
    $profile->execute(['user_id' => $shipperId]);
    $oldStatus = $profile->fetchColumn();
    if (!in_array($oldStatus, ['PENDING', 'NEEDS_INFO'], true)) {
        throw new ApiProblem(409, 'This application is no longer waiting for review.', 'REVIEW_STATE_CONFLICT');
    }

    $identityLock = $db->prepare('SELECT identity_document_id FROM user_identity_documents WHERE user_id = :id FOR UPDATE');
    $identityLock->execute(['id' => $shipperId]);
    if (!$identityLock->fetchColumn()) throw new ApiProblem(409, 'An identity document is required for review.', 'IDENTITY_REQUIRED');

    $approved = $decision === 'APPROVED';
    if ($approved) {
        $updateProfile = $db->prepare(
            "UPDATE shipper_profiles SET verification_status = 'APPROVED', availability = FALSE,
             approved_at = now(), approved_by = :admin_id, updated_at = now() WHERE user_id = :user_id"
        );
        $updateProfile->execute(['admin_id' => $adminId, 'user_id' => $shipperId]);
    } else {
        $updateProfile = $db->prepare(
            "UPDATE shipper_profiles SET verification_status = :decision, availability = FALSE,
             approved_at = NULL, approved_by = NULL, updated_at = now() WHERE user_id = :user_id"
        );
        $updateProfile->execute(['user_id' => $shipperId, 'decision' => $decision]);
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
    person1_error($error);
}
