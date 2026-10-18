<?php // Customer: Account (Tài khoản) ?>
<section class="c-head">
  <div>
    <h1>Tài khoản</h1>
    <p>Thông tin liên hệ được dùng để shipper và người nhận gọi cho bạn khi giao hàng.</p>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-4"><aside class="card card-body c-profile" data-profile></aside></div>

  <div class="col-lg-8 vstack gap-4">
    <section class="card card-body">
      <h2>Thông tin cá nhân</h2>
      <p class="c-panel-sub">Đổi email cần xác thực lại email mới.</p>
      <form data-profile-form novalidate>
        <div class="row g-3">
          <div class="c-field col-md-6"><label class="form-label" for="fullName">Họ và tên</label><input class="form-control" id="fullName" name="fullName" autocomplete="name" maxlength="100"></div>
          <div class="c-field col-md-6"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" maxlength="100"></div>
        </div>
        <div class="c-form-actions" data-profile-actions hidden>
          <button type="button" class="btn btn-outline-dark" data-profile-cancel>Hủy thay đổi</button>
          <button type="submit" class="btn btn-dark">Lưu thông tin</button>
        </div>
      </form>
    </section>

    <section class="card card-body" data-phone-panel></section>

    <section class="card card-body">
      <h2>Ngôn ngữ và tiền tệ</h2>
      <p class="c-panel-sub">Ngôn ngữ dùng chung với trang chủ SOMI. Tiền tệ áp dụng cho báo giá và đơn mới, đơn đã tạo giữ nguyên tiền tệ lúc đặt.</p>
      <div class="row g-3">
        <div class="c-field col-md-6"><span class="form-label c-label">Ngôn ngữ</span>
          <div class="btn-group w-100 c-locale-seg" role="group" aria-label="Ngôn ngữ">
            <button type="button" class="btn" data-set-lang="vi">Tiếng Việt</button>
            <button type="button" class="btn" data-set-lang="en">English</button>
          </div>
        </div>
        <div class="c-field col-md-6"><span class="form-label c-label">Tiền tệ</span>
          <div class="btn-group w-100 c-locale-seg" role="group" aria-label="Tiền tệ">
            <button type="button" class="btn" data-set-currency="VND">VND ₫</button>
            <button type="button" class="btn" data-set-currency="USD">USD $</button>
          </div>
        </div>
      </div>
      <p class="c-fx-note" data-fx-note></p>
    </section>

    <section class="card card-body">
      <h2>Đổi mật khẩu</h2>
      <p class="c-panel-sub" data-password-meta></p>
      <form data-password-form novalidate>
        <div class="row g-3">
          <div class="c-field col-12"><label class="form-label" for="currentPassword">Mật khẩu hiện tại</label><input class="form-control" id="currentPassword" type="password" autocomplete="current-password"></div>
          <div class="c-field col-md-6"><label class="form-label" for="newPassword">Mật khẩu mới</label><input class="form-control" id="newPassword" type="password" autocomplete="new-password"><small class="c-hint">Ít nhất 8 ký tự.</small></div>
          <div class="c-field col-md-6"><label class="form-label" for="confirmPassword">Nhập lại mật khẩu mới</label><input class="form-control" id="confirmPassword" type="password" autocomplete="new-password"></div>
        </div>
        <div class="c-form-actions"><button type="submit" class="btn btn-dark">Đổi mật khẩu</button></div>
      </form>
    </section>

    <section class="card card-body">
      <a class="c-section-row" href="?page=customer&amp;view=addresses"><span class="c-todo-icon c-tone-gray"><svg class="c-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M19 10c0 5.5-7 11-7 11s-7-5.5-7-11a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><div><strong>Sổ địa chỉ</strong><small data-address-count>Địa chỉ lấy và giao hàng đã lưu</small></div><svg class="c-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
      <a class="c-section-row" href="?page=customer&amp;view=reports"><span class="c-todo-icon c-tone-gray"><svg class="c-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M10 13h6M10 17h4"/></svg></span><div><strong>Báo cáo của tôi</strong><small>Theo dõi khiếu nại và kết quả xử lý</small></div><svg class="c-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
      <button class="c-section-row" type="button" data-signout style="width:100%;border:0;border-top:1px solid var(--c-line);background:none;text-align:left;padding:14px 2px"><span class="c-todo-icon c-tone-red"><svg class="c-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 5H5v14h5"/><path d="m15 8 4 4-4 4M9 12h10"/></svg></span><div><strong style="color:var(--c-red)">Đăng xuất</strong><small>Quay về trang chủ SOMI</small></div><span></span></button>
    </section>
  </div>
</div>
