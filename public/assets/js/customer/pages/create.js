/* Customer: Create delivery order (4 steps + live fee estimate) */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const I18N = window.SomiI18n;
  const { t, tx } = I18N;
  const { $, $$, h, icon } = U;
  const DRAFT_KEY = "somi-customer-draft";
  // New orders use the currency chosen in the header; the order keeps it afterwards.
  const CURRENCY = I18N.currency;
  const money = (amount) => U.money(amount, CURRENCY);

  let step = 1;
  let form;

  function blockedState() {
    const a = S.getAccount();
    const holder = $("[data-create-blocked]");
    holder.hidden = false;
    $("[data-create-form-wrap]").hidden = true;
    const suspended = a.status === "SUSPENDED";
    holder.innerHTML = `<section class="c-state">
      <span class="c-state-icon ${suspended ? "c-tone-red" : "c-tone-amber"}">${icon(suspended ? "shield" : "phone", 28)}</span>
      <h1>${suspended ? t("Tài khoản đang tạm khóa") : t("Cần xác thực số điện thoại")}</h1>
      <p>${suspended ? t("Bạn chưa thể tạo đơn mới trong thời gian tài khoản bị khóa. Đơn cũ vẫn xem được bình thường.") : t("Shipper và người nhận cần liên lạc với bạn qua số điện thoại đã xác thực. Việc này chỉ mất một phút.")}</p>
      <div class="c-actions">${suspended ? `<a class="btn btn-dark" href="tel:19000000">${t("Gọi hỗ trợ 1900 0000")}</a>` : `<a class="btn btn-dark" href="${U.url("account", { verify: 1 })}">${t("Xác thực số điện thoại")}</a>`}<a class="btn btn-outline-dark" href="${U.url("orders")}">${t("Xem đơn hàng")}</a></div>
    </section>`;
  }

  /* ---------- Address blocks ---------- */
  const fieldsOf = (side) => ({
    saved: $(`#${side}-saved`), name: $(`#${side}-name`), phone: $(`#${side}-phone`),
    detail: $(`#${side}-detail`), area: $(`#${side}-area`),
    saveWrap: $(`[data-side="${side}"] [data-save-wrap]`), save: $(`input[name="${side}-save"]`, form)
  });

  function setupAddressBlock(side) {
    const f = fieldsOf(side);
    const addresses = S.listAddresses();
    f.saved.innerHTML = `<option value="">${t("Nhập địa chỉ mới")}</option>` + addresses.map((a) => `<option value="${a.id}">${h(t(a.label))} · ${h(a.detail)}, ${h(a.area)}</option>`).join("");
    f.area.innerHTML = `<option value="">${t("Chọn khu vực")}</option>` + S.AREAS.map((a) => `<option>${h(a.name)}</option>`).join("");
    f.saved.addEventListener("change", () => {
      const a = addresses.find((x) => x.id === f.saved.value);
      if (a) { f.name.value = a.receiverName; f.phone.value = a.receiverPhone; f.detail.value = a.detail; f.area.value = a.area; }
      else { f.name.value = ""; f.phone.value = ""; f.detail.value = ""; f.area.value = ""; }
      [f.name, f.phone, f.detail, f.area].forEach((el) => U.setError(el, ""));
      f.saveWrap.hidden = Boolean(a);
      update();
    });
    // Editing a filled saved address turns it into a one-off address for this order.
    [f.name, f.phone, f.detail, f.area].forEach((el) => el.addEventListener("input", () => {
      if (f.saved.value) {
        const a = addresses.find((x) => x.id === f.saved.value);
        const same = a && f.name.value === a.receiverName && f.phone.value.replace(/\s/g, "") === a.receiverPhone && f.detail.value === a.detail && f.area.value === a.area;
        if (!same) { f.saved.value = ""; f.saveWrap.hidden = false; }
      }
      U.setError(el, "");
      update();
    }));
    f.saveWrap.hidden = Boolean(f.saved.value);
  }

  function readAddress(side) {
    const f = fieldsOf(side);
    const saved = S.listAddresses().find((a) => a.id === f.saved.value);
    return {
      addressId: saved ? saved.id : null, label: saved ? saved.label : "",
      name: f.name.value.trim(), phone: f.phone.value.replace(/\s/g, ""), detail: f.detail.value.trim(), area: f.area.value
    };
  }

  /* ---------- Package ---------- */
  function setupPackage() {
    $("[data-types]").innerHTML = S.PACKAGE_TYPES.map((p, i) => `<label class="c-tile"><input type="radio" name="pkg-type" value="${p.value}" ${i === 1 ? "checked" : ""}><strong>${h(p.label)}</strong><small>${h(p.hint)}${S.surchargeFor(p.value, CURRENCY) ? ` · +${money(S.surchargeFor(p.value, CURRENCY))}` : ""}</small></label>`).join("");
    $("[data-weight-hint]").textContent = t`Tối đa ${S.MAX_WEIGHT_KG} kg. Miễn phụ phí đến ${S.PRICING[CURRENCY].freeWeightKg} kg.`;
    $$("input[name=pkg-type], #pkg-weight, #pkg-quantity, #pkg-description").forEach((el) => el.addEventListener("input", () => { U.setError(el, ""); update(); }));
  }
  function readPackage() {
    return {
      type: form.querySelector("input[name=pkg-type]:checked")?.value || "SMALL",
      description: $("#pkg-description").value.trim(),
      weightKg: Number(String($("#pkg-weight").value).replace(",", ".")),
      quantity: Number($("#pkg-quantity").value),
      note: $("#pkg-note").value.trim()
    };
  }

  /* ---------- Schedule ---------- */
  const toLocalInput = (ms) => { const d = new Date(ms - new Date().getTimezoneOffset() * 60000); return d.toISOString().slice(0, 16); };
  function setupSchedule() {
    const at = $("#schedule-at");
    at.min = toLocalInput(Date.now() + 30 * 60000);
    at.max = toLocalInput(Date.now() + 7 * 864e5);
    $$("input[name=schedule]").forEach((r) => r.addEventListener("change", () => {
      $("[data-later]").hidden = readSchedule().mode !== "later";
      if (!at.value) at.value = toLocalInput(Date.now() + 2 * 3600000);
      U.setError(at, "");
    }));
  }
  function readSchedule() {
    const mode = form.querySelector("input[name=schedule]:checked")?.value === "later" ? "later" : "now";
    const value = $("#schedule-at").value;
    return { mode, at: mode === "later" && value ? new Date(value).toISOString() : null };
  }

  /* ---------- Validation per step ---------- */
  function validateStep(n) {
    let ok = true;
    const need = (el, cond, msg) => { if (!cond) { U.setError(el, msg); if (ok) el.focus(); ok = false; } else U.setError(el, ""); };
    if (n === 1) {
      ["pickup", "delivery"].forEach((side) => {
        const f = fieldsOf(side);
        need(f.name, f.name.value.trim().length >= 2, side === "pickup" ? t("Nhập tên người gửi.") : t("Nhập tên người nhận."));
        need(f.phone, U.phoneOk(f.phone.value), t("Số điện thoại gồm 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09."));
        need(f.detail, f.detail.value.trim().length >= 5, t("Nhập số nhà, tên đường và phường."));
        need(f.area, Boolean(f.area.value), t("Chọn khu vực."));
      });
      const p = readAddress("pickup");
      const d = readAddress("delivery");
      if (ok && p.detail.toLowerCase() === d.detail.toLowerCase() && p.area === d.area) {
        need($("#delivery-detail"), false, t("Điểm giao đang trùng điểm lấy hàng."));
      }
    }
    if (n === 2) {
      const pkg = readPackage();
      const weight = $("#pkg-weight");
      const qty = $("#pkg-quantity");
      need($("#pkg-description"), pkg.description.length >= 3, t("Mô tả ngắn kiện hàng, tối thiểu 3 ký tự."));
      need(weight, Number.isFinite(pkg.weightKg) && pkg.weightKg > 0, t("Khối lượng phải là số lớn hơn 0."));
      if (ok) need(weight, pkg.weightKg <= S.MAX_WEIGHT_KG, t`SOMI nhận kiện tối đa ${S.MAX_WEIGHT_KG} kg. Hãy chia nhỏ kiện hàng.`);
      need(qty, Number.isInteger(pkg.quantity) && pkg.quantity >= 1 && pkg.quantity <= S.MAX_QUANTITY, t`Số kiện là số nguyên từ 1 đến ${S.MAX_QUANTITY}.`);
    }
    if (n === 3) {
      const sch = readSchedule();
      if (sch.mode === "later") {
        const at = $("#schedule-at");
        const when = Date.parse(sch.at || "");
        need(at, when >= Date.now() + 29 * 60000, t("Chọn thời điểm sau hiện tại ít nhất 30 phút."));
        if (ok) need(at, when <= Date.now() + 7 * 864e5, t("Chỉ hẹn được trong 7 ngày tới."));
      }
    }
    if (n === 4) {
      const agree = $("[data-agree]");
      if (!agree.checked) { U.toast(t("Vui lòng xác nhận kiện hàng không chứa hàng cấm.")); agree.focus(); ok = false; }
    }
    return ok;
  }

  /* ---------- Steps ---------- */
  function showStep(n) {
    step = n;
    $$("[data-step]").forEach((s) => { s.hidden = Number(s.dataset.step) !== n; });
    $$("[data-stepper] li").forEach((li, i) => {
      li.classList.toggle("is-done", i + 1 < n);
      li.classList.toggle("is-current", i + 1 === n);
      li.toggleAttribute("aria-current", i + 1 === n);
    });
    $("[data-prev]").hidden = n === 1;
    $("[data-next]").textContent = n === 4 ? t("Xác nhận tạo đơn") : t("Tiếp tục");
    if (n === 4) renderSummary();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function renderSummary() {
    const p = readAddress("pickup");
    const d = readAddress("delivery");
    const pkg = readPackage();
    const sch = readSchedule();
    const block = (title, goto, lines) => `<section><h3>${title}${goto ? `<button type="button" class="c-link" data-goto="${goto}">${t("Sửa")}</button>` : ""}</h3>${lines.map((l) => `<p>${l}</p>`).join("")}</section>`;
    $("[data-summary]").innerHTML = [
      block(t("Lấy hàng tại"), 1, [`${h(p.detail)}, ${h(p.area)}`, `${h(p.name)} · ${h(U.prettyPhone(p.phone))}`]),
      block(t("Giao đến"), 1, [`${h(d.detail)}, ${h(d.area)}`, `${h(d.name)} · ${h(U.prettyPhone(d.phone))}`]),
      block(t("Kiện hàng"), 2, [t`${h(U.packageLabel(pkg.type))} · ${pkg.weightKg} kg · ${pkg.quantity} kiện`, h(pkg.description) + (pkg.note ? t` · Ghi chú: ${h(pkg.note)}` : "")]),
      block(t("Thời gian"), 3, [sch.mode === "now" ? t("Giao ngay") : t`Lấy hàng lúc ${U.dateTime(sch.at, true)}`]),
      block(t("Tiền tệ thanh toán"), 0, [CURRENCY === "USD" ? t("USD theo bảng giá USD. Chỉ thanh toán bằng thẻ quốc tế.") : t("VND theo bảng giá VND.")])
    ].join("");
    $$("[data-goto]").forEach((b) => b.addEventListener("click", () => showStep(Number(b.dataset.goto))));
  }

  /* ---------- Live fee estimate ---------- */
  function update() {
    const p = readAddress("pickup");
    const d = readAddress("delivery");
    const pkg = readPackage();
    const q = S.quote(p, d, { ...pkg, weightKg: pkg.weightKg || 0 }, CURRENCY);
    const fxNote = CURRENCY === "USD" ? `<p class="c-fx-note">${t`Bảng giá USD. Tham khảo: 1 USD = ${U.money(S.USD_RATE, "VND")}.`}</p>` : "";
    const panel = $("[data-quote]");
    if (!q.ready) {
      panel.innerHTML = `<h2>${t("Phí giao hàng")}</h2><div class="c-quote-total"><small>${t("Ước tính")}</small><strong>— ${I18N.CURRENCIES[CURRENCY].symbol}</strong></div><p class="c-quote-empty">${t("Chọn khu vực cho điểm lấy và điểm giao để SOMI tính quãng đường và phí.")}</p>${fxNote}`;
    } else {
      panel.innerHTML = `<h2>${t("Phí giao hàng")}</h2>
        <div class="c-quote-total"><small>${t("Ước tính")}</small><strong>${money(q.deliveryFee)}</strong></div>
        <div class="c-quote-meta"><span>${icon("map", 15)} <b>${q.distanceKm} km</b></span><span>${icon("clock", 15)} <b>${t`khoảng ${q.durationMin} phút`}</b></span></div>
        <dl class="c-fees">${q.lines.map((l) => `<div><dt>${h(tx(l.label))}</dt><dd>${money(l.amount)}</dd></div>`).join("")}<div class="c-fees-total"><dt>${t("Tổng")}</dt><dd>${money(q.deliveryFee)}</dd></div></dl>
        <p class="c-quote-note">${icon("shield", 16)}<span>${t`Phí được chốt khi bạn xác nhận. Nếu giao thất bại do người nhận, phí hoàn hàng là ${money(q.returnFee)}.`}</span></p>${fxNote}`;
    }
    saveDraft();
  }

  /* ---------- Draft (survives a reload during the flow) ---------- */
  function saveDraft() {
    try {
      const data = { fields: {}, radios: {}, saved: {} };
      $$("input, select, textarea", form).forEach((el) => {
        const isChoice = el instanceof HTMLInputElement && (el.type === "checkbox" || el.type === "radio");
        if (el.id && !isChoice) data.fields[el.id] = el.value;
      });
      data.radios = { type: form.querySelector("input[name=pkg-type]:checked")?.value, schedule: readSchedule().mode };
      data.saved = { pickup: $("#pickup-saved").value, delivery: $("#delivery-saved").value };
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(data));
    } catch { /* ignore */ }
  }
  function restoreDraft() {
    let data = null;
    try { data = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || "null"); } catch { data = null; }
    if (!data || !data.fields) return false;
    const fields = data.fields;
    ["pickup", "delivery"].forEach((side) => {
      const f = fieldsOf(side);
      f.saved.value = data?.saved[side] || "";
      ["name", "phone", "detail", "area"].forEach((k) => {
        const value = fields[`${side}-${k}`];
        if (value !== undefined) f[k].value = value;
      });
      f.saveWrap.hidden = Boolean(f.saved.value);
    });
    ["pkg-description", "pkg-weight", "pkg-quantity", "pkg-note", "schedule-at"].forEach((id) => {
      if (fields[id] !== undefined) $(`#${id}`).value = fields[id];
    });
    const type = form.querySelector(`input[name=pkg-type][value="${data.radios.type}"]`);
    if (type) type.checked = true;
    const sch = form.querySelector(`input[name=schedule][value="${data.radios.schedule}"]`);
    if (sch) { sch.checked = true; $("[data-later]").hidden = sch.value !== "later"; }
    return true;
  }

  /* ---------- Submit ---------- */
  function submit() {
    if (!validateStep(4)) return;
    const btn = $("[data-next]");
    btn.disabled = true;
    try {
      const pickup = readAddress("pickup");
      const delivery = readAddress("delivery");
      ["pickup", "delivery"].forEach((side) => {
        const addr = side === "pickup" ? pickup : delivery;
        if (!addr.addressId && fieldsOf(side).save.checked) {
          const saved = S.saveAddress({ label: "Khác", receiverName: addr.name, receiverPhone: addr.phone, detail: addr.detail, area: addr.area });
          addr.addressId = saved.id; addr.label = saved.label;
        }
      });
      const pkg = readPackage();
      const order = S.createOrder({ pickup, delivery, pkg: { ...pkg, quantity: Math.floor(pkg.quantity) }, schedule: readSchedule(), currency: CURRENCY });
      sessionStorage.removeItem(DRAFT_KEY);
      sessionStorage.setItem("somi-flash", t`Đã tạo đơn ${order.code}. Chọn cách thanh toán để bắt đầu tìm shipper.`);
      U.go("payment", { code: order.code });
    } catch (err) {
      btn.disabled = false;
      U.toast(U.errorMessage(err), "danger");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (!U.canSend()) { blockedState(); return; }
    form = $("[data-create-form]");
    setupAddressBlock("pickup");
    setupAddressBlock("delivery");
    setupPackage();
    setupSchedule();
    const restored = restoreDraft();
    if (!restored) {
      const def = S.listAddresses().find((a) => a.isDefault);
      if (def) { $("#pickup-saved").value = def.id; $("#pickup-saved").dispatchEvent(new Event("change")); }
    }
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      if (step < 4) { if (validateStep(step)) showStep(step + 1); }
      else submit();
    });
    $("[data-prev]").addEventListener("click", () => showStep(Math.max(1, step - 1)));
    $("#pkg-note").addEventListener("input", saveDraft);
    $("#schedule-at").addEventListener("input", saveDraft);
    update();
  });
})();
