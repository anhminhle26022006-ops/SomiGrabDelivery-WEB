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
if (!array_key_exists($customerView, $customerViews)) {
    $customerView = 'dashboard';
}
[$customerTitle, $customerSection] = $customerViews[$customerView];
$asset = 'public/assets/js/customer/';
$assetVersion = '20261018-2';

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="public/assets/css/modules/customer.css?v=<?= $assetVersion ?>">
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
          <?= customerIcon($navIcon['globe'], 18) ?><span data-locale-current>VI · VND</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end c-locale-menu" data-locale-menu>
          <p class="c-locale-title">Ngôn ngữ</p>
          <div class="btn-group w-100 c-locale-seg" role="group" aria-label="Ngôn ngữ">
            <button type="button" class="btn" data-set-lang="vi">Tiếng Việt</button>
            <button type="button" class="btn" data-set-lang="en">English</button>
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
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item text-danger" type="button" data-signout>Đăng xuất</button></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="c-main" id="c-main">
    <div data-account-banner></div>
    <?php require __DIR__ . '/pages/' . $customerView . '.php'; ?>
  </main>

  <nav class="c-tabbar d-md-none" aria-label="Điều hướng nhanh">
    <?php
    $tabs = ['dashboard' => ['Tổng quan', 'home'], 'create' => ['Gửi hàng', 'send'], 'orders' => ['Đơn hàng', 'list'], 'notifications' => ['Thông báo', 'bell'], 'account' => ['Tài khoản', 'user']];
    foreach ($tabs as $key => [$label, $iconKey]): ?>
      <a href="?page=customer&amp;view=<?= e($key) ?>"<?= $key === 'create' ? ' data-send-link' : '' ?> class="<?= $customerSection === $key ? 'active' : '' ?>"<?= $customerSection === $key ? ' aria-current="page"' : '' ?>>
        <?= customerIcon($navIcon[$iconKey], 21) ?><span><?= e($label) ?></span><?php if ($key === 'notifications'): ?><i class="badge rounded-pill c-count" data-unread hidden>0</i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <aside class="c-demo" data-demo>
    <button type="button" class="c-demo-toggle" data-demo-toggle aria-expanded="false"><?= customerIcon('<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>', 18) ?><span>Mô phỏng demo</span></button>
    <div class="c-demo-body" data-demo-body hidden></div>
  </aside>
</div>
<script>document.body.dataset.cView = <?= json_encode($customerView) ?>; document.title = <?= json_encode($customerTitle . ' · SOMI') ?>;</script>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script defer src="<?= $asset ?>lang/en.js?v=<?= $assetVersion ?>"></script>
<script defer src="<?= $asset ?>i18n.js?v=<?= $assetVersion ?>"></script>
<script defer src="<?= $asset ?>store.js?v=<?= $assetVersion ?>"></script>
<script defer src="<?= $asset ?>ui.js?v=<?= $assetVersion ?>"></script>
<script defer src="<?= $asset ?>pages/<?= e($customerView) ?>.js?v=<?= $assetVersion ?>"></script>
