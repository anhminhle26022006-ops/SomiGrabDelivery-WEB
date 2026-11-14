<?php // Shipper page: history ?>
<section id="page-history" class="page<?= $shipperPage === 'history' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'history' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">CÁC ĐƠN ĐÃ KẾT THÚC</div><h1>Lịch sử giao hàng</h1><p>Tra cứu đơn hoàn tất hoặc bị hủy; không bao gồm đơn đang giao.</p></div></div>
    <section class="card"><div class="filterbar"><button class="outline selected" data-history-filter="all">Tất cả</button><button class="outline" data-history-filter="done">Hoàn thành</button><button class="outline" data-history-filter="returned">Đã hoàn hàng</button><button class="outline" data-history-filter="cancel">Đã hủy</button></div><div class="tablewrap"><table class="historytable"><thead><tr><th>Mã đơn</th><th>Thời gian</th><th>Lộ trình</th><th>Thu nhập</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody id="historyRows"></tbody></table></div><button class="outline" type="button" data-history-more hidden>Tải thêm đơn</button></section>
  </section>

<dialog class="s-history-dialog" data-history-dialog aria-labelledby="history-receipt-title">
  <header><div><small>BIÊN NHẬN GIAO HÀNG</small><h2 id="history-receipt-title">Chi tiết lịch sử</h2></div><button type="button" class="outline" data-close-history aria-label="Đóng chi tiết">✕</button></header>
  <div data-history-content aria-live="polite"></div>
  <footer><button type="button" class="outline" data-close-history>Quay lại lịch sử</button></footer>
</dialog>
<dialog class="s-history-photo-dialog" data-history-photo aria-label="Ảnh minh chứng">
  <button type="button" class="outline" data-close-history-photo>Quay lại chi tiết</button><img alt="Ảnh minh chứng giao nhận" data-history-full-photo>
</dialog>
