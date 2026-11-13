<?php // Shipper page: active. Nội dung do public/assets/js/shipper-active.js dựng từ API deliveries.php. ?>
<section id="page-active" class="page<?= $shipperPage === 'active' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'active' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">ĐƠN ĐÃ NHẬN</div><h1>Đơn đang giao</h1><p>Quản lý các đơn bạn đã nhận và cập nhật từng mốc giao hàng.</p></div><span class="tag" id="activeCount">Đang tải…</span></div>
    <link rel="stylesheet" href="<?= e(assetUrl('public/assets/vendor/leaflet/leaflet.css')) ?>">
    <script defer src="<?= e(assetUrl('public/assets/vendor/leaflet/leaflet.js')) ?>"></script>
    <script defer src="<?= e(assetUrl('public/assets/js/customer/maps.js')) ?>"></script>
    <div class="delivery-workspace">
    <section class="card" id="activeDelivery" aria-live="polite">
      <div class="empty"><strong>Đang tải đơn đang giao…</strong></div>
    </section>
    <section class="card delivery-map-panel" data-delivery-map-panel data-geoapify-key="<?= e(getenv('GEOAPIFY_BROWSER_API_KEY') ?: '') ?>" hidden>
      <div class="sectiontitle"><div><h2>Lộ trình giao hàng</h2><p data-navigation-step>Đang tải lộ trình…</p></div><span class="tag" data-gps-badge>Đang kết nối GPS</span></div>
      <div id="shipperDeliveryMap" class="delivery-live-map" aria-label="Bản đồ giao hàng"></div>
      <p class="delivery-map-legend"><span>● Điểm lấy</span><span>● Điểm giao</span><span>● Vị trí của bạn</span></p>
      <p data-gps-status role="status">Đang kiểm tra quyền truy cập vị trí…</p>
      <p data-route-status role="status"></p>
      <a class="primary blue" data-open-navigation target="_blank" rel="noopener">Mở chỉ đường</a>
    </section>
    </div>
    <section class="support-hub card" data-order-chat hidden aria-label="Trò chuyện với khách hàng">
      <p data-order-chat-status role="status" aria-live="polite"></p>
      <div data-order-chat-content></div>
    </section>
    <link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/shipper-support.css')) ?>">
    <script defer src="<?= e(assetUrl('public/assets/js/shipper-support.js')) ?>"></script>
    <script defer src="<?= e(assetUrl('public/assets/js/shipper-order-chat.js')) ?>"></script>
    <input id="evidenceFile" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" hidden>
  </section>
