<?php
declare(strict_types=1);
// Loopback-only render fixture; does not create an authenticated application session.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {http_response_code(404);exit;}
if (parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) !== '/__application-test') {
    if (str_starts_with($_SERVER['REQUEST_URI'], '/public/assets/') && !str_contains($_SERVER['REQUEST_URI'], '..')) return false;
    http_response_code(404);exit;
}
function e(string $value):string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
function assetUrl(string $value):string{return '/'.$value;}
$hasShipperWorkspace=isset($_GET['shipper']);$sessionUserId=321;$sessionUser=[];
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><link rel="stylesheet" href="/public/assets/css/app.css"></head><body data-page="customer">
<?php require __DIR__.'/../app/Views/customer/index.php'; ?>
<script defer src="/public/assets/js/app.js"></script></body></html>
