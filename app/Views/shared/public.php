<?php
$en = [
 'how'=>['HOW SOMI WORKS','Delivery, made clear at every step.','One simple flow from pickup request to proof of delivery.',['Create your delivery','Choose and pay','Track with confidence'],['Tell us where, when, and what you need delivered.','See your fee upfront and select your preferred payment method.','Get a verified shipper and follow each delivery milestone.']],
 'customers'=>['FOR CUSTOMERS','Every delivery under control.','From urgent documents to thoughtful packages, Somi keeps you informed.',['Simple booking','Clear pricing','Human support'],['Create a delivery in a few focused steps.','Review every fee before you confirm.','Report an issue and follow its resolution.']],
 'shippers'=>['FOR SHIPPERS','Your time. Your routes. Your earnings.','Go online when it suits you and accept only the offers that work.',['Choose your work','Earn transparently','Grow your rating'],['Review distance, package, and earnings before accepting.','See daily and weekly earnings at a glance.','Deliver with care and build customer trust.']],
 'pricing'=>[]
];
$vi = [
 'how'=>['CÁCH SOMI HOẠT ĐỘNG','Giao hàng rõ ràng ở mọi bước.','Một quy trình đơn giản từ lúc tạo yêu cầu đến khi hoàn tất giao hàng.',['Tạo đơn giao hàng','Chọn và thanh toán','Theo dõi an tâm'],['Cho biết nơi nhận, thời gian và món hàng cần giao.','Xem trước chi phí và chọn phương thức thanh toán.','Theo dõi từng mốc giao hàng.']],
 'customers'=>['DÀNH CHO KHÁCH HÀNG','Mọi chuyến giao đều trong tầm kiểm soát.','Từ tài liệu cần giao gấp đến những gói hàng quan trọng, Somi luôn cập nhật cho bạn.',['Đặt đơn đơn giản','Chi phí minh bạch','Hỗ trợ trực tiếp'],['Tạo yêu cầu giao hàng qua vài bước.','Xem mọi khoản phí trước khi xác nhận.','Báo cáo vấn đề và theo dõi xử lý.']],
 'shippers'=>['DÀNH CHO NGƯỜI GIAO','Thời gian của bạn. Tuyến đường của bạn. Thu nhập của bạn.','Online khi phù hợp và chỉ nhận những đơn giao phù hợp với bạn.',['Chọn công việc','Thu nhập minh bạch','Nâng cao đánh giá'],['Xem khoảng cách, gói hàng và thu nhập trước khi nhận.','Theo dõi thu nhập theo ngày và tuần.','Giao hàng tận tâm và xây dựng niềm tin.']],
 'pricing'=>[]
];
?>
<header class="site-header <?= $page === 'home' ? 'header-home' : '' ?>">
  <a class="brand" href="<?= linkTo('home') ?>"><strong>SOMI</strong><span>DELIVERY</span></a>
  <nav class="desktop-nav" aria-label="Main navigation">
    <?php foreach (['how'=>'How It Works','customers'=>'For Customers','shippers'=>'For Shippers','pricing'=>'Pricing'] as $key=>$label): ?>
      <a class="<?= $page === $key ? 'active' : '' ?>" href="<?= linkTo($key) ?>" data-nav="<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="header-actions">
    <?php require __DIR__ . '/language-picker.php'; ?>
    <a class="button button-dark button-small" href="<?= linkTo('auth') ?>" data-signin>↗ <span data-i18n="signin">Sign in</span></a>
    <button class="icon-button mobile-menu-button" type="button" data-menu-toggle aria-label="Open menu">☰</button>
  </div>
  <nav class="mobile-nav" data-mobile-nav hidden>
    <?php foreach (['how'=>'How It Works','customers'=>'For Customers','shippers'=>'For Shippers','pricing'=>'Pricing'] as $key=>$label): ?><a href="<?= linkTo($key) ?>" data-nav="<?= $key ?>"><?= $label ?></a><?php endforeach; ?>
    <a href="<?= linkTo('auth') ?>" data-i18n="mobileAuth">Sign in / Create account</a>
  </nav>
