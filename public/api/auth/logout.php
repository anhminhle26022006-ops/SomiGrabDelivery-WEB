<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
somi_api_require_post();
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'],
    'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
somi_api_json(200, ['status' => 'SIGNED_OUT']);
