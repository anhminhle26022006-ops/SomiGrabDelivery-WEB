<?php // Shipper page: account ?>
<section id="page-account" class="page<?= $shipperPage === 'account' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'account' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">HỒ SƠ NGƯỜI GIAO</div><h1>Tài khoản &amp; hồ sơ</h1><p>Quản lý thông tin tài khoản, phương tiện và giấy tờ của bạn.</p></div></div>
    <div class="account-layout">
      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Thông tin cá nhân</h2><p>Thông tin tài khoản đã đăng ký. Bạn có thể cập nhật họ tên.</p></div></div>
        <div class="account-identity">
          <span class="avatar account-avatar" id="accountAvatar"><span>NA</span></span>
          <div><strong data-shipper-name>Đang tải…</strong><span class="muted" data-shipper-account-id>Shipper</span></div>
        </div>
        <div class="form-grid">
          <p class="form-field"><label for="shipperName">Họ và tên</label><input class="form-input" id="shipperName" required maxlength="150"></p>
          <p class="form-field"><label for="shipperPhone">Số điện thoại</label><input class="form-input" id="shipperPhone" readonly></p>
          <p class="form-field"><label for="shipperEmail">Email</label><input class="form-input" id="shipperEmail" readonly></p>
          <p class="form-field"><label for="shipperArea">Khu vực hoạt động</label><input class="form-input" id="shipperArea" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
        </div>
        <div class="form-actions"><button id="saveProfileBtn" type="button" class="primary" disabled>Lưu họ tên</button><span class="muted form-hint">Thông tin đăng ký hiển thị từ hồ sơ đã nộp. Giấy tờ được quản lý qua hồ sơ KYC.</span></div>
      </section>

      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Địa chỉ liên hệ</h2><p>Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.</p></div></div>
        <div class="form-grid">
          <p class="form-field form-field-wide"><label for="shipperAddress">Địa chỉ cụ thể</label><input class="form-input" id="shipperAddress" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="shipperWard">Phường / xã</label><input class="form-input" id="shipperWard" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="shipperCity">Tỉnh / thành phố</label><input class="form-input" id="shipperCity" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
        </div>
      </section>

      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Tài khoản nhận thanh toán</h2><p>Đổi tài khoản bằng mật khẩu hiện tại và OTP gửi đến email đã xác minh. Admin chuyển khoản và đối soát chứng từ.</p></div></div>
        <div class="p3-panel account-bank" data-shipper-bank-editor aria-live="polite"><p>Đang tải thông tin ngân hàng…</p></div>
      </section>

      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Tài liệu KYC đã nộp</h2><p>Giấy tờ bạn đã gửi để Admin xác minh hồ sơ.</p></div></div>
        <div class="account-documents" id="kycDocuments" aria-live="polite">Đang tải tài liệu…</div>
        <form id="kycResubmitForm" class="account-resubmit" hidden>
          <h3>Bổ sung giấy tờ KYC</h3>
          <p class="form-field"><label for="kycDocumentType">Loại giấy tờ</label><select class="form-input" id="kycDocumentType" name="document_type"><option value="IDENTITY_FRONT">Mặt trước giấy tờ định danh</option><option value="IDENTITY_BACK">Mặt sau giấy tờ định danh</option><option value="DRIVER_LICENSE">Giấy phép lái xe</option><option value="VEHICLE_REGISTRATION">Đăng ký xe</option><option value="PORTRAIT">Ảnh chân dung</option></select></p>
          <p class="form-field"><label for="kycDocumentFile">File (tối đa 5 MiB)</label><input class="form-input" id="kycDocumentFile" type="file" name="document_file" accept="image/jpeg,image/png,image/webp,application/pdf" required></p>
          <div class="form-actions"><button type="submit" class="primary">Tải giấy tờ và nộp lại hồ sơ</button></div>
          <p role="status" id="kycUploadStatus" class="muted form-hint"></p>
        </form>
      </section>

      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Thông tin phương tiện</h2><p>Thông tin dùng để xác minh phương tiện và ghép đơn phù hợp.</p></div></div>
        <div class="form-grid">
          <p class="form-field"><label for="vehicleType">Loại phương tiện</label><select class="form-input" id="vehicleType"><option>Xe máy</option><option>Xe máy điện</option><option>Ô tô</option><option>Xe tải nhỏ</option></select></p>
          <p class="form-field"><label for="vehicleBrand">Hãng xe / dòng xe</label><input class="form-input" id="vehicleBrand" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="vehiclePlate">Biển số xe</label><input class="form-input" id="vehiclePlate"></p>
          <p class="form-field"><label for="vehicleColor">Màu xe</label><input class="form-input" id="vehicleColor" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="licenseNumber">Số giấy phép lái xe (nếu áp dụng)</label><input class="form-input" id="licenseNumber" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="licenseExpiry">Ngày hết hạn GPLX</label><input class="form-input" id="licenseExpiry" type="date" disabled></p>
          <p class="form-field"><label for="registrationNumber">Số đăng ký xe</label><input class="form-input" id="registrationNumber" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="insuranceExpiry">Ngày hết hạn bảo hiểm</label><input class="form-input" id="insuranceExpiry" type="date" disabled></p>
          <p class="form-field form-field-wide"><label for="serviceArea">Khu vực / bán kính nhận đơn</label><input class="form-input" id="serviceArea" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
        </div>
        <div class="account-status"><span class="muted">Trạng thái xác minh hồ sơ</span><strong id="kycStatusLine">Đang tải…</strong><p class="muted form-hint" id="kycHint"></p></div>
        <div class="form-actions"><button class="primary" type="button" id="saveVehicleBtn" disabled>Lưu thông tin phương tiện</button></div>
      </section>

      <section class="card account-card">
        <div class="sectiontitle"><div><h2>Người liên hệ khẩn cấp</h2><p>Dùng khi có sự cố trong quá trình giao hàng.</p></div></div>
        <div class="form-grid">
          <p class="form-field"><label for="emergencyName">Họ tên người liên hệ</label><input class="form-input" id="emergencyName" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
          <p class="form-field"><label for="emergencyPhone">Số điện thoại người liên hệ</label><input class="form-input" id="emergencyPhone" type="tel" disabled placeholder="Chưa hỗ trợ cập nhật"></p>
        </div>
      </section>
    </div>
  </section>
