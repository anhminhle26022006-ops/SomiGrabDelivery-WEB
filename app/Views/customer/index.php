<?php
/**
 * Customer workspace shell.
 * URL: ?page=customer&view=<view>[&code=SOMI-xxxx]
 * Each view lives in app/Views/customer/pages/<view>.php and has a matching
 * controller in public/assets/js/customer/pages/<view>.js.
 * Text here is the Vietnamese source; i18n.js swaps it for English when the
 * saved language (localStorage "somi-language", shared with the index page) is "en".
 */
$customerViews = [
    'dashboard'     => ['Tổng quan', 'dashboard'],
    'create'        => ['Gửi hàng', 'create'],
    'orders'        => ['Đơn hàng', 'orders'],
    'order'         => ['Chi tiết đơn', 'orders'],
    'payment'       => ['Thanh toán', 'orders'],
    'matching'      => ['Tìm shipper', 'orders'],
    'tracking'      => ['Theo dõi đơn', 'orders'],
    'review'        => ['Đánh giá shipper', 'orders'],
    'report'        => ['Báo cáo sự cố', 'reports'],
    'reports'       => ['Báo cáo của tôi', 'reports'],
    'notifications' => ['Thông báo', 'notifications'],
    'account'       => ['Tài khoản', 'account'],
    'addresses'     => ['Sổ địa chỉ', 'account'],
];
$customerView = $_GET['view'] ?? 'dashboard';
if ($customerView === 'backend') $customerView = 'account';
if (!is_string($customerView) || !array_key_exists($customerView, $customerViews)) {
    $customerView = 'dashboard';
}
[$customerTitle, $customerSection] = $customerViews[$customerView];
$person3View = in_array($customerView, ['report','reports','notifications'], true);
$liveCustomerView = in_array($customerView, ['dashboard','create','orders','order','payment','account','matching','tracking','addresses'], true);
$asset = 'public/assets/js/customer/';
$customerNeedsMap = in_array($customerView,['create','addresses','order','tracking','matching','payment'],true);

