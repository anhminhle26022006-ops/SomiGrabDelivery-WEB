<?php
declare(strict_types=1);

// Only public assets and explicit API endpoints may bypass the front controller.
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (PHP_SAPI === 'cli-server' && $requestPath !== '/') {
    $candidate = realpath(__DIR__ . '/' . ltrim($requestPath, '/'));
    $publicRoot = realpath(__DIR__ . '/public');
    if ($candidate !== false && $publicRoot !== false
        && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR) && is_file($candidate)
        && !str_starts_with(basename($candidate), '_')) {
        return false;
    }
    if (!in_array($requestPath, ['/admin', '/admin/', '/index.php'], true)) {
        http_response_code(404);
        exit('Not found');
    }
}
if (in_array($requestPath, ['/admin', '/admin/'], true)) {
    $_GET['page'] = 'admin';
}
require_once __DIR__ . '/app/Services/SessionGuard.php';
require_once __DIR__ . '/app/Services/RequestOrigin.php';
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$allowedPages = ['home', 'how', 'customers', 'shippers', 'pricing', 'auth', 'reset-password', 'customer', 'shipper', 'admin'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'home';
}
$hasCustomerWorkspace = false;
$hasShipperWorkspace = false;
$shipperVerificationStatus = null;
$shipperIdentityReviewStatus = null;
$shipperRejectionReason = null;
$pendingShipperApplications = [];
if (in_array($page, ['customer', 'shipper', 'admin'], true)) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => somi_session_is_secure(),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
    $sessionUserId = $_SESSION['somi_user_id'] ?? null;
    $sessionRole = strtoupper((string) ($_SESSION['somi_role'] ?? ''));
    if (!is_int($sessionUserId) || $sessionUserId < 1) {
        header('Location: ?page=auth&next=' . rawurlencode($page), true, 303);
        exit;
    }
    header('Cache-Control: no-store');
    try {
    $sessionUser = null;
    if (!somi_require_active_session($sessionUser)) {
        header('Location: ?page=auth', true, 303);
        exit;
    }
    $userRoleCodes = json_decode($sessionUser['roles'], true, 512, JSON_THROW_ON_ERROR);
    $hasCustomerWorkspace = in_array('CUSTOMER', $userRoleCodes, true);
    $hasShipperWorkspace = in_array('SHIPPER', $userRoleCodes, true);
    if (!in_array($sessionRole, $userRoleCodes, true) || $sessionRole !== strtoupper($page)) {
        header('Location: ?page=auth&next=' . rawurlencode($page), true, 303);
        exit;
    }
    if ($page === 'shipper') {
        $shipperVerificationStatus = $sessionUser['verification_status'];
        $shipperIdentityReviewStatus = $sessionUser['review_status'];
        $shipperRejectionReason = $sessionUser['rejection_reason'];
    }
    if ($page === 'admin') {
        $pendingQuery = somi_db()->query(
            "SELECT sp.user_id, u.full_name, u.email, sp.vehicle_type, sp.vehicle_registration,
                    sp.verification_status, uid.review_status AS identity_review_status
             FROM shipper_profiles sp
             JOIN users u ON u.user_id = sp.user_id
             LEFT JOIN user_identity_documents uid ON uid.user_id = sp.user_id
             WHERE sp.verification_status IN ('PENDING', 'NEEDS_INFO')
             ORDER BY sp.created_at ASC"
        );
        $pendingShipperApplications = $pendingQuery->fetchAll();
    }
    } catch (Throwable $error) {
        error_log('[SOMI workspace] ' . get_class($error));
        http_response_code(503);
        exit('Workspace temporarily unavailable. Please try again later.');
    }
    session_write_close();
}
$shipperView = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'dashboard';
$allowedShipperViews = ['dashboard','offers','detail','active','history','earnings','account','registration','chat','support','cancel','withdraw','report','notifications'];
if (!in_array($shipperView, $allowedShipperViews, true)) $shipperView = 'dashboard';
$shipperViewPages = [
    'dashboard' => 'dashboard', 'offers' => 'offers', 'detail' => 'active', 'active' => 'active',
    'history' => 'history', 'earnings' => 'earnings', 'account' => 'account', 'registration' => 'account',
    'chat' => 'active', 'support' => 'active', 'cancel' => 'active', 'withdraw' => 'earnings',
    'report' => 'earnings', 'notifications' => 'notifications',
];
$shipperPage = $shipperViewPages[$shipperView];

$adminView = $_GET['view'] ?? 'index';
if (!is_string($adminView) || !in_array($adminView, ['index','users','customers','shippers','orders','complaints','disputes','payments','reviews','notifications','audit-logs'], true)) $adminView = 'index';
$publicPages = ['home','how','customers','shippers','pricing'];
$pageTitles = [
    'home' => 'SOMI — Delivery on Your Time',
    'how' => 'How It Works — SOMI',
    'customers' => 'For Customers — SOMI',
    'shippers' => 'For Shippers — SOMI',
    'pricing' => 'Pricing — SOMI',
    'auth' => 'Sign in — SOMI',
    'reset-password' => 'Reset password — SOMI',
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
  <base href="<?= e(rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/') . '/') ?>">
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
  <?php if ($page === 'admin'): ?>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script defer src="public/assets/js/admin.js"></script>
  <?php endif; ?>
  <script defer src="public/assets/js/app.js?v=20261018-passwordreset1"></script>
</head>
<body data-page="<?= e($page) ?>"<?php if ($page === 'shipper'): ?> data-shipper-view="<?= e($shipperView) ?>"<?php endif; ?>>
<?php if (in_array($page, $publicPages, true)): ?>
  <?php require __DIR__ . '/app/Views/shared/public.php'; ?>
<?php elseif ($page === 'auth'): ?>
  <?php require __DIR__ . '/app/Views/shared/auth.php'; ?>
<?php elseif ($page === 'reset-password'): ?>
  <?php require __DIR__ . '/app/Views/shared/reset-password.php'; ?>
<?php elseif ($page === 'customer'): ?>
  <?php require __DIR__ . '/app/Views/customer/index.php'; ?>
<?php elseif ($page === 'shipper'): ?>
  <?php if ($shipperVerificationStatus !== 'APPROVED'): ?>
    <?php require __DIR__ . '/app/Views/shipper/pending.php'; ?>
  <?php else: ?>
    <?php require __DIR__ . '/app/Views/shipper/index.php'; ?>
  <?php endif; ?>
<?php else: ?>
  <?php require __DIR__ . '/app/Views/admin/' . $adminView . '.php'; ?>
<?php endif; ?>
<?php if ($page === 'shipper'): ?><script defer src="public/assets/js/shipper-navigation.js?v=20261014-6"></script><script defer src="public/assets/js/shipper.js?v=20261014-6"></script><?php endif; ?>
</body>
</html>
