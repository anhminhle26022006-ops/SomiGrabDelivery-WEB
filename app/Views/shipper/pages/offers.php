<?php // Shipper page: offers ?>
<section id="page-offers" class="page<?= $shipperPage === 'offers' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'offers' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">CƠ HỘI GIAO HÀNG</div><h1>Đơn giao phù hợp</h1><p>Chỉ hiển thị đơn chưa được nhận, phù hợp với bạn.</p></div><span class="pill" id="offerAvailability">ĐANG NHẬN ĐƠN</span></div>
    <section class="card"><div class="sectiontitle"><div><h2>Đơn mới trong khu vực</h2><p>Kiểm tra lộ trình và thu nhập trước khi nhận đơn.</p></div><select class="outline" id="offerSort"><option value="recommended">Đề xuất phù hợp</option><option value="pay">Thu nhập cao nhất</option><option value="distance">Gần nhất</option></select></div><div id="allOrders"></div></section>
  </section>
