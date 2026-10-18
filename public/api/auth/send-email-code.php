<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/EmailVerificationService.php';

somi_api_require_post();
try {
    $userId = somi_api_current_user_id();
    $phoneCheck = somi_db()->prepare('SELECT phone_verified_at FROM users WHERE user_id = :user_id');
    $phoneCheck->execute(['user_id' => $userId]);
    $phoneRow = $phoneCheck->fetch();
    if (!$phoneRow || $phoneRow['phone_verified_at'] === null) {
        throw new RuntimeException('Verify your phone before requesting the email code.');
    }
    EmailVerificationService::sendCode($userId);
    somi_api_json(200, ['status' => 'EMAIL_CODE_SENT']);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