$nav = [
    'dashboard' => ['Tổng quan', 'home'],
    'create'    => ['Gửi hàng', 'send'],
    'orders'    => ['Đơn hàng', 'list'],
    'reports'   => ['Báo cáo', 'report'],
];
$navIcon = [
    'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
    'send' => '<path d="M4 12h12"/><path d="m11 6 6 6-6 6"/><path d="M20 5v14"/>',
    'list' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'report' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
    'bell' => '<path d="M18 9a6 6 0 0 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 15 18 9"/><path d="M10.5 20a1.7 1.7 0 0 0 3 0"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3"/>',
];
function customerIcon(string $paths, int $size = 20): string {
    return '<svg class="c-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}
?>
<?php /* Bootstrap 5.3 (CDN) chỉ nạp cho trang Customer, sau đó nạp lại customer.css để màu và bố cục SOMI đè lên Bootstrap. */ ?>

<link rel="stylesheet" href="<?= e(assetUrl('public/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/customer.css')) ?>">
<?php if ($customerNeedsMap): ?><link rel="stylesheet" href="<?= e(assetUrl('public/assets/vendor/leaflet/leaflet.css')) ?>"><?php endif; ?>
<div class="c-app" data-customer-app>
  <header class="navbar c-top">
    <a class="navbar-brand c-brand" href="?page=customer&amp;view=dashboard" aria-label="SOMI, về trang tổng quan"><strong>SOMI</strong><span>DELIVERY</span></a>
    <nav class="nav nav-pills c-nav" aria-label="Điều hướng khách hàng">
      <?php foreach ($nav as $key => [$label, $iconKey]): ?>
        <a href="?page=customer&amp;view=<?= e($key) ?>"<?= $key === 'create' ? ' data-send-link' : '' ?> class="nav-link<?= $customerSection === $key ? ' active' : '' ?>"<?= $customerSection === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="c-top-actions">
      <div class="dropdown c-locale">
        <button class="c-locale-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-locale-btn aria-expanded="false" aria-label="Ngôn ngữ và tiền tệ">
          <span data-locale-flag aria-hidden="true"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg></span><span data-locale-current>EN · VND</span><span class="language-chevron" aria-hidden="true">⌄</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end c-locale-menu" data-locale-menu>
          <p class="c-locale-title">Ngôn ngữ</p>
          <div class="btn-group w-100 c-locale-seg" role="group" aria-label="Ngôn ngữ">
            <button type="button" class="btn" data-set-lang="vi"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#da251d"/><path d="M15 3.5L16.8 8.4L22 8.5L17.9 11.6L19.4 16.5L15 13.6L10.6 16.5L12.1 11.6L8 8.5L13.2 8.4Z" fill="#ffde00"/></svg>Tiếng Việt</button>
            <button type="button" class="btn" data-set-lang="en"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg>English</button>
          </div>
          <p class="c-locale-title">Tiền tệ</p>
          <div class="btn-group w-100 c-locale-seg" role="group" aria-label="Tiền tệ">
            <button type="button" class="btn" data-set-currency="VND">VND ₫</button>
            <button type="button" class="btn" data-set-currency="USD">USD $</button>
          </div>
          <p class="c-locale-note">Tiền tệ áp dụng cho báo giá và đơn mới. Đơn đã tạo giữ nguyên tiền tệ lúc đặt.</p>
        </div>
      </div>
      <a class="c-icon-btn c-bell<?= $customerSection === 'notifications' ? ' active' : '' ?>" href="?page=customer&amp;view=notifications" aria-label="Thông báo">
        <?= customerIcon($navIcon['bell']) ?><span class="badge rounded-pill c-count" data-unread hidden>0</span>
      </a>
      <div class="dropdown c-account">
        <button class="c-avatar-btn" type="button" data-bs-toggle="dropdown" data-c-avatar aria-expanded="false">
          <span class="c-avatar" data-account-initials>NH</span>
          <span class="c-avatar-name" data-account-name>Khách hàng</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end c-menu" data-c-menu>
          <li><a class="dropdown-item" href="?page=customer&amp;view=account">Tài khoản</a></li>
          <li><a class="dropdown-item" href="?page=customer&amp;view=addresses">Sổ địa chỉ</a></li>
          <li><a class="dropdown-item" href="?page=customer&amp;view=reports">Báo cáo của tôi</a></li>
          <?php if ($hasShipperWorkspace): ?>
          <li><button class="dropdown-item" type="button" data-workspace-switch="SHIPPER" data-copy-en="Switch to Shipper workspace" data-copy-vi="Chuyển sang Shipper">Chuyển sang Shipper</button></li>
          <?php else: ?>
          <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#shipperApplicationModal" data-copy-en="Become a Shipper" data-copy-vi="Đăng ký làm Shipper">Đăng ký làm Shipper</button></li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item text-danger" type="button" data-signout>Đăng xuất</button></li>
        </ul>
      </div>
    </div>
  </header>

  <main data-geoapify-key="<?= e(getenv('GEOAPIFY_BROWSER_API_KEY') ?: '') ?>" class="c-main" id="c-main"<?= $liveCustomerView ? ' data-customer-server aria-busy="true"' : '' ?>>
    <div data-account-banner></div>
    <?php if ($person3View): require __DIR__.'/../shared/person3-panel.php'; else: require __DIR__ . '/pages/' . $customerView . '.php'; endif; ?>
    <?php if (in_array($customerView,['order','tracking'],true)) require __DIR__.'/../shared/person3-panel.php'; ?>
  </main>

  <?php if (!$hasShipperWorkspace): ?>
  <div class="modal fade" id="shipperApplicationModal" tabindex="-1" aria-labelledby="shipperApplicationTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="shipperApplicationTitle" data-copy-en="Become a Shipper" data-copy-vi="Đăng ký làm Shipper">Đăng ký làm Shipper</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
          <?php require __DIR__.'/../shared/shipper-application-form.php'; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="c-tabbar d-md-none" aria-label="Điều hướng nhanh">
    <?php
    $tabs = ['dashboard' => ['Tổng quan', 'home'], 'create' => ['Gửi hàng', 'send'], 'orders' => ['Đơn hàng', 'list'], 'notifications' => ['Thông báo', 'bell'], 'account' => ['Tài khoản', 'user']];
    foreach ($tabs as $key => [$label, $iconKey]): ?>
      <a href="?page=customer&amp;view=<?= e($key) ?>"<?= $key === 'create' ? ' data-send-link' : '' ?> class="<?= $customerSection === $key ? 'active' : '' ?>"<?= $customerSection === $key ? ' aria-current="page"' : '' ?>>
        <?= customerIcon($navIcon[$iconKey], 21) ?><span><?= e($label) ?></span><?php if ($key === 'notifications'): ?><i class="badge rounded-pill c-count" data-unread hidden>0</i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$liveCustomerView && !$person3View): ?>
  <aside class="c-demo" data-demo <?= $liveCustomerView ? 'hidden' : '' ?>>
    <button type="button" class="c-demo-toggle" data-demo-toggle aria-expanded="false"><?= customerIcon('<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>', 18) ?><span>Mô phỏng demo</span></button>
    <div class="c-demo-body" data-demo-body hidden></div>
  </aside>
  <?php endif; ?>
</div>
<script>document.body.dataset.userId = <?= json_encode((string) $sessionUserId) ?>; document.body.dataset.cView = <?= json_encode($customerView) ?>; document.title = <?= json_encode($customerTitle . ' · SOMI') ?>;</script>
<script defer src="<?= e(assetUrl('public/assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script defer src="<?= e(assetUrl($asset . 'lang/en.js')) ?>"></script>
<script defer src="<?= e(assetUrl($asset . 'i18n.js')) ?>"></script>
<?php if ($liveCustomerView): ?>
<?php if ($customerNeedsMap): ?>
<script defer src="<?= e(assetUrl('public/assets/vendor/leaflet/leaflet.js')) ?>"></script>
<script defer src="<?= e(assetUrl($asset . 'maps.js')) ?>"></script>
<script defer src="<?= e(assetUrl($asset . 'places.js')) ?>"></script>
<?php endif; ?>
<?php if ($customerView === 'create'): ?><script defer src="<?= e(assetUrl($asset . 'create-route.js')) ?>"></script><?php endif; ?>
<?php if (isset($sessionUser['user_id'],$sessionUser['full_name'],$sessionUser['app_language'])): ?>
<script type="application/json" id="customer-profile-data"><?= json_encode(['profile'=>array_intersect_key($sessionUser,array_flip(['user_id','full_name','email','phone','nationality_code','app_language','account_status','email_verified_at','phone_verified_at']))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<script defer src="<?= e(assetUrl($asset . 'server.js')) ?>"></script>
<?php elseif (!$person3View): ?>
<script defer src="<?= e(assetUrl($asset . 'store.js')) ?>"></script>
<script defer src="<?= e(assetUrl($asset . 'ui.js')) ?>"></script>
<?php if ($customerView !== 'backend'): ?><script defer src="<?= e(assetUrl($asset . 'pages/' . $customerView . '.js')) ?>"></script><?php endif; ?>
<?php endif; ?>
