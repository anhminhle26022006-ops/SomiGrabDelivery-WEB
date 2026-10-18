<?php
$countryList = json_decode((string) file_get_contents(__DIR__ . '/../../../resources/countries.json'), true);
if (!is_array($countryList)) $countryList = [['code' => 'VN', 'name' => 'Vietnam']];
usort($countryList, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
?>
<main class="auth-page">
  <section class="auth-shell">
    <aside class="auth-aside"><a class="brand brand-light" href="?page=home"><strong>SOMI</strong><span>DELIVERY</span></a><div><p class="eyebrow" data-auth-aside-eyebrow>Welcome to Somi</p><h1 data-auth-heading>Delivery that fits your day.</h1><p data-auth-aside-description>Sign in to send a package or manage your delivery work with Somi.</p></div><small data-auth-aside-footer>Simple. Flexible. Transparent.</small></aside>
    <section class="auth-panel">
      <div class="auth-top"><a href="?page=home" class="back-link">← Back to website</a><div class="language-picker auth-language-picker" data-language-picker>
        <button class="language-trigger" type="button" aria-label="Choose language" aria-expanded="false" data-language-trigger><span class="language-globe" aria-hidden="true">◎</span><span data-language-current>EN</span><span class="language-chevron" aria-hidden="true">⌄</span></button>
        <div class="language-menu" data-language-menu hidden>
          <button type="button" class="language-option" data-language-option="en"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#17356b"/><path d="M0 0L30 20M30 0L0 20" stroke="#fff" stroke-width="5"/><path d="M0 0L30 20M30 0L0 20" stroke="#c8102e" stroke-width="2"/><path d="M15 0V20M0 10H30" stroke="#fff" stroke-width="7"/><path d="M15 0V20M0 10H30" stroke="#c8102e" stroke-width="3.5"/></svg><span>English</span><span class="language-code">EN</span></button>
          <button type="button" class="language-option" data-language-option="vi"><svg class="language-flag-svg" viewBox="0 0 30 20" aria-hidden="true" focusable="false"><rect width="30" height="20" rx="2" fill="#da251d"/><path d="M15 3.5L16.8 8.4L22 8.5L17.9 11.6L19.4 16.5L15 13.6L10.6 16.5L12.1 11.6L8 8.5L13.2 8.4Z" fill="#ffde00"/></svg><span>Tiếng Việt</span><span class="language-code">VI</span></button>
        </div>
      </div></div>
      <div class="auth-form-wrap">
        <div class="auth-tabs"><button class="active" type="button" data-auth-mode="signin">Sign in</button><button type="button" data-auth-mode="signup">Create account</button></div>
        <h2 data-auth-title>Welcome back</h2><p class="muted" data-auth-subtitle>Enter your phone number or email to continue.</p>
        <form data-auth-form novalidate>
          <fieldset class="role-picker auth-role-picker"><legend>Choose a workspace</legend>
            <label><input type="radio" name="role" value="customer" <?= ($_GET['next'] ?? '') !== 'shipper' ? 'checked' : '' ?>><span class="role-choice"><i aria-hidden="true">👤</i><strong>Customer</strong><small>Send and track deliveries</small></span></label>
            <label><input type="radio" name="role" value="shipper" <?= ($_GET['next'] ?? '') === 'shipper' ? 'checked' : '' ?>><span class="role-choice"><i aria-hidden="true">🛵</i><strong>Shipper</strong><small>Manage delivery work</small></span></label>
            <label><input type="radio" name="role" value="admin"><span class="role-choice"><i aria-hidden="true">🛡</i><strong>Admin</strong><small>Open admin console</small></span></label>
          </fieldset>
          <div class="field" data-signin-fields><label for="identifier">Phone number or email</label><input id="identifier" name="identifier" autocomplete="username" placeholder="+84 90 123 4567 or you@example.com" required><div class="field-row"><label for="password">Password</label><button type="button" class="text-button" data-forgot>Forgot password?</button></div><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required></div>
          <div class="signup-fields" data-signup-fields hidden>
            <div class="step-indicator"><span data-step-label>STEP 1 OF 5</span><div class="step-track"><i data-step-progress></i></div></div>
            <div class="signup-step" data-signup-step="1">
              <div class="field"><label for="fullName">Full name</label><input id="fullName" name="fullName" placeholder="Your full name"></div>
              <div class="field"><label for="phone">Phone number with country code</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+84901234567"></div>
              <div class="field"><label for="nationalityCode">Nationality</label><select id="nationalityCode" name="nationalityCode" required>
                <?php foreach ($countryList as $country): ?>
                <option value="<?= e((string) $country['code']) ?>"<?= $country['code'] === 'VN' ? ' selected' : '' ?>><?= e((string) $country['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
              <div class="field"><label for="dateOfBirth">Date of birth</label><input id="dateOfBirth" name="dateOfBirth" type="date" required></div>
              <div class="field"><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Select</option><option value="M">Male</option><option value="F">Female</option></select></div>
            </div>
            <div class="signup-step" data-signup-step="2" hidden>
              <div class="field"><label for="documentType">Identity document type</label><select id="documentType" name="documentType" required><option value="NATIONAL_ID">National ID</option><option value="PASSPORT">Passport</option><option value="FOREIGN_ID">Other foreign identity document</option></select></div>
              <div class="field"><label for="documentNumber">Identity document number</label><input id="documentNumber" name="documentNumber" autocomplete="off" maxlength="40" placeholder="Letters and numbers"></div>
              <p class="demo-note">The number is encrypted before it is stored. Shipper documents require Admin review.</p>
            </div>
            <div class="signup-step" data-signup-step="3" hidden>
              <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" placeholder="you@example.com"></div>
              <div class="field"><label for="newPassword">Password</label><input id="newPassword" name="newPassword" type="password" placeholder="At least 8 characters"></div>
              <div class="field"><label for="confirmPassword">Confirm password</label><input id="confirmPassword" name="confirmPassword" type="password" placeholder="Repeat password"></div>
              <label class="check-row"><input type="checkbox" name="terms"> <span>I agree to the Terms of Service and Privacy Policy.</span></label>
            </div>
            <div class="signup-step" data-signup-step="4" hidden>
              <div class="field"><label for="phoneOtp">Phone verification code</label><input id="phoneOtp" name="phoneOtp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit code"></div>
              <p class="demo-note" data-phone-demo-code hidden></p>
              <button class="text-button" type="button" data-resend-phone>Resend code</button>
            </div>
            <div class="signup-step" data-signup-step="5" hidden>
              <div class="field"><label for="emailOtp">Email verification code</label><input id="emailOtp" name="emailOtp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit code"></div>
              <small>Check your inbox and spam folder. The code expires in 5 minutes.</small>
              <button class="text-button" type="button" data-resend-email>Resend code</button>
            </div>
            <div class="signup-controls"><button type="button" class="button button-light" data-step-back hidden>Back</button><button type="button" class="button button-dark" data-step-next>Continue <span>→</span></button></div>
          </div>
          <p class="form-error" data-form-error role="alert" hidden></p>
          <button class="button button-dark button-full" type="submit" data-auth-submit><span data-auth-submit-text>Sign in</span><span>↗</span></button>
          <p class="demo-note">New accounts must verify both their phone number and email address.</p>
        </form>
      </div>
    </section>
  </section>
</main>
