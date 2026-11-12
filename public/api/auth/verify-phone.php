<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/PhoneVerificationService.php';

somi_api_require_post();
$input = somi_api_input();
try {
    PhoneVerificationService::verifyCode(somi_api_current_user_id(), trim((string) ($input['code'] ?? '')));
    somi_api_json(200, ['status' => 'PHONE_VERIFIED']);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
