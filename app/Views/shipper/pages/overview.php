<?php // Shipper page: overview ?>
<section id="page-overview" class="page<?= $shipperPage === 'overview' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'overview' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">KHÔNG GIAN NGƯỜI GIAO</div><h1>Tổng quan</h1><p>Xem nhanh tình trạng hoạt động hôm nay của bạn.</p></div><button class="datebtn">▦ &nbsp; Hôm nay (12/10/2026)⌄</button></div>
    <div class="cards">
      <div class="card metric"><div class="metricicon">▣</div><div><div class="metriclabel">Đơn hoàn thành hôm nay</div><div class="metricvalue">5</div><div class="trend">↑ 25% <span class="muted">so với hôm qua</span></div></div></div>
      <div class="card metric"><div class="metricicon" style="background:#e4f8ed;color:#14a568">▱</div><div><div class="metriclabel">Thu nhập hôm nay</div><div class="metricvalue">₫320,000</div><div class="trend">↑ 12% <span class="muted">so với hôm qua</span></div></div></div>
      <div class="card metric"><div class="metricicon" style="background:#f0eaff;color:#8657e8">◷</div><div><div class="metriclabel">Thời gian hoạt động</div><div class="metricvalue">4h 30m</div><div class="trend">↑ 10% <span class="muted">so với hôm qua</span></div></div></div>
      <div class="card metric"><div class="metricicon" style="background:#fff0db;color:#e68a17">☆</div><div><div class="metriclabel">Đánh giá trung bình</div><div class="metricvalue">4.9 <span style="font-size:12px;font-weight:600">/ 5</span></div><div class="muted" style="font-size:11px">28 đánh giá</div></div></div>
    </div>
    <div class="dashboardgrid">
      <div class="stack">
        <section class="card">
          <div class="sectiontitle"><div><h2>Đơn giao phù hợp</h2><p>Đơn mới phù hợp với khu vực và phương tiện của bạn.</p></div><button class="textlink" data-goto="offers">Xem tất cả →</button></div>
          <div id="overviewOrders"></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Đơn đang giao</h2><p>Tóm tắt tiến độ đơn bạn đã nhận.</p></div><button class="textlink" data-goto="active">Chi tiết →</button></div>
          <div class="orderid">SOMI-240126 <span class="tag">ĐANG GIAO</span></div>
          <div class="route" style="margin-top:12px"><div class="routepoints"><span class="dot"></span><span class="routebar"></span><span class="dot red"></span></div><div class="address"><strong>Phú Nhuận, TP. Hồ Chí Minh</strong><small>12 Nguyễn Văn Trỗi, Phường 11</small><div style="height:14px"></div><strong>Quận 3, TP. Hồ Chí Minh</strong><small>241 Võ Văn Tần, Phường 5</small></div><div class="pay"><small>Thu nhập</small><strong>₫42,000</strong></div></div>
          <div class="progress"><div class="step done"><div class="stepnum">✓</div><strong>Đã nhận</strong></div><div class="step done"><div class="stepnum">●</div><strong>Đến điểm lấy</strong></div><div class="step current"><div class="stepnum">3</div><strong>Đang giao</strong></div><div class="step"><div class="stepnum">4</div><strong>Hoàn tất</strong></div></div>
          <div class="mapmock"><span class="mapcaption">● 2.8 km · khoảng 12 phút</span><span class="pin start">●</span><span class="pin end">♥</span><span class="maproute"></span></div>
          <div style="display:flex;justify-content:flex-end;margin-top:12px"><button class="primary" data-goto="active">Mở đơn đang giao →</button></div>
        </section>
      </div>
      <div class="stack">
        <section class="card">
          <div class="sectiontitle"><div><h2>Thông báo mới</h2><p>Các cập nhật gần đây cần bạn chú ý.</p></div><button class="textlink" data-goto="notifications">Xem tất cả →</button></div>
          <div id="overviewNotices"></div>
        </section>
        <section class="card">
          <div class="sectiontitle"><div><h2>Thu nhập</h2><p>Tóm tắt thu nhập hôm nay.</p></div><button class="textlink" data-goto="earnings">Chi tiết →</button></div>
          <div class="metricvalue">₫320,000</div><div class="trend">↑ 12% <span class="muted">so với hôm qua</span></div>
          <div class="chart"><div class="barwrap"><div class="bar" style="height:35%"></div><span class="barlabel">9h</span></div><div class="barwrap"><div class="bar" style="height:55%"></div><span class="barlabel">11h</span></div><div class="barwrap"><div class="bar" style="height:43%"></div><span class="barlabel">13h</span></div><div class="barwrap"><div class="bar" style="height:85%"></div><span class="barlabel">15h</span></div><div class="barwrap"><div class="bar" style="height:62%"></div><span class="barlabel">17h</span></div></div>
          <p class="muted" style="font-size:11px;margin-bottom:0">Biểu đồ tóm tắt · vào Thu nhập để xem chi tiết.</p>
        </section>
      </div>
    </div>
  </section>
