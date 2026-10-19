<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/PhoneVerificationService.php';

somi_api_require_post();
try {
    $phoneCode = PhoneVerificationService::sendCode(somi_api_current_user_id());
    somi_api_json(200, [
        'status' => 'PHONE_CODE_SENT',
        'delivery_mode' => 'DEMO',
        'demo_code' => $phoneCode,
    ]);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
