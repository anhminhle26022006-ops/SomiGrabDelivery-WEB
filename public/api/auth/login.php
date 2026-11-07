<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/EmailVerificationService.php';
require_once __DIR__ . '/../../../app/Services/PhoneVerificationService.php';
require_once __DIR__ . '/../../../app/Services/AuthRateLimiter.php';

somi_api_require_post();
$input = somi_api_input();
$identifier = trim((string) ($input['identifier'] ?? ''));
$roleCode = strtoupper(trim((string) ($input['role'] ?? '')));
$password = (string) ($input['password'] ?? '');
if ($identifier === '' || $password === '' || !in_array($roleCode, ['CUSTOMER', 'SHIPPER', 'ADMIN'], true)) {
    somi_api_json(422, ['error' => 'Enter your login details and choose a valid workspace.']);
}

try {
    AuthRateLimiter::hit(somi_db(),'login-ip:'.($_SERVER['REMOTE_ADDR']??'unknown'),100);
    AuthRateLimiter::hit(somi_db(),'login-identity:'.strtolower($identifier),10);
    $db = somi_db();
    $db->beginTransaction();
    $emailIdentifier = strtolower($identifier);
    $phoneIdentifier = preg_replace('/[\s().-]+/', '', $identifier);
    $query = $db->prepare(
        "SELECT u.user_id, u.password_hash, u.account_status,
                u.phone_verified_at, u.email_verified_at
         FROM users u
         JOIN user_roles ur ON ur.user_id = u.user_id
         JOIN roles r ON r.role_id = ur.role_id AND r.role_code = :role_code
         WHERE lower(u.email) = :email OR u.phone = :phone
         LIMIT 1 FOR UPDATE OF u"
    );
    $query->execute([
        'role_code' => $roleCode,
        'email' => $emailIdentifier,
        'phone' => $phoneIdentifier,
    ]);
    $user = $query->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        $db->rollBack();
        somi_api_json(401, ['error' => 'The login details are incorrect or the account is not active.']);
    }

    if (!in_array($user['account_status'], ['ACTIVE', 'PENDING_VERIFICATION'], true)) {
        $db->rollBack();
        somi_api_json(401, ['error' => 'The account is not active.']);
    }
    if ($user['account_status'] === 'ACTIVE') {
        $lastLogin = $db->prepare('UPDATE users SET last_login_at = now(), updated_at = now() WHERE user_id = :user_id');
        $lastLogin->execute(['user_id' => $user['user_id']]);
    }
    $authenticatedAt = (float) $db->query('SELECT EXTRACT(EPOCH FROM clock_timestamp())')->fetchColumn();
    $db->commit();
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['somi_authenticated_at'] = $authenticatedAt;
    $_SESSION['somi_last_activity'] = time();
    $_SESSION['somi_user_id'] = (int) $user['user_id'];
    $_SESSION['somi_role'] = strtolower($roleCode);

    // Let a user resume signup verification after closing the original browser tab.
    if ($user['account_status'] === 'PENDING_VERIFICATION') {
        if ($user['phone_verified_at'] === null) {
            try {
                $phoneCode = PhoneVerificationService::sendCode((int) $user['user_id']);
                somi_api_json(200, [
                    'status' => 'PHONE_VERIFICATION_REQUIRED',
                    'role' => strtolower($roleCode),
                    'demo_code' => $phoneCode,
                ]);
            } catch (Throwable $deliveryError) {
                if (str_contains($deliveryError->getMessage(), 'Please wait before requesting another phone code.')) {
                    somi_api_json(200, [
                        'status' => 'PHONE_VERIFICATION_REQUIRED',
                        'role' => strtolower($roleCode),
                        'demo_code' => null,
                        'notice' => 'A phone code was recently generated. Wait briefly, then request another code.',
                    ]);
                }
                throw $deliveryError;
            }
        }

        if ($user['email_verified_at'] === null) {
            try {
                EmailVerificationService::sendCode((int) $user['user_id']);
                $notice = 'A new email verification code was queued.';
            } catch (Throwable $deliveryError) {
                if (str_contains($deliveryError->getMessage(), 'Please wait before requesting another email code.')) {
                    $notice = 'An email code was recently sent. Enter that code or wait briefly to request another.';
                } else {
                    error_log('[SOMI EMAIL VERIFICATION RESUME] ' . $deliveryError->getMessage());
                    $notice = 'Could not send the email code right now. Use Resend code to try again.';
                }
            }

            somi_api_json(200, [
                'status' => 'EMAIL_VERIFICATION_REQUIRED',
                'role' => strtolower($roleCode),
                'notice' => $notice,
            ]);
        }

        somi_api_json(401, ['error' => 'The account is pending verification. Contact support to restore access.']);
    }

    if ($user['account_status'] !== 'ACTIVE') {
        somi_api_json(401, ['error' => 'The login details are incorrect or the account is not active.']);
    }

    somi_api_json(200, ['status' => 'SIGNED_IN', 'role' => strtolower($roleCode)]);
} catch (Throwable $error) {
    if (isset($db)) somi_rollback_safely($db);
    somi_api_handle_error($error);
}
