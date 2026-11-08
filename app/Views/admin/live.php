<?php
require_once __DIR__.'/_entry.php';
$adminSections=[
 'index'=>['Tổng quan','Overview','house'], 'users'=>['Tài khoản','Accounts','user-group'],
 'customers'=>['Khách hàng','Customers','users'], 'shippers'=>['Shipper & KYC','Shippers & KYC','motorcycle'],
 'orders'=>['Đơn hàng','Orders','box'], 'payments'=>['Thanh toán','Payments','wallet'], 'refunds'=>['Hoàn tiền','Refunds','rotate-left'],
 'complaints'=>['Ticket hỗ trợ','Support tickets','headset'], 'reviews'=>['Đánh giá','Reviews','star'],
 'categories'=>['Danh mục kiện hàng','Parcel categories','layer-group'], 'policies'=>['Giá & chính sách','Pricing & policies','sliders'],
 'nationality'=>['Yêu cầu quốc tịch','Nationality requests','earth-asia'], 'notifications'=>['Thông báo của tôi','My notifications','bell'],
 'audit-logs'=>['Nhật ký quản trị','Audit logs','shield-halved'], 'operations'=>['Email & tác vụ','Email & jobs','list-check']
];
$liveView=['backend'=>'policies','disputes'=>'complaints'][$adminView]??$adminView;
if(!isset($adminSections[$liveView]))$liveView='index';
?>
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/admin-live.css')) ?>">
<div class="app-container admin-live" data-admin-console data-view="<?= e($liveView) ?>">
 <aside class="sidebar" aria-label="Admin navigation">
  <a class="sidebar-brand admin-brand" href="?page=admin"><strong>SOMI <span>DELIVERY</span></strong></a>
  <div class="sidebar-category">ADMIN CONSOLE</div>
  <nav class="sidebar-menu nav flex-column">
   <?php foreach($adminSections as $key=>[$vn,$en,$symbol]): ?>
   <a class="menu-item nav-link <?= $liveView===$key?'active':'' ?>" href="?page=admin&amp;view=<?= e($key) ?>" <?= $liveView===$key?'aria-current="page"':'' ?>><i class="fa-solid fa-<?= e($symbol) ?>" aria-hidden="true"></i><span data-admin-vi="<?= e($vn) ?>" data-admin-en="<?= e($en) ?>"><?= e($vn) ?></span></a>
   <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer"><button class="menu-item logout" type="button" data-signout><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span data-admin-vi="Đăng xuất" data-admin-en="Sign out">Đăng xuất</span></button></div>
 </aside>
 <main class="main-content">
  <header class="top-header"><span class="admin-breadcrumb">SOMI / <span data-admin-vi="<?= e($adminSections[$liveView][0]) ?>" data-admin-en="<?= e($adminSections[$liveView][1]) ?>"><?= e($adminSections[$liveView][0]) ?></span></span><div class="header-right"><?php require __DIR__.'/../shared/language-picker.php'; ?><a class="admin-profile" href="?page=admin&amp;view=notifications"><span class="admin-avatar" data-admin-initials>A</span><span data-admin-name>Admin</span></a><button class="btn btn-outline-secondary admin-btn admin-mobile-signout" type="button" data-signout><span data-admin-vi="Đăng xuất" data-admin-en="Sign out">Đăng xuất</span></button></div></header>
  <div class="dashboard-body">
   <div class="admin-heading"><div><span class="subtitle">SOMI OPERATIONS</span><h1 data-admin-vi="<?= e($adminSections[$liveView][0]) ?>" data-admin-en="<?= e($adminSections[$liveView][1]) ?>"><?= e($adminSections[$liveView][0]) ?></h1><p class="admin-muted" data-admin-vi="Quản lý hoạt động giao hàng từ dữ liệu hệ thống." data-admin-en="Manage delivery operations using live system data.">Quản lý hoạt động giao hàng từ dữ liệu hệ thống.</p></div><button type="button" class="btn btn-outline-secondary admin-btn" data-admin-refresh><i class="fa-solid fa-rotate" aria-hidden="true"></i><span data-admin-vi="Làm mới" data-admin-en="Refresh">Làm mới</span></button></div>
   <div data-admin-status role="status" aria-live="polite"></div>
   <section data-admin-content aria-busy="true"><p data-admin-vi="Đang tải dữ liệu…" data-admin-en="Loading data…">Đang tải dữ liệu…</p></section>
  </div>
 </main>
 <dialog class="admin-dialog" data-admin-dialog aria-labelledby="admin-dialog-title"><div class="admin-dialog-head"><h2 id="admin-dialog-title"></h2><button type="button" class="btn btn-outline-secondary admin-btn" data-dialog-close aria-label="Close">×</button></div><div data-dialog-body></div></dialog>
</div>
<script defer src="<?= e(assetUrl('public/assets/js/admin-live.js')) ?>"></script>
