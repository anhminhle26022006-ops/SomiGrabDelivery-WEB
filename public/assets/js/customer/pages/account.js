/* Customer: Account (profile, phone OTP verification, password) */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, h, icon } = U;

  function profile() {
    const a = S.getAccount();
    const initials = a.fullName.split(" ").slice(-2).map((p) => p[0]).join("");
    $("[data-profile]").innerHTML = `
      <span class="c-avatar">${h(initials)}</span>
      <h2>${h(a.fullName)}</h2>
      <p class="c-muted" style="margin:0;font-size:13px">${t`Mã khách hàng ${h(a.id)}`}</p>
      <div class="c-verified">
        <span class="badge rounded-pill c-badge c-tone-${a.status === "ACTIVE" ? "green" : "red"}">${a.status === "ACTIVE" ? t("Đang hoạt động") : t("Tạm khóa")}</span>
        <span class="badge rounded-pill c-badge c-tone-${a.phoneVerified ? "green" : "amber"}">${a.phoneVerified ? t("SĐT đã xác thực") : t("SĐT chưa xác thực")}</span>
        <span class="badge rounded-pill c-badge c-tone-${a.emailVerified ? "green" : "amber"}">${a.emailVerified ? t("Email đã xác thực") : t("Email chưa xác thực")}</span>
      </div>
      <div class="c-kv">
        <div><span>${t("Số điện thoại")}</span><strong>${h(U.prettyPhone(a.phone))}</strong></div>
        <div><span>Email</span><strong style="word-break:break-all">${h(a.email)}</strong></div>
        <div><span>${t("Thành viên từ")}</span><strong>${U.dateOnly(a.memberSince)}</strong></div>
      </div>`;
    $("[data-address-count]").textContent = t`${S.listAddresses().length} địa chỉ đã lưu`;
    $("[data-password-meta]").textContent = t`Lần đổi gần nhất: ${U.dateOnly(a.passwordChangedAt)}.`;
  }

  /* ---------- Personal info ---------- */
  function setupProfileForm() {
    const form = $("[data-profile-form]");
    const fullName = $("#fullName", form);
    const email = $("#email", form);
    const fill = () => { const a = S.getAccount(); fullName.value = a.fullName; email.value = a.email; $("[data-profile-actions]").hidden = true; U.setError(fullName, ""); U.setError(email, ""); };
    fill();
    form.addEventListener("input", (e) => { $("[data-profile-actions]").hidden = false; U.setError(e.target, ""); });
    $("[data-profile-cancel]").addEventListener("click", fill);
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const nameOk = U.setError(fullName, fullName.value.trim().length >= 2 ? "" : t("Nhập họ và tên, tối thiểu 2 ký tự."));
      const emailOk = U.setError(email, U.emailOk(email.value) ? "" : t("Email chưa đúng định dạng, ví dụ ban@example.com."));
      if (!nameOk || !emailOk) return;
      const { emailChanged } = S.updateProfile({ fullName: fullName.value, email: email.value });
      fill();
      U.toast(emailChanged ? t("Đã lưu. Kiểm tra hộp thư để xác thực email mới.") : t("Đã lưu thông tin cá nhân."));
    });
  }

  /* ---------- Phone + OTP ---------- */
  function phonePanel() {
    const a = S.getAccount();
    $("[data-phone-panel]").innerHTML = `
      <div class="c-panel-head"><h2>${t("Số điện thoại")}</h2><span class="badge rounded-pill c-badge c-tone-${a.phoneVerified ? "green" : "amber"}">${a.phoneVerified ? t("Đã xác thực") : t("Chưa xác thực")}</span></div>
      <p style="margin:0 0 14px;font-size:20px;font-weight:700;color:var(--c-navy);font-variant-numeric:tabular-nums">${h(U.prettyPhone(a.phone))}</p>
      <p class="c-panel-sub">${a.phoneVerified ? t("Đổi sang số mới cần nhập mã OTP gửi đến số đó.") : t("Bạn cần xác thực số điện thoại trước khi tạo đơn giao hàng.")}</p>
      <div class="c-actions">${a.phoneVerified ? `<button type="button" class="btn btn-outline-dark" data-phone-change>${t("Đổi số điện thoại")}</button>` : `<button type="button" class="btn btn-dark" data-phone-verify>${t("Xác thực số này")}</button><button type="button" class="btn btn-outline-dark" data-phone-change>${t("Dùng số khác")}</button>`}</div>
      ${!a.emailVerified ? `<div class="alert alert-warning c-banner" style="margin:18px 0 0">${icon("alert")}<div><strong>${t`Email ${h(a.email)} chưa xác thực`}</strong><p>${t("Mở liên kết trong email SOMI vừa gửi.")}</p></div><button type="button" class="btn btn-dark btn-sm" data-email-verify>${t("Mô phỏng: đã bấm liên kết")}</button></div>` : ""}`;
    const change = $("[data-phone-change]");
    if (change) change.addEventListener("click", () => openPhoneFlow(""));
    const verify = $("[data-phone-verify]");
    if (verify) verify.addEventListener("click", () => openPhoneFlow(a.phone, true));
    const emailBtn = $("[data-email-verify]");
    if (emailBtn) emailBtn.addEventListener("click", () => { S.verifyEmailDemo(); U.toast(t("Email đã được xác thực.")); });
  }

  function openPhoneFlow(prefill, sendNow = false) {
    let timer;
    let otp = null;
    const m = U.modal({
      title: t("Xác thực số điện thoại"),
      body: `<div data-phase="phone">
          <div class="c-field"><label class="form-label" for="otp-phone">${t("Số điện thoại")}</label><input class="form-control" id="otp-phone" type="tel" inputmode="numeric" value="${h(prefill)}" placeholder="0903 128 456"></div>
          <p class="c-muted" style="font-size:12px">${t("SOMI sẽ gửi mã gồm 6 chữ số qua SMS. Mã có hiệu lực 5 phút.")}</p>
        </div>
        <div data-phase="otp" hidden>
          <p class="c-muted" style="margin-top:0">${t("Nhập mã đã gửi đến")} <strong data-otp-target style="color:var(--c-navy)"></strong>.</p>
          <div class="c-field c-otp"><label class="visually-hidden" for="otp-code">${t("Mã OTP")}</label><input class="form-control" id="otp-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······"></div>
          <div class="c-otp-meta"><span data-otp-left></span><button type="button" class="c-link" data-resend disabled>${t("Gửi lại mã")}</button></div>
          <p class="c-demo-note" style="margin-top:12px" data-otp-demo></p>
        </div>`,
      actions: [
        { label: t("Đóng"), variant: "btn-outline-dark", onClick: ({ close }) => { clearInterval(timer); close(); } },
        { label: t("Gửi mã"), onClick: () => (otp ? confirm() : send()) }
      ]
    });
    const el = m.el;
    const phoneInput = $("#otp-phone", el);
    const codeInput = $("#otp-code", el);

    function send() {
      const value = phoneInput.value.replace(/\s/g, "");
      if (!U.phoneOk(value)) { U.setError(phoneInput, t("Số điện thoại gồm 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.")); phoneInput.focus(); return; }
      const current = S.getAccount();
      if (value === current.phone && current.phoneVerified) { U.setError(phoneInput, t("Đây là số bạn đang dùng và đã xác thực.")); return; }
      try {
        otp = S.requestOtp(value);
        $("[data-phase=phone]", el).hidden = true;
        $("[data-phase=otp]", el).hidden = false;
        $("[data-otp-target]", el).textContent = U.prettyPhone(value);
        $("[data-otp-demo]", el).textContent = t`Bản demo chưa nối SMS. Mã OTP của bạn là ${otp.code}.`;
        m.button(1).textContent = t("Xác thực");
        codeInput.focus();
        countdown();
      } catch (err) { U.setError(phoneInput, U.errorMessage(err)); }
    }
    function countdown() {
      clearInterval(timer);
      const resend = $("[data-resend]", el);
      const left = $("[data-otp-left]", el);
      const tick = () => {
        const s = otp ? Math.max(0, Math.round((Date.parse(otp.resendAt) - Date.now()) / 1000)) : 0;
        left.textContent = s > 0 ? t`Gửi lại sau ${s} giây` : t("Chưa nhận được mã?");
        resend.disabled = s > 0;
        if (s === 0) clearInterval(timer);
      };
      tick();
      timer = window.setInterval(tick, 1000);
      resend.onclick = () => {
        try { otp = S.requestOtp(phoneInput.value.replace(/\s/g, "")); $("[data-otp-demo]", el).textContent = t`Bản demo chưa nối SMS. Mã OTP mới là ${otp.code}.`; U.toast(t("Đã gửi mã mới.")); countdown(); }
        catch (err) { U.setError(codeInput, U.errorMessage(err)); }
      };
    }
    function confirm() {
      if (!/^\d{6}$/.test(codeInput.value.trim())) { U.setError(codeInput, t("Mã gồm 6 chữ số.")); return; }
      try {
        S.verifyOtp(codeInput.value);
        clearInterval(timer);
        m.close();
        U.toast(t("Đã xác thực số điện thoại."), "success");
      } catch (err) {
        U.setError(codeInput, U.errorMessage(err));
        codeInput.select();
        if (S.otpLocked()) { m.button(1).disabled = true; clearInterval(timer); }
      }
    }
    codeInput.addEventListener("input", () => U.setError(codeInput, ""));
    phoneInput.addEventListener("input", () => U.setError(phoneInput, ""));
    phoneInput.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); send(); } });
    codeInput.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); confirm(); } });
    if (sendNow) send();
  }

  /* ---------- Password ---------- */
  function setupPassword() {
    const form = $("[data-password-form]");
    const cur = $("#currentPassword"), next = $("#newPassword"), again = $("#confirmPassword");
    form.addEventListener("input", (e) => U.setError(e.target, ""));
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const a = U.setError(cur, cur.value ? "" : t("Nhập mật khẩu hiện tại."));
      const b = U.setError(next, next.value.length >= 8 ? (next.value === cur.value ? t("Mật khẩu mới phải khác mật khẩu hiện tại.") : "") : t("Mật khẩu mới cần ít nhất 8 ký tự."));
      const c = U.setError(again, again.value === next.value ? "" : t("Mật khẩu nhập lại chưa khớp."));
      if (!(a && b && c)) return;
      try { S.changePassword(cur.value, next.value); form.reset(); U.toast(t("Đã đổi mật khẩu."), "success"); }
      catch (err) { U.toast(U.errorMessage(err), "danger"); }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    profile();
    setupProfileForm();
    phonePanel();
    setupPassword();
    if (U.param("verify")) {
      const a = S.getAccount();
      if (!a.phoneVerified) openPhoneFlow(a.phone, true);
    }
  });
  document.addEventListener("somi:change", () => { profile(); phonePanel(); });
})();
