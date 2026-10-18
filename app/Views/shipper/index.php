<?php
// Shipper module UI based on the approved responsive shipper demo.
?>

<header class="topbar">
  <div class="brand">SOMI <small>DELIVERY</small></div>

  <div class="top-actions">
    <div class="language-picker shipper-language-picker" data-language-picker>
      <button class="language-trigger" type="button" aria-label="Choose language" aria-expanded="false" data-language-trigger><span class="language-globe" aria-hidden="true">◎</span><span data-language-current>EN</span><span class="language-chevron" aria-hidden="true">⌄</span></button>
      <div class="language-menu" data-language-menu hidden>
        <button type="button" class="language-option" data-language-option="en"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg><span>English</span><span class="language-code">EN</span></button>
        <button type="button" class="language-option" data-language-option="vi"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#da251d"/><path d="M15 3.5L16.8 8.4L22 8.5L17.9 11.6L19.4 16.5L15 13.6L10.6 16.5L12.1 11.6L8 8.5L13.2 8.4Z" fill="#ffde00"/></svg><span>Tiếng Việt</span><span class="language-code">VI</span></button>
      </div>
    </div>
    <button class="iconbtn" id="bellBtn" title="Thông báo" aria-label="Thông báo">🔔<span class="badge-count" id="bellCount">3</span></button>
    <div class="profile">
      <button class="profilebtn" id="profileBtn" aria-expanded="false">
        <span class="avatar" id="avatar"><span>NA</span></span>
      </button>
      <div class="dropdown" id="profileDropdown">
        <div class="drophead"><strong>Nguyễn Văn A</strong><span>Quản lý tài khoản & trợ giúp</span></div>
        <button data-profile-action="account">👤 Tài khoản & hồ sơ</button>
        <button data-profile-action="support">🎧 <span class="shipper-copy-en">Support center</span><span class="shipper-copy-vi">Trung tâm hỗ trợ</span></button>
        <button data-profile-action="policy">📄 <span class="shipper-copy-en">Policies & terms</span><span class="shipper-copy-vi">Chính sách &amp; điều khoản</span></button>
        <button class="danger" data-profile-action="logout">↪ <span class="shipper-copy-en">Sign out</span><span class="shipper-copy-vi">Đăng xuất</span></button>
      </div>
    </div>
  </div>
</header>
<div class="layout">
<aside id="sidebar">
  <div class="side-label">KHÔNG GIAN NGƯỜI GIAO</div>
  <a class="sidebtn<?= $shipperPage === 'dashboard' ? ' active' : '' ?>" href="?page=shipper&amp;view=dashboard" data-page="dashboard" aria-controls="page-dashboard"<?= $shipperPage === 'dashboard' ? ' aria-current="page"' : '' ?>><span>⌂</span>Tổng quan</a>
  <a class="sidebtn<?= $shipperPage === 'offers' ? ' active' : '' ?>" href="?page=shipper&amp;view=offers" data-page="offers" aria-controls="page-offers"<?= $shipperPage === 'offers' ? ' aria-current="page"' : '' ?>>Đơn giao phù hợp</a>
  <a class="sidebtn<?= $shipperPage === 'active' ? ' active' : '' ?>" href="?page=shipper&amp;view=active" data-page="active" aria-controls="page-active"<?= $shipperPage === 'active' ? ' aria-current="page"' : '' ?>>Đơn đang giao</a>
  <a class="sidebtn<?= $shipperPage === 'history' ? ' active' : '' ?>" href="?page=shipper&amp;view=history" data-page="history" aria-controls="page-history"<?= $shipperPage === 'history' ? ' aria-current="page"' : '' ?>>Lịch sử giao hàng</a>
  <a class="sidebtn<?= $shipperPage === 'earnings' ? ' active' : '' ?>" href="?page=shipper&amp;view=earnings" data-page="earnings" aria-controls="page-earnings"<?= $shipperPage === 'earnings' ? ' aria-current="page"' : '' ?>>Thu nhập</a>
  <div class="asidebottom">
    <div class="statusrow"><strong>Trạng thái nhận đơn</strong><button class="toggle on" id="availabilityToggle" aria-label="Bật tắt nhận đơn"></button></div>
    <div style="margin-top:8px"><span class="pill" id="availabilityPill">ĐANG NHẬN ĐƠN</span></div>
    <p class="muted" style="font-size:11px;margin:8px 0 0" id="availabilityHelp">Bạn sẽ nhận được các đơn phù hợp khi trực tuyến.</p>
  </div>
</aside>
<main>
  <?php require __DIR__ . '/pages/' . $shipperPage . '.php'; ?>
</main>
</div>
<div id="contactModal" class="modalback hidden" role="dialog" aria-modal="true" aria-labelledby="contactTitle">
  <div class="modal">
    <div class="modalhead"><div><span class="eyebrow">LIÊN HỆ THEO ĐƠN</span><h2 id="contactTitle">Liên hệ người nhận</h2><span class="muted" style="font-size:12px">Đơn SOMI-240126</span></div><button class="outline" aria-label="Đóng" onclick="closeContactChat()">✕</button></div>
    <div class="modalbody">
      <div class="recipient-summary"><div class="recipient-avatar">NA</div><div><b>Nguyễn Minh Anh</b><span>Người nhận · 090 000 0000</span><small>241 Võ Văn Tần, Phường 5, Quận 3</small></div></div>
      <div class="contact-actions"><a class="primary blue" href="tel:0900000000">☎ Gọi người nhận</a><span class="pill">● Chat trong đơn hàng</span></div>
      <div class="chatmessages" id="chatMessages"><div class="chat-date">Hôm nay · Đơn SOMI-240126</div><div class="bubble"><small>Shipper · 14:32</small>Chào anh/chị, tôi đang trên đường giao đơn hàng.</div><div class="bubble theirs"><small>Người nhận · 14:34</small>Vâng, vui lòng gọi tôi khi đến nơi nhé.</div><div class="bubble mine"><small>Bạn · 14:36</small>Tôi đã đến nơi, anh/chị có thể nhận hàng chưa ạ?</div></div>
      <form class="chatcompose" id="chatForm"><input id="chatInput" placeholder="Nhập tin nhắn cho người nhận..." autocomplete="off" required><button class="primary blue" type="submit">Gửi</button></form>
      <p class="muted demo-disclaimer">Chat và cuộc gọi là mô phỏng; thông tin liên hệ sẽ lấy từ dữ liệu đơn khi tích hợp backend.</p>
    </div>
  </div>
</div>
<div id="orderDetailModal" class="modalback hidden" role="dialog" aria-modal="true" aria-labelledby="orderDetailTitle">
  <div class="modal detailmodal"><div class="modalhead"><div><span class="eyebrow">THÔNG TIN ĐƠN GIAO</span><h2 id="orderDetailTitle">Chi tiết đơn</h2></div><button class="outline" aria-label="Đóng" onclick="closeOrderDetail()">✕</button></div><div class="modalbody" id="orderDetailBody"></div></div>
