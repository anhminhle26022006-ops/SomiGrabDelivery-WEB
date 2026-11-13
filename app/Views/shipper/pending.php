<header class="workspace-header"><a class="brand" href="?page=home"><strong>SOMI</strong><span>DELIVERY</span></a><div class="header-actions"><?php require __DIR__ . "/../shared/language-picker.php"; ?><span class="user-pill">◉ Shipper application</span><button class="button button-light button-small" type="button" data-workspace-switch="CUSTOMER">Switch to Customer</button><button class="button button-dark button-small" type="button" data-signout>Sign out</button></div></header>
<?php
$applicationQuery = somi_db()->prepare('SELECT application_submitted_at FROM shipper_profiles WHERE user_id=?');
$applicationQuery->execute([$sessionUserId]);
$hasCompleteApplication = (bool)$applicationQuery->fetchColumn();
?>
<main class="workspace">
  <div class="workspace-heading"><p class="eyebrow dark-eyebrow">SHIPPER WORKSPACE</p><h1>Shipper verification</h1><p>Your account can use both Customer and Shipper workspaces. Shipper delivery features unlock after Admin review.</p></div>
  <?php if ($shipperVerificationStatus === 'PENDING' && $hasCompleteApplication): ?>
    <section class="panel"><span class="status status-amber">PENDING REVIEW</span><h2>Application received</h2><p class="muted">Your vehicle and identity details are waiting for Admin review. You can switch to the Customer workspace while you wait.</p></section>
  <?php else: ?>
    <section class="panel">
      <h2 data-copy-vi="Hoàn thiện hồ sơ Shipper" data-copy-en="Complete your Shipper application">Hoàn thiện hồ sơ Shipper</h2>
      <?php if ($shipperRejectionReason): ?><p class="sa-error" role="status"><?= e((string)$shipperRejectionReason) ?></p><?php endif; ?>
      <?php require __DIR__.'/../shared/shipper-application-form.php'; ?>
    </section>
  <?php endif; ?>
</main>
