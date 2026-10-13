<?php // Shipper page: history ?>
<section id="page-history" class="page<?= $shipperPage === 'history' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'history' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">CÁC ĐƠN ĐÃ KẾT THÚC</div><h1>Lịch sử giao hàng</h1><p>Tra cứu đơn hoàn tất hoặc bị hủy; không bao gồm đơn đang giao.</p></div></div>
    <section class="card"><div class="filterbar"><button class="outline selected" data-history-filter="all">Tất cả</button><button class="outline" data-history-filter="done">Hoàn thành</button><button class="outline" data-history-filter="cancel">Đã hủy</button></div><div class="tablewrap"><table class="historytable"><thead><tr><th>Mã đơn</th><th>Thời gian</th><th>Lộ trình</th><th>Thu nhập</th><th>Trạng thái</th></tr></thead><tbody id="historyRows"></tbody></table></div></section>
  </section>
