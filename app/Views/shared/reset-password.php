<main class="auth-page">
  <section class="auth-shell">
    <aside class="auth-aside"><a class="brand brand-light" href="?page=home"><strong>SOMI</strong><span>DELIVERY</span></a><div><p class="eyebrow">Account security</p><h1>Choose a new password.</h1><p>Set a new password to regain access to your SOMI account.</p></div><small>Simple. Flexible. Transparent.</small></aside>
    <section class="auth-panel">
      <div class="auth-top"><a href="?page=auth" class="back-link">← Back to sign in</a></div>
      <div class="auth-form-wrap">
        <h2>Reset your password</h2>
        <p class="muted">Enter a new password for your account.</p>
        <form data-password-reset-form novalidate>
          <input type="hidden" name="token" value="<?= e((string) ($_GET['token'] ?? '')) ?>">
          <div class="field"><label for="resetPassword">New password</label><input id="resetPassword" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
          <div class="field"><label for="resetPasswordConfirm">Confirm new password</label><input id="resetPasswordConfirm" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
          <p class="form-error" data-reset-error role="alert" hidden></p>
          <p class="demo-note" data-reset-success role="status" hidden>Password updated. You can now sign in with your new password.</p>
          <button class="button button-dark button-full" type="submit" data-reset-submit>Save new password</button>
          <p><a class="text-button" href="?page=auth">Back to sign in</a></p>
        </form>
      </div>
    </section>
  </section>
</main>
