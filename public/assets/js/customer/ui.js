/*
 * SOMI Customer: shared UI building blocks.
 * Pages in ./pages/*.js use these helpers; nothing here touches Shipper or Admin.
 * Text goes through t() and saved messages through tx() (see i18n.js).
 */

function createSomiUI() {
  "use strict";
  const S = window.SomiStore;
  const I18N = window.SomiI18n;
  const { t, tx } = I18N;

  /* ---------- Basics ---------- */
  /** querySelector typed to the element the caller expects (inputs, buttons, ...). */
  function $(sel, root = document) {
    return root.querySelector(sel);
  }
  function $$(sel, root = document) {
    return Array.from(root.querySelectorAll(sel));
  }
  const ESCAPES = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
  const h = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => ESCAPES[c]);
  /** money(amount, currency): amount in the smallest unit; pass order.currency for order amounts. */
  const money = (amount, currency) => I18N.money(amount, currency);
  const { dateTime, dateOnly } = I18N;
  function relTime(isoValue) {
    const diff = Date.now() - new Date(isoValue).getTime();
    const m = Math.round(diff / 60000);
    if (m < 1) return t("Vừa xong");
    if (m < 60) return t`${m} phút trước`;
    const hrs = Math.round(m / 60);
    if (hrs < 24) return t`${hrs} giờ trước`;
    const days = Math.round(hrs / 24);
    if (days === 1) return t("Hôm qua");
    if (days < 7) return t`${days} ngày trước`;
    return dateOnly(isoValue);
  }
  function url(view, params = {}) {
    const q = new URLSearchParams({ page: "customer", view });
    Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== "") q.set(k, String(v)); });
    return `?${q.toString()}`;
  }
  function go(view, params) { window.location.href = url(view, params); }
  function param(name) {
    const value = new URLSearchParams(window.location.search).get(name);
    return value ? value.trim() : "";
  }
  function phoneOk(value) { return /^0[35789]\d{8}$/.test(String(value).replace(/\s/g, "")); }
  function emailOk(value) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value).trim()); }
  function prettyPhone(value) {
    const d = String(value).replace(/\D/g, "");
    return d.length === 10 ? `${d.slice(0, 4)} ${d.slice(4, 7)} ${d.slice(7)}` : value;
  }
  function errorMessage(err) {
    return err instanceof Error ? err.message : String(err);
  }

  /* ---------- Icons (inline SVG, stroke = currentColor) ---------- */
  const PATHS = {
    home: '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
    box: '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
    send: '<path d="M4 12h12"/><path d="m11 6 6 6-6 6"/><path d="M20 5v14"/>',
    list: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    bell: '<path d="M18 9a6 6 0 0 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 15 18 9"/><path d="M10.5 20a1.7 1.7 0 0 0 3 0"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    pin: '<path d="M19 10c0 5.5-7 11-7 11s-7-5.5-7-11a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    flag: '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    star: '<path d="m12 3 2.8 5.8 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.7l6.2-.9Z"/>',
    check: '<path d="m5 12 4.5 4.5L19 7"/>',
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    alert: '<path d="M12 3 2 20h20Z"/><path d="M12 10v4M12 17h.01"/>',
    card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
    cash: '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
    back: '<path d="M15 5 8 12l7 7"/>',
    chevron: '<path d="m9 5 7 7-7 7"/>',
    truck: '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
    refresh: '<path d="M20 11a8 8 0 0 0-14-5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14 5l2-2"/><path d="M20 20v-4h-4"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
    trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
    shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    logout: '<path d="M10 5H5v14h5"/><path d="m15 8 4 4-4 4M9 12h10"/>',
    report: '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
    sliders: '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    wallet: '<path d="M4 7h15a1 1 0 0 1 1 1v11H5a1 1 0 0 1-1-1z"/><path d="M4 7l12-4v4"/><circle cx="16" cy="13" r="1.3"/>',
    map: '<path d="m9 4-6 3v13l6-3 6 3 6-3V4l-6 3z"/><path d="M9 4v13M15 7v13"/>'
  };

  function icon(name, size = 18) {
    return `<svg class="c-icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${PATHS[name]}</svg>`;
  }

  /* ---------- Status pieces ---------- */
  function badge(status) {
    const meta = S.STATUS[status];
    return `<span class="badge rounded-pill c-badge c-tone-${meta.tone}">${h(meta.label)}</span>`;
  }
  function reportBadge(status) {
    const meta = S.REPORT_STATUS[status];
    return `<span class="badge rounded-pill c-badge c-tone-${meta.tone}">${h(meta.label)}</span>`;
  }
  function packageLabel(type) {
    const found = S.PACKAGE_TYPES.find((x) => x.value === type);
    return found ? found.label : type;
  }

  function reportTypeLabel(type) {
    const found = S.REPORT_TYPES.find((x) => x.value === type);
    return found ? found.label : type;
  }

  /* The waybill route: pickup A on top, delivery B below, joined by a rail. */
  function route(order, { contacts = true } = {}) {
    const stop = (letter, title, p) => `
      <div class="c-stop">
        <span class="c-stop-mark c-stop-${letter.toLowerCase()}">${letter}</span>
        <div>
          <small>${title}</small>
          <strong>${h(p.detail)}, ${h(p.area)}</strong>
          ${contacts ? `<span>${h(p.name)} · ${h(prettyPhone(p.phone))}</span>` : ""}
        </div>
      </div>`;
    return `<div class="c-route">${stop("A", t("Lấy hàng"), order.pickup)}${stop("B", t("Giao đến"), order.delivery)}</div>`;
  }

  const MAIN_PATH_ONLINE = ["PENDING", "PAID", "MATCHING", "ASSIGNED", "PICKING_UP", "IN_TRANSIT", "ARRIVED", "DELIVERED", "COMPLETED"];
  const MAIN_PATH_COD = ["PENDING", "MATCHING", "ASSIGNED", "PICKING_UP", "IN_TRANSIT", "ARRIVED", "DELIVERED", "COMPLETED"];
  const ACTOR = { customer: t("Bạn"), shipper: "Shipper", system: t("Hệ thống"), admin: "SOMI" };

  /* Timeline = what happened (history) + what is still expected next. */
  function timeline(order) {
    const done = order.history.map((entry, i) => {
      const last = i === order.history.length - 1;
      const bad = S.STATUS[entry.status].tone === "red" || entry.status === "CANCELLED";
      const sameStatusNote = i > 0 && order.history[i - 1].status === entry.status;
      const title = sameStatusNote ? t("Cập nhật") : S.STATUS[entry.status].step;
      return `<li class="c-tl-item ${last ? "is-current" : "is-done"} ${bad ? "is-bad" : ""}">
        <span class="c-tl-dot">${last ? "" : icon("check", 12)}</span>
        <div><strong>${h(title)}</strong><small>${dateTime(entry.at)} · ${ACTOR[entry.actor] || t("Hệ thống")}</small>${entry.note ? `<p>${h(tx(entry.note))}</p>` : ""}</div>
      </li>`;
    });
    const path = order.payment.method === "COD" ? MAIN_PATH_COD : MAIN_PATH_ONLINE;
    let upcoming = [];
    const at = path.indexOf(order.status);
    if (at >= 0) upcoming = path.slice(at + 1);
    else if (order.status === "PAYMENT_PENDING") upcoming = MAIN_PATH_ONLINE.slice(1);
    else if (order.status === "WAITING_FOR_RECIPIENT" || order.status === "RESCHEDULED") upcoming = ["DELIVERED", "COMPLETED"];
    else if (order.status === "RETURNING") upcoming = ["RETURNED"];
    const next = upcoming.map((s) => `<li class="c-tl-item is-next"><span class="c-tl-dot"></span><div><strong>${h(S.STATUS[s].step)}</strong></div></li>`);
    return `<ol class="c-timeline">${done.join("")}${next.join("")}</ol>`;
  }

  /* Fee breakdown for an existing order. */
  function feeTable(order) {
    const f = order.fees;
    const money = (amount) => I18N.money(amount, order.currency);
    const rows = [];
    if (order.status === "CANCELLED") {
      rows.push([t("Phí giao hàng"), `<s>${money(f.delivery)}</s> ${t("Không tính")}`]);
      rows.push([t("Phí hủy đơn"), money(f.cancellation)]);
    } else {
      (order.feeLines || []).forEach((line) => rows.push([tx(line.label), money(line.amount)]));
      if (f.additional) rows.push([t("Phí phát sinh (đổi địa chỉ)"), money(f.additional)]);
      if (f.reschedule) rows.push([t("Phí giao lại"), money(f.reschedule)]);
      if (f.cancellation) rows.push([t("Phí hủy đơn"), money(f.cancellation)]);
      if (f.return) rows.push([t("Phí hoàn hàng"), money(f.return)]);
      if (f.waived) rows.push([t("Miễn phí giao (sự cố từ phía shipper)"), `−${money(f.waived)}`]);
    }
    const total = S.charges(order);
    const paid = order.payment.paid;
    const owe = S.outstanding(order);
    const refund = order.payment.refund;
    return `<dl class="c-fees">
      ${rows.map(([k, v]) => `<div><dt>${h(k)}</dt><dd>${v}</dd></div>`).join("")}
      <div class="c-fees-total"><dt>${t("Tổng phí")}</dt><dd>${money(total)}</dd></div>
      <div><dt>${t("Đã thanh toán")}</dt><dd>${money(paid)}</dd></div>
      ${owe > 0 ? `<div class="c-fees-owe"><dt>${t("Còn phải trả")}</dt><dd>${money(owe)}</dd></div>` : ""}
      ${refund ? `<div class="c-fees-refund"><dt>${refund.status === "DONE" ? t("Hoàn lại (đã hoàn)") : t("Hoàn lại (đang xử lý)")}</dt><dd>${money(refund.amount)}</dd></div>` : ""}
    </dl>
    ${order.currency === "USD" ? `<p class="c-fx-note">${t("Đơn tính theo bảng giá USD lúc đặt. Giá đã chốt không đổi khi bạn đổi tiền tệ hiển thị.")}</p>` : ""}`;
  }

  function paymentSummary(order) {
    const p = order.payment;
    const method = p.method ? S.PAYMENT_METHODS[p.method].label : t("Chưa chọn");
    const labels = { PENDING: p.method === "COD" ? t("Thu khi giao hàng") : t("Chưa thanh toán"), PAID: t("Đã thanh toán"), FAILED: t("Thất bại"), REFUNDED: t("Đã hoàn tiền") };
    const last = [...p.attempts].reverse().find((a) => a.status === "SUCCESS");
    return `<div class="c-kv">
      <div><span>${t("Phương thức")}</span><strong>${h(method)}</strong></div>
      <div><span>${t("Trạng thái")}</span><strong>${h(labels[p.status])}</strong></div>
      ${last ? `<div><span>${t("Mã giao dịch")}</span><strong class="c-num">${h(last.txn)}</strong></div>` : ""}
      ${p.paidAt ? `<div><span>${t("Thời gian")}</span><strong>${dateTime(p.paidAt)}</strong></div>` : ""}
    </div>`;
  }

  function initials(name) {
    return name.split(" ").slice(-2).map((p) => p[0]).join("");
  }

  function shipperCard(order, { call = true } = {}) {
    const s = order.shipper;
    if (!s) return "";
    return `<div class="c-shipper">
      <span class="c-avatar">${h(initials(s.name))}</span>
      <div class="c-shipper-main"><strong>${h(s.name)}</strong><small>${icon("star", 13)} ${t`${s.rating.toFixed(1)} · ${s.trips} chuyến · ${h(s.vehicle)}`}</small></div>
      <span class="c-plate">${h(s.plate)}</span>
      ${call ? `<a class="btn btn-outline-dark btn-sm" href="tel:${h(s.phone.replace(/\s/g, ""))}">${icon("phone", 16)} ${t("Gọi")}</a>` : ""}
    </div>`;
  }

  function orderRow(o) {
    return `<a class="c-order-row" href="${url("order", { code: o.code })}">
      <div class="c-order-code"><strong>${h(o.code)}</strong><small>${relTime(o.createdAt)}</small></div>
      <div class="c-order-route"><span>${h(o.pickup.area)} → ${h(o.delivery.area)}</span><small>${h(packageLabel(o.pkg.type))} · ${h(o.delivery.name)}</small></div>
      ${badge(o.status)}
      <span class="c-order-amount">${money(S.charges(o), o.currency)}</span>
      ${icon("chevron", 18)}
    </a>`;
  }

  function stars(rating) {
    return `<span class="c-stars" aria-label="${t`${rating} trên 5 sao`}">${[1, 2, 3, 4, 5].map((i) => `<span class="${i <= rating ? "on" : ""}">${icon("star", 15)}</span>`).join("")}</span>`;
  }

  function empty({ title, text = "", action = "" }) {
    return `<div class="c-empty">${icon("box", 28)}<strong>${h(title)}</strong>${text ? `<p>${h(text)}</p>` : ""}${action}</div>`;
  }

  /* ---------- Loading / error wrapper ---------- */
  // Bootstrap placeholders while data "loads" (the demo waits a moment like an API call would).
  function skeleton(lines = 3) {
    return `<div class="placeholder-glow c-skeleton" aria-busy="true" aria-label="${t("Đang tải")}">${Array.from({ length: lines }, () => '<span class="placeholder col-12"></span>').join("")}</div>`;
  }
  function load(container, render, { lines = 3 } = {}) {
    if (!container) return;
    container.innerHTML = skeleton(lines);
    setTimeout(() => {
      if (S.getSim().loadError) {
        container.innerHTML = `<div class="c-empty c-error">${icon("alert", 28)}<strong>${t("Không tải được dữ liệu")}</strong><p>${t("Kết nối đến máy chủ bị gián đoạn. Kiểm tra mạng rồi thử lại.")}</p><button class="btn btn-dark" type="button" data-retry>${icon("refresh", 16)} ${t("Thử lại")}</button></div>`;
        $("[data-retry]", container).addEventListener("click", () => load(container, render, { lines }));
        return;
      }
      render(container);
    }, 380);
  }

  /* ---------- Bootstrap components (JS loaded from the CDN in index.php) ----------
   * If the CDN cannot be reached, a tiny fallback still opens and closes modals,
   * toasts and dropdowns so the demo keeps working offline. */
  const BS = window.bootstrap || null;
  function fallbackModal(el) {
    let backdrop = null;
    const fire = (name) => el.dispatchEvent(new Event(name));
    return {
      show() {
        backdrop = document.createElement("div");
        backdrop.className = "modal-backdrop show";
        document.body.append(backdrop);
        el.style.display = "block"; el.classList.add("show"); document.body.classList.add("modal-open");
        el.addEventListener("click", (e) => { if (e.target === el) this.hide(); });
        el.querySelectorAll('[data-bs-dismiss="modal"]').forEach((b) => b.addEventListener("click", () => this.hide()));
        document.addEventListener("keydown", this._key = (e) => { if (e.key === "Escape") this.hide(); });
        fire("shown.bs.modal");
      },
      hide() {
        el.classList.remove("show"); el.style.display = "none"; document.body.classList.remove("modal-open");
        if (backdrop) backdrop.remove();
        document.removeEventListener("keydown", this._key);
        fire("hidden.bs.modal");
      }
    };
  }
  if (!BS) {
    document.addEventListener("click", (e) => {
      const toggle = e.target.closest('[data-bs-toggle="dropdown"]');
      document.querySelectorAll(".dropdown-menu.show").forEach((m) => {
        if (!toggle || m !== toggle.nextElementSibling) { if (!m.contains(e.target)) m.classList.remove("show"); }
      });
      if (toggle) toggle.nextElementSibling.classList.toggle("show");
    });
  }

  /* ---------- Toast (Bootstrap toast) ---------- */
  const TOAST_TONE = { dark: "text-bg-dark", danger: "text-bg-danger", success: "text-bg-success" };
  function toast(message, tone = "dark") {
    let box = document.getElementById("c-toasts");
    if (!box) {
      box = document.createElement("div");
      box.id = "c-toasts";
      box.className = "toast-container position-fixed bottom-0 start-50 translate-middle-x p-3";
      box.setAttribute("aria-live", "polite");
      document.body.appendChild(box);
    }
    const item = document.createElement("div");
    item.className = `toast align-items-center border-0 c-toast ${TOAST_TONE[tone] || TOAST_TONE.dark}`;
    item.setAttribute("role", "status");
    item.innerHTML = `<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="${t("Đóng")}"></button></div>`;
    $(".toast-body", item).textContent = message;
    box.appendChild(item);
    if (BS) {
      item.addEventListener("hidden.bs.toast", () => item.remove());
      BS.Toast.getOrCreateInstance(item, { delay: 3200 }).show();
    } else {
      item.classList.add("show");
      setTimeout(() => item.remove(), 3200);
    }
  }

  /* ---------- Modal (Bootstrap modal) ----------
   * actions: [{ label, variant: "btn-dark" | "btn-outline-dark" | "btn-danger", disabled, onClick({ close, el }) }]
   * onDismiss runs when the user closes it with X, Esc or the backdrop (not when an action calls close). */
  let modalSeq = 0;
  function modal({ title, body, actions = [], size = "", onOpen, onDismiss }) {
    const id = `c-modal-${++modalSeq}`;
    const wrap = document.createElement("div");
    wrap.className = "modal fade";
    wrap.tabIndex = -1;
    wrap.setAttribute("aria-labelledby", `${id}-title`);
    wrap.setAttribute("aria-modal", "true");
    wrap.setAttribute("role", "dialog");
    wrap.innerHTML = `<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable ${size === "c-modal-wide" ? "modal-lg" : ""}">
      <div class="modal-content c-modal">
        <div class="modal-header"><h2 class="modal-title" id="${id}-title">${h(title)}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${t("Đóng")}"></button></div>
        <div class="modal-body c-modal-body">${body}</div>
        ${actions.length ? `<div class="modal-footer">${actions.map((a, i) => `<button type="button" class="btn ${a.variant || "btn-dark"}" data-action="${i}" ${a.disabled ? "disabled" : ""}>${a.label}</button>`).join("")}</div>` : ""}
      </div>
    </div>`;
    document.body.appendChild(wrap);
    const instance = BS ? new BS.Modal(wrap) : fallbackModal(wrap);
    let byAction = false;
    // Bootstrap ignores hide() while the open animation runs; remember it and hide once shown.
    let shown = false;
    let pendingHide = false;
    const hide = () => { if (shown) instance.hide(); else pendingHide = true; };
    wrap.addEventListener("hidden.bs.modal", () => {
      if (BS) instance.dispose();
      wrap.remove();
      if (!byAction && onDismiss) onDismiss();
    });
    const plainClose = () => { byAction = true; hide(); };
    actions.forEach((a, i) => $(`[data-action="${i}"]`, wrap).addEventListener("click", () => {
      if (a.onClick) a.onClick({ close: plainClose, el: wrap });
      else hide();
    }));
    wrap.addEventListener("shown.bs.modal", () => {
      shown = true;
      if (pendingHide) { instance.hide(); return; }
      const focusable = $("input:not([type=radio]):not([type=checkbox]), select, textarea", $(".modal-body", wrap));
      if (focusable) focusable.focus();
    });
    const api = { close: plainClose, el: wrap, button: (i) => $(`[data-action="${i}"]`, wrap) };
    if (onOpen) onOpen(api);
    instance.show();
    return api;
  }

  function confirmBox({ title, text, confirmLabel = t("Xác nhận"), danger = false }) {
    return new Promise((resolve) => {
      modal({
        title,
        body: `<p class="c-muted">${h(text)}</p>`,
        onDismiss: () => resolve(false),
        actions: [
          { label: t("Quay lại"), variant: "btn-outline-dark", onClick: ({ close }) => { close(); resolve(false); } },
          { label: confirmLabel, variant: danger ? "btn-danger" : "btn-dark", onClick: ({ close }) => { close(); resolve(true); } }
        ]
      });
    });
  }

  /* ---------- Form errors ---------- */
  /** Shows (or clears, with an empty message) the error under a field. Returns true when valid. */
  function setError(input, message) {
    const field = (input.closest(".c-field") || input.parentElement);
    let msg = field.querySelector(".c-field-error");
    if (!msg) {
      msg = document.createElement("div");
      msg.className = "invalid-feedback c-field-error";
      msg.id = `${input.id || input.name}-error`;
      field.appendChild(msg);
    }
    msg.textContent = message || "";
    // Bootstrap validation style: red border on the control, message below it.
    input.classList.toggle("is-invalid", Boolean(message));
    msg.classList.toggle("d-block", Boolean(message));
    input.toggleAttribute("aria-invalid", Boolean(message));
    if (message) input.setAttribute("aria-describedby", msg.id);
    field.classList.toggle("has-error", Boolean(message));
    return !message;
  }

  /* ---------- Cancel flow (used by order, matching, tracking pages) ----------
   * Reasons are saved in Vietnamese (S.CANCEL_REASONS) and translated when shown. */
  const OTHER_REASON = "Lý do khác";

  function openCancel(code, { onDone } = {}) {
    const order = S.getOrder(code);
    if (!order) { toast(t("Không tìm thấy đơn hàng.")); return; }
    const policy = S.cancelPolicy(order);
    if (!policy.allowed) { toast(policy.reason); return; }
    const money = (amount) => I18N.money(amount, order.currency);
    const intro = {
      free: t("Chưa có shipper nhận đơn nên bạn được hủy miễn phí."),
      fee: t("Shipper đã nhận đơn và đang trên đường. Hủy lúc này sẽ tính phí hủy để bù công di chuyển."),
      return: t("Shipper đã lấy hàng. Đơn sẽ không kết thúc ngay mà shipper mang hàng quay về trả bạn, kèm phí hủy và phí hoàn hàng.")
    }[policy.kind];
    const lines = [];
    if (policy.cancellationFee) lines.push([t("Phí hủy đơn"), money(policy.cancellationFee)]);
    if (policy.kind === "return") {
      lines.unshift([t("Phí giao hàng (đã chạy)"), money(order.fees.delivery + order.fees.additional + order.fees.reschedule)]);
      lines.push([t("Phí hoàn hàng"), money(policy.returnFee)]);
    }
    if (!lines.length) lines.push([t("Phí hủy đơn"), money(0)]);
    lines.push([t("Bạn đã thanh toán"), money(order.payment.paid)]);
    const result = policy.refund > 0
      ? `<div class="c-fees-refund"><dt>${t("Được hoàn lại")}</dt><dd>${money(policy.refund)}</dd></div>`
      : policy.owe > 0 ? `<div class="c-fees-owe"><dt>${t("Cần thanh toán thêm")}</dt><dd>${money(policy.owe)}</dd></div>`
      : `<div><dt>${t("Hoàn lại")}</dt><dd>${t("Không có khoản thanh toán nào")}</dd></div>`;
    const body = `
      <p class="c-muted">${intro}</p>
      <dl class="c-fees c-fees-compact">${lines.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join("")}${result}</dl>
      <fieldset class="c-reasons"><legend>${t("Lý do hủy")}</legend>
        ${S.CANCEL_REASONS.map((r) => `<label class="form-check c-radio"><input class="form-check-input" type="radio" name="cancel-reason" value="${h(r)}"><span class="form-check-label">${h(t(r))}</span></label>`).join("")}
      </fieldset>
      <div class="c-field" hidden data-other><label class="form-label" for="cancel-other">${t("Mô tả lý do")}</label><textarea class="form-control" id="cancel-other" rows="2" maxlength="200" placeholder="${t("Cho SOMI biết thêm để cải thiện dịch vụ")}"></textarea></div>`;
    modal({
      title: t`Hủy đơn ${order.code}?`,
      body,
      actions: [
        { label: t("Giữ đơn hàng"), variant: "btn-outline-dark" },
        {
          label: policy.kind === "return" ? t("Hủy và hoàn hàng") : t("Xác nhận hủy"),
          variant: "btn-danger",
          disabled: true,
          onClick: ({ close, el }) => {
            const picked = $("input[name=cancel-reason]:checked", el);
            let reason = picked ? picked.value : "";
            if (reason === OTHER_REASON) {
              const other = $("#cancel-other", el);
              if (!other.value.trim()) { setError(other, t("Vui lòng mô tả lý do.")); other.focus(); return; }
              reason = other.value.trim();
            }
            try {
              S.cancelOrder(code, reason);
              close();
              if (onDone) onDone(policy);
              else go("order", { code, cancelled: 1 });
            } catch (err) { toast(errorMessage(err), "danger"); }
          }
        }
      ],
      onOpen: ({ el, button }) => {
        $$("input[name=cancel-reason]", el).forEach((radio) => radio.addEventListener("change", () => {
          button(1).disabled = false;
          $("[data-other]", el).hidden = radio.value !== OTHER_REASON;
        }));
      }
    });
  }

  /* ---------- Shell: header counters, language menu, demo panel (avatar menu = Bootstrap dropdown) ---------- */
  function refreshCounters() {
    const count = S.unreadCount();
    $$("[data-unread]").forEach((el) => { el.textContent = count > 9 ? "9+" : String(count); el.hidden = count === 0; });
    const account = S.getAccount();
    $$("[data-account-name]").forEach((el) => { el.textContent = account.fullName; });
    $$("[data-account-initials]").forEach((el) => { el.textContent = initials(account.fullName); });
  }

  /* Language and currency switch in the header. Language is shared with the
   * public pages; both choices reload the page so every text and price redraws. */
  function setupLocaleMenu() {
    const btn = $("[data-locale-btn]");
    if (!btn) return;
    $("[data-locale-current]", btn).textContent = `${I18N.LANGUAGES[I18N.lang].short} · ${I18N.currency}`;
    $$("[data-fx-note]").forEach((el) => {
      el.textContent = t`Bảng giá USD tham chiếu tỷ giá 1 USD = ${money(S.USD_RATE, "VND")}. USD chỉ thanh toán bằng thẻ quốc tế.`;
    });
    // Header menu and the Account page share the same buttons.
    $$("[data-set-lang]").forEach((b) => {
      const on = b.dataset.setLang === I18N.lang;
      b.classList.toggle("active", on);
      b.setAttribute("aria-pressed", String(on));
      b.addEventListener("click", () => { if (!on) I18N.setLanguage(b.dataset.setLang); });
    });
    $$("[data-set-currency]").forEach((b) => {
      const on = b.dataset.setCurrency === I18N.currency;
      b.classList.toggle("active", on);
      b.setAttribute("aria-pressed", String(on));
      b.addEventListener("click", () => { if (!on) I18N.setCurrency(b.dataset.setCurrency); });
    });
  }

  /* Demo panel: stands in for the Shipper app, payment gateway and Admin while
   * there is no backend. Clearly labelled so nobody mistakes it for a feature. */
  function setupDemoPanel() {
    const panel = $("[data-demo]");
    if (!panel) return;
    const toggle = $("[data-demo-toggle]", panel);
    const body = $("[data-demo-body]", panel);
    toggle.addEventListener("click", () => {
      body.hidden = !body.hidden;
      toggle.setAttribute("aria-expanded", String(!body.hidden));
      if (!body.hidden) renderDemo();
    });

    function renderDemo() {
      const account = S.getAccount();
      const sim = S.getSim();
      const accountState = account.status === "SUSPENDED" ? "suspended" : account.phoneVerified ? "active" : "unverified";
      const code = param("code");
      const order = code ? S.getOrder(code) : null;
      const view = document.body.dataset.cView;
      const reportId = param("id");
      const report = view === "reports" && reportId ? S.getReport(reportId) : null;
      const orderActions = order ? S.simulationsFor(order) : [];
      const locations = [["live", t("Đang cập nhật")], ["stale", t("Cũ hơn 2 phút")], ["off", t("Không có GPS")]];
      const accountStates = [["active", t("Bình thường")], ["unverified", t("Chưa xác thực SĐT")], ["suspended", t("Bị khóa")]];
      body.innerHTML = `
        <p class="c-demo-note">${t("Bảng này thay cho app Shipper, cổng thanh toán và Admin khi chưa có backend. Không thuộc giao diện thật.")}</p>
        ${order ? `<section><h4>${t`Đơn ${h(order.code)} · ${h(S.STATUS[order.status].label)}`}</h4>
          ${orderActions.length ? `<div class="c-demo-actions">${orderActions.map((a) => `<button type="button" data-sim="${a.action}">${h(a.label)}</button>`).join("")}</div>` : `<p class="c-demo-note">${t("Trạng thái này chờ thao tác từ phía bạn.")}</p>`}
        </section>` : ""}
        ${report && (report.status === "OPEN" || report.status === "UNDER_REVIEW") ? `<section><h4>${t`Báo cáo ${h(report.id)}`}</h4><div class="c-demo-actions">
          ${report.status === "OPEN" ? `<button type="button" data-report="review">${t("Admin tiếp nhận")}</button>` : `<button type="button" data-report="resolve">${t("Admin giải quyết")}</button><button type="button" data-report="reject">${t("Admin từ chối")}</button>`}
        </div></section>` : ""}
        ${view === "tracking" ? `<section><h4>${t("Vị trí shipper")}</h4><div class="c-demo-seg">
          ${locations.map(([v, l]) => `<button type="button" data-loc="${v}" class="${sim.location === v ? "on" : ""}">${l}</button>`).join("")}
        </div></section>` : ""}
        <section><h4>${t("Tài khoản")}</h4><div class="c-demo-seg">
          ${accountStates.map(([v, l]) => `<button type="button" data-acc="${v}" class="${accountState === v ? "on" : ""}">${l}</button>`).join("")}
        </div></section>
        <section><label class="c-demo-check"><input type="checkbox" data-load-error ${sim.loadError ? "checked" : ""}> ${t("Mô phỏng lỗi tải dữ liệu")}</label></section>
        <section><button type="button" class="c-demo-reset" data-reset>${t("Khôi phục dữ liệu mẫu")}</button></section>`;

      $$("[data-sim]", body).forEach((b) => b.addEventListener("click", () => {
        try { S.simulate(code, b.dataset.sim || ""); toast(t`Mô phỏng: ${b.textContent}`); } catch (err) { toast(errorMessage(err), "danger"); }
      }));
      $$("[data-report]", body).forEach((b) => b.addEventListener("click", () => {
        S.simulateReportReview(reportId, b.dataset.report);
      }));
      $$("[data-loc]", body).forEach((b) => b.addEventListener("click", () => S.setSim("location", b.dataset.loc)));
      $$("[data-acc]", body).forEach((b) => b.addEventListener("click", () => {
        S.setAccountState(b.dataset.acc);
        window.location.reload();
      }));
      $("[data-load-error]", body).addEventListener("change", (e) => {
        S.setSim("loadError", e.target.checked);
        window.location.reload();
      });
      $("[data-reset]", body).addEventListener("click", async () => {
        if (await confirmBox({ title: t("Khôi phục dữ liệu mẫu?"), text: t("Mọi đơn, địa chỉ và thông báo bạn tạo trong bản demo sẽ bị xóa."), confirmLabel: t("Khôi phục"), danger: true })) {
          S.reset();
          go("dashboard");
        }
      });
    }
    document.addEventListener("somi:change", () => { if (!body.hidden) renderDemo(); });
  }

  /* Banner shown on every page when the account cannot create orders (BR-05, BR-20). */
  function accountBanner() {
    const holder = $("[data-account-banner]");
    if (!holder) return;
    const a = S.getAccount();
    if (a.status === "SUSPENDED") {
      holder.innerHTML = `<div class="alert alert-danger c-banner">${icon("shield")}<div><strong>${t("Tài khoản đang tạm khóa")}</strong><p>${t("Bạn vẫn xem được đơn cũ nhưng chưa thể gửi hàng. Liên hệ hỗ trợ SOMI qua 1900 0000 để mở khóa.")}</p></div></div>`;
    } else if (!a.phoneVerified) {
      holder.innerHTML = `<div class="alert alert-warning c-banner">${icon("phone")}<div><strong>${t("Xác thực số điện thoại để gửi hàng")}</strong><p>${t("Shipper cần liên lạc với bạn qua số đã xác thực.")}</p></div><a class="btn btn-dark btn-sm" href="${url("account", { verify: 1 })}">${t("Xác thực ngay")}</a></div>`;
    } else holder.innerHTML = "";
    // Disable every "send a package" entry point while blocked.
    const blocked = a.status === "SUSPENDED" || !a.phoneVerified;
    $$("[data-send-link]").forEach((el) => {
      el.classList.toggle("is-disabled", blocked);
      if (blocked) {
        el.setAttribute("aria-disabled", "true");
        el.title = a.status === "SUSPENDED" ? t("Tài khoản đang bị khóa") : t("Cần xác thực số điện thoại");
      } else { el.removeAttribute("aria-disabled"); el.removeAttribute("title"); }
    });
  }
  function canSend() {
    const a = S.getAccount();
    return a.status === "ACTIVE" && a.phoneVerified;
  }

  document.addEventListener("DOMContentLoaded", () => {
    refreshCounters();
    setupLocaleMenu();
    setupDemoPanel();
    accountBanner();
    try {
      const flash = sessionStorage.getItem("somi-flash");
      if (flash) { sessionStorage.removeItem("somi-flash"); setTimeout(() => toast(flash, "success"), 450); }
    } catch { /* ignore */ }
    document.addEventListener("click", (e) => {
      const blocked = e.target.closest("[data-send-link].is-disabled");
      if (blocked) { e.preventDefault(); toast(blocked.title); }
    });
  });
  document.addEventListener("somi:change", () => { refreshCounters(); accountBanner(); });

  return {
    $, $$, h, money, dateTime, dateOnly, relTime, url, go, param, phoneOk, emailOk, prettyPhone, errorMessage,
    icon, badge, reportBadge, packageLabel, reportTypeLabel, route, timeline, feeTable, paymentSummary, shipperCard, stars, orderRow,
    empty, skeleton, load, toast, modal, confirmBox, setError, openCancel, canSend, refreshCounters
  };
}

window.SomiUI = createSomiUI();
