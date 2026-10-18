<?php
declare(strict_types=1);

// Let PHP's built-in server serve real CSS/JS/image files directly when this
// file is used as its router (php -S localhost:3000 index.php).
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($requestPath) && $requestPath !== '/') {
        $decodedPath = rawurldecode($requestPath);
        $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($decodedPath, '/\\'));
        $root = realpath(__DIR__);
        if ($candidate !== false && $root !== false
            && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)
            && is_file($candidate)
            && !str_ends_with(strtolower($candidate), '.php')) {
            return false;
        }
    }
}

$page = $_GET['page'] ?? 'home';
$allowedPages = ['home', 'how', 'customers', 'shippers', 'pricing', 'auth', 'customer', 'shipper', 'admin'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'home';
}
if (in_array($page, ['customer', 'shipper', 'admin'], true)) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
    $sessionRole = $_SESSION['somi_role'] ?? null;
    if (!is_int($_SESSION['somi_user_id'] ?? null) || $sessionRole !== $page) {
        header('Location: ?page=auth&next=' . rawurlencode($page), true, 303);
        exit;
    }
}
$shipperView = $_GET['view'] ?? 'dashboard';
$allowedShipperViews = ['dashboard','offers','detail','active','history','earnings','account','registration','chat','support','cancel','withdraw','report','notifications'];
if (!in_array($shipperView, $allowedShipperViews, true)) $shipperView = 'dashboard';
$shipperViewPages = [
    'dashboard' => 'dashboard', 'offers' => 'offers', 'detail' => 'active', 'active' => 'active',
    'history' => 'history', 'earnings' => 'earnings', 'account' => 'account', 'registration' => 'account',
    'chat' => 'active', 'support' => 'active', 'cancel' => 'active', 'withdraw' => 'earnings',
    'report' => 'earnings', 'notifications' => 'notifications',
];
$shipperPage = $shipperViewPages[$shipperView];

$publicPages = ['home','how','customers','shippers','pricing'];
$pageTitles = [
    'home' => 'SOMI — Delivery on Your Time',
    'how' => 'How It Works — SOMI',
    'customers' => 'For Customers — SOMI',
    'shippers' => 'For Shippers — SOMI',
    'pricing' => 'Pricing — SOMI',
    'auth' => 'Sign in — SOMI',
    'customer' => 'Customer Workspace — SOMI',
    'shipper' => 'Shipper Workspace — SOMI',
    'admin' => 'Admin Dashboard — SOMI',
];
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function linkTo(string $page, array $params = []): string {
    return '?' . http_build_query(array_merge(['page' => $page], $params));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#14284b">
  <title><?= e($pageTitles[$page]) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public/assets/css/app.css?v=20261018-auth2">
  <?php if (in_array($page, ['customer', 'shipper', 'admin'], true)): ?>
  <link rel="stylesheet" href="public/assets/css/modules/<?= e($page) ?>.css<?= $page === 'shipper' ? '?v=20261014-3' : '' ?>">
  <?php endif; ?>
  <script defer src="public/assets/js/app.js?v=20261018-demootp1"></script>
</head>
<body data-page="<?= e($page) ?>"<?php if ($page === 'shipper'): ?> data-shipper-view="<?= e($shipperView) ?>"<?php endif; ?>>
<?php if (in_array($page, $publicPages, true)): ?>
  <?php require __DIR__ . '/app/Views/shared/public.php'; ?>
<?php elseif ($page === 'auth'): ?>
  <?php require __DIR__ . '/app/Views/shared/auth.php'; ?>
<?php elseif ($page === 'customer'): ?>
  <?php require __DIR__ . '/app/Views/customer/index.php'; ?>
<?php elseif ($page === 'shipper'): ?>
  <?php require __DIR__ . '/app/Views/shipper/index.php'; ?>
<?php else: ?>
  <?php require __DIR__ . '/app/Views/admin/index.php'; ?>
<?php endif; ?>
<?php if ($page === 'shipper'): ?><script defer src="public/assets/js/shipper-navigation.js?v=20261014-6"></script><script defer src="public/assets/js/shipper.js?v=20261014-6"></script><?php endif; ?>
</body>
</html>
