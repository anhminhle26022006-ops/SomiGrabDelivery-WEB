<?php
// Shipper workspace.
if ($shipperPage === 'notifications') $shipperPage = 'dashboard';
?>

<header class="topbar">
  <div class="brand">SOMI <small>DELIVERY</small></div>
  <button type="button" class="shipper-menu-toggle" aria-controls="shipperNav" aria-expanded="false" aria-label="Mở menu điều hướng"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
  <nav class="nav" id="shipperNav" aria-label="Điều hướng người giao">
    <a class="<?= $shipperPage === 'dashboard' ? 'active' : '' ?>" href="?page=shipper&amp;view=dashboard" data-page="dashboard" aria-controls="page-dashboard"<?= $shipperPage === 'dashboard' ? ' aria-current="page"' : '' ?>>Tổng quan</a>
    <a class="<?= $shipperPage === 'offers' ? 'active' : '' ?>" href="?page=shipper&amp;view=offers" data-page="offers" aria-controls="page-offers"<?= $shipperPage === 'offers' ? ' aria-current="page"' : '' ?>>Đơn giao phù hợp</a>
    <a class="<?= $shipperPage === 'active' ? 'active' : '' ?>" href="?page=shipper&amp;view=active" data-page="active" aria-controls="page-active"<?= $shipperPage === 'active' ? ' aria-current="page"' : '' ?>>Đơn đang giao</a>
    <a class="<?= $shipperPage === 'history' ? 'active' : '' ?>" href="?page=shipper&amp;view=history" data-page="history" aria-controls="page-history"<?= $shipperPage === 'history' ? ' aria-current="page"' : '' ?>>Lịch sử giao hàng</a>
    <a class="<?= $shipperPage === 'earnings' ? 'active' : '' ?>" href="?page=shipper&amp;view=earnings" data-page="earnings" aria-controls="page-earnings"<?= $shipperPage === 'earnings' ? ' aria-current="page"' : '' ?>>Thu nhập</a>
    <a class="<?= $shipperPage === 'support' ? 'active' : '' ?>" href="?page=shipper&amp;view=support" data-page="support" aria-controls="page-support"<?= $shipperPage === 'support' ? ' aria-current="page"' : '' ?>>Trung tâm hỗ trợ</a>
  </nav>

  <div class="top-actions">
    <div class="language-picker" data-language-picker>
      <button class="language-trigger" type="button" aria-label="Choose language" aria-expanded="false" data-language-trigger><span data-language-flag aria-hidden="true"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg></span><span data-language-current>EN</span><span class="language-chevron" aria-hidden="true">⌄</span></button>
      <div class="language-menu" data-language-menu hidden>
        <button type="button" class="language-option" data-language-option="en"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg><span>English</span><span class="language-code">EN</span></button>
        <button type="button" class="language-option" data-language-option="vi"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#da251d"/><path d="M15 3.5L16.8 8.4L22 8.5L17.9 11.6L19.4 16.5L15 13.6L10.6 16.5L12.1 11.6L8 8.5L13.2 8.4Z" fill="#ffde00"/></svg><span>Tiếng Việt</span><span class="language-code">VI</span></button>
      </div>
    </div>
    <button class="iconbtn" id="bellBtn" type="button" aria-expanded="false" aria-controls="shipperNotificationPopup" title="Thông báo" aria-label="Thông báo"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 9a6 6 0 0 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 15 18 9"/><path d="M10.5 20a1.7 1.7 0 0 0 3 0"/></svg><span class="badge-count" id="bellCount" data-unread hidden>0</span></button>
    <div class="profile">
      <button class="profilebtn" id="profileBtn" aria-expanded="false">
        <span class="avatar" id="avatar"><span>NA</span></span>
      </button>
      <div class="dropdown" id="profileDropdown">
        <div class="drophead"><strong data-shipper-name>Đang tải…</strong><span>Quản lý tài khoản & trợ giúp</span></div>
        <button data-profile-action="account">👤 Tài khoản & hồ sơ</button>
        <button data-profile-action="support">🎧 <span class="shipper-copy-en">Support center</span><span class="shipper-copy-vi">Trung tâm hỗ trợ</span></button>
        <button data-profile-action="policy">📄 <span class="shipper-copy-en">Policies & terms</span><span class="shipper-copy-vi">Chính sách &amp; điều khoản</span></button>
        <button type="button" data-workspace-switch="CUSTOMER">⇄ <span class="shipper-copy-en">Switch to Customer</span><span class="shipper-copy-vi">Chuyển sang Khách hàng</span></button>
        <button class="danger" data-signout>↪ <span class="shipper-copy-en">Sign out</span><span class="shipper-copy-vi">Đăng xuất</span></button>
      </div>
    </div>
  </div>
  <section id="shipperNotificationPopup" class="shipper-notification-popup" role="dialog" aria-label="Thông báo" tabindex="-1" hidden>
    <header class="notification-popup-head"><div><h2 data-notification-title>Thông báo</h2><p data-notification-count>Đang tải…</p></div><button type="button" data-notification-close aria-label="Đóng thông báo">✕</button></header>
    <div class="notification-popup-toolbar"><div><button type="button" data-notification-filter="all" aria-pressed="true">Tất cả</button><button type="button" data-notification-filter="unread" aria-pressed="false">Chưa đọc</button></div><button type="button" data-notification-read-all>Đọc tất cả</button></div>
    <p data-notification-status role="status" aria-live="polite" hidden></p>
    <div class="notification-popup-list" data-notification-list></div>
    <footer><button type="button" data-notification-refresh>Làm mới</button><button type="button" data-notification-more hidden>Xem thêm</button></footer>
  </section>
