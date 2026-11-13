<?php // Customer: Dashboard (Tổng quan) ?>
<div class="c-dashboard">
<section class="c-head c-dashboard-heading">
  <div>
    <span class="c-eyebrow">MỖI CHUYẾN GỬI, MỘT NIỀM TIN</span>
    <h1 data-greeting>Xin chào</h1>
    <p data-today>Theo dõi các đơn đang chạy và những việc cần bạn xử lý.</p>
  </div>
  <div class="c-head-actions">
    <a class="btn btn-dark" href="?page=customer&amp;view=create" data-send-link>Gửi hàng mới</a>
  </div>
</section>

<?php require __DIR__.'/../../shared/workspace-weather.php'; ?>

<div class="row g-4 align-items-start">
  <div class="col-lg-7 vstack gap-4">
    <div data-live></div>
    <section class="card card-body c-month-card">
      <h2>Tháng này</h2>
      <p class="c-panel-sub" data-month-label>Số liệu từ đầu tháng</p>
      <div class="c-figures" data-figures></div>
    </section>
    <section class="card card-body">
      <div class="c-panel-head"><h2>Đơn gần đây</h2><a class="c-link" href="?page=customer&amp;view=orders">Xem tất cả</a></div>
      <div class="c-orders" data-recent></div>
    </section>
    <section class="card card-body" data-default-address></section>
  </div>
  <div class="col-lg-5 vstack gap-4">
    <section class="card card-body c-attention-card">
      <div class="c-panel-head"><h2>Cần bạn xử lý</h2></div>
      <div class="c-todo" data-todo></div>
    </section>
    <section class="workspace-guide">
      <div class="c-panel-sub" data-w-vi="GỬI HÀNG AN TÂM" data-w-en="SEND WITH CONFIDENCE">GỬI HÀNG AN TÂM</div>
      <h2 data-w-vi="Chuẩn bị tốt, giao thuận lợi" data-w-en="Prepare well, deliver smoothly">Chuẩn bị tốt, giao thuận lợi</h2>
      <div class="wg-tip"><h3 data-w-vi="Giữ giấy tờ khô ráo" data-w-en="Keep documents dry">Giữ giấy tờ khô ráo</h3><p data-w-vi="Dùng túi chống nước và dán kín kiện hàng, nhất là khi dự báo có mưa." data-w-en="Use waterproof packaging and seal parcels, especially when rain is forecast.">Dùng túi chống nước và dán kín kiện hàng, nhất là khi dự báo có mưa.</p></div>
      <div class="wg-tip"><h3 data-w-vi="Đúng số, dễ liên hệ" data-w-en="Make contact easy">Đúng số, dễ liên hệ</h3><p data-w-vi="Kiểm tra số điện thoại người nhận và ghi chú lối vào cho shipper." data-w-en="Check the recipient’s phone number and add access instructions for your shipper.">Kiểm tra số điện thoại người nhận và ghi chú lối vào cho shipper.</p></div>
      <a class="wg-link" href="?page=customer&amp;view=create" data-w-vi="Chuẩn bị đơn gửi mới →" data-w-en="Prepare a new delivery →">Chuẩn bị đơn gửi mới →</a>
    </section>
  </div>
</div>
<section class="workspace-support"><div><h2 data-w-vi="Cần hỗ trợ cho một đơn hàng?" data-w-en="Need help with a delivery?">Cần hỗ trợ cho một đơn hàng?</h2><p data-w-vi="Tạo yêu cầu và theo dõi phản hồi ngay trong tài khoản." data-w-en="Create a support request and follow replies in your account.">Tạo yêu cầu và theo dõi phản hồi ngay trong tài khoản.</p></div><a class="btn btn-outline-dark" href="?page=customer&amp;view=reports" data-w-vi="Trung tâm hỗ trợ" data-w-en="Support centre">Trung tâm hỗ trợ</a></section>

</div>
