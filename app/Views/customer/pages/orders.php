<?php // Customer: Order history (Đơn hàng) ?>
<section class="c-head">
  <div>
    <h1>Đơn hàng của bạn</h1>
    <p>Mọi đơn bạn đã tạo, kể cả đơn đã hủy hoặc hoàn hàng.</p>
  </div>
  <div class="c-head-actions"><a class="btn btn-dark" href="?page=customer&amp;view=create" data-send-link>Gửi hàng mới</a></div>
</section>

<div class="c-toolbar">
  <div class="nav nav-pills c-tabs" role="tablist" aria-label="Lọc theo trạng thái" data-tabs></div>
</div>
<div class="c-toolbar">
  <label class="c-search"><span class="visually-hidden">Tìm theo mã đơn hoặc người nhận</span>
    <svg class="c-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
    <input class="form-control" type="search" data-search placeholder="Tìm mã đơn, người nhận hoặc khu vực">
  </label>
  <div class="c-date-filter">
    <label class="visually-hidden" for="from">Từ ngày</label><input class="form-control" type="date" id="from" data-from aria-label="Từ ngày">
    <span>đến</span>
    <label class="visually-hidden" for="to">Đến ngày</label><input class="form-control" type="date" id="to" data-to aria-label="Đến ngày">
    <button type="button" class="c-link" data-clear hidden>Xóa lọc</button>
  </div>
</div>

<section class="card card-body">
  <div class="c-orders" data-list></div>
</section>