</div>
<div id="shipperSupportModal" class="shipper-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="shipperSupportTitle">
  <section class="shipper-dialog">
    <header class="shipper-dialog-head"><div><span class="eyebrow"><span class="shipper-copy-en">SHIPPER HELP</span><span class="shipper-copy-vi">TRỢ GIÚP NGƯỜI GIAO</span></span><h2 id="shipperSupportTitle"><span class="shipper-copy-en">Support center</span><span class="shipper-copy-vi">Trung tâm hỗ trợ</span></h2><p><span class="shipper-copy-en">Find answers or send a request to the SOMI support team.</span><span class="shipper-copy-vi">Tìm câu trả lời hoặc gửi yêu cầu đến đội ngũ hỗ trợ SOMI.</span></p></div><button type="button" class="outline" data-close-shipper-modal aria-label="Close">✕</button></header>
    <div class="shipper-dialog-body">
      <div class="shipper-support-grid">
        <div class="shipper-support-main">
          <h3><span class="shipper-copy-en">Frequently asked questions</span><span class="shipper-copy-vi">Câu hỏi thường gặp</span></h3>
          <details><summary><span class="shipper-copy-en">What should I do if I cannot reach the recipient?</span><span class="shipper-copy-vi">Tôi cần làm gì khi không liên hệ được người nhận?</span></summary><p><span class="shipper-copy-en">Try calling or messaging once more, record the issue on the active delivery, then contact support. Do not mark the delivery complete unless the parcel was handed over.</span><span class="shipper-copy-vi">Hãy thử gọi hoặc nhắn thêm một lần, ghi nhận sự cố trong đơn đang giao rồi liên hệ hỗ trợ. Chỉ xác nhận hoàn tất khi đã bàn giao hàng.</span></p></details>
          <details><summary><span class="shipper-copy-en">When will delivery earnings be paid?</span><span class="shipper-copy-vi">Khi nào thu nhập giao hàng được thanh toán?</span></summary><p><span class="shipper-copy-en">Earnings are held while delivery is in progress. After successful completion, the platform fee is deducted and the net amount is sent to your verified bank account.</span><span class="shipper-copy-vi">Thu nhập được giữ trong quá trình vận chuyển. Khi giao thành công, phí sàn được khấu trừ và số tiền thực nhận được chuyển vào tài khoản ngân hàng đã xác minh.</span></p></details>
          <details><summary><span class="shipper-copy-en">How do I report a safety or parcel issue?</span><span class="shipper-copy-vi">Báo sự cố an toàn hoặc hàng hóa như thế nào?</span></summary><p><span class="shipper-copy-en">Use the active order support form and include the order number, issue type, and a short description. For urgent danger, contact local emergency services first.</span><span class="shipper-copy-vi">Dùng biểu mẫu hỗ trợ trong đơn đang giao, ghi mã đơn, loại sự cố và mô tả ngắn. Nếu có nguy hiểm khẩn cấp, hãy liên hệ cơ quan cứu hộ địa phương trước.</span></p></details>
        </div>
        <form id="shipperHelpForm" class="shipper-help-form">
          <h3><span class="shipper-copy-en">Send a support request</span><span class="shipper-copy-vi">Gửi yêu cầu hỗ trợ</span></h3>
          <label><span class="shipper-copy-en">Topic</span><span class="shipper-copy-vi">Chủ đề</span><select id="shipperHelpTopic" required><option value="delivery" data-en="Delivery issue" data-vi="Sự cố giao hàng">Delivery issue</option><option value="account" data-en="Account" data-vi="Tài khoản">Account</option><option value="payment" data-en="Payment" data-vi="Thanh toán">Payment</option><option value="other" data-en="Other" data-vi="Khác">Other</option></select></label>
          <label><span class="shipper-copy-en">Order number (optional)</span><span class="shipper-copy-vi">Mã đơn (không bắt buộc)</span><input id="shipperHelpOrder" data-en-placeholder="e.g. SOMI-240126" data-vi-placeholder="VD: SOMI-240126" placeholder="e.g. SOMI-240126"></label>
          <label><span class="shipper-copy-en">How can we help?</span><span class="shipper-copy-vi">Bạn cần hỗ trợ nội dung gì?</span><textarea id="shipperHelpMessage" rows="4" required data-en-placeholder="Describe your issue" data-vi-placeholder="Mô tả vấn đề" placeholder="Describe your issue"></textarea></label>
          <button type="submit" class="primary blue"><span class="shipper-copy-en">Submit request</span><span class="shipper-copy-vi">Gửi yêu cầu</span></button>
          <p class="shipper-demo-note"><span class="shipper-copy-en">Demo only: requests are not sent to a server.</span><span class="shipper-copy-vi">Bản demo: yêu cầu chưa được gửi đến máy chủ.</span></p>
        </form>
      </div>
      <div class="shipper-support-footer"><div><b><span class="shipper-copy-en">Still need help?</span><span class="shipper-copy-vi">Bạn vẫn cần trợ giúp?</span></b><span><span class="shipper-copy-en">Our support team is available for delivery and account questions.</span><span class="shipper-copy-vi">Đội ngũ hỗ trợ tiếp nhận câu hỏi về giao hàng và tài khoản.</span></span></div><a class="outline" href="mailto:support@somi.example">✉ support@somi.example</a></div>
    </div>
  </section>
