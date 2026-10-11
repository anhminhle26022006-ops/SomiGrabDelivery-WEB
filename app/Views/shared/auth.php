<main class="auth-page">
  <section class="auth-shell">
    <aside class="auth-aside"><a class="brand brand-light" href="?page=home"><strong>SOMI</strong><span>DELIVERY</span></a><div><p class="eyebrow">WELCOME TO SOMI</p><h1 data-auth-heading>Delivery that fits your day.</h1><p>Sign in to send a package or manage your delivery work with Somi.</p></div><small>Simple. Flexible. Transparent.</small></aside>
    <section class="auth-panel">
      <div class="auth-top"><a href="?page=home" class="back-link">← Back to website</a><select data-language aria-label="Language"><option value="en">English</option><option value="vi">Tiếng Việt</option></select></div>
      <div class="auth-form-wrap">
        <div class="auth-tabs"><button class="active" type="button" data-auth-mode="signin">Sign in</button><button type="button" data-auth-mode="signup">Create account</button></div>
        <h2 data-auth-title>Welcome back</h2><p class="muted" data-auth-subtitle>Enter your phone number or email to continue.</p>
        <form data-auth-form novalidate>
          <fieldset class="role-picker"><legend>I want to continue as</legend><label><input type="radio" name="role" value="customer" <?= ($_GET['next'] ?? '') !== 'shipper' ? 'checked' : '' ?>><span>Customer</span></label><label><input type="radio" name="role" value="shipper" <?= ($_GET['next'] ?? '') === 'shipper' ? 'checked' : '' ?>><span>Shipper</span></label></fieldset>
          <div class="field" data-signin-fields><label for="identifier">Phone number or email</label><input id="identifier" name="identifier" autocomplete="username" placeholder="+84 90 123 4567 or you@example.com" required><div class="field-row"><label for="password">Password</label><button type="button" class="text-button" data-forgot>Forgot password?</button></div><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required></div>
          <div class="signup-fields" data-signup-fields hidden>
            <div class="step-indicator"><span data-step-label>STEP 1 OF 4</span><div class="step-track"><i data-step-progress></i></div></div>
            <div class="signup-step" data-signup-step="1">
              <div class="field"><label for="fullName">Full name</label><input id="fullName" name="fullName" placeholder="Your full name"></div>
              <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" placeholder="090 123 4567"></div>
            </div>
            <div class="signup-step" data-signup-step="2" hidden>
              <div class="field"><label for="otp">Verification code</label><input id="otp" name="otp" inputmode="numeric" maxlength="6" placeholder="6-digit demo code"><small>Frontend demo: enter any 6 digits to continue.</small></div>
            </div>
            <div class="signup-step" data-signup-step="3" hidden>
              <div class="field"><label for="nationalId">CCCD / National ID number</label><input id="nationalId" name="nationalId" inputmode="numeric" maxlength="12" placeholder="12-digit CCCD number"></div>
              <p class="demo-note">VNeID/eKYC is not connected in this frontend-only build.</p>
            </div>
            <div class="signup-step" data-signup-step="4" hidden>
              <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" placeholder="you@example.com"></div>
              <div class="field"><label for="newPassword">Password</label><input id="newPassword" name="newPassword" type="password" placeholder="At least 8 characters"></div>
              <div class="field"><label for="confirmPassword">Confirm password</label><input id="confirmPassword" name="confirmPassword" type="password" placeholder="Repeat password"></div>
              <label class="check-row"><input type="checkbox" name="terms"> <span>I agree to the Terms of Service and Privacy Policy.</span></label>
            </div>
            <div class="signup-controls"><button type="button" class="button button-light" data-step-back hidden>Back</button><button type="button" class="button button-dark" data-step-next>Continue <span>→</span></button></div>
          </div>
          <p class="form-error" data-form-error role="alert" hidden></p>
          <button class="button button-dark button-full" type="submit" data-auth-submit>Sign in <span>↗</span></button>
          <p class="demo-note">Demo interface only. Authentication is not connected to a backend.</p>
        </form>
      </div>
    </section>
  </section>
</main>
