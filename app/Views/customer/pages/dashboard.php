<?php // Customer: Dashboard (Tổng quan) ?>
<section class="c-head">
  <div>
    <h1 data-greeting>Xin chào</h1>
    <p data-today>Theo dõi các đơn đang chạy và những việc cần bạn xử lý.</p>
  </div>
  <div class="c-head-actions">
    <a class="btn btn-dark" href="?page=customer&amp;view=create" data-send-link>Gửi hàng mới</a>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-7 vstack gap-4">
    <div data-live></div>
    <section class="card card-body">
      <div class="c-panel-head"><h2>Đơn gần đây</h2><a class="c-link" href="?page=customer&amp;view=orders">Xem tất cả</a></div>
      <div class="c-orders" data-recent></div>
    </section>
  </div>
  <div class="col-lg-5 vstack gap-4">
    <section class="card card-body">
      <div class="c-panel-head"><h2>Cần bạn xử lý</h2></div>
      <div class="c-todo" data-todo></div>
    </section>
    <section class="card card-body">
      <h2>Tháng này</h2>
      <p class="c-panel-sub" data-month-label>Số liệu từ đầu tháng</p>
      <div class="c-figures" data-figures></div>
    </section>
    <section class="card card-body" data-default-address></section>
  </div>
</div>
