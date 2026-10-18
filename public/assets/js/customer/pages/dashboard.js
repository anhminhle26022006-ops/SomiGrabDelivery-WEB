/* Customer: Dashboard */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t } = window.SomiI18n;
  const { $, h, icon, money, url } = U;

  const STAGES = [t("Đã nhận"), t("Lấy hàng"), t("Đang giao"), t("Đến nơi"), t("Đã giao")];
  const STAGE_OF = { ASSIGNED: 1, PICKING_UP: 2, IN_TRANSIT: 3, RESCHEDULED: 3, ARRIVED: 4, WAITING_FOR_RECIPIENT: 4, DELIVERED: 5 };

  function greeting() {
    const a = S.getAccount();
    const hour = new Date().getHours();
    const part = hour < 11 ? t("Chào buổi sáng") : hour < 14 ? t("Chào buổi trưa") : hour < 18 ? t("Chào buổi chiều") : t("Chào buổi tối");
    $("[data-greeting]").textContent = `${part}, ${a.fullName.split(" ").pop()}`;
    const d = new Date();
    const weekday = [t("Chủ nhật"), t("Thứ hai"), t("Thứ ba"), t("Thứ tư"), t("Thứ năm"), t("Thứ sáu"), t("Thứ bảy")][d.getDay()];
    $("[data-today]").textContent = t`${weekday}, ${U.dateOnly(d.toISOString())}. Theo dõi đơn đang chạy và những việc cần bạn xử lý.`;
  }

  function liveCard(orders) {
    const holder = $("[data-live]");
    const live = orders.find((o) => S.ON_THE_ROAD.includes(o.status) && o.status !== "RETURNING");
    const matching = orders.find((o) => o.status === "MATCHING");
    if (live) {
      const stage = STAGE_OF[live.status] || 0;
      const minutes = live.eta ? Math.max(1, Math.round((Date.parse(live.eta) - Date.now()) / 60000)) : live.durationMin;
      const etaText = live.status === "WAITING_FOR_RECIPIENT" ? t("Đang chờ người nhận")
        : live.status === "RESCHEDULED" ? t`Giao lại lúc ${U.dateTime(live.rescheduleAt)}`
        : live.status === "ARRIVED" ? t("Shipper đã ở điểm giao") : t`Dự kiến ${U.dateTime(new Date(Date.now() + minutes * 60000).toISOString())}`;
      holder.innerHTML = `<section class="card card-body c-dark c-live">
        <div class="c-live-top"><div>${U.badge(live.status)}<h2 style="margin-top:10px">${t`Đơn ${h(live.code)}`}</h2></div></div>
        <div class="c-live-body">${U.route(live, { contacts: false })}
          <div class="c-eta">${["ARRIVED", "WAITING_FOR_RECIPIENT", "RESCHEDULED"].includes(live.status) ? `<small>${t("Trạng thái")}</small><strong style="font-size:20px;letter-spacing:-.01em">${h(S.STATUS[live.status].label)}</strong>` : `<small>${t("Còn khoảng")}</small><strong>${minutes}<span style="font-size:16px;color:#b7c3d8"> ${t("phút")}</span></strong>`}<span>${h(etaText)}</span></div>
        </div>
        <div class="c-progress" aria-hidden="true">${STAGES.map((_, i) => `<i class="${i < stage ? "on" : ""}"></i>`).join("")}</div>
        <div class="c-progress-labels">${STAGES.map((s, i) => `<span class="${i < stage ? "on" : ""}">${s}</span>`).join("")}</div>
        <div class="c-live-foot">${U.shipperCard(live, { call: false })}<a class="btn btn-light" href="${url("tracking", { code: live.code })}">${t("Theo dõi đơn")}</a></div>
      </section>`;
    } else if (matching) {
      holder.innerHTML = `<section class="card card-body c-dark c-start">
        ${U.badge("MATCHING")}
        <h2>${t`Đang tìm shipper cho ${h(matching.code)}`}</h2>
        <p>${t`${h(matching.pickup.area)} → ${h(matching.delivery.area)}. Bạn sẽ nhận thông báo ngay khi có shipper nhận đơn.`}</p>
        <a class="btn btn-light" href="${url("matching", { code: matching.code })}">${t("Xem tiến trình")}</a>
      </section>`;
    } else {
      holder.innerHTML = `<section class="card card-body c-dark c-start">
        <h2>${t("Bạn chưa có đơn nào đang giao")}</h2>
        <p>${t("Nhập điểm lấy, điểm giao và thông tin kiện hàng. SOMI báo phí trước khi bạn xác nhận.")}</p>
        <a class="btn btn-light" href="${url("create")}" data-send-link>${t("Gửi một kiện hàng")}</a>
      </section>`;
    }
  }

  function todo(orders) {
    const items = [];
    orders.forEach((o) => {
      if (o.status === "PENDING" || o.status === "PAYMENT_PENDING") items.push(["amber", "card", t`Thanh toán đơn ${o.code}`, t("Đơn chỉ được gửi cho shipper sau khi thanh toán hoặc chọn COD."), url("payment", { code: o.code })]);
      if (o.status === "NO_SHIPPER_FOUND") items.push(["red", "alert", t`Chưa có shipper cho ${o.code}`, t("Tìm lại với phạm vi rộng hơn hoặc hủy miễn phí."), url("matching", { code: o.code })]);
      if (o.status === "WAITING_FOR_RECIPIENT") items.push(["amber", "clock", t`Shipper đang chờ người nhận`, t`Đơn ${o.code}: hẹn giao lại hoặc yêu cầu hoàn hàng.`, url("tracking", { code: o.code })]);
      if (o.addressIssue) items.push(["red", "pin", t`Cập nhật địa chỉ giao cho ${o.code}`, t("Shipper không tìm thấy người nhận tại địa chỉ cũ."), url("tracking", { code: o.code })]);
      const owe = S.outstanding(o);
      if (owe > 0) items.push(["red", "wallet", t`Còn ${money(owe, o.currency)} chưa thanh toán`, t`Đơn ${o.code} (${S.STATUS[o.status].label.toLowerCase()}).`, url("payment", { code: o.code })]);
      if (o.status === "COMPLETED" && !o.review && Date.now() - Date.parse(o.updatedAt) < 7 * 864e5) items.push(["blue", "star", t`Đánh giá shipper đơn ${o.code}`, t`${o.shipper ? o.shipper.name : "Shipper"} đã giao xong. Đánh giá giúp shipper tốt được ưu tiên.`, url("review", { code: o.code })]);
    });
    $("[data-todo]").innerHTML = items.length
      ? items.map(([tone, ic, title, text, href]) => `<a href="${href}"><span class="c-todo-icon c-tone-${tone}">${icon(ic, 18)}</span><div><strong>${h(title)}</strong><small>${h(text)}</small></div>${icon("chevron", 16)}</a>`).join("")
      : `<p class="c-muted" style="margin:0;font-size:13px">${t("Không có việc nào cần xử lý. Mọi đơn đang chạy đúng tiến trình.")}</p>`;
  }

  function figures(orders) {
    const start = new Date(); start.setDate(1); start.setHours(0, 0, 0, 0);
    const month = orders.filter((o) => Date.parse(o.createdAt) >= start.getTime());
    const done = month.filter((o) => S.GROUPS.done.includes(o.status)).length;
    // Spending is added up per currency: VND and USD orders are never mixed in one total.
    const spent = {};
    month.forEach((o) => {
      const net = o.payment.paid - (o.payment.refund && o.payment.refund.status === "DONE" ? o.payment.refund.amount : 0);
      spent[o.currency] = (spent[o.currency] || 0) + net;
    });
    const currencies = Object.keys(spent).filter((c) => spent[c] > 0);
    const spentText = currencies.length ? currencies.map((c) => money(spent[c], c)).join(" + ") : money(0);
    $("[data-month-label]").textContent = t`Từ ${U.dateOnly(start.toISOString())} đến hôm nay`;
    $("[data-figures]").innerHTML = `<div><strong>${month.length}</strong><span>${t("Đơn đã tạo")}</span></div><div><strong>${done}</strong><span>${t("Đã giao")}</span></div><div><strong class="c-figure-money">${spentText}</strong><span>${t("Đã chi")}</span></div>`;
  }

  function defaultAddress() {
    const a = S.listAddresses().find((x) => x.isDefault);
    $("[data-default-address]").innerHTML = `<div class="c-panel-head"><h2>${t("Điểm lấy hàng mặc định")}</h2><a class="c-link" href="${url("addresses")}">${t("Sổ địa chỉ")}</a></div>
      ${a ? `<p style="margin:0;color:var(--c-navy);font-weight:600">${h(a.detail)}, ${h(a.area)}</p><p class="c-muted" style="margin:4px 0 0;font-size:13px">${h(t(a.label))} · ${h(a.receiverName)} · ${h(U.prettyPhone(a.receiverPhone))}</p>`
        : `<p class="c-muted" style="margin:0;font-size:13px">${t("Chưa có địa chỉ mặc định. Lưu một địa chỉ để tạo đơn nhanh hơn.")}</p>`}`;
  }

  function render() {
    const orders = S.listOrders();
    greeting();
    U.load($("[data-live]"), () => liveCard(orders), { lines: 2 });
    U.load($("[data-recent]"), (el) => {
      el.innerHTML = orders.length ? orders.slice(0, 5).map(U.orderRow).join("") : U.empty({ title: t("Chưa có đơn hàng"), text: t("Đơn bạn tạo sẽ xuất hiện ở đây.") });
    });
    todo(orders);
    figures(orders);
    defaultAddress();
  }

  document.addEventListener("DOMContentLoaded", render);
  document.addEventListener("somi:change", () => {
    const orders = S.listOrders();
    liveCard(orders); todo(orders); figures(orders);
    $("[data-recent]").innerHTML = orders.slice(0, 5).map(U.orderRow).join("");
  });
})();
