<?php
// Loopback test router only. Never routed by the production front controller.
declare(strict_types=1);
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/__shipper-test') { return require $root.'/index.php'; }
define('SOMI_FRONT_CONTROLLER', true);
if (!function_exists('e')) {
    function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
    function assetUrl(string $path): string { return '/'.$path; }
}
$shipperPage = in_array($_GET['view'] ?? '', ['dashboard', 'account', 'support', 'notifications', 'earnings', 'active'], true) ? $_GET['view'] : 'dashboard';
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/public/assets/vendor/bootstrap/bootstrap.min.css">
<script defer src="/public/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="/public/assets/css/app.css">
<link rel="stylesheet" href="/public/assets/css/bootstrap-workspaces.css">
<link rel="stylesheet" href="/public/assets/css/modules/shipper.css">
<script defer src="/public/assets/js/app.js"></script>
</head><body data-page="shipper" data-shipper-view="<?= e($shipperPage) ?>">
<?php require $root.'/app/Views/shipper/index.php'; ?>
<script defer src="/public/assets/js/shipper-navigation.js"></script>
<script defer src="/public/assets/js/shipper.js"></script>
</body></html>
