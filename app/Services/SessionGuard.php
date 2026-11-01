<?php
declare(strict_types=1);
require_once __DIR__ . '/../Config/database.php';

function somi_require_active_session(?array &$sessionUser = null): bool
{
    $query = somi_db()->prepare(
        "SELECT u.account_status, u.password_changed_at,
                COALESCE((SELECT json_agg(r.role_code) FROM user_roles ur
                  JOIN roles r ON r.role_id = ur.role_id WHERE ur.user_id = u.user_id), '[]') AS roles,
                sp.verification_status, uid.review_status, uid.rejection_reason
         FROM users u
         LEFT JOIN shipper_profiles sp ON sp.user_id = u.user_id
         LEFT JOIN user_identity_documents uid ON uid.user_id = u.user_id
         WHERE u.user_id = :id"
    );
    $query->execute(['id' => $_SESSION['somi_user_id'] ?? 0]);
    $user = $query->fetch();
    $issued = (int) ($_SESSION['somi_authenticated_at'] ?? 0);
    $last = (int) ($_SESSION['somi_last_activity'] ?? $issued);
    if (!$user || $user['account_status'] !== 'ACTIVE' || !$issued
        || time() - $last > 1800
        || ($user['password_changed_at'] !== null && strtotime($user['password_changed_at']) > $issued)) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }
    $sessionUser = $user;
    $_SESSION['somi_last_activity'] = time();
    return true;
}