</header>
<?php if ($page === 'home'): ?>
<main class="landing">
  <section class="hero">
    <video class="hero-media" autoplay muted loop playsinline preload="metadata" aria-hidden="true" poster="" src="https://videos.pexels.com/video-files/7835290/7835290-uhd_3840_2160_25fps.mp4"></video>
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <div class="eyebrow"><span class="status-dot"></span><span data-i18n="badge">Flexible Delivery Network</span></div>
      <h1><span data-i18n="hero1">Deliver More.</span><br><span class="hero-accent" data-i18n="hero2">Earn on Your Time.</span></h1>
      <p class="hero-description" data-i18n="herodesc">Somi connects customers with trusted part-time shippers, making every delivery simple, flexible, and transparent.</p>
      <div class="hero-actions">
        <a class="button button-dark" href="<?= linkTo('auth', ['next'=>'customer']) ?>" data-i18n="send">Send a Package <span>↗</span></a>
        <a class="button button-glass" href="<?= linkTo('auth', ['next'=>'shipper']) ?>" data-i18n="become">Become a Shipper <span>→</span></a>
      </div>
      <a class="text-link" href="<?= linkTo('how') ?>"><span data-i18n="explore">Explore how Somi works</span> <span>↗</span></a>
    </div>
    <div class="hero-bottom"><span>© 2026 SOMI DELIVERY</span><span class="hero-bottom-center"><span class="status-dot"></span><span data-i18n="network">Delivery Network</span></span><span>VIETNAM · ON YOUR TIME</span></div>
  </section>
  <section class="section section-intro">
    <div><p class="eyebrow dark-eyebrow">A MORE FLEXIBLE WAY TO DELIVER</p><h2>Every delivery.<br><span class="muted">A little simpler.</span></h2></div>
    <p class="section-lead">One connected network for people who need to send a package and people who want to deliver on their own schedule.</p>
  </section>
  <section class="feature-grid">
    <article class="feature-card"><span class="feature-number">01</span><div class="feature-icon">↗</div><h3>Simple booking</h3><p>Create a delivery request with clear pickup and drop-off details.</p><a href="<?= linkTo('customers') ?>">For customers <span>→</span></a></article>
    <article class="feature-card feature-card-dark"><span class="feature-number">02</span><div class="feature-icon">⌁</div><h3>Flexible earning</h3><p>Choose the offers that fit your time, route, and delivery preferences.</p><a href="<?= linkTo('shippers') ?>">For shippers <span>→</span></a></article>
    <article class="feature-card"><span class="feature-number">03</span><div class="feature-icon">◎</div><h3>Transparent pricing</h3><p>Review estimated delivery fees before you confirm an order.</p><a href="<?= linkTo('pricing') ?>">View pricing <span>→</span></a></article>
  </section>
</main>
<?php elseif ($page === 'pricing'): require __DIR__ . '/pricing.php'; ?>
<?php else: $content = $en[$page]; ?>
<main class="info-page">
  <section class="info-hero"><p class="eyebrow dark-eyebrow"><?= e($content[0]) ?></p><h1><?= e($content[1]) ?></h1><p><?= e($content[2]) ?></p>
  <?php if ($page === 'customers' || $page === 'pricing'): ?><a class="button button-dark" href="<?= linkTo('auth',['next'=>'customer']) ?>">Send a Package <span>↗</span></a><?php elseif ($page === 'shippers'): ?><a class="button button-dark" href="<?= linkTo('auth',['next'=>'shipper']) ?>">Become a Shipper <span>↗</span></a><?php endif; ?></section>
  <section class="info-cards"><?php foreach ($content[3] as $i=>$title): ?><article class="info-card"><span class="feature-number">0<?= $i+1 ?></span><div class="feature-icon"><?= ['↗','⌁','◎'][$i] ?></div><h2><?= e($title) ?></h2><p><?= e($content[4][$i]) ?></p></article><?php endforeach; ?></section>
</main>
<?php endif; ?>
<footer class="site-footer"><a class="brand" href="<?= linkTo('home') ?>"><strong>SOMI</strong><span>DELIVERY</span></a><span>© 2026 Somi Delivery</span><div><button type="button" data-legal="terms">Terms of Service</button><button type="button" data-legal="privacy">Privacy Policy</button></div></footer>
<div class="modal-backdrop" data-legal-modal hidden><section class="modal" role="dialog" aria-modal="true" aria-labelledby="legal-title"><button class="modal-close" type="button" data-modal-close aria-label="Close">×</button><p class="eyebrow dark-eyebrow">SOMI DELIVERY</p><h2 id="legal-title" data-legal-title>Terms of Service</h2><p>This demo presents the information and policies for the Somi frontend. Production terms and privacy details should be reviewed before launch.</p><button class="button button-dark" type="button" data-modal-close>Close</button></section></div>
