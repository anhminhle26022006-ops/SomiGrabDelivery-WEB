<?php // Shipper page: account ?>
<section id="page-account" class="page<?= $shipperPage === 'account' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'account' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">HỒ SƠ NGƯỜI GIAO</div><h1>Tài khoản & hồ sơ</h1><p>Quản lý thông tin cá nhân và ảnh đại diện của bạn.</p></div></div>
    <section class="card" style="max-width:900px">
      <div class="sectiontitle"><div><h2>Thông tin cá nhân</h2><p>Thông tin minh họa cho bản demo.</p></div></div>
      <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:22px">
        <span class="avatar" id="accountAvatar" style="width:88px;height:88px;font-size:24px"><span>NA</span></span>
        <div><strong style="display:block;font-size:16px">Nguyễn Văn A</strong><span class="muted">Shipper · Mã tài khoản SH-00128</span><div style="margin-top:10px"><label for="avatarUpload" class="primary blue" style="display:inline-block;padding:10px 13px;border-radius:10px;cursor:pointer">📷 Tải ảnh đại diện</label><input id="avatarUpload" type="file" accept="image/*" hidden></div></div>
      </div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="shipperName">Họ và tên</label><input id="shipperName" value="Nguyễn Văn A" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperPhone">Số điện thoại</label><input id="shipperPhone" value="0900 123 456" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperEmail">Email</label><input id="shipperEmail" value="shipper@example.com" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperArea">Khu vực hoạt động</label><input id="shipperArea" value="TP. Hồ Chí Minh" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Địa chỉ liên hệ</h2><p>Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.</p></div></div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div style="grid-column:1/-1"><label class="muted" for="shipperAddress">Địa chỉ cụ thể</label><input id="shipperAddress" placeholder="Số nhà, tên đường..." style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperWard">Phường / xã</label><input id="shipperWard" placeholder="Nhập phường / xã" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="shipperCity">Tỉnh / thành phố</label><input id="shipperCity" value="TP. Hồ Chí Minh" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div class="sectiontitle" style="margin-top:26px"><div><h2>Tài khoản nhận thanh toán</h2><p>Tiền của đơn hoàn thành sẽ được đối soát, trừ phí dịch vụ rồi chuyển vào tài khoản đã xác minh.</p></div></div>
      <div style="padding:14px 16px;border:1px solid #cfe9dc;background:#f0fbf5;border-radius:12px;margin-bottom:14px">
        <strong style="color:#087443">● Quy trình xác minh tài khoản ngân hàng</strong>
        <p class="muted" style="font-size:12px;margin:6px 0 0">Bản demo chưa kết nối ngân hàng. Khi triển khai thật, tài khoản chỉ được nhận tiền sau khi xác minh tên chủ tài khoản và thông tin tài khoản; hệ thống chỉ hiển thị số tài khoản đã che.</p>
      </div>
      <div class="cards" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><label class="muted" for="payoutBank">Ngân hàng</label><select id="payoutBank" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px;background:#fff"><option value="">Chọn ngân hàng</option><option>Vietcombank</option><option>VietinBank</option><option>BIDV</option><option>MB Bank</option><option>Techcombank</option><option>ACB</option><option>TPBank</option><option>Ngân hàng khác</option></select></div>
        <div><label class="muted" for="payoutName">Tên chủ tài khoản</label><input id="payoutName" placeholder="Tên chủ tài khoản như trên ngân hàng" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
        <div><label class="muted" for="payoutNumber">Số tài khoản</label><input id="payoutNumber" inputmode="numeric" autocomplete="off" placeholder="Nhập số tài khoản" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /><small class="muted">Sau khi lưu, giao diện chỉ hiển thị dạng che số, ví dụ •••• 1234.</small></div>
        <div><label class="muted" for="payoutBranch">Chi nhánh (không bắt buộc)</label><input id="payoutBranch" placeholder="Nhập chi nhánh" style="display:block;width:100%;margin-top:6px;padding:11px;border:1px solid var(--line);border-radius:10px" /></div>
      </div>
      <div style="margin-top:12px;padding:13px 15px;background:#fff8e8;border:1px solid #f3dfae;border-radius:12px">
        <strong style="color:#875500">Bảo mật khi đổi tài khoản nhận tiền</strong>
        <p class="muted" style="font-size:12px;margin:6px 0 10px">Khi thay đổi tài khoản, yêu cầu xác thực lại bằng OTP gửi đến số điện thoại đã xác minh và kiểm tra tên chủ tài khoản. Tạm giữ khoản thanh toán chưa chuyển trong thời gian kiểm tra; gửi thông báo khi thay đổi và lưu nhật ký thao tác. Không cho đổi chỉ bằng cách sửa hồ sơ thông thường.</p>
        <button class="outline" type="button" onclick="demoAction('Bắt đầu quy trình xác minh OTP và đổi tài khoản nhận tiền')">🔐 Yêu cầu đổi tài khoản nhận tiền</button>
      </div>
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
      <p class="muted" style="font-size:12px;margin-top:18px">Trạng thái xác minh giấy tờ và phương tiện: <strong>Demo — chưa kết nối hệ thống xác minh.</strong> Không nhập thông tin thật khi thử bản demo.</p>
      <button class="primary" onclick="demoAction('Lưu thông tin tài khoản và phương tiện')">Lưu thay đổi</button>
    </section>
  </section>
