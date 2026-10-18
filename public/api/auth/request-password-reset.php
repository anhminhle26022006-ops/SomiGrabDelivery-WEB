<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/PasswordResetService.php';

somi_api_require_post();
$input = somi_api_input();
$email = trim((string) ($input['email'] ?? ''));
$language = strtolower(trim((string) ($input['language'] ?? 'en')));
if (!in_array($language, ['en', 'vi'], true)) {
    $language = 'en';
}

try {
    PasswordResetService::request($email, $language);
    somi_api_json(200, ['status' => 'RESET_EMAIL_REQUESTED']);
} catch (Throwable $error) {
    somi_api_handle_error($error);
}
