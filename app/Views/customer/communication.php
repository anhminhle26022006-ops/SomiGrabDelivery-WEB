<?php // Customer chat and Admin support are intentionally separate destinations. ?>
<section class="cc" data-customer-communication data-mode="<?= e($customerCommunicationMode) ?>">
  <p class="cc-feedback" data-cc-status role="status" aria-live="polite"></p>
  <div data-cc-content aria-busy="true"></div>
</section>
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/customer-communication.css')) ?>">
<script defer src="<?= e(assetUrl('public/assets/js/customer/communication.js')) ?>"></script>