</div>
<div id="shipperPolicyModal" class="shipper-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="shipperPolicyTitle">
  <section class="shipper-dialog shipper-policy-dialog">
    <header class="shipper-dialog-head"><div><span class="eyebrow"><span class="shipper-copy-en">SHIPPER DOCUMENTS</span><span class="shipper-copy-vi">TÀI LIỆU NGƯỜI GIAO</span></span><h2 id="shipperPolicyTitle"><span class="shipper-copy-en">Policies &amp; terms</span><span class="shipper-copy-vi">Chính sách &amp; điều khoản</span></h2><p><span class="shipper-copy-en">Demo policy summary · Last updated 18 October 2026</span><span class="shipper-copy-vi">Tóm tắt chính sách demo · Cập nhật ngày 18/10/2026</span></p></div><button type="button" class="outline" data-close-shipper-modal aria-label="Close">✕</button></header>
    <div class="shipper-dialog-body">
      <nav class="shipper-policy-tabs" aria-label="Policy sections"><button type="button" class="selected" data-policy-tab="service"><span class="shipper-copy-en">Service terms</span><span class="shipper-copy-vi">Điều khoản dịch vụ</span></button><button type="button" data-policy-tab="privacy"><span class="shipper-copy-en">Privacy</span><span class="shipper-copy-vi">Quyền riêng tư</span></button><button type="button" data-policy-tab="payments"><span class="shipper-copy-en">Earnings &amp; payments</span><span class="shipper-copy-vi">Thu nhập &amp; thanh toán</span></button><button type="button" data-policy-tab="delivery"><span class="shipper-copy-en">Delivery standards</span><span class="shipper-copy-vi">Quy chuẩn giao hàng</span></button></nav>
      <article class="shipper-policy-content" data-policy-content="service"><h3><span class="shipper-copy-en">Service terms</span><span class="shipper-copy-vi">Điều khoản dịch vụ</span></h3><p><span class="shipper-copy-en">Shippers must keep their profile and vehicle information accurate, review order details before accepting, and follow the order status flow. Acceptance is optional; once accepted, report delays or delivery issues through the order support flow.</span><span class="shipper-copy-vi">Người giao cần cập nhật chính xác hồ sơ và thông tin phương tiện, xem chi tiết trước khi nhận đơn và cập nhật đúng trạng thái. Bạn có thể tự quyết định nhận đơn; sau khi nhận, hãy báo trễ hoặc sự cố qua mục hỗ trợ của đơn hàng.</span></p><p><span class="shipper-copy-en">Do not use customer information outside the delivery, alter delivery proof, or mark an order complete before handing over the parcel.</span><span class="shipper-copy-vi">Không sử dụng thông tin khách hàng ngoài mục đích giao hàng, làm sai lệch bằng chứng giao hàng hoặc xác nhận hoàn tất trước khi bàn giao kiện hàng.</span></p></article>
      <article class="shipper-policy-content hidden" data-policy-content="privacy"><h3><span class="shipper-copy-en">Privacy &amp; customer data</span><span class="shipper-copy-vi">Quyền riêng tư &amp; dữ liệu khách hàng</span></h3><p><span class="shipper-copy-en">Use recipient names, phone numbers, and addresses only to complete the assigned delivery and resolve related support requests. Keep account credentials private and report suspected account access or data exposure promptly.</span><span class="shipper-copy-vi">Chỉ sử dụng tên, số điện thoại và địa chỉ người nhận để hoàn thành đơn được giao và xử lý yêu cầu hỗ trợ liên quan. Bảo mật thông tin đăng nhập, báo ngay khi nghi ngờ tài khoản bị truy cập hoặc dữ liệu bị lộ.</span></p><p><span class="shipper-copy-en">This demo does not store or transmit support requests. Production data handling, retention periods, and user rights must be defined in the published privacy notice.</span><span class="shipper-copy-vi">Bản demo không lưu hoặc gửi yêu cầu hỗ trợ. Quy trình xử lý dữ liệu thực tế, thời hạn lưu trữ và quyền của người dùng cần được quy định trong thông báo quyền riêng tư chính thức.</span></p></article>
      <article class="shipper-policy-content hidden" data-policy-content="payments"><h3><span class="shipper-copy-en">Earnings, platform fee &amp; payout</span><span class="shipper-copy-vi">Thu nhập, phí sàn &amp; thanh toán</span></h3><p><span class="shipper-copy-en">Delivery proceeds are held while an order is in progress. After a successful delivery is confirmed, the applicable platform fee is deducted and the remaining amount is transferred automatically to the shipper’s verified payout account. Shippers cannot manually withdraw held funds.</span><span class="shipper-copy-vi">Tiền đơn được giữ trong thời gian vận chuyển. Sau khi xác nhận giao thành công, phí sàn áp dụng được khấu trừ và phần còn lại tự động chuyển vào tài khoản nhận tiền đã xác minh của người giao. Người giao không thể tự rút khoản tiền đang được giữ.</span></p><p><span class="shipper-copy-en">The actual fee rate, settlement timing, failed-delivery handling, and payment provider rules must be shown before launch. Values in this interface are examples only.</span><span class="shipper-copy-vi">Tỷ lệ phí thực tế, thời gian đối soát, cách xử lý đơn giao không thành công và quy định của đơn vị thanh toán cần được công bố trước khi vận hành. Số liệu trên giao diện chỉ để minh họa.</span></p></article>
      <article class="shipper-policy-content hidden" data-policy-content="delivery"><h3><span class="shipper-copy-en">Delivery &amp; safety standards</span><span class="shipper-copy-vi">Quy chuẩn giao hàng &amp; an toàn</span></h3><p><span class="shipper-copy-en">Check the parcel and pickup details, handle goods with reasonable care, protect them from weather or damage, and deliver only to the stated recipient or an authorized contact. Record proof of delivery using the approved process.</span><span class="shipper-copy-vi">Kiểm tra kiện hàng và thông tin lấy hàng, bảo quản hàng cẩn thận, tránh thời tiết và hư hỏng; chỉ giao cho người nhận hoặc người được ủy quyền. Ghi nhận bằng chứng giao hàng theo quy trình được duyệt.</span></p><p><span class="shipper-copy-en">If an address is unsafe, a parcel appears prohibited, or a recipient cannot be reached, pause and contact support for instructions. Do not put yourself or others at risk.</span><span class="shipper-copy-vi">Nếu địa chỉ không an toàn, hàng hóa có dấu hiệu bị cấm hoặc không liên hệ được người nhận, hãy tạm dừng và hỏi bộ phận hỗ trợ. Không đặt bản thân hay người khác vào tình huống nguy hiểm.</span></p></article>
      <p class="shipper-demo-note"><span class="shipper-copy-en">This is draft demo copy for review, not a final legal agreement.</span><span class="shipper-copy-vi">Đây là nội dung demo để xem xét, chưa phải văn bản pháp lý cuối cùng.</span></p>
    </div>
  </section>
