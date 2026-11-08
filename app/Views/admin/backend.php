<?php require_once __DIR__.'/_entry.php'; ?>
<header class="workspace-header">
  <a class="brand" href="?page=admin"><strong>SOMI</strong><span>ADMIN</span></a>
  <div class="header-actions">
    <a class="button button-light" href="?page=admin">Tổng quan</a>
    <?php require __DIR__ . '/../shared/language-picker.php'; ?>
    <button class="button button-dark" type="button" data-signout>Sign out</button>
  </div>
</header>
<main class="workspace"><?php require __DIR__ . '/../shared/person1-workspace.php'; ?></main>
