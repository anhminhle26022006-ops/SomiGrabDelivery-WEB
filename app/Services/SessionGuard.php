<?php
declare(strict_types=1);
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Domain/User.php';

/**
 * Validates the signed-in session against the database in one round trip.
 * When $permission is given, the row also reports (has_permission) whether the
 * current session workspace role grants it, so API calls need no second query.
 */
function somi_require_active_session(?array &$sessionUser = null, ?string $permission = null): bool
{
    $query = somi_db()->prepare(
        "SELECT u.user_id,u.full_name,u.email,u.phone,u.nationality_code,u.app_language,
                u.email_verified_at,u.phone_verified_at,u.account_status,u.password_changed_at,
                EXISTS (SELECT 1 FROM customer_profiles cp WHERE cp.user_id=u.user_id) AS has_customer_profile,
                (SELECT max(h.created_at) FROM account_status_history h
                 WHERE h.user_id = u.user_id AND h.new_status = 'SUSPENDED') AS last_suspended_at,
                COALESCE((SELECT json_agg(r.role_code) FROM user_roles ur
                  JOIN roles r ON r.role_id = ur.role_id WHERE ur.user_id = u.user_id), '[]') AS roles,
                EXISTS (SELECT 1 FROM user_roles pur
                  JOIN roles pr ON pr.role_id = pur.role_id
                  JOIN role_permissions rp ON rp.role_id = pr.role_id
                  JOIN permissions p ON p.permission_id = rp.permission_id
                  WHERE pur.user_id = u.user_id AND pr.role_code = :workspace
                    AND p.permission_code = :permission) AS has_permission,
                sp.verification_status, uid.review_status, uid.rejection_reason
         FROM users u
         LEFT JOIN shipper_profiles sp ON sp.user_id = u.user_id
         LEFT JOIN user_identity_documents uid ON uid.user_id = u.user_id
         WHERE u.user_id = :id"
    );
    $query->execute([
        'id' => $_SESSION['somi_user_id'] ?? 0,
        'workspace' => strtoupper((string) ($_SESSION['somi_role'] ?? '')),
        'permission' => $permission ?? '',
    ]);
    $user = $query->fetch();
    $issued = (float) ($_SESSION['somi_authenticated_at'] ?? 0);
    $last = (int) ($_SESSION['somi_last_activity'] ?? $issued);
    if (!$user || !\Somi\Domain\User::fromRow($user)->isActive() || !$issued
        || time() - $last > 1800
        || ($user['password_changed_at'] !== null && (float) (new DateTimeImmutable($user['password_changed_at']))->format('U.u') > $issued)
        || ($user['last_suspended_at'] !== null && (float) (new DateTimeImmutable($user['last_suspended_at']))->format('U.u') > $issued)) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        return false;
    }
    $sessionUser = $user;
    $_SESSION['somi_last_activity'] = time();
    return true;
}
