<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/EmailVerificationService.php';

somi_api_require_post();
$input = somi_api_input();
try {
    $userId = somi_api_current_user_id();
    $phoneCheck = somi_db()->prepare('SELECT phone_verified_at FROM users WHERE user_id = :user_id');
    $phoneCheck->execute(['user_id' => $userId]);
    $phoneRow = $phoneCheck->fetch();
    if (!$phoneRow || $phoneRow['phone_verified_at'] === null) {
        throw new RuntimeException('Verify your phone before verifying email.');
    }
    EmailVerificationService::verifyCode($userId, trim((string) ($input['code'] ?? '')));
    somi_api_json(200, ['status' => 'ACCOUNT_VERIFIED', 'role' => $_SESSION['somi_role'] ?? 'customer']);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
