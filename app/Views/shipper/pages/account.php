<?php // Shipper page: account ?>
<section id="page-account" class="page<?= $shipperPage === 'account' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'account' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">HỒ SƠ NGƯỜI GIAO</div><h1>Tài khoản & hồ sơ</h1><p>Quản lý thông tin cá nhân và ảnh đại diện của bạn.</p></div></div>
    <section class="card" style="max-width:900px">
      <div class="sectiontitle"><div><h2>Thông tin cá nhân</h2><p>Thông tin tài khoản của bạn trên SOMI.</p></div></div>
      <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:22px">
        <span class="avatar" id="accountAvatar" style="width:88px;height:88px;font-size:24px"><span>NA</span></span>
        <div><strong style="display:block;font-size:16px" id="accountName">Đang tải…</strong><span class="muted">Shipper, mã tài khoản <span id="accountCode">…</span></span><div style="margin-top:10px"><label for="avatarUpload" class="primary blue" style="display:inline-block;padding:10px 13px;border-radius:10px;cursor:pointer">📷 Tải ảnh đại diện</label><input id="avatarUpload" type="file" accept="image/*" hidden></div></div>
      </div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="shipperName">Họ và tên</label><input id="shipperName" value="" readonly style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperPhone">Số điện thoại</label><input id="shipperPhone" value="" readonly style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperEmail">Email</label><input id="shipperEmail" value="" readonly style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperArea">Khu vực hoạt động</label><input id="shipperArea" value="TP. Hồ Chí Minh" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Địa chỉ liên hệ</h2><p>Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div style="grid-column:1/-1"><label class="muted" for="shipperAddress">Địa chỉ cụ thể</label><input id="shipperAddress" placeholder="Số nhà, tên đường..." style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperWard">Phường / xã</label><input id="shipperWard" placeholder="Nhập phường / xã" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperCity">Tỉnh / thành phố</label><input id="shipperCity" value="TP. Hồ Chí Minh" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Tài khoản nhận thanh toán</h2><p>Đổi tài khoản bằng mật khẩu hiện tại và OTP gửi đến email đã xác minh. Admin chuyển khoản và đối soát chứng từ.</p></div></div>
      <div class="p3-panel" data-shipper-bank-editor aria-live="polite"><p>Đang tải thông tin ngân hàng…</p></div>
      <form id="kycResubmitForm" hidden style="margin-top:24px">
        <h3>Bổ sung giấy tờ KYC</h3>
        <label>Loại giấy tờ <select name="document_type"><option value="IDENTITY_FRONT">Mặt trước giấy tờ định danh</option><option value="IDENTITY_BACK">Mặt sau giấy tờ định danh</option><option value="DRIVER_LICENSE">Giấy phép lái xe</option><option value="VEHICLE_REGISTRATION">Đăng ký xe</option><option value="PORTRAIT">Ảnh chân dung</option></select></label>
        <label>File (tối đa 5 MiB) <input type="file" name="document_file" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
        <button type="submit" class="primary">Tải giấy tờ và nộp lại hồ sơ</button>
        <p role="status" id="kycUploadStatus"></p>
      </form>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Thông tin phương tiện</h2><p>Thông tin dùng để xác minh phương tiện và ghép đơn phù hợp.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="vehicleType">Loại phương tiện</label><select id="vehicleType" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px"><option>Xe máy</option><option>Xe máy điện</option><option>Ô tô</option><option>Xe tải nhỏ</option></select></div>
        <div><label class="muted" for="vehicleBrand">Hãng xe / dòng xe</label><input id="vehicleBrand" value="Honda Vision" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="vehiclePlate">Biển số xe</label><input id="vehiclePlate" value="59-X1 123.45" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="vehicleColor">Màu xe</label><input id="vehicleColor" value="Trắng" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="licenseNumber">Số giấy phép lái xe (nếu áp dụng)</label><input id="licenseNumber" placeholder="Nhập số GPLX" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="licenseExpiry">Ngày hết hạn GPLX</label><input id="licenseExpiry" type="date" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="registrationNumber">Số đăng ký xe</label><input id="registrationNumber" placeholder="Nhập số đăng ký xe" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="insuranceExpiry">Ngày hết hạn bảo hiểm</label><input id="insuranceExpiry" type="date" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="serviceArea">Khu vực / bán kính nhận đơn</label><input id="serviceArea" value="TP. Hồ Chí Minh · 10 km" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Người liên hệ khẩn cấp</h2><p>Dùng khi có sự cố trong quá trình giao hàng.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="emergencyName">Họ tên người liên hệ</label><input id="emergencyName" placeholder="Nhập họ tên" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="emergencyPhone">Số điện thoại người liên hệ</label><input id="emergencyPhone" type="tel" placeholder="Nhập số điện thoại" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <p class="muted" style="font-size:12px;margin-top:18px">Trạng thái xác minh hồ sơ: <strong id="kycStatusLine">Đang tải…</strong></p>
      <p class="muted" id="kycHint" style="font-size:12px"></p>
      <button class="primary" type="button" id="saveVehicleBtn">Lưu thông tin phương tiện</button>
    </section>
  </section>
