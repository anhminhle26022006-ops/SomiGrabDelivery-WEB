<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

somi_api_require_post();
$input = somi_api_input();
$identifier = trim((string) ($input['identifier'] ?? ''));
$roleCode = strtoupper(trim((string) ($input['role'] ?? '')));
$password = (string) ($input['password'] ?? '');
if ($identifier === '' || $password === '' || !in_array($roleCode, ['CUSTOMER', 'SHIPPER', 'ADMIN'], true)) {
    somi_api_json(422, ['error' => 'Enter your login details and choose a valid workspace.']);
}

try {
    $emailIdentifier = strtolower($identifier);
    $phoneIdentifier = preg_replace('/[\s().-]+/', '', $identifier);
    $query = somi_db()->prepare(
        "SELECT u.user_id, u.password_hash, u.account_status
         FROM users u
         JOIN user_roles ur ON ur.user_id = u.user_id
         JOIN roles r ON r.role_id = ur.role_id AND r.role_code = :role_code
         WHERE lower(u.email) = :email OR u.phone = :phone
         LIMIT 1"
    );
    $query->execute([
        'role_code' => $roleCode,
        'email' => $emailIdentifier,
        'phone' => $phoneIdentifier,
    ]);
    $user = $query->fetch();
    if (!$user || $user['account_status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
        somi_api_json(401, ['error' => 'The login details are incorrect or the account is not active.']);
    }

    session_regenerate_id(true);
    $_SESSION['somi_user_id'] = (int) $user['user_id'];
    $_SESSION['somi_role'] = strtolower($roleCode);

    $lastLogin = somi_db()->prepare('UPDATE users SET last_login_at = now(), updated_at = now() WHERE user_id = :user_id');
    $lastLogin->execute(['user_id' => $user['user_id']]);
    somi_api_json(200, ['status' => 'SIGNED_IN', 'role' => strtolower($roleCode)]);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
