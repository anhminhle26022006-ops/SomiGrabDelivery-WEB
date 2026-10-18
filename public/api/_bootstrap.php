<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Config/database.php';

function somi_api_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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
}

function somi_api_input(): array
{
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        somi_api_json(400, ['error' => 'Request body must be valid JSON.']);
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
    if ($error instanceof PDOException && $error->getCode() === '23505') {
        somi_api_json(409, ['error' => 'Email or phone number is already registered.']);
    }
    if ($error instanceof InvalidArgumentException || $error instanceof RuntimeException) {
        somi_api_json(422, ['error' => $error->getMessage()]);
    }
    error_log('[SOMI API ERROR] ' . $error->getMessage());
    somi_api_json(500, ['error' => 'The request could not be completed. Please try again.']);
}

somi_api_start_session();
