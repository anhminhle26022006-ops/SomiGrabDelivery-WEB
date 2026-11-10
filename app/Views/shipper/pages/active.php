<?php // Shipper page: active. Nội dung do public/assets/js/shipper-active.js dựng từ API deliveries.php. ?>
<section id="page-active" class="page<?= $shipperPage === 'active' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'active' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">ĐƠN ĐÃ NHẬN</div><h1>Đơn đang giao</h1><p>Quản lý các đơn bạn đã nhận và cập nhật từng mốc giao hàng.</p></div><span class="tag" id="activeCount">Đang tải…</span></div>
    <section class="card" id="activeDelivery" aria-live="polite">
      <div class="empty"><strong>Đang tải đơn đang giao…</strong></div>
    </section>
    <input id="evidenceFile" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" hidden>
  </section>
