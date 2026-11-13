<?php // Shipper page: account ?>
<section id="page-account" class="page<?= $shipperPage === 'account' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'account' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">HỒ SƠ NGƯỜI GIAO</div><h1>Tài khoản & hồ sơ</h1><p>Quản lý thông tin tài khoản, phương tiện và giấy tờ của bạn.</p></div></div>
    <section class="card" style="max-width:900px">
      <div class="sectiontitle"><div><h2>Thông tin cá nhân</h2><p>Thông tin tài khoản đã đăng ký. Bạn có thể cập nhật họ tên.</p></div></div>
      <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:22px">
        <span class="avatar" id="accountAvatar" style="width:88px;height:88px;font-size:24px"><span>NA</span></span>
        <div><strong data-shipper-name style="display:block;font-size:16px">Đang tải…</strong><span class="muted" data-shipper-account-id>Shipper</span></div>
      </div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="shipperName">Họ và tên</label><input id="shipperName" required maxlength="150" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperPhone">Số điện thoại</label><input id="shipperPhone" readonly style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperEmail">Email</label><input id="shipperEmail" readonly style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperArea">Khu vực hoạt động</label><input id="shipperArea" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
      </div>
      <button id="saveProfileBtn" type="button" class="primary" style="margin-top:16px" disabled>Lưu họ tên</button>
      <p class="muted">Thông tin đăng ký hiển thị từ hồ sơ đã nộp. Giấy tờ được quản lý qua hồ sơ KYC.</p>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Địa chỉ liên hệ</h2><p>Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div style="grid-column:1/-1"><label class="muted" for="shipperAddress">Địa chỉ cụ thể</label><input id="shipperAddress" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="shipperWard">Phường / xã</label><input id="shipperWard" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="shipperCity">Tỉnh / thành phố</label><input id="shipperCity" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Tài khoản nhận thanh toán</h2><p>Đổi tài khoản bằng mật khẩu hiện tại và OTP gửi đến email đã xác minh. Admin chuyển khoản và đối soát chứng từ.</p></div></div>
      <button class="outline" type="button" onclick="window.somiPerson3Open('bank')">Quản lý tài khoản nhận tiền</button>
      <h3 style="margin-top:24px">Tài liệu KYC đã nộp</h3><div id="kycDocuments" aria-live="polite">Đang tải tài liệu…</div>
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
        <div><label class="muted" for="vehicleBrand">Hãng xe / dòng xe</label><input id="vehicleBrand" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="vehiclePlate">Biển số xe</label><input id="vehiclePlate" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="vehicleColor">Màu xe</label><input id="vehicleColor" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="licenseNumber">Số giấy phép lái xe (nếu áp dụng)</label><input id="licenseNumber" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="licenseExpiry">Ngày hết hạn GPLX</label><input id="licenseExpiry" type="date" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="registrationNumber">Số đăng ký xe</label><input id="registrationNumber" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="insuranceExpiry">Ngày hết hạn bảo hiểm</label><input id="insuranceExpiry" type="date" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="serviceArea">Khu vực / bán kính nhận đơn</label><input id="serviceArea" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Người liên hệ khẩn cấp</h2><p>Dùng khi có sự cố trong quá trình giao hàng.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="emergencyName">Họ tên người liên hệ</label><input id="emergencyName" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
        <div><label class="muted" for="emergencyPhone">Số điện thoại người liên hệ</label><input id="emergencyPhone" type="tel" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" disabled placeholder="Chưa hỗ trợ cập nhật" /></div>
      </div>
      <p class="muted" style="font-size:12px;margin-top:18px">Trạng thái xác minh hồ sơ: <strong id="kycStatusLine">Đang tải…</strong></p>
      <p class="muted" id="kycHint" style="font-size:12px"></p>
      <button class="primary" type="button" id="saveVehicleBtn" disabled>Lưu thông tin phương tiện</button>
    </section>
  </section>
