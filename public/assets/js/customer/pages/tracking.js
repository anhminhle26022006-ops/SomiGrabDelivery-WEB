/* Customer: Tracking. Live position is optional (BR-189 to BR-195): the page keeps working from order status alone. */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  const code = U.param("code");
  let ticker;
  // Amounts belong to the tracked order and use its currency.
  let currency = "VND";
  const money = (amount) => U.money(amount, currency);

  // Where the courier sits on the drawn route (0 = pickup A, 1 = delivery B).
  const PROGRESS = { ASSIGNED: -0.25, PICKING_UP: -0.08, IN_TRANSIT: 0.55, RESCHEDULED: 0.5, ARRIVED: 1, WAITING_FOR_RECIPIENT: 1, DELIVERED: 1, COMPLETED: 1, RETURNING: 0.45, RETURNED: 0, DELIVERY_FAILED: 0.95 };

  function map(o) {
    const sim = S.getSim();
    const located = S.ON_THE_ROAD.includes(o.status);
    const updated = sim.location === "stale" ? Date.now() - 3 * 60000 : Date.now() - 12000;
    const chip = !located ? `<span class="c-map-chip">${icon("map", 14)} ${t("Lộ trình đơn hàng")}</span>`
      : sim.location === "off" ? `<span class="c-map-chip is-off">${icon("alert", 14)} ${t("Không có vị trí trực tiếp, đang theo dõi theo trạng thái đơn")}</span>`
      : sim.location === "stale" ? `<span class="c-map-chip is-stale">${icon("clock", 14)} ${t`Vị trí có thể đã cũ, cập nhật ${Math.round((Date.now() - updated) / 60000)} phút trước`}</span>`
      : `<span class="c-map-chip">${icon("pin", 14)} ${t("Cập nhật")} <b data-loc-age>${t("vài giây")}</b> ${t("trước")}</span>`;
    const minutes = o.eta ? Math.max(1, Math.round((Date.parse(o.eta) - Date.now()) / 60000)) : o.durationMin;
    const eta = ["PICKING_UP", "ASSIGNED", "IN_TRANSIT"].includes(o.status) ? `<div class="c-map-eta"><small>${o.status === "IN_TRANSIT" ? t("Đến nơi sau") : t("Đến lấy hàng sau")}</small><strong>${t`${o.status === "IN_TRANSIT" ? minutes : Math.max(4, Math.round(o.durationMin / 2))} phút`}</strong></div>` : "";
    return `<div class="c-map" role="img" aria-label="${t`Bản đồ minh họa lộ trình từ ${h(o.pickup.area)} đến ${h(o.delivery.area)}`}">
      <svg viewBox="0 0 640 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <rect width="640" height="400" fill="#e9ede6"/>
        <path d="M0 300 C120 290 180 330 300 320 S520 280 640 300 V400 H0Z" fill="#d6e4ea"/>
        <g stroke="#ffffff" stroke-width="14" fill="none" stroke-linecap="round">
          <path d="M-10 110 H660"/><path d="M-10 230 H660"/><path d="M150 -10 V410"/><path d="M360 -10 V410"/><path d="M520 -10 V410"/><path d="M40 -10 L620 410"/>
        </g>
        <g stroke="#ffffff" stroke-width="6" fill="none" opacity=".8"><path d="M-10 170 H660"/><path d="M250 -10 V410"/><path d="M440 -10 V410"/><path d="M-10 50 H660"/></g>
        <g fill="#d9dfd3"><rect x="170" y="125" width="70" height="38" rx="6"/><rect x="380" y="125" width="50" height="38" rx="6"/><rect x="270" y="185" width="80" height="35" rx="6"/><rect x="460" y="60" width="50" height="40" rx="6"/><rect x="60" y="180" width="80" height="40" rx="6"/></g>
        <path id="c-route-path" d="M110 300 C150 230 150 175 250 170 S360 110 440 110 S520 90 548 72" fill="none" stroke="#14284b" stroke-width="5" stroke-linecap="round" stroke-dasharray="${["DELIVERED", "COMPLETED"].includes(o.status) ? "0" : "1 0"}"/>
        <g transform="translate(110 300)"><circle r="15" fill="#fff" stroke="#14284b" stroke-width="3"/><text y="5" text-anchor="middle" font-size="13" font-weight="700" fill="#14284b">A</text></g>
        <g transform="translate(548 72)"><circle r="15" fill="#14284b"/><text y="5" text-anchor="middle" font-size="13" font-weight="700" fill="#fff">B</text></g>
        <g class="c-courier" data-courier ${located && sim.location !== "off" ? "" : 'style="display:none"'}>
          <circle r="20" fill="#14284b" opacity=".14"/><circle r="11" fill="#fff" stroke="#14284b" stroke-width="3"/><circle r="4.5" fill="#14284b"/>
        </g>
      </svg>
      ${chip}${eta}
    </div>`;
  }

  function placeCourier(o) {
    const path = document.getElementById("c-route-path");
    const courier = document.querySelector("[data-courier]");
    if (!path || !courier) return;
    const len = path.getTotalLength();
    const p = PROGRESS[o.status] ?? 0;
    let point;
    if (p < 0) { const start = path.getPointAtLength(0); point = { x: start.x + p * 260, y: start.y + Math.abs(p) * 120 }; } // still heading to pickup
    else point = path.getPointAtLength(Math.min(1, p) * len);
    courier.setAttribute("transform", `translate(${point.x} ${point.y})`);
  }

  function statusPanel(o) {
    const owe = S.outstanding(o);
    if (o.addressIssue) {
      return `<section class="card card-body" style="border-color:var(--c-red)"><h2 style="color:var(--c-red)">${t("Shipper không tìm thấy người nhận")}</h2>
        <p class="c-panel-sub">${t`Tại ${h(o.delivery.detail)}, ${h(o.delivery.area)}. Cập nhật địa chỉ đúng để tiếp tục giao, hoặc cho shipper mang hàng về. Nếu địa chỉ mới xa hơn sẽ có phí phát sinh.`}</p>
        <div class="c-actions"><button type="button" class="btn btn-dark" data-new-address>${t("Cập nhật địa chỉ")}</button><button type="button" class="btn btn-outline-dark" data-decline-address>${t("Hoàn hàng về")}</button></div></section>`;
    }
    if (o.status === "WAITING_FOR_RECIPIENT") {
      const since = [...o.history].reverse().find((e) => e.status === "WAITING_FOR_RECIPIENT");
      return `<section class="card card-body" style="border-color:#e9c27a"><div class="c-panel-head"><h2>${t("Shipper đang chờ người nhận")}</h2><span class="badge rounded-pill c-badge c-tone-amber">${t("Còn")} <span data-wait>10:00</span></span></div>
        <p class="c-panel-sub">${t("Shipper đã gọi người nhận nhưng chưa ai ra nhận. Sau 10 phút chờ, đơn sẽ chuyển sang giao thất bại và hoàn hàng.")}</p>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-dark w-100" data-coming>${t("Người nhận đang ra")}</button>
          <button type="button" class="btn btn-outline-dark w-100" data-reschedule>${t`Hẹn giao lại (+${money(S.PRICING[o.currency].reschedule)})`}</button>
          <button type="button" class="btn btn-outline-dark w-100" data-refuse>${t("Không nhận được, hoàn hàng về")}</button>
        </div></section>` + `<span hidden data-wait-since="${since ? since.at : new Date().toISOString()}"></span>`;
    }
    if (o.status === "RESCHEDULED") return `<section class="card card-body"><h2>${t("Đã hẹn giao lại")}</h2><p class="c-panel-sub" style="margin:0">${t("Shipper sẽ giao lại lúc")} <strong style="color:var(--c-navy)">${U.dateTime(o.rescheduleAt, true)}</strong>${t`. Phí giao lại ${money(S.PRICING[o.currency].reschedule)} được cộng vào đơn.`}</p></section>`;
    if (["DELIVERY_FAILED", "RETURNING", "RETURNED"].includes(o.status)) {
      return `<section class="card card-body" style="border-color:var(--c-red)"><h2>${o.status === "RETURNED" ? t("Hàng đã được trả lại cho bạn") : t("Đang hoàn hàng về cho bạn")}</h2>
        <p class="c-panel-sub">${h(o.failureReason ? tx(o.failureReason) : t("Giao hàng không thành công"))}.</p>
        ${o.fees.return ? `<dl class="c-fees c-fees-compact" style="margin:0 0 14px"><div><dt>${t("Phí hoàn hàng")}</dt><dd>${money(o.fees.return)}</dd></div>${owe > 0 ? `<div class="c-fees-owe"><dt>${t("Còn phải trả")}</dt><dd>${money(owe)}</dd></div>` : ""}</dl>` : `<p class="c-muted" style="font-size:13px;margin:0 0 14px">${t("Sự cố không do bạn nên không tính phí hoàn hàng.")}</p>`}
        ${owe > 0 ? `<a class="btn btn-dark w-100" href="${url("payment", { code: o.code })}">${t`Thanh toán ${money(owe)}`}</a>` : ""}</section>`;
    }
    if (o.status === "DELIVERED" || o.status === "COMPLETED") {
      const at = [...o.history].reverse().find((e) => e.status === "DELIVERED");
      return `<section class="card card-body" style="border-color:#9ed1b5"><h2 style="color:var(--c-green)">${t("Đã giao thành công")}</h2><p class="c-panel-sub">${t`Người nhận đã nhận hàng lúc ${at ? U.dateTime(at.at, true) : ""}.`}</p>
        ${o.review ? `<div>${U.stars(o.review.rating)}</div>` : o.status === "COMPLETED" ? `<a class="btn btn-dark w-100" href="${url("review", { code: o.code })}">${t("Đánh giá shipper")}</a>` : `<p class="c-muted" style="font-size:13px;margin:0">${t("Bạn có thể đánh giá khi đơn chuyển sang hoàn tất.")}</p>`}</section>`;
    }
    return "";
  }

  function headerActions(o) {
    const policy = S.cancelPolicy(o);
    return [
      policy.allowed ? `<button type="button" class="btn btn-outline-dark" data-cancel>${policy.kind === "return" ? t("Hủy và hoàn hàng") : t("Hủy đơn")}</button>` : "",
      `<a class="btn btn-outline-dark" href="${url("report", { code: o.code })}">${t("Báo cáo sự cố")}</a>`
    ].join("");
  }

  function render() {
    clearInterval(ticker);
    const el = $("[data-tracking]");
    const o = S.getOrder(code);
    if (!o) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-gray">${icon("search", 28)}</span><h1>${t("Không tìm thấy đơn hàng")}</h1><p>${t`Mã ${h(code || t("(trống)"))} không tồn tại.`}</p><div class="c-actions"><a class="btn btn-dark" href="${url("orders")}">${t("Về danh sách đơn")}</a></div></section>`;
      return;
    }
    currency = o.currency;
    $("[data-back]").href = url("order", { code: o.code });
    if (o.status === "MATCHING" || o.status === "NO_SHIPPER_FOUND") { U.go("matching", { code: o.code }); return; }
    if (o.status === "PENDING" || o.status === "PAYMENT_PENDING") { U.go("payment", { code: o.code }); return; }
    const titles = {
      ASSIGNED: t("Shipper đã nhận đơn"), PICKING_UP: t("Shipper đang đến lấy hàng"), IN_TRANSIT: t("Kiện hàng đang trên đường giao"),
      ARRIVED: t("Shipper đã đến điểm giao"), WAITING_FOR_RECIPIENT: t("Shipper đang chờ người nhận"), RESCHEDULED: t("Đơn được hẹn giao lại"),
      DELIVERED: t("Đã giao thành công"), COMPLETED: t("Đơn hàng hoàn tất"), DELIVERY_FAILED: t("Giao hàng không thành công"),
      RETURNING: t("Đang hoàn hàng về cho bạn"), RETURNED: t("Đã hoàn hàng"), CANCELLED: t("Đơn hàng đã hủy"), DISPUTED: t("Đơn đang được xử lý khiếu nại")
    };
    const title = titles[o.status] || S.STATUS[o.status].label;
    el.innerHTML = `
      <section class="c-head"><div><h1>${h(title)}</h1><div class="c-head-meta">${U.badge(o.status)}<span>${h(o.code)}</span><span>${h(U.packageLabel(o.pkg.type))} · ${o.pkg.weightKg} kg</span></div></div>
        <div class="c-head-actions c-actions">${headerActions(o)}</div></section>
      <div class="row g-4 align-items-start">
        <div class="col-lg-7 vstack gap-4">
          ${map(o)}
          <section class="card card-body">${U.route(o)}</section>
        </div>
        <div class="col-lg-5 vstack gap-4">
          ${statusPanel(o)}
          ${o.shipper ? `<section class="card card-body"><h2 style="margin-bottom:14px">Shipper</h2>${U.shipperCard(o, { call: S.ON_THE_ROAD.includes(o.status) })}</section>` : ""}
          <section class="card card-body"><h2 style="margin-bottom:16px">${t("Hành trình")}</h2>${U.timeline(o)}</section>
        </div>
      </div>`;
    placeCourier(o);
    bind(o);
    // Live counters: location age and the waiting countdown.
    const ageEl = $("[data-loc-age]");
    const waitEl = $("[data-wait]");
    const waitSince = $("[data-wait-since]");
    let age = 12;
    ticker = window.setInterval(() => {
      if (ageEl) { age = age >= 20 ? 3 : age + 1; ageEl.textContent = t`${age} giây`; }
      if (waitEl && waitSince) {
        const left = Math.max(0, 600 - Math.floor((Date.now() - Date.parse(waitSince.dataset.waitSince || "")) / 1000));
        waitEl.textContent = `${String(Math.floor(left / 60)).padStart(2, "0")}:${String(left % 60).padStart(2, "0")}`;
      }
    }, 1000);
  }

  function bind(o) {
    const on = (sel, fn) => { const b = $(sel); if (b) b.addEventListener("click", fn); };
    on("[data-cancel]", () => U.openCancel(o.code, { onDone: (p) => U.toast(p.kind === "return" ? t("Đã yêu cầu hoàn hàng. Shipper sẽ mang hàng về.") : t("Đã hủy đơn hàng.")) }));
    on("[data-coming]", () => U.toast(t("Đã báo shipper: người nhận đang ra lấy hàng.")));
    on("[data-refuse]", async () => {
      const fee = S.returnFeeOf(o);
      if (await U.confirmBox({ title: t("Hoàn hàng về cho bạn?"), text: t`Đơn sẽ chuyển sang giao thất bại và shipper mang hàng về. Phí hoàn hàng ${money(fee)} sẽ được tính thêm.`, confirmLabel: t("Hoàn hàng về"), danger: true })) {
        try { S.refuseDelivery(o.code); U.toast(t("Shipper đang mang hàng về cho bạn.")); } catch (err) { U.toast(U.errorMessage(err), "danger"); }
      }
    });
    on("[data-reschedule]", () => {
      const min = new Date(Date.now() + 60 * 60000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
      U.modal({
        title: t("Hẹn giao lại"),
        body: `<p class="c-muted">${t`Shipper sẽ quay lại giao vào thời điểm bạn chọn. Phí giao lại ${money(S.PRICING[o.currency].reschedule)}.`}</p>
          <div class="c-field"><label class="form-label" for="re-at">${t("Thời điểm giao lại")}</label><input class="form-control" id="re-at" type="datetime-local" min="${min}" value="${min}"></div>`,
        actions: [
          { label: t("Quay lại"), variant: "btn-outline-dark" },
          { label: t("Xác nhận hẹn"), onClick: ({ close, el }) => {
            const input = $("#re-at", el);
            try { S.reschedule(o.code, new Date(input.value).toISOString()); close(); U.toast(t("Đã hẹn giao lại.")); }
            catch (err) { U.setError(input, U.errorMessage(err)); }
          } }
        ]
      });
    });
    on("[data-decline-address]", async () => {
      if (await U.confirmBox({ title: t("Hoàn hàng về cho bạn?"), text: t("Đơn sẽ chuyển sang giao thất bại do sai địa chỉ và tính phí hoàn hàng."), confirmLabel: t("Hoàn hàng về"), danger: true })) {
        S.declineAddressChange(o.code); U.toast(t("Shipper đang mang hàng về cho bạn."));
      }
    });
    on("[data-new-address]", () => {
      U.modal({
        title: t("Cập nhật địa chỉ giao"),
        body: `<div class="row g-3">
          <div class="c-field col-12"><label class="form-label" for="na-detail">${t("Số nhà, tên đường, phường")}</label><input class="form-control" id="na-detail" value="${h(o.delivery.detail)}"></div>
          <div class="c-field col-md-6"><label class="form-label" for="na-area">${t("Khu vực")}</label><select class="form-select" id="na-area">${S.AREAS.map((a) => `<option ${a.name === o.delivery.area ? "selected" : ""}>${h(a.name)}</option>`).join("")}</select></div>
          <div class="c-field col-md-6"><label class="form-label" for="na-phone">${t("SĐT người nhận")}</label><input class="form-control" id="na-phone" type="tel" value="${h(o.delivery.phone)}"></div>
        </div>
        <dl class="c-fees c-fees-compact" data-na-quote></dl>`,
        actions: [
          { label: t("Quay lại"), variant: "btn-outline-dark" },
          { label: t("Đồng ý và tiếp tục giao"), onClick: ({ close, el }) => {
            const detail = $("#na-detail", el), phone = $("#na-phone", el);
            const detailOk = U.setError(detail, detail.value.trim().length >= 5 ? "" : t("Nhập địa chỉ đầy đủ."));
            const phoneOk = U.setError(phone, U.phoneOk(phone.value) ? "" : t("Số điện thoại chưa đúng."));
            if (!detailOk || !phoneOk) return;
            const res = S.approveAddressChange(o.code, { ...o.delivery, detail: detail.value.trim(), area: $("#na-area", el).value, phone: phone.value.replace(/\s/g, "") });
            close();
            if (res.needsPayment) { sessionStorage.setItem("somi-flash", t("Đã cập nhật địa chỉ. Thanh toán phí phát sinh để shipper tiếp tục giao.")); U.go("payment", { code: o.code }); }
            else U.toast(o.payment.method === "COD" ? t("Đã cập nhật địa chỉ. Phí phát sinh sẽ thu khi giao.") : t("Đã cập nhật địa chỉ, shipper tiếp tục giao."));
          } }
        ],
        onOpen: ({ el }) => {
          const refresh = () => {
            const q = S.quoteAddressChange(o.code, { detail: $("#na-detail", el).value, area: $("#na-area", el).value });
            $("[data-na-quote]", el).innerHTML = `<div><dt>${t("Quãng đường mới")}</dt><dd>${q.newKm} km</dd></div><div><dt>${t("Tăng thêm")}</dt><dd>${q.extraKm} km</dd></div><div class="c-fees-total"><dt>${t("Phí phát sinh")}</dt><dd>${money(q.additionalFee)}</dd></div>`;
          };
          $$("#na-detail, #na-area", el).forEach((i) => i.addEventListener("input", refresh));
          refresh();
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-tracking]"), render, { lines: 4 }));
  document.addEventListener("somi:change", render);
})();