</div>
<div id="toast" style="position:fixed;right:20px;bottom:20px;background:#142c53;color:#fff;padding:13px 17px;border-radius:12px;box-shadow:0 8px 30px #0002;z-index:100;display:none;max-width:360px"></div>
<style>
body[data-page="shipper"] .shipper-language-picker{position:relative;flex-shrink:0}
body[data-page="shipper"] .shipper-language-picker .language-trigger{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-width:82px;min-height:42px;padding:0 13px;border:1px solid rgba(255,255,255,.72);border-radius:999px;color:var(--navy);background:rgba(255,255,255,.72);box-shadow:0 5px 16px rgba(20,40,75,.08);font:inherit;font-size:13px;font-weight:700;cursor:pointer}
body[data-page="shipper"] .shipper-language-picker .language-globe{font-size:17px;line-height:1;opacity:.82}
body[data-page="shipper"] .shipper-language-picker .language-chevron{font-size:17px;line-height:1;margin-top:-4px;opacity:.7}
body[data-page="shipper"] .shipper-language-picker .language-menu{position:absolute;z-index:95;top:calc(100% + 9px);right:0;width:178px;padding:6px;border:1px solid rgba(255,255,255,.8);border-radius:16px;background:rgba(255,255,255,.96);box-shadow:0 14px 38px rgba(17,35,65,.18);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
body[data-page="shipper"] .shipper-language-picker .language-menu[hidden]{display:none!important}
body[data-page="shipper"] .shipper-language-picker .language-option{width:100%;display:grid;grid-template-columns:26px 1fr auto;align-items:center;gap:8px;padding:10px 11px;border:0;border-radius:10px;background:transparent;color:var(--navy);text-align:left;font:inherit;font-size:13px;cursor:pointer}
body[data-page="shipper"] .shipper-language-picker .language-option:hover,body[data-page="shipper"] .shipper-language-picker .language-option.is-active{background:#eaf0f8}
body[data-page="shipper"] .shipper-language-picker .language-flag-svg{display:block;width:24px;height:16px;flex:0 0 24px;border-radius:2px;overflow:hidden;box-shadow:0 0 0 1px rgba(18,39,70,.12)}
body[data-page="shipper"] .shipper-language-picker .language-code{font-size:10px;letter-spacing:.06em;color:#71809a}
@media(max-width:760px){body[data-page="shipper"] .shipper-language-picker .language-trigger{min-height:38px;min-width:76px;padding:0 10px;gap:7px}body[data-page="shipper"] .shipper-language-picker .language-menu{width:164px}}
body[data-page="shipper"][data-shipper-lang="en"] .shipper-copy-vi,body[data-page="shipper"][data-shipper-lang="vi"] .shipper-copy-en{display:none!important}
body[data-page="shipper"] .shipper-overlay{position:fixed;inset:0;z-index:90;background:#10213b80;display:grid;place-items:center;padding:20px;overflow:auto}
body[data-page="shipper"] .shipper-overlay.hidden{display:none!important}
body[data-page="shipper"] .shipper-dialog{width:min(100%,1000px);max-height:min(90vh,900px);overflow:auto;background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 24px 70px #0b1f3d40}
body[data-page="shipper"] .shipper-policy-dialog{width:min(100%,860px)}
body[data-page="shipper"] .shipper-dialog-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:21px 24px;background:#fff;border-bottom:1px solid #edf1f6}
body[data-page="shipper"] .shipper-dialog-head h2{margin:4px 0;color:var(--navy);font-size:24px}
body[data-page="shipper"] .shipper-dialog-head p{margin:0;color:var(--muted);font-size:13px}
body[data-page="shipper"] .shipper-dialog-body{padding:22px 24px}
body[data-page="shipper"] .shipper-dialog-body h3{margin:0 0 12px;color:var(--navy);font-size:16px}
body[data-page="shipper"] .shipper-support-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:22px}
body[data-page="shipper"] .shipper-support-main details{padding:13px 0;border-bottom:1px solid #edf1f6}
body[data-page="shipper"] .shipper-support-main summary{cursor:pointer;color:var(--navy);font-size:13px;font-weight:750}
body[data-page="shipper"] .shipper-support-main details p{color:var(--muted);font-size:12px;line-height:1.65;margin:9px 0 0}
body[data-page="shipper"] .shipper-help-form{display:grid;align-content:start;gap:12px;padding:17px;background:#f7f9fc;border:1px solid var(--line);border-radius:14px}
body[data-page="shipper"] .shipper-help-form label{display:grid;gap:6px;color:var(--navy);font-size:12px;font-weight:700}
body[data-page="shipper"] .shipper-help-form input,body[data-page="shipper"] .shipper-help-form select,body[data-page="shipper"] .shipper-help-form textarea{width:100%;min-width:0;border:1px solid var(--line);border-radius:9px;background:#fff;padding:10px;color:var(--ink);font:inherit;font-weight:400}
body[data-page="shipper"] .shipper-help-form textarea{resize:vertical}
body[data-page="shipper"] .shipper-demo-note{color:var(--muted);font-size:11px;line-height:1.55;margin:0}
body[data-page="shipper"] .shipper-support-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:20px;padding:14px 15px;background:#f5f8fd;border-radius:12px}
body[data-page="shipper"] .shipper-support-footer div{display:grid;gap:3px;color:var(--muted);font-size:12px}
body[data-page="shipper"] .shipper-support-footer b{color:var(--navy)}
body[data-page="shipper"] .shipper-policy-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
body[data-page="shipper"] .shipper-policy-tabs button{padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);font-weight:700;font-size:12px;cursor:pointer}
body[data-page="shipper"] .shipper-policy-tabs button.selected{border-color:#bdd8fa;background:#edf5ff;color:var(--blue)}
body[data-page="shipper"] .shipper-policy-content{min-height:230px;padding:20px;border:1px solid var(--line);border-radius:14px;background:#fff}
body[data-page="shipper"] .shipper-policy-content p{color:#536782;font-size:13px;line-height:1.75;margin:10px 0}
body[data-page="shipper"] .shipper-policy-dialog>.shipper-dialog-body>.shipper-demo-note{margin-top:13px}
@media(max-width:700px){body[data-page="shipper"] .shipper-overlay{padding:8px;align-items:end}body[data-page="shipper"] .shipper-dialog{max-height:94vh;border-radius:18px 18px 12px 12px}body[data-page="shipper"] .shipper-dialog-head,body[data-page="shipper"] .shipper-dialog-body{padding:16px}body[data-page="shipper"] .shipper-support-grid{grid-template-columns:1fr}body[data-page="shipper"] .shipper-support-footer{align-items:flex-start;flex-direction:column}}
</style>
<script>
(() => {
  const support = document.getElementById('shipperSupportModal');
  const policy = document.getElementById('shipperPolicyModal');
  let locale = localStorage.getItem('somi-language') === 'vi' ? 'vi' : 'en';
  const shipperCopy = {
    'CẬP NHẬT TÀI KHOẢN VÀ ĐƠN HÀNG':'ACCOUNT & DELIVERY UPDATES','Thông báo':'Notifications','Tập trung mọi cập nhật để bạn không bỏ lỡ thông tin quan trọng.':'See all updates in one place so you do not miss important information.','✓ Đánh dấu tất cả đã đọc':'✓ Mark all as read','Tất cả':'All','Đơn hàng':'Orders','Tài khoản':'Account','Xem':'View','5 phút trước':'5 minutes ago','8 phút trước':'8 minutes ago','10 phút trước':'10 minutes ago','12 phút trước':'12 minutes ago','1 giờ trước':'1 hour ago','2 giờ trước':'2 hours ago','3 giờ trước':'3 hours ago','Quản lý tài khoản & trợ giúp':'Account management & help',
    'Có đơn giao mới phù hợp với bạn':'A new delivery offer is available for you','Đơn SOMI-240126 đã được giao thành công':'Order SOMI-240126 was delivered successfully','Yêu cầu xác minh tài khoản':'Account verification required','Tin nhắn từ khách hàng':'Message from a customer','Bạn đã nhận được ₫42,000':'You received ₫42,000','Vui lòng cập nhật giấy tờ tùy thân để hoàn tất hồ sơ.':'Update your identity documents to complete your profile.','Xem các thay đổi có hiệu lực trong tuần này.':'Review the changes taking effect this week.','“Vui lòng giao giúp trước 17h nhé.”':'“Please deliver this before 5 PM.”',
    'KHÔNG GIAN NGƯỜI GIAO':'SHIPPER WORKSPACE','Tổng quan':'Dashboard','Đơn giao phù hợp':'Delivery offers','Đơn đang giao':'Active delivery','Lịch sử giao hàng':'Delivery history','Thu nhập':'Earnings','Trạng thái nhận đơn':'Order availability','ĐANG NHẬN ĐƠN':'ONLINE · ACCEPTING ORDERS','ĐÃ TẮT NHẬN ĐƠN':'OFFLINE · NOT ACCEPTING ORDERS','Bạn sẽ nhận được các đơn phù hợp khi trực tuyến.':'You will receive suitable offers while online.','Bạn sẽ không nhận đơn mới khi trạng thái đang tắt.':'You will not receive new offers while offline.',
    'Xem nhanh tình trạng hoạt động hôm nay của bạn.':'A quick look at your delivery activity today.','▦   Hôm nay (12/10/2026)⌄':'▦   Today (12/10/2026)⌄','Đơn hoàn thành hôm nay':'Deliveries completed today','Thu nhập hôm nay':'Today’s earnings','Thời gian hoạt động':'Active time','Đánh giá trung bình':'Average rating','28 đánh giá':'28 reviews','Đơn mới phù hợp với khu vực và phương tiện của bạn.':'New offers matching your area and vehicle.','Xem tất cả →':'View all →','Tóm tắt tiến độ đơn bạn đã nhận.':'A summary of your accepted delivery.','Chi tiết →':'Details →','Phú Nhuận, TP. Hồ Chí Minh':'Phu Nhuan, Ho Chi Minh City','Quận 3, TP. Hồ Chí Minh':'District 3, Ho Chi Minh City','ĐANG GIAO':'IN PROGRESS','Thu nhập':'Earnings','Đã nhận':'Accepted','Đến điểm lấy':'At pickup','Đang giao':'In transit','Hoàn tất':'Completed','Mở đơn đang giao →':'Open active delivery →','Thông báo mới':'Recent notifications','Các cập nhật gần đây cần bạn chú ý.':'Recent updates that need your attention.','Tóm tắt thu nhập hôm nay.':'A summary of today’s earnings.','so với hôm qua':'vs. yesterday','Biểu đồ tóm tắt · vào Thu nhập để xem chi tiết.':'Summary chart · open Earnings for more details.',
    'CƠ HỘI GIAO HÀNG':'DELIVERY OPPORTUNITIES','Chỉ hiển thị đơn chưa được nhận, phù hợp với bạn.':'Showing unclaimed offers that match your profile.','Đơn mới trong khu vực':'New offers nearby','Xem thông tin, tuyến đường và thu nhập trước khi quyết định.':'Review the details, route, and earnings before deciding.','Đề xuất phù hợp':'Recommended','Thu nhập cao nhất':'Highest earnings','Gần nhất':'Nearest','Thu nhập dự kiến':'Estimated earnings','Đối soát sau khi giao thành công':'Settled after successful delivery','Khoảng cách':'Distance','Ước tính':'Estimated time','Loại hàng':'Package type','Xem chi tiết':'View details','Từ chối':'Decline','Từ chối đơn':'Decline offer','Nhận đơn':'Accept offer','Chưa có đơn phù hợp':'No matching offers','Hãy kiểm tra lại sau hoặc bật trạng thái nhận đơn.':'Check again later or go online to receive offers.','Giao ngay':'Express','Ưu tiên':'Priority','Giao trong ngày':'Same day','Tài liệu':'Documents','Hàng hóa':'Goods',
    'ĐƠN ĐÃ NHẬN':'ACCEPTED DELIVERY','Quản lý các đơn bạn đã nhận và cập nhật từng mốc giao hàng.':'Manage your accepted deliveries and update each milestone.','1 đơn đang xử lý':'1 delivery in progress','Nhận đơn lúc 14:20 · Thu nhập dự kiến ₫42,000':'Accepted at 2:20 PM · Estimated earnings ₫42,000','Điểm lấy hàng':'Pickup location','Điểm giao hàng':'Drop-off location','Khoảng cách còn lại':'Distance remaining','Thời gian ước tính':'Estimated time','Đã nhận đơn':'Offer accepted','Đã lấy hàng':'Picked up','Đã đến nơi':'Arrived','Kết quả giao':'Delivery result','Lộ trình giao hàng':'Delivery route','Liên hệ người nhận':'Contact recipient','Báo sự cố / Hỗ trợ Admin':'Report an issue / Get support','Đã đến nơi →':'I have arrived →','Gửi yêu cầu hỗ trợ':'Send a support request','Mã đơn SOMI-240126 sẽ được gửi kèm để Admin nắm tình huống.':'Order SOMI-240126 will be included so support can review the issue.','Đóng':'Close','Loại yêu cầu':'Request type','Sự cố giao hàng':'Delivery issue','Không liên hệ được khách':'Unable to reach recipient','Địa chỉ hoặc tuyến đường':'Address or route','Mô tả tình huống':'Describe the issue','Mô tả ngắn vấn đề bạn cần Admin hỗ trợ...':'Briefly describe what you need help with...','Gửi yêu cầu cho Admin':'Send request to support','Demo: yêu cầu hiển thị xác nhận trên màn hình.':'Demo only: a confirmation will appear on screen.','Đã đến điểm giao':'Arrived at the drop-off point','Xác nhận giao thành công nếu người nhận đã nhận hàng. Nếu không thể bàn giao, chọn lý do để ghi nhận đúng trạng thái.':'Confirm successful delivery only after handing over the parcel. If delivery is not possible, record the reason.','✓ Đã giao hàng thành công':'✓ Marked as delivered','Không thể giao hàng…':'Unable to deliver…','Lý do không thể giao':'Reason delivery failed','Không liên lạc được với người nhận':'Unable to reach the recipient','Người nhận từ chối nhận hàng':'Recipient refused the parcel','Sai địa chỉ / không tìm thấy địa chỉ':'Wrong or unfindable address','Người nhận vắng mặt':'Recipient unavailable','Sự cố với hàng hóa / bao bì':'Parcel or packaging issue','Lý do khác':'Other reason','Ghi chú (không bắt buộc)':'Note (optional)','Mô tả ngắn tình huống...':'Briefly describe what happened...','Ghi nhận sự cố':'Record issue','Hủy':'Cancel',
    'THU NHẬP CỦA BẠN':'YOUR EARNINGS','Theo dõi doanh thu, các khoản thanh toán và lịch sử rút tiền.':'Track earnings, settlements, and payment history.','Thực nhận hôm nay':'Net earnings today','Đã trừ phí sàn':'Platform fee deducted','Tuần này':'This week','Tháng này':'This month','Thực nhận sau đối soát':'Net after settlement','Đang được giữ':'On hold','Sẽ thanh toán sau khi giao thành công':'Paid after successful delivery','Xuất báo cáo':'Export report','Biến động thu nhập':'Earnings activity','Minh họa thu nhập theo giờ trong ngày.':'Illustrative hourly earnings for today.','Hôm nay':'Today','Thanh toán':'Payments','Các khoản tiền gần đây.':'Recent transactions.','Đối soát & thanh toán':'Settlements & payments','Hệ thống tự động thanh toán về tài khoản đã đăng ký.':'Payments are sent automatically to your registered account.','Giao thành công':'Delivered successfully','tiền đơn':'delivery earnings','phí sàn':'platform fee','thực nhận':'net earnings','Đã chuyển vào tài khoản':'Sent to account','Đang vận chuyển':'In transit','đang được giữ':'on hold','Thanh toán sau khi giao thành công':'Paid after successful delivery','Chưa đối soát':'Not settled','Không cần rút tiền thủ công':'No manual withdrawal needed','Khi đơn hoàn tất, phí sàn được khấu trừ và số tiền thực nhận tự động chuyển vào tài khoản của bạn.':'After delivery is completed, the platform fee is deducted and your net earnings are transferred automatically to your account.',
    'CÁC ĐƠN ĐÃ KẾT THÚC':'COMPLETED DELIVERIES','Tra cứu đơn hoàn tất hoặc bị hủy; không bao gồm đơn đang giao.':'Review completed or cancelled deliveries. Active deliveries are not shown here.','Tất cả':'All','Hoàn thành':'Completed','Đã hủy':'Cancelled','Mã đơn':'Order ID','Thời gian':'Time','Lộ trình':'Route','Trạng thái':'Status',
    'HỒ SƠ NGƯỜI GIAO':'SHIPPER PROFILE','Tài khoản & hồ sơ':'Account & profile','Quản lý tài khoản & trợ giúp':'Account management & help','Quản lý thông tin cá nhân và ảnh đại diện của bạn.':'Manage your personal information and profile photo.','Thông tin cá nhân':'Personal information','Thông tin minh họa cho bản demo.':'Sample information for this demo.','Họ và tên':'Full name','Số điện thoại':'Phone number','Khu vực hoạt động':'Service area','Địa chỉ liên hệ':'Contact address','Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.':'Residential or contact address for your shipper profile.','Địa chỉ cụ thể':'Street address','Phường / xã':'Ward / commune','Tỉnh / thành phố':'Province / city','Tài khoản nhận thanh toán':'Payout account','Tiền của đơn hoàn thành sẽ được đối soát, trừ phí dịch vụ rồi chuyển vào tài khoản đã xác minh.':'Completed delivery earnings are settled, the service fee is deducted, and the balance is sent to your verified account.','Quy trình xác minh tài khoản ngân hàng':'Bank account verification','Bản demo chưa kết nối ngân hàng.':'This demo is not connected to a bank.','Khi triển khai thật, tài khoản chỉ được nhận tiền sau khi xác minh tên chủ tài khoản và thông tin tài khoản; hệ thống chỉ hiển thị số tài khoản đã che.':'In production, payouts are enabled only after the account holder and bank details are verified. The interface will show only a masked account number.','Sau khi lưu, giao diện chỉ hiển thị dạng che số, ví dụ •••• 1234.':'After saving, the account number is masked, for example •••• 1234.','Bảo mật khi đổi tài khoản nhận tiền':'Payout account change security','Khi thay đổi tài khoản, yêu cầu xác thực lại bằng OTP gửi đến số điện thoại đã xác minh và kiểm tra tên chủ tài khoản. Tạm giữ khoản thanh toán chưa chuyển trong thời gian kiểm tra; gửi thông báo khi thay đổi và lưu nhật ký thao tác. Không cho đổi chỉ bằng cách sửa hồ sơ thông thường.':'Changing the payout account requires OTP verification using the verified phone number and a name match. Hold unsettled payouts during review, notify the shipper, and record the change. Do not allow account changes through ordinary profile edits.','🔐 Yêu cầu đổi tài khoản nhận tiền':'🔐 Request payout account change','Thông tin phương tiện':'Vehicle information','Thông tin dùng để xác minh phương tiện và ghép đơn phù hợp.':'Used to verify your vehicle and match suitable deliveries.','Loại phương tiện':'Vehicle type','Hãng xe / dòng xe':'Make / model','Biển số xe':'License plate','Màu xe':'Vehicle color','Số giấy phép lái xe (nếu áp dụng)':'Driver license number (if applicable)','Ngày hết hạn GPLX':'Driver license expiry date','Số đăng ký xe':'Vehicle registration number','Ngày hết hạn bảo hiểm':'Insurance expiry date','Khu vực / bán kính nhận đơn':'Service area / offer radius','Người liên hệ khẩn cấp':'Emergency contact','Dùng khi có sự cố trong quá trình giao hàng.':'For emergencies during delivery.','Họ tên người liên hệ':'Contact name','Số điện thoại người liên hệ':'Contact phone number','Trạng thái xác minh giấy tờ và phương tiện: Demo — chưa kết nối hệ thống xác minh. Không nhập thông tin thật khi thử bản demo.':'Document and vehicle verification status: Demo — verification is not connected. Do not enter real personal information while testing.','Lưu thay đổi':'Save changes','📷 Tải ảnh đại diện':'📷 Upload profile photo'
  };
  const shipperReverseCopy = Object.fromEntries(Object.entries(shipperCopy).map(([en, vi]) => [vi, en]));
  const localizeText = value => {
    const current = value.trim();
    const replacement = locale === 'vi' ? shipperReverseCopy[current] : shipperCopy[current];
    if (replacement) return value.replace(current, replacement);
    if (locale === 'en') {
      const notice = current.match(/^Phú Nhuận → Quận 3 · Thu nhập dự kiến (.+)$/);
      if (notice) return value.replace(current, `Phu Nhuan → District 3 · Estimated earnings ${notice[1]}`);
      const declined = current.match(/^Đã từ chối đơn (.+) trong bản demo\.$/);
      if (declined) return `Declined order ${declined[1]} in this demo.`;
      const arrived = current.match(/^Đã đến điểm giao \(demo\)\.$/);
      if (arrived) return 'Arrival recorded (demo).';
      const payout = current.match(/^Giao thành công · Phí sàn (.+) · Thực nhận (.+) \(demo\)\.$/);
      if (payout) return `Delivered · Platform fee ${payout[1]} · Net earnings ${payout[2]} (demo).`;
      const payoutNote = current.match(/^Đã giao thành công\. (.+) thực nhận \(sau phí sàn demo 10%\) được chuyển tự động vào tài khoản\.$/);
      if (payoutNote) return `Delivered successfully. ${payoutNote[1]} net earnings (after the demo 10% platform fee) are transferred automatically to your account.`;
      if (current === 'Đã ghi nhận giao hàng thành công (demo).') return 'Successful delivery recorded (demo).';
      if (current === 'Bạn cần bật trạng thái nhận đơn trước.') return 'Go online before accepting an offer.';
      if (current === 'Đã bật nhận đơn.') return 'You are now online.';
      if (current === 'Đã tắt nhận đơn.') return 'You are now offline.';
      if (current === 'Đã ghi nhận đến điểm giao (demo).') return 'Arrival at the drop-off point recorded (demo).';
      if (current === 'Đã ghi nhận sự cố giao hàng (demo). Liên hệ Admin để xử lý tiếp.') return 'Delivery issue recorded (demo). Contact support for next steps.';
    } else {
      const notice = current.match(/^Phu Nhuan → District 3 · Estimated earnings (.+)$/);
      if (notice) return value.replace(current, `Phú Nhuận → Quận 3 · Thu nhập dự kiến ${notice[1]}`);
      const declined = current.match(/^Declined order (.+) in this demo\.$/);
      if (declined) return `Đã từ chối đơn ${declined[1]} trong bản demo.`;
      const payout = current.match(/^Delivered · Platform fee (.+) · Net earnings (.+) \(demo\)\.$/);
      if (payout) return `Giao thành công · Phí sàn ${payout[1]} · Thực nhận ${payout[2]} (demo).`;
      const payoutNote = current.match(/^Delivered successfully\. (.+) net earnings \(after the demo 10% platform fee\) are transferred automatically to your account\.$/);
      if (payoutNote) return `Đã giao thành công. ${payoutNote[1]} thực nhận (sau phí sàn demo 10%) được chuyển tự động vào tài khoản.`;
      if (current === 'Arrival recorded (demo).') return 'Đã đến điểm giao (demo).';
      if (current === 'Successful delivery recorded (demo).') return 'Đã ghi nhận giao hàng thành công (demo).';
      if (current === 'Go online before accepting an offer.') return 'Bạn cần bật trạng thái nhận đơn trước.';
      if (current === 'You are now online.') return 'Đã bật nhận đơn.';
      if (current === 'You are now offline.') return 'Đã tắt nhận đơn.';
      if (current === 'Arrival at the drop-off point recorded (demo).') return 'Đã ghi nhận đến điểm giao (demo).';
      if (current === 'Delivery issue recorded (demo). Contact support for next steps.') return 'Đã ghi nhận sự cố giao hàng (demo). Liên hệ Admin để xử lý tiếp.';
    }
    return value;
  };
  const translateShipperPage = () => {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      const node = walker.currentNode;
      if (!node.parentElement.closest('.shipper-copy-en,.shipper-copy-vi,script,style,.language-option')) nodes.push(node);
    }
    nodes.forEach(node => { const updated = localizeText(node.nodeValue); if (updated !== node.nodeValue) node.nodeValue = updated; });
    document.querySelectorAll('input[placeholder],textarea[placeholder]').forEach(input => {
      const placeholder = localizeText(input.placeholder); if (placeholder !== input.placeholder) input.placeholder = placeholder;
    });
    document.querySelectorAll('[aria-label],[title]').forEach(element => ['aria-label','title'].forEach(attribute => {
      const value = element.getAttribute(attribute); if (!value) return;
      const updated = localizeText(value); if (updated !== value) element.setAttribute(attribute, updated);
    }));
  };
  let localeTimer;
  const scheduleShipperTranslation = () => { clearTimeout(localeTimer); localeTimer = setTimeout(translateShipperPage, 0); };
  document.body.dataset.shipperLang = locale;
  const setShipperLocale = language => {
    locale = language === 'vi' ? 'vi' : 'en';
    document.body.dataset.shipperLang = locale;
    localStorage.setItem('somi-language', locale);
    document.querySelectorAll('#shipperHelpTopic option').forEach(option => { option.textContent = option.dataset[locale]; });
    document.querySelectorAll('[data-en-placeholder]').forEach(input => { input.placeholder = input.dataset[locale + 'Placeholder']; });
    scheduleShipperTranslation();
  };
  setShipperLocale(locale);
  document.addEventListener('somi:language-change', event => setShipperLocale(event.detail?.language));
  window.addEventListener('storage', event => { if (event.key === 'somi-language' && event.newValue) setShipperLocale(event.newValue); });
  document.querySelectorAll('[data-language-option]').forEach(option => option.addEventListener('click', () => setShipperLocale(option.dataset.languageOption)));

  const datePicker = document.querySelector('.date-picker');
  const dateTrigger = datePicker?.querySelector('[data-date-trigger]');
  const dateMenu = datePicker?.querySelector('[data-date-menu]');
  const dateLabel = datePicker?.querySelector('[data-date-label]');
  const dateValue = datePicker?.querySelector('[data-date-value]');
  const rangeNames = {today:['Today','Hôm nay'],yesterday:['Yesterday','Hôm qua'],week:['This week','Tuần này'],month:['This month','Tháng này']};
  const formatDate = date => new Intl.DateTimeFormat(locale === 'vi' ? 'vi-VN' : 'en-GB', {day:'2-digit',month:'2-digit',year:'numeric'}).format(date);
  const renderDateRange = range => {
    if (!dateLabel || !dateValue || !rangeNames[range]) return;
    dateLabel.textContent = rangeNames[range][locale === 'vi' ? 1 : 0];
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    let end = new Date(start);
    if (range === 'yesterday') start.setDate(start.getDate() - 1), end = new Date(start);
    if (range === 'week') { start.setDate(start.getDate() - ((start.getDay() + 6) % 7)); end = new Date(start); end.setDate(end.getDate() + 6); }
    if (range === 'month') { start.setDate(1); end = new Date(start.getFullYear(), start.getMonth() + 1, 0); }
    dateValue.textContent = range === 'today' || range === 'yesterday' ? `(${formatDate(start)})` : `(${formatDate(start)} – ${formatDate(end)})`;
    dateMenu?.querySelectorAll('[data-date-range]').forEach(option => option.setAttribute('aria-pressed', String(option.dataset.dateRange === range)));
    renderDashboardPeriod(range);
  };
  const dashboardPeriods = {
    today:{description:['A quick look at your delivery activity today.','Xem nhanh tình trạng hoạt động hôm nay của bạn.'],period:['today','hôm nay'],comparison:['vs. yesterday','so với hôm qua'],deliveries:5,earnings:320000,hours:'4h 30m',change:[25,12,10],chartTitle:['Hourly earnings','Thu nhập theo giờ'],chartNote:['Hourly earnings summary for today.','Biểu đồ thu nhập theo giờ hôm nay.'],labels:['9h','11h','13h','15h','17h'],bars:[35,55,43,85,62]},
    yesterday:{description:['Your delivery activity yesterday.','Tình hình hoạt động giao hàng hôm qua của bạn.'],period:['yesterday','hôm qua'],comparison:['vs. the day before','so với hôm kia'],deliveries:4,earnings:256000,hours:'4h 05m',change:[18,9,7],chartTitle:['Hourly earnings','Thu nhập theo giờ'],chartNote:['Hourly earnings summary for yesterday.','Biểu đồ thu nhập theo giờ hôm qua.'],labels:['9h','11h','13h','15h','17h'],bars:[28,48,65,54,78]},
    week:{description:['Your delivery activity this week.','Tình hình hoạt động giao hàng tuần này của bạn.'],period:['this week','tuần này'],comparison:['vs. last week','so với tuần trước'],deliveries:32,earnings:2180000,hours:'28h 40m',change:[18,14,8],chartTitle:['Earnings by day','Thu nhập theo ngày'],chartNote:['Daily earnings summary for this week.','Biểu đồ thu nhập theo từng ngày trong tuần.'],labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],labelsVi:['T2','T3','T4','T5','T6','T7','CN'],bars:[46,72,58,84,65,96,40]},
    month:{description:['Your delivery activity this month.','Tình hình hoạt động giao hàng tháng này của bạn.'],period:['this month','tháng này'],comparison:['vs. last month','so với tháng trước'],deliveries:126,earnings:8420000,hours:'112h 15m',change:[11,9,6],chartTitle:['Earnings by week','Thu nhập theo tuần'],chartNote:['Weekly earnings summary for this month.','Biểu đồ thu nhập theo từng tuần trong tháng.'],labels:['Week 1','Week 2','Week 3','Week 4','Week 5'],labelsVi:['Tuần 1','Tuần 2','Tuần 3','Tuần 4','Tuần 5'],bars:[55,72,63,91,48]}
  };
  const moneyText = amount => locale === 'vi' ? `₫${new Intl.NumberFormat('vi-VN').format(amount)}` : `₫${new Intl.NumberFormat('en-US').format(amount)}`;
  function renderDashboardPeriod(range) {
    const data = dashboardPeriods[range];
    if (!data) return;
    const text = pair => pair[locale === 'vi' ? 1 : 0];
    document.querySelectorAll('[data-dashboard-description]').forEach(el => { el.textContent = text(data.description); });
    document.querySelectorAll('[data-period-stat]').forEach(el => {
      const kind = el.dataset.periodStat;
      el.textContent = kind === 'deliveries' ? (locale === 'vi' ? `Đơn hoàn thành ${data.period[1]}` : `Deliveries completed ${data.period[0]}`)
        : kind === 'earnings' ? (locale === 'vi' ? `Thu nhập ${data.period[1]}` : `Earnings ${data.period[0]}`)
        : (locale === 'vi' ? `Thời gian hoạt động ${data.period[1]}` : `Active time ${data.period[0]}`);
    });
    document.querySelectorAll('[data-period-value]').forEach(el => {
      const kind = el.dataset.periodValue;
      el.textContent = kind === 'earnings' ? moneyText(data.earnings) : kind === 'deliveries' ? String(data.deliveries) : data.hours;
    });
    document.querySelectorAll('[data-period-trend]').forEach(el => {
      const kind = el.dataset.periodTrend;
      const index = kind === 'deliveries' ? 0 : kind === 'earnings' ? 1 : 2;
      el.innerHTML = `↑ ${data.change[index]}% <span class="muted">${text(data.comparison)}</span>`;
    });
    document.querySelectorAll('[data-period-earnings-title]').forEach(el => { el.textContent = locale === 'vi' ? `Thu nhập ${data.period[1]}` : `Earnings ${data.period[0]}`; });
    document.querySelectorAll('[data-period-chart-title]').forEach(el => { el.textContent = text(data.chartTitle); });
    document.querySelectorAll('[data-period-chart-note]').forEach(el => { el.textContent = text(data.chartNote); });
    document.querySelectorAll('[data-period-chart]').forEach(chart => {
      const labels = locale === 'vi' && data.labelsVi ? data.labelsVi : data.labels;
      chart.innerHTML = data.bars.map((height, index) => `<div class="barwrap"><div class="bar" style="height:${height}%"></div><span class="barlabel">${labels[index]}</span></div>`).join('');
    });
  }
  if (dateTrigger && dateMenu) {
    document.addEventListener('somi:language-change', () => renderDateRange(localStorage.getItem('somi-shipper-date-range') || 'today'));
    dateTrigger.addEventListener('click', () => { dateMenu.hidden = !dateMenu.hidden; dateTrigger.setAttribute('aria-expanded', String(!dateMenu.hidden)); });
    dateMenu.querySelectorAll('[data-date-range]').forEach(option => option.addEventListener('click', () => {
      localStorage.setItem('somi-shipper-date-range', option.dataset.dateRange);
      renderDateRange(option.dataset.dateRange);
      dateMenu.hidden = true;
      dateTrigger.setAttribute('aria-expanded', 'false');
    }));
    document.addEventListener('click', event => { if (!datePicker.contains(event.target)) { dateMenu.hidden = true; dateTrigger.setAttribute('aria-expanded', 'false'); } });
    renderDateRange(localStorage.getItem('somi-shipper-date-range') || 'today');
  }
  document.addEventListener('click', event => {
    const action = event.target.closest('[data-profile-action]')?.dataset.profileAction;
    if (action === 'support' || action === 'policy') {
      event.preventDefault(); event.stopImmediatePropagation();
      document.getElementById('profileDropdown')?.classList.remove('open');
      document.getElementById('profileBtn')?.setAttribute('aria-expanded', 'false');
      (action === 'support' ? support : policy).classList.remove('hidden');
    } else if (action === 'logout') {
      event.preventDefault(); event.stopImmediatePropagation();
      localStorage.removeItem('somi-demo-session');
      window.location.href = '?page=home';
    }
  }, true);
  document.querySelectorAll('[data-close-shipper-modal]').forEach(button => button.addEventListener('click', () => {
    support.classList.add('hidden'); policy.classList.add('hidden');
  }));
  [support, policy].forEach(modal => modal.addEventListener('click', event => {
    if (event.target === modal) modal.classList.add('hidden');
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { support.classList.add('hidden'); policy.classList.add('hidden'); }
  });
  document.querySelectorAll('[data-policy-tab]').forEach(tab => tab.addEventListener('click', () => {
    document.querySelectorAll('[data-policy-tab]').forEach(item => item.classList.toggle('selected', item === tab));
    document.querySelectorAll('[data-policy-content]').forEach(content => content.classList.toggle('hidden', content.dataset.policyContent !== tab.dataset.policyTab));
  }));
  document.addEventListener('DOMContentLoaded', scheduleShipperTranslation);
  const shipperLanguageObserver = new MutationObserver(scheduleShipperTranslation);
  shipperLanguageObserver.observe(document.body, {subtree:true,childList:true,characterData:true});
  document.getElementById('shipperHelpForm')?.addEventListener('submit', event => {
    event.preventDefault();
    const message = locale === 'vi' ? 'Đã ghi nhận yêu cầu hỗ trợ trong bản demo.' : 'Your support request has been recorded in this demo.';
    document.getElementById('shipperSupportModal').classList.add('hidden');
    const toast = document.getElementById('toast');
    if (toast) { toast.textContent = message; toast.style.display = 'block'; clearTimeout(window.toastTimer); window.toastTimer = setTimeout(() => toast.style.display = 'none', 2800); }
    event.currentTarget.reset();
  });
})();
</script>
