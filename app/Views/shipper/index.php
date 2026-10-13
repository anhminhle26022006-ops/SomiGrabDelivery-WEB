<?php
// Shipper module UI based on the approved responsive shipper demo.
?>

<header class="topbar">
  <div class="brand">SOMI <small>DELIVERY</small></div>

  <div class="top-actions">
    <button class="iconbtn" id="bellBtn" title="Thông báo" aria-label="Thông báo">🔔<span class="badge-count" id="bellCount">3</span></button>
    <div class="profile">
      <button class="profilebtn" id="profileBtn" aria-expanded="false">
        <span class="avatar" id="avatar"><span>NA</span></span>
      </button>
      <div class="dropdown" id="profileDropdown">
        <div class="drophead"><strong>Nguyễn Văn A</strong><span>Quản lý tài khoản & trợ giúp</span></div>
        <button data-profile-action="account">👤 Tài khoản & hồ sơ</button>
        <button data-profile-action="support">🎧 Trung tâm hỗ trợ</button>
        <button data-profile-action="policy">📄 Chính sách & điều khoản</button>
        <button class="danger" data-profile-action="logout">↪ Đăng xuất</button>
      </div>
    </div>
  </div>
</header>
<div class="layout">
<aside id="sidebar">
  <div class="side-label">KHÔNG GIAN NGƯỜI GIAO</div>
  <a class="sidebtn<?= $shipperPage === 'dashboard' ? ' active' : '' ?>" href="?page=shipper&amp;view=dashboard" data-page="dashboard" aria-controls="page-dashboard"<?= $shipperPage === 'dashboard' ? ' aria-current="page"' : '' ?>><span>⌂</span>Tổng quan</a>
  <a class="sidebtn<?= $shipperPage === 'offers' ? ' active' : '' ?>" href="?page=shipper&amp;view=offers" data-page="offers" aria-controls="page-offers"<?= $shipperPage === 'offers' ? ' aria-current="page"' : '' ?>>Đơn giao phù hợp</a>
  <a class="sidebtn<?= $shipperPage === 'active' ? ' active' : '' ?>" href="?page=shipper&amp;view=active" data-page="active" aria-controls="page-active"<?= $shipperPage === 'active' ? ' aria-current="page"' : '' ?>>Đơn đang giao</a>
  <a class="sidebtn<?= $shipperPage === 'history' ? ' active' : '' ?>" href="?page=shipper&amp;view=history" data-page="history" aria-controls="page-history"<?= $shipperPage === 'history' ? ' aria-current="page"' : '' ?>>Lịch sử giao hàng</a>
  <a class="sidebtn<?= $shipperPage === 'earnings' ? ' active' : '' ?>" href="?page=shipper&amp;view=earnings" data-page="earnings" aria-controls="page-earnings"<?= $shipperPage === 'earnings' ? ' aria-current="page"' : '' ?>>Thu nhập</a>
  <div class="asidebottom">
    <div class="statusrow"><strong>Trạng thái nhận đơn</strong><button class="toggle on" id="availabilityToggle" aria-label="Bật tắt nhận đơn"></button></div>
    <div style="margin-top:8px"><span class="pill" id="availabilityPill">ĐANG NHẬN ĐƠN</span></div>
    <p class="muted" style="font-size:11px;margin:8px 0 0" id="availabilityHelp">Bạn sẽ nhận được các đơn phù hợp khi trực tuyến.</p>
  </div>
</aside>
<main>
  <?php require __DIR__ . '/pages/' . $shipperPage . '.php'; ?>
</main>
</div>
<div id="contactModal" class="modalback hidden" role="dialog" aria-modal="true" aria-labelledby="contactTitle">
  <div class="modal">
    <div class="modalhead"><div><h2 id="contactTitle">Liên hệ người nhận</h2><span class="muted" style="font-size:11px">Đơn SOMI-240126 · Nguyễn Minh Anh</span></div><button class="outline" onclick="closeContactChat()">✕</button></div>
    <div class="modalbody">
      <div style="display:flex;gap:9px;flex-wrap:wrap;margin-bottom:12px"><a class="primary blue" href="tel:0900000000" style="text-decoration:none">☎ Gọi người nhận</a><span class="pill">Chat trong đơn hàng</span></div>
      <div class="chatmessages" id="chatMessages"><div class="bubble">Chào anh/chị, tôi là shipper của đơn SOMI-240126. Tôi đang đến điểm giao hàng.</div><div class="bubble mine">Tôi đã đến nơi, anh/chị có thể nhận hàng chưa ạ?</div></div>
      <form class="chatcompose" id="chatForm"><input id="chatInput" placeholder="Nhập tin nhắn cho người nhận..." autocomplete="off" required><button class="primary blue" type="submit">Gửi</button></form>
      <p class="muted" style="font-size:11px;margin-bottom:0">Chat và gọi điện chỉ mô phỏng trong demo; số điện thoại cần được lấy từ dữ liệu đơn hàng khi tích hợp thật.</p>
    </div>
  </div>
</div>
<div id="toast" style="position:fixed;right:20px;bottom:20px;background:#142c53;color:#fff;padding:13px 17px;border-radius:12px;box-shadow:0 8px 30px #0002;z-index:100;display:none;max-width:360px"></div>