</header>
<main>
  <?php if ($shipperPage !== 'support'): ?>
  <div class="shipper-status d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Trạng thái nhận đơn">
    <button class="toggle" id="availabilityToggle" disabled aria-label="Bật tắt nhận đơn"></button><strong>Trạng thái nhận đơn</strong>
    <span class="pill" id="availabilityPill">Đang tải…</span>
    <p class="muted" id="availabilityHelp">Đang kiểm tra trạng thái nhận đơn…</p>
  </div>
  <?php endif; ?>
  <?php require __DIR__ . '/pages/' . $shipperPage . '.php'; ?>
  <?php if ($shipperPage !== 'support'): ?>
  <div hidden data-shipper-support-utility>
    <?php require __DIR__.'/../shared/person3-panel.php'; ?>
  </div>
  <?php endif; ?>
</main>
<div id="orderDetailModal" class="modalback hidden" role="dialog" aria-modal="true" aria-labelledby="orderDetailTitle">
  <div class="somi-modal detailmodal"><div class="modalhead"><div><span class="eyebrow">THÔNG TIN ĐƠN GIAO</span><h2 id="orderDetailTitle">Chi tiết đơn</h2></div><button class="outline" aria-label="Đóng" onclick="closeOrderDetail()">✕</button></div><div class="modalbody" id="orderDetailBody"></div></div>
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

  const policy = document.getElementById('shipperPolicyModal');
  let locale = localStorage.getItem('somi-language') === 'vi' ? 'vi' : 'en';
  const shipperCopy = {
    'CẬP NHẬT TÀI KHOẢN VÀ ĐƠN HÀNG':'ACCOUNT & DELIVERY UPDATES','Thông báo':'Notifications','Tập trung mọi cập nhật để bạn không bỏ lỡ thông tin quan trọng.':'See all updates in one place so you do not miss important information.','✓ Đánh dấu tất cả đã đọc':'✓ Mark all as read','Tất cả':'All','Đơn hàng':'Orders','Tài khoản':'Account','Xem':'View','5 phút trước':'5 minutes ago','8 phút trước':'8 minutes ago','10 phút trước':'10 minutes ago','12 phút trước':'12 minutes ago','1 giờ trước':'1 hour ago','2 giờ trước':'2 hours ago','3 giờ trước':'3 hours ago','Quản lý tài khoản & trợ giúp':'Account management & help',
    'Có đơn giao mới phù hợp với bạn':'A new delivery offer is available for you','Đơn SOMI-240126 đã được giao thành công':'Order SOMI-240126 was delivered successfully','Yêu cầu xác minh tài khoản':'Account verification required','Tin nhắn từ khách hàng':'Message from a customer','Bạn đã nhận được 42.000 ₫':'You received ₫42,000','Vui lòng cập nhật giấy tờ tùy thân để hoàn tất hồ sơ.':'Update your identity documents to complete your profile.','Xem các thay đổi có hiệu lực trong tuần này.':'Review the changes taking effect this week.','“Vui lòng giao giúp trước 17h nhé.”':'“Please deliver this before 5 PM.”',
    'KHÔNG GIAN NGƯỜI GIAO':'SHIPPER WORKSPACE','Tổng quan':'Dashboard','Đơn giao phù hợp':'Delivery offers','Đơn đang giao':'Active delivery','Lịch sử giao hàng':'Delivery history','Thu nhập':'Earnings','Trạng thái nhận đơn':'Order availability','ĐANG NHẬN ĐƠN':'ONLINE · ACCEPTING ORDERS','ĐÃ TẮT NHẬN ĐƠN':'OFFLINE · NOT ACCEPTING ORDERS','Bạn sẽ nhận được các đơn phù hợp khi trực tuyến.':'You will receive suitable offers while online.','Bạn sẽ không nhận đơn mới khi trạng thái đang tắt.':'You will not receive new offers while offline.',
    'Xem nhanh tình trạng hoạt động hôm nay của bạn.':'A quick look at your delivery activity today.','▦   Hôm nay (12/10/2026)⌄':'▦   Today (12/10/2026)⌄','Đơn hoàn thành hôm nay':'Deliveries completed today','Thu nhập hôm nay':'Today’s earnings','Thời gian hoạt động':'Active time','Đánh giá trung bình':'Average rating','28 đánh giá':'28 reviews','Đơn mới phù hợp với khu vực và phương tiện của bạn.':'New offers matching your area and vehicle.','Xem tất cả →':'View all →','Tóm tắt tiến độ đơn bạn đã nhận.':'A summary of your accepted delivery.','Chi tiết →':'Details →','Phú Nhuận, TP. Hồ Chí Minh':'Phu Nhuan, Ho Chi Minh City','Quận 3, TP. Hồ Chí Minh':'District 3, Ho Chi Minh City','ĐANG GIAO':'IN PROGRESS','Thu nhập':'Earnings','Đã nhận':'Accepted','Đến điểm lấy':'At pickup','Đang giao':'In transit','Hoàn tất':'Completed','Mở đơn đang giao →':'Open active delivery →','Thông báo mới':'Recent notifications','Các cập nhật gần đây cần bạn chú ý.':'Recent updates that need your attention.','Tóm tắt thu nhập hôm nay.':'A summary of today’s earnings.','so với hôm qua':'vs. yesterday','Biểu đồ tóm tắt · vào Thu nhập để xem chi tiết.':'Summary chart · open Earnings for more details.',
    'CƠ HỘI GIAO HÀNG':'DELIVERY OPPORTUNITIES','Chỉ hiển thị đơn chưa được nhận, phù hợp với bạn.':'Showing unclaimed offers that match your profile.','Đơn mới trong khu vực':'New offers nearby','Xem thông tin, tuyến đường và thu nhập trước khi quyết định.':'Review the details, route, and earnings before deciding.','Đề xuất phù hợp':'Recommended','Thu nhập cao nhất':'Highest earnings','Gần nhất':'Nearest','Thu nhập dự kiến':'Estimated earnings','Đối soát sau khi giao thành công':'Settled after successful delivery','Khoảng cách':'Distance','Ước tính':'Estimated time','Loại hàng':'Package type','Xem chi tiết':'View details','Từ chối':'Decline','Từ chối đơn':'Decline offer','Nhận đơn':'Accept offer','Chưa có đơn phù hợp':'No matching offers','Hãy kiểm tra lại sau hoặc bật trạng thái nhận đơn.':'Check again later or go online to receive offers.','Giao ngay':'Express','Ưu tiên':'Priority','Giao trong ngày':'Same day','Tài liệu':'Documents','Hàng hóa':'Goods',
    'ĐƠN ĐÃ NHẬN':'ACCEPTED DELIVERY','Quản lý các đơn bạn đã nhận và cập nhật từng mốc giao hàng.':'Manage your accepted deliveries and update each milestone.','1 đơn đang xử lý':'1 delivery in progress','Nhận đơn lúc 14:20 · Thu nhập dự kiến 42.000 ₫':'Accepted at 2:20 PM · Estimated earnings ₫42,000','Điểm lấy hàng':'Pickup location','Điểm giao hàng':'Drop-off location','Khoảng cách còn lại':'Distance remaining','Thời gian ước tính':'Estimated time','Đã nhận đơn':'Offer accepted','Đã lấy hàng':'Picked up','Đã đến nơi':'Arrived','Kết quả giao':'Delivery result','Lộ trình giao hàng':'Delivery route','Liên hệ người nhận':'Contact recipient','Báo sự cố / Hỗ trợ Admin':'Report an issue / Get support','Đã đến nơi →':'I have arrived →','Gửi yêu cầu hỗ trợ':'Send a support request','Mã đơn SOMI-240126 sẽ được gửi kèm để Admin nắm tình huống.':'Order SOMI-240126 will be included so support can review the issue.','Đóng':'Close','Loại yêu cầu':'Request type','Sự cố giao hàng':'Delivery issue','Không liên hệ được khách':'Unable to reach recipient','Địa chỉ hoặc tuyến đường':'Address or route','Mô tả tình huống':'Describe the issue','Mô tả ngắn vấn đề bạn cần Admin hỗ trợ...':'Briefly describe what you need help with...','Gửi yêu cầu cho Admin':'Send request to support','Demo: yêu cầu hiển thị xác nhận trên màn hình.':'Demo only: a confirmation will appear on screen.','Đã đến điểm giao':'Arrived at the drop-off point','Xác nhận giao thành công nếu người nhận đã nhận hàng. Nếu không thể bàn giao, chọn lý do để ghi nhận đúng trạng thái.':'Confirm successful delivery only after handing over the parcel. If delivery is not possible, record the reason.','✓ Đã giao hàng thành công':'✓ Marked as delivered','Không thể giao hàng…':'Unable to deliver…','Lý do không thể giao':'Reason delivery failed','Không liên lạc được với người nhận':'Unable to reach the recipient','Người nhận từ chối nhận hàng':'Recipient refused the parcel','Sai địa chỉ / không tìm thấy địa chỉ':'Wrong or unfindable address','Người nhận vắng mặt':'Recipient unavailable','Sự cố với hàng hóa / bao bì':'Parcel or packaging issue','Lý do khác':'Other reason','Ghi chú (không bắt buộc)':'Note (optional)','Mô tả ngắn tình huống...':'Briefly describe what happened...','Ghi nhận sự cố':'Record issue','Hủy':'Cancel',
    'THU NHẬP CỦA BẠN':'YOUR EARNINGS','Theo dõi doanh thu, các khoản thanh toán và lịch sử rút tiền.':'Track earnings, settlements, and payment history.','Thực nhận hôm nay':'Net earnings today','Đã trừ phí sàn':'Platform fee deducted','Tuần này':'This week','Tháng này':'This month','Thực nhận sau đối soát':'Net after settlement','Đang được giữ':'On hold','Sẽ thanh toán sau khi giao thành công':'Paid after successful delivery','Xuất báo cáo':'Export report','Biến động thu nhập':'Earnings activity','Minh họa thu nhập theo giờ trong ngày.':'Illustrative hourly earnings for today.','Hôm nay':'Today','Thanh toán':'Payments','Các khoản tiền gần đây.':'Recent transactions.','Đối soát & thanh toán':'Settlements & payments','Hệ thống tự động thanh toán về tài khoản đã đăng ký.':'Payments are sent automatically to your registered account.','Giao thành công':'Delivered successfully','tiền đơn':'delivery earnings','phí sàn':'platform fee','thực nhận':'net earnings','Đã chuyển vào tài khoản':'Sent to account','Đang vận chuyển':'In transit','đang được giữ':'on hold','Thanh toán sau khi giao thành công':'Paid after successful delivery','Chưa đối soát':'Not settled','Không cần rút tiền thủ công':'No manual withdrawal needed','Khi đơn hoàn tất, phí sàn được khấu trừ và số tiền thực nhận tự động chuyển vào tài khoản của bạn.':'After delivery is completed, the platform fee is deducted and your net earnings are transferred automatically to your account.',
    'CÁC ĐƠN ĐÃ KẾT THÚC':'COMPLETED DELIVERIES','Tra cứu đơn hoàn tất hoặc bị hủy; không bao gồm đơn đang giao.':'Review completed or cancelled deliveries. Active deliveries are not shown here.','Tất cả':'All','Hoàn thành':'Completed','Đã hủy':'Cancelled','Mã đơn':'Order ID','Thời gian':'Time','Lộ trình':'Route','Trạng thái':'Status',
    'HỒ SƠ NGƯỜI GIAO':'SHIPPER PROFILE','Tài khoản & hồ sơ':'Account & profile','Quản lý tài khoản & trợ giúp':'Account management & help','Quản lý thông tin cá nhân và ảnh đại diện của bạn.':'Manage your personal information and profile photo.','Thông tin cá nhân':'Personal information','Thông tin minh họa cho bản demo.':'Sample information for this demo.','Họ và tên':'Full name','Số điện thoại':'Phone number','Khu vực hoạt động':'Service area','Địa chỉ liên hệ':'Contact address','Địa chỉ cư trú/liên hệ phục vụ hồ sơ người giao.':'Residential or contact address for your shipper profile.','Địa chỉ cụ thể':'Street address','Phường / xã':'Ward / commune','Tỉnh / thành phố':'Province / city','Tài khoản nhận thanh toán':'Payout account','Tiền của đơn hoàn thành sẽ được đối soát, trừ phí dịch vụ rồi chuyển vào tài khoản đã xác minh.':'Completed delivery earnings are settled, the service fee is deducted, and the balance is sent to your verified account.','Quy trình xác minh tài khoản ngân hàng':'Bank account verification','Bản demo chưa kết nối ngân hàng.':'This demo is not connected to a bank.','Khi triển khai thật, tài khoản chỉ được nhận tiền sau khi xác minh tên chủ tài khoản và thông tin tài khoản; hệ thống chỉ hiển thị số tài khoản đã che.':'In production, payouts are enabled only after the account holder and bank details are verified. The interface will show only a masked account number.','Sau khi lưu, giao diện chỉ hiển thị dạng che số, ví dụ •••• 1234.':'After saving, the account number is masked, for example •••• 1234.','Bảo mật khi đổi tài khoản nhận tiền':'Payout account change security','Khi thay đổi tài khoản, yêu cầu xác thực lại bằng OTP gửi đến số điện thoại đã xác minh và kiểm tra tên chủ tài khoản. Tạm giữ khoản thanh toán chưa chuyển trong thời gian kiểm tra; gửi thông báo khi thay đổi và lưu nhật ký thao tác. Không cho đổi chỉ bằng cách sửa hồ sơ thông thường.':'Changing the payout account requires OTP verification using the verified phone number and a name match. Hold unsettled payouts during review, notify the shipper, and record the change. Do not allow account changes through ordinary profile edits.','🔐 Yêu cầu đổi tài khoản nhận tiền':'🔐 Request payout account change','Thông tin phương tiện':'Vehicle information','Thông tin dùng để xác minh phương tiện và ghép đơn phù hợp.':'Used to verify your vehicle and match suitable deliveries.','Loại phương tiện':'Vehicle type','Hãng xe / dòng xe':'Make / model','Biển số xe':'License plate','Màu xe':'Vehicle color','Số giấy phép lái xe (nếu áp dụng)':'Driver license number (if applicable)','Ngày hết hạn GPLX':'Driver license expiry date','Số đăng ký xe':'Vehicle registration number','Ngày hết hạn bảo hiểm':'Insurance expiry date','Khu vực / bán kính nhận đơn':'Service area / offer radius','Người liên hệ khẩn cấp':'Emergency contact','Dùng khi có sự cố trong quá trình giao hàng.':'For emergencies during delivery.','Họ tên người liên hệ':'Contact name','Số điện thoại người liên hệ':'Contact phone number','Trạng thái xác minh giấy tờ và phương tiện: Demo — chưa kết nối hệ thống xác minh. Không nhập thông tin thật khi thử bản demo.':'Document and vehicle verification status: Demo — verification is not connected. Do not enter real personal information while testing.','Lưu thay đổi':'Save changes','📷 Tải ảnh đại diện':'📷 Upload profile photo'
  };
  const shipperReverseCopy = Object.fromEntries(Object.entries(shipperCopy).map(([en, vi]) => [vi, en]));
  // One VND formatter for every amount on the Shipper pages, matching the Customer
  // workspace (I18N.money): "42.000 ₫" in Vietnamese, "₫42,000" in English.
  const formatShipperMoney = amount => {
    const value = Number(amount);
    if (!Number.isFinite(value)) return '—';
    return new Intl.NumberFormat(locale === 'vi' ? 'vi-VN' : 'en-US', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
  };
  window.formatShipperMoney = formatShipperMoney;
  // Copy is written in Vietnamese; amounts inside it are re-formatted for the active language.
  const localizeMoney = text => locale === 'vi'
    ? text.replace(/₫(\d{1,3}(?:,\d{3})*)/g, (match, digits) => formatShipperMoney(digits.replace(/,/g, '')))
    : text.replace(/(\d{1,3}(?:\.\d{3})*)\s₫/g, (match, digits) => formatShipperMoney(digits.replace(/\./g, '')));
  const translateCopy = value => {
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
  const localizeText = value => localizeMoney(translateCopy(value));
  const translateShipperPage = () => {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      const node = walker.currentNode;
      if (!node.parentElement.closest('.shipper-copy-en,.shipper-copy-vi,script,style,.language-option,[data-support-localized]')) nodes.push(node);
    }
    nodes.forEach(node => { const updated = localizeText(node.nodeValue); if (updated !== node.nodeValue) node.nodeValue = updated; });
    document.querySelectorAll('input[placeholder],textarea[placeholder]').forEach(input => {
      if (input.closest('[data-support-localized]')) return;
      const placeholder = localizeText(input.placeholder); if (placeholder !== input.placeholder) input.placeholder = placeholder;
    });
    document.querySelectorAll('[aria-label],[title]').forEach(element => ['aria-label','title'].forEach(attribute => {
      if (element.closest('[data-support-localized]')) return;
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
  function renderDashboardPeriod(range) {
    window.dispatchEvent(new CustomEvent('somi:shipper-period', {detail:{range}}));
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
      if (action === 'support') window.somiPerson3Open?.('tickets'); else policy.classList.remove('hidden');
    } else if (action === 'logout') {
      event.preventDefault(); event.stopImmediatePropagation();
      localStorage.removeItem('somi-demo-session');
      window.location.href = '?page=home';
    }
  }, true);
  document.querySelectorAll('[data-close-shipper-modal]').forEach(button => button.addEventListener('click', () => {
    policy.classList.add('hidden');
  }));
  [policy].forEach(modal => modal.addEventListener('click', event => {
    if (event.target === modal) modal.classList.add('hidden');
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { policy.classList.add('hidden'); }
  });
  document.querySelectorAll('[data-policy-tab]').forEach(tab => tab.addEventListener('click', () => {
    document.querySelectorAll('[data-policy-tab]').forEach(item => item.classList.toggle('selected', item === tab));
    document.querySelectorAll('[data-policy-content]').forEach(content => content.classList.toggle('hidden', content.dataset.policyContent !== tab.dataset.policyTab));
  }));
  document.addEventListener('DOMContentLoaded', scheduleShipperTranslation);
  const shipperLanguageObserver = new MutationObserver(scheduleShipperTranslation);
  shipperLanguageObserver.observe(document.body, {subtree:true,childList:true,characterData:true});
})();
</script>
<?php // Module giao diện nối API Shipper (Người 2). Chỉ nạp những file đã có trong mã nguồn. ?>
<?php foreach (['shipper-api', 'shipper-notifications', 'shipper-offers', 'shipper-tracking', 'shipper-active', 'shipper-account', 'shipper-dashboard'] as $shipperModule): ?>
<?php if (is_file(__DIR__ . '/../../../public/assets/js/' . $shipperModule . '.js')): ?><script defer src="<?= e(assetUrl('public/assets/js/' . $shipperModule . '.js')) ?>"></script><?php endif; ?>
<?php endforeach; ?>
