<?php // Shipper page: notifications ?>
<section id="page-notifications" class="page<?= $shipperPage === 'notifications' ? '' : ' hidden' ?> notificationpage" aria-hidden="<?= $shipperPage === 'notifications' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">CẬP NHẬT TÀI KHOẢN VÀ ĐƠN HÀNG</div><h1>Thông báo</h1><p>Tập trung mọi cập nhật để bạn không bỏ lỡ thông tin quan trọng.</p></div><div class="noticecontrols"><button class="outline" id="markAll">✓ Đánh dấu tất cả đã đọc</button></div></div>
    <section class="card"><div class="filterbar"><button class="outline selected" data-notice-filter="all">Tất cả</button><button class="outline" data-notice-filter="orders">Đơn hàng</button><button class="outline" data-notice-filter="account">Tài khoản</button></div><div id="allNotices"></div></section>
  </section>
