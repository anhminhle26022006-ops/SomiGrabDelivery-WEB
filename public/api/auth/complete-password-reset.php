<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/PasswordResetService.php';

somi_api_require_post();
$input = somi_api_input();
try {
    PasswordResetService::reset(
        trim((string) ($input['token'] ?? '')),
        (string) ($input['password'] ?? '')
    );
    somi_api_json(200, ['status' => 'PASSWORD_RESET']);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
