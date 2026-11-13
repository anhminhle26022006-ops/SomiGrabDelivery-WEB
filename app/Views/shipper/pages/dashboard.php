<?php // Shipper Dashboard page ?>
<section id="page-dashboard" class="page<?= $shipperPage === 'dashboard' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'dashboard' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">KHÔNG GIAN NGƯỜI GIAO</div><h1>Tổng quan</h1><p data-dashboard-description>Xem nhanh tình trạng hoạt động hôm nay của bạn.</p></div><div class="date-picker"><button class="datebtn" type="button" data-date-trigger aria-expanded="false">▦ &nbsp; <span data-date-label>Hôm nay</span> <span data-date-value></span>⌄</button><div class="date-menu" data-date-menu hidden><button type="button" data-date-range="today"><span class="shipper-copy-en">Today</span><span class="shipper-copy-vi">Hôm nay</span></button><button type="button" data-date-range="yesterday"><span class="shipper-copy-en">Yesterday</span><span class="shipper-copy-vi">Hôm qua</span></button><button type="button" data-date-range="week"><span class="shipper-copy-en">This week</span><span class="shipper-copy-vi">Tuần này</span></button><button type="button" data-date-range="month"><span class="shipper-copy-en">This month</span><span class="shipper-copy-vi">Tháng này</span></button></div></div></div>
    <p data-dashboard-status role="status" hidden></p>
    <div class="cards row g-3">
      <div class="col-12 col-sm-6 col-xl-3"><div class="card metric h-100"><div class="metricicon">▣</div><div><div class="metriclabel" data-period-stat="deliveries">Đơn hoàn thành hôm nay</div><div class="metricvalue" data-period-value="deliveries">…</div></div></div></div>
      <div class="col-12 col-sm-6 col-xl-3"><div class="card metric h-100"><div class="metricicon">▱</div><div><div class="metriclabel">Thu nhập đã ghi nhận</div><div data-p3-summary>Đang tải…</div><button class="textlink" data-goto="earnings">Xem chi tiết</button></div></div></div>
      <div class="col-12 col-sm-6 col-xl-3"><div class="card metric h-100"><div class="metricicon" style="background:#f0eaff;color:#8657e8">◷</div><div><div class="metriclabel" data-period-stat="hours">Thời gian xử lý đơn</div><div class="metricvalue" data-period-value="hours">…</div></div></div></div>
      <div class="col-12 col-sm-6 col-xl-3"><div class="card metric h-100"><div class="metricicon" style="background:#fff0db;color:#e68a17">☆</div><div><div class="metriclabel">Đánh giá trung bình</div><div class="metricvalue" data-shipper-rating>…</div><div class="muted" style="font-size:11px" data-shipper-rating-count></div></div></div></div>
    </div>
    <div class="dashboardgrid">
      <div class="stack">
        <section class="card">
          <div class="sectiontitle"><div><h2>Đơn giao phù hợp</h2><p>Đơn mới phù hợp với khu vực và phương tiện của bạn.</p></div><button class="textlink" data-goto="offers">Xem tất cả →</button></div>
          <div id="overviewOrders"></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Đơn đang giao</h2><p>Tóm tắt tiến độ đơn bạn đã nhận.</p></div><button class="textlink" data-goto="active">Chi tiết →</button></div>
          <div id="overviewDelivery" role="status">Đang tải đơn đang giao…</div>
        </section>
      </div>
      <div class="stack">
        <section class="card">
          <div class="sectiontitle"><div><h2>Thông báo mới</h2><p>Các cập nhật gần đây cần bạn chú ý.</p></div><button class="textlink" data-goto="notifications">Xem tất cả →</button></div>
          <div id="overviewNotices"></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Thu nhập và chi trả</h2><p>Tổng hợp các khoản đã ghi nhận.</p></div><button class="textlink" data-goto="earnings">Chi tiết →</button></div>
          <div data-p3-summary>Đang tải…</div>
        </section>
      </div>
    </div>
  </section>
