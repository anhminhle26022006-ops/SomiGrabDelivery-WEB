<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Services/RequestOrigin.php';
require_once __DIR__ . '/../../app/Services/DeliveryApiError.php';

function somi_api_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => somi_session_is_secure(),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function somi_api_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function somi_api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        somi_api_json(405, ['error' => 'Method not allowed.']);
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        somi_api_json(415, ['error' => 'Content-Type must be application/json.']);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null && !somi_origin_allowed($origin)) {
        somi_api_json(403, ['error' => 'Cross-origin requests are not allowed.']);
    }

}

function somi_api_input(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        somi_api_json(415, ['error' => 'Content-Type must be application/json.']);
    }
    $body = (string) file_get_contents('php://input');
    $input = json_decode($body, true);
    if (!is_array($input) || !str_starts_with(ltrim($body), '{')) {
        somi_api_json(400, ['error' => 'Request body must be valid JSON.']);
    }
    foreach ($input as $value) {
        if (is_array($value)) somi_api_json(422, ['error' => 'Fields must contain scalar values.']);
    }
    return $input;
}

function somi_api_current_user_id(): int
{
    $userId = $_SESSION['somi_user_id'] ?? null;
    if (!is_int($userId) || $userId < 1) {
        somi_api_json(401, ['error' => 'Please restart registration.']);
    }
    return $userId;
}

function somi_api_handle_error(Throwable $error): void
{
    if ($error instanceof DeliveryApiError) somi_api_json($error->httpStatus, ['code'=>$error->errorCode,'error'=>$error->getMessage()]);
    if ($error instanceof PDOException && $error->getCode() === '23505') {
        somi_api_json(409, ['error' => 'Email or phone number is already registered.']);
    }
    if ($error instanceof InvalidArgumentException || ($error instanceof RuntimeException && !$error instanceof PDOException && !preg_match('/environment|configured|SOMI_|RESEND_|Resend|SQLSTATE/i', $error->getMessage()))) {
        somi_api_json(422, ['error' => $error->getMessage()]);
    }
    error_log('[SOMI API ERROR] ' . get_class($error) . ' code=' . $error->getCode());
    somi_api_json(500, ['error' => 'The request could not be completed. Please try again.']);
}

somi_api_start_session();
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    somi_api_json(403, ['error' => 'Cross-site requests are not allowed.']);
}
