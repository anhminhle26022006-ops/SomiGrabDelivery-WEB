<?php // Shipper page: active ?>
<section id="page-active" class="page<?= $shipperPage === 'active' ? '' : ' hidden' ?>" aria-hidden="<?= $shipperPage === 'active' ? 'false' : 'true' ?>">
    <div class="pagehead"><div><div class="eyebrow">ĐƠN ĐÃ NHẬN</div><h1>Đơn đang giao</h1><p>Quản lý các đơn bạn đã nhận và cập nhật từng mốc giao hàng.</p></div><span class="tag">1 đơn đang xử lý</span></div>
    <section class="card">
      <div class="sectiontitle"><div><h2>SOMI-240126</h2><p>Nhận đơn lúc 14:20 · Thu nhập dự kiến ₫42,000</p></div><span class="pill">ĐANG GIAO</span></div>
      <div class="route"><div class="routepoints"><span class="dot"></span><span class="routebar" style="height:34px"></span><span class="dot red"></span></div><div class="address"><strong>Điểm lấy hàng · Phú Nhuận</strong><small>12 Nguyễn Văn Trỗi, Phường 11, TP. Hồ Chí Minh</small><div style="height:18px"></div><strong>Điểm giao hàng · Quận 3</strong><small>241 Võ Văn Tần, Phường 5, TP. Hồ Chí Minh</small></div><div class="pay"><small>Khoảng cách còn lại</small><strong>2.8 km</strong><small style="margin-top:7px">Thời gian ước tính</small><strong>12 phút</strong></div></div>
      <div class="progress" id="activeProgress"><div class="step done"><div class="stepnum">✓</div><strong>Đã nhận đơn</strong></div><div class="step done"><div class="stepnum">✓</div><strong>Đã lấy hàng</strong></div><div class="step current"><div class="stepnum">3</div><strong>Đang giao</strong></div><div class="step"><div class="stepnum">4</div><strong>Đã đến nơi</strong></div><div class="step"><div class="stepnum">5</div><strong>Kết quả giao</strong></div></div>
      <div class="mapmock" style="height:230px"><span class="mapcaption">Lộ trình giao hàng · 2.8 km</span><span class="pin start">●</span><span class="pin end">♥</span><span class="maproute"></span></div>
      <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:15px"><button class="outline" onclick="openContactChat()">☎ Liên hệ người nhận</button><button class="outline" onclick="showSupport()">🎧 Báo sự cố / Hỗ trợ Admin</button><button class="primary blue" id="arrivedBtn">Đã đến nơi →</button></div>
      <div id="supportPanel" class="hidden" style="margin-top:14px;padding:16px;border:1px solid #cfe0f5;border-radius:13px;background:#f7fbff">
        <strong style="display:block;color:var(--navy);margin-bottom:5px">Hỗ trợ SOMI khi giao hàng</strong>
        <p class="muted" style="margin:0 0 12px">Mã đơn SOMI-240126 sẽ được đính kèm để Admin/tổng đài hỗ trợ xử lý sự cố.</p>
        <div style="display:flex;gap:9px;flex-wrap:wrap"><a class="primary blue" href="tel:1900XXXX" style="text-decoration:none">☎ Gọi tổng đài (demo)</a><button class="outline" onclick="demoAction('Gửi yêu cầu hỗ trợ cho Admin · SOMI-240126')">Gửi yêu cầu cho Admin</button><button class="outline" onclick="document.getElementById('supportPanel').classList.add('hidden')">Đóng</button></div>
        <small class="muted" style="display:block;margin-top:9px">Số tổng đài đang là placeholder; cần thay bằng số chính thức.</small>
      </div>
      <div id="arrivalPanel" class="hidden" style="margin-top:14px;padding:16px;border:1px solid #cfe0f5;border-radius:13px;background:#f7fbff">
        <strong style="display:block;color:var(--navy);margin-bottom:5px">Đã đến điểm giao</strong>
        <p class="muted" style="margin:0 0 12px">Xác nhận giao thành công nếu người nhận đã nhận hàng. Nếu không thể bàn giao, chọn lý do để ghi nhận đúng trạng thái.</p>
        <div style="display:flex;gap:9px;flex-wrap:wrap;margin-bottom:12px"><button class="primary blue" id="deliveredBtn">✓ Đã giao hàng thành công</button><button class="outline" id="failedBtn">Không thể giao hàng…</button></div>
        <div id="failedOptions" class="hidden">
          <label for="failureReason" style="display:block;font-weight:750;margin-bottom:6px">Lý do không thể giao</label>
          <select id="failureReason" class="outline" style="width:100%;margin-bottom:10px">
            <option value="no_answer">Không liên lạc được với người nhận</option>
            <option value="refused">Người nhận từ chối nhận hàng</option>
            <option value="wrong_address">Sai địa chỉ / không tìm thấy địa chỉ</option>
            <option value="recipient_absent">Người nhận vắng mặt</option>
            <option value="package_issue">Sự cố với hàng hóa / bao bì</option>
            <option value="other">Lý do khác</option>
          </select>
          <label for="failureNote" style="display:block;font-weight:750;margin-bottom:6px">Ghi chú (không bắt buộc)</label><input id="failureNote" placeholder="Mô tả ngắn tình huống..." style="width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;margin-bottom:10px">
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="primary" id="submitFailure">Ghi nhận sự cố</button><button class="outline" onclick="document.getElementById('failedOptions').classList.add('hidden')">Hủy</button></div>
        </div>
      </div>
      <p class="muted" id="progressHelp" style="font-size:12px;margin-bottom:0">Bước tiếp theo: xác nhận đã đến điểm giao, sau đó ghi nhận kết quả bàn giao.</p>
    </section>
  </section>
