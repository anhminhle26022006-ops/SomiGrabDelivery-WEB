<?php
declare(strict_types=1);

$page = $_GET['page'] ?? 'home';
$allowedPages = ['home', 'how', 'customers', 'shippers', 'pricing', 'auth', 'customer', 'shipper', 'admin'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'home';
}
$shipperView = $_GET['view'] ?? 'dashboard';
$allowedShipperViews = ['dashboard','offers','detail','active','history','earnings','account','registration','chat','support','cancel','withdraw','report'];
if (!in_array($shipperView, $allowedShipperViews, true)) $shipperView = 'dashboard';

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
  <link rel="stylesheet" href="public/assets/css/app.css">
  <?php if (in_array($page, ['customer', 'shipper', 'admin'], true)): ?>
  <link rel="stylesheet" href="public/assets/css/modules/<?= e($page) ?>.css">
  <?php endif; ?>
  <script defer src="public/assets/js/app.js"></script>
</head>
<body data-page="<?= e($page) ?>">
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
</body>
</html>
