<?php // Shipper Dashboard page ?>
<section id="page-dashboard" class="page<?= $shipperPage === 'dashboard' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'dashboard' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">KHÔNG GIAN NGƯỜI GIAO</div><h1>Tổng quan</h1><p data-dashboard-description>Xem nhanh tình trạng hoạt động hôm nay của bạn.</p></div><div class="date-picker"><button class="datebtn" type="button" data-date-trigger aria-expanded="false">▦ &nbsp; <span data-date-label>Hôm nay</span> <span data-date-value></span>⌄</button><div class="date-menu" data-date-menu hidden><button type="button" data-date-range="today"><span class="shipper-copy-en">Today</span><span class="shipper-copy-vi">Hôm nay</span></button><button type="button" data-date-range="yesterday"><span class="shipper-copy-en">Yesterday</span><span class="shipper-copy-vi">Hôm qua</span></button><button type="button" data-date-range="week"><span class="shipper-copy-en">This week</span><span class="shipper-copy-vi">Tuần này</span></button><button type="button" data-date-range="month"><span class="shipper-copy-en">This month</span><span class="shipper-copy-vi">Tháng này</span></button></div></div></div>
    <?php // Số liệu bốn thẻ dưới đây do public/assets/js/shipper-dashboard.js điền từ API shipper/dashboard.php. ?>
    <p data-dashboard-status role="status" hidden></p>
    <div class="cards row g-3">
      <div class="col-12 col-sm-6 col-lg-4 col-xl"><div class="card metric h-100"><div class="metricicon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 7 9-4 9 4-9 4-9-4Z M3 7v10l9 4 9-4V7 M12 11v10"/></svg></div><div class="metricbody"><div class="metriclabel" data-period-stat="deliveries">Đơn hoàn thành hôm nay</div><div class="metricvalue" id="dashDeliveries" data-period-value="deliveries">…</div><div class="trend" id="dashDeliveriesTrend"></div></div></div></div>
      <div class="col-12 col-sm-6 col-lg-4 col-xl"><div class="card metric h-100"><div class="metricicon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18v14H3V6Z M3 10h18 M16 15h2"/></svg></div><div class="metricbody"><div class="metriclabel" data-copy-vi="Thu nhập đã ghi nhận" data-copy-en="Earnings recorded">Thu nhập đã ghi nhận</div><div class="metricmoney" data-p3-summary>Đang tải…</div><button class="textlink" data-goto="earnings">Xem chi tiết</button></div></div></div>
      <div class="col-12 col-sm-6 col-lg-4 col-xl"><div class="card metric h-100"><div class="metricicon metricicon-violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 M12 3v9h9"/></svg></div><div class="metricbody"><div class="metriclabel" data-period-stat="acceptance">Tỷ lệ nhận đơn hôm nay</div><div class="metricvalue" id="dashAcceptance">…</div><div class="metricnote muted" id="dashAcceptanceNote"></div></div></div></div>
      <div class="col-12 col-sm-6 col-lg-4 col-xl"><div class="card metric h-100"><div class="metricicon metricicon-teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l3 2"/></svg></div><div class="metricbody"><div class="metriclabel" data-copy-vi="Thời gian xử lý đơn" data-copy-en="Time spent on deliveries">Thời gian xử lý đơn</div><div class="metricvalue" data-period-value="hours">…</div></div></div></div>
      <div class="col-12 col-sm-6 col-lg-4 col-xl"><div class="card metric h-100"><div class="metricicon metricicon-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg></div><div class="metricbody"><div class="metriclabel" data-copy-vi="Đánh giá trung bình" data-copy-en="Average rating">Đánh giá trung bình</div><div class="metricvalue" id="dashRating" data-shipper-rating>…</div><div class="metricnote muted" id="dashRatingNote" data-shipper-rating-count></div></div></div></div>
    </div>
    <div class="dashboardgrid">
      <div class="stack">
        <section class="card s-active-card">
          <div class="sectiontitle"><div><h2>Đơn đang giao</h2><p>Tóm tắt tiến độ đơn bạn đã nhận.</p></div><button class="textlink" data-goto="active">Chi tiết →</button></div>
          <div id="overviewActive" aria-live="polite"><div class="empty"><strong>Đang tải đơn đang giao…</strong></div></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Đơn giao phù hợp</h2><p>Đơn mới phù hợp với khu vực và phương tiện của bạn.</p></div><button class="textlink" data-goto="offers">Xem tất cả →</button></div>
          <div id="overviewOrders"></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Thu nhập và chi trả</h2><p>Tổng hợp các khoản đã ghi nhận.</p></div><button class="textlink" data-goto="earnings">Chi tiết →</button></div>
          <div class="summarymoney" data-p3-summary>Đang tải…</div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Thông báo mới</h2><p>Các cập nhật gần đây cần bạn chú ý.</p></div><button class="textlink" data-goto="notifications">Xem tất cả →</button></div>
          <div id="overviewNotices"></div>
        </section>
      </div>
      <div class="stack">
        <?php require __DIR__.'/../../shared/workspace-weather.php'; ?>
        <section class="workspace-guide" data-shift-checklist>
          <div class="sectiontitle"><h2 data-w-vi="Sẵn sàng lên đường" data-w-en="Ready for the road">Sẵn sàng lên đường</h2><small data-shift-ready>0 / 3</small></div>
          <div class="workspace-checklist">
            <label><input type="checkbox"><span><span data-w-vi="Mũ bảo hiểm, giấy tờ và xe" data-w-en="Helmet, documents and vehicle">Mũ bảo hiểm, giấy tờ và xe</span><small data-w-vi="Kiểm tra trước khi bắt đầu ca." data-w-en="Check before starting your shift.">Kiểm tra trước khi bắt đầu ca.</small></span></label>
            <label><input type="checkbox"><span><span data-w-vi="Áo mưa và túi chống nước" data-w-en="Rainwear and waterproof bags">Áo mưa và túi chống nước</span><small data-w-vi="Giữ hàng khô khi thời tiết thay đổi." data-w-en="Keep parcels dry when weather changes.">Giữ hàng khô khi thời tiết thay đổi.</small></span></label>
            <label><input type="checkbox"><span><span data-w-vi="Pin điện thoại và nước uống" data-w-en="Phone battery and drinking water">Pin điện thoại và nước uống</span><small data-w-vi="Sẵn sàng liên lạc trong suốt chuyến giao." data-w-en="Stay reachable throughout your delivery.">Sẵn sàng liên lạc trong suốt chuyến giao.</small></span></label>
          </div>
        </section>
        <section class="workspace-safety"><strong data-w-vi="An toàn trước, đơn hàng sau." data-w-en="Safety comes before the delivery.">An toàn trước, đơn hàng sau.</strong><span data-w-vi="Dừng xe ở nơi an toàn trước khi xem thông báo, gọi điện hoặc cập nhật đơn." data-w-en="Stop somewhere safe before checking notifications, calling or updating a delivery.">Dừng xe ở nơi an toàn trước khi xem thông báo, gọi điện hoặc cập nhật đơn.</span></section>
      </div>
    </div>
  </section>
