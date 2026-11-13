<?php
// Dedicated support workspace; ticket and chat operations use authenticated APIs.
$supportCopy = static fn(string $vi, string $en): string => '<span class="shipper-copy-vi">' . e($vi) . '</span><span class="shipper-copy-en">' . e($en) . '</span>';
?>
<section id="page-support" class="page support-hub" aria-labelledby="supportPageTitle">
  <div class="support-breadcrumb"><a href="?page=shipper&amp;view=dashboard"><?= $supportCopy('Không gian người giao', 'Shipper workspace') ?></a><span aria-hidden="true">/</span><span><?= $supportCopy('Trung tâm hỗ trợ', 'Support center') ?></span></div>
  <header class="support-hero">
    <div class="support-hero-copy"><div class="eyebrow">SOMI CARE</div><h1 id="supportPageTitle"><?= $supportCopy('Chúng tôi có thể giúp gì cho bạn?', 'How can we help you?') ?></h1><p><?= $supportCopy('Gửi yêu cầu, theo dõi phản hồi và trao đổi về đơn hàng tại một nơi.', 'Create requests, follow responses and discuss deliveries in one place.') ?></p></div>
    <div class="support-hero-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><path d="M13 33v-5a19 19 0 0 1 38 0v5M13 30H9v15h9V30h-5Zm38 0h4v15h-9V30h5Zm0 15v3a8 8 0 0 1-8 8H32" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M27 53h10v6H27z" fill="currentColor"/></svg></div>
  </header>
  <script defer src="<?= e(assetUrl('public/assets/js/shipper-support.js')) ?>"></script>
  <?php require __DIR__ . '/../../shared/person3-panel.php'; ?>
  <section class="support-faq" aria-labelledby="supportFaqTitle">
    <div><span class="eyebrow"><?= $supportCopy('GIẢI ĐÁP NHANH', 'QUICK ANSWERS') ?></span><h2 id="supportFaqTitle"><?= $supportCopy('Thông tin hữu ích', 'Useful information') ?></h2><p><?= $supportCopy('Một vài hướng dẫn trong quá trình giao hàng.', 'A few pointers to help with your deliveries.') ?></p></div>
    <div class="support-faq-items">
      <details><summary><?= $supportCopy('Không liên hệ được người nhận?', 'Cannot reach the recipient?') ?></summary><p><?= $supportCopy('Thử gọi hoặc nhắn tin trong mục Trò chuyện. Nếu vẫn không liên hệ được, ghi nhận sự cố tại đơn đang giao và gửi yêu cầu hỗ trợ kèm mã đơn. Chỉ xác nhận hoàn tất sau khi bàn giao hàng.', 'Try calling or messaging through Conversations. If there is no response, record the issue on the active delivery and create a request with the order code. Only mark a delivery complete after handing over the parcel.') ?></p></details>
      <details><summary><?= $supportCopy('Kiểm tra khoản chi trả ở đâu?', 'Where can I check a payout?') ?></summary><p><?= $supportCopy('Xem trạng thái trong mục Thu nhập. Khoản đang xử lý chưa đồng nghĩa tiền đã được chuyển. Nếu có sai lệch, tạo yêu cầu thuộc danh mục Thanh toán và chọn đơn liên quan.', 'Check the Earnings page. Processing does not confirm a completed transfer. For a discrepancy, create a request under Earnings & payments and select the related order.') ?></p></details>
      <details><summary><?= $supportCopy('Bổ sung ảnh và tài liệu cho yêu cầu?', 'How do I add supporting files?') ?></summary><p><?= $supportCopy('Mở yêu cầu đã tạo để gửi phản hồi và đính kèm bằng chứng. Hệ thống hỗ trợ ảnh JPEG, PNG, WebP và PDF, mỗi tệp tối đa 5 MiB.', 'Open an existing request to reply and attach evidence. JPEG, PNG, WebP and PDF files up to 5 MiB each are supported.') ?></p></details>
    </div>
  </section>
</section>
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/shipper-support.css')) ?>">
