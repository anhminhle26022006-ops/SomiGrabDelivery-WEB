/* Customer: Order detail. Buttons follow the order status (Business Rules 110 to 167). */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  // Order amounts are shown in the currency the order was created with.
  const money = (amount, o) => U.money(amount, o.currency);
  const code = U.param("code");

  function notFound(el) {
    el.innerHTML = `<section class="c-state c-notfound"><span class="c-state-icon c-tone-gray">${icon("search", 28)}</span>
      <h1>${t("Không tìm thấy đơn hàng")}</h1><p>${t`Mã ${h(code || t("(trống)"))} không tồn tại hoặc không thuộc tài khoản của bạn.`}</p>
      <div class="c-actions"><a class="btn btn-dark" href="${url("orders")}">${t("Về danh sách đơn")}</a></div></section>`;
  }

  function actions(o) {
    const list = [];
    const owe = S.outstanding(o);
    const policy = S.cancelPolicy(o);
    const btn = (label, cls, attrs) => `<${attrs.href ? "a" : "button type=\"button\""} class="btn ${cls}" ${Object.entries(attrs).map(([k, v]) => `${k}="${h(v)}"`).join(" ")}>${label}</${attrs.href ? "a" : "button"}>`;
    if (o.status === "PENDING" || o.status === "PAYMENT_PENDING") list.push(btn(t("Thanh toán"), "btn-dark", { href: url("payment", { code: o.code }) }));
    if (owe > 0) list.push(btn(t`Thanh toán ${money(owe, o)}`, "btn-dark", { href: url("payment", { code: o.code }) }));
    if (["PAID", "MATCHING"].includes(o.status)) list.push(btn(t("Xem tiến trình tìm shipper"), "btn-dark", { href: url("matching", { code: o.code }) }));
    if (o.status === "NO_SHIPPER_FOUND") list.push(btn(t("Tìm lại shipper"), "btn-dark", { "data-retry": "1" }));
    if (S.ON_THE_ROAD.includes(o.status)) list.push(btn(t("Theo dõi đơn"), owe > 0 ? "btn-outline-dark" : "btn-dark", { href: url("tracking", { code: o.code }) }));
    if (o.status === "COMPLETED" && !o.review) list.push(btn(t("Đánh giá shipper"), "btn-dark", { href: url("review", { code: o.code }) }));
    if (policy.allowed) list.push(btn(policy.kind === "return" ? t("Hủy và hoàn hàng") : t("Hủy đơn"), "btn-outline-dark", { "data-cancel": "1" }));
    if (o.status !== "PENDING") list.push(btn(t("Báo cáo sự cố"), "btn-outline-dark", { href: url("report", { code: o.code }) }));
    if (["COMPLETED", "CANCELLED", "RETURNED"].includes(o.status)) list.push(btn(t("Gửi lại đơn tương tự"), "btn-outline-dark", { "data-reorder": "1" }));
    return list.join("");
  }

  function banners(o) {
    const out = [];
    if (o.status === "CANCELLED" && o.cancel) {
      const refund = o.payment.refund;
      const who = o.cancel.by === "customer" ? t("Bạn đã hủy đơn") : t("Hệ thống đã hủy đơn");
      const owe = S.outstanding(o);
      out.push(`<div class="alert alert-${owe > 0 ? "danger" : "primary"} c-banner">${icon("x")}<div><strong>${t`${who} lúc ${U.dateTime(o.cancel.at)}`}</strong><p>${t`Lý do: ${h(tx(o.cancel.reason))}. ${refund ? t`Hoàn ${money(refund.amount, o)} về ${h(o.payment.method ? S.PAYMENT_METHODS[o.payment.method].label : t("tài khoản"))}: ${refund.status === "DONE" ? t("đã hoàn xong") : t("đang xử lý, thường mất 1 đến 3 ngày làm việc")}.` : o.payment.paid ? "" : owe > 0 ? t`Phí hủy ${money(owe, o)} cần được thanh toán.` : t("Đơn chưa thanh toán nên không có khoản hoàn tiền.")}`}</p></div></div>`);
    }
    if (o.failureReason && ["DELIVERY_FAILED", "RETURNING", "RETURNED", "DISPUTED"].includes(o.status)) {
      out.push(`<div class="alert alert-danger c-banner">${icon("alert")}<div><strong>${t("Giao hàng không thành công")}</strong><p>${h(tx(o.failureReason))}.${o.status === "RETURNING" ? t(" Shipper đang mang hàng về trả bạn.") : o.status === "RETURNED" ? t(" Hàng đã được trả lại cho bạn.") : ""}</p></div></div>`);
    }
    if (o.addressIssue) out.push(`<div class="alert alert-danger c-banner">${icon("pin")}<div><strong>${t("Shipper không tìm thấy người nhận")}</strong><p>${t("Cập nhật địa chỉ giao mới hoặc chọn hoàn hàng.")}</p></div><a class="btn btn-dark btn-sm" href="${url("tracking", { code: o.code })}">${t("Xử lý ngay")}</a></div>`);
    if (o.status === "RESCHEDULED") out.push(`<div class="alert alert-warning c-banner">${icon("clock")}<div><strong>${t`Hẹn giao lại lúc ${U.dateTime(o.rescheduleAt, true)}`}</strong><p>${t`Shipper sẽ giao lại đúng thời điểm này. Phí giao lại ${money(S.PRICING[o.currency].reschedule, o)}.`}</p></div></div>`);
    if (o.status === "DISPUTED") out.push(`<div class="alert alert-warning c-banner">${icon("shield")}<div><strong>${t("Đơn đang được SOMI xem xét khiếu nại")}</strong><p>${t("Phí và trạng thái đơn được giữ nguyên cho đến khi có kết luận.")}</p></div></div>`);
    if (o.schedule && o.schedule.mode === "later" && ["PENDING", "PAYMENT_PENDING", "PAID", "MATCHING"].includes(o.status)) out.push(`<div class="alert alert-primary c-banner">${icon("clock")}<div><strong>${t`Hẹn lấy hàng lúc ${U.dateTime(o.schedule.at, true)}`}</strong></div></div>`);
    return out.join("");
  }

  function render(el) {
    const o = S.getOrder(code);
    if (!o) { notFound(el); return; }
    const reports = o.reportIds.map((id) => S.getReport(id)).filter((r) => r !== null);
    el.innerHTML = `
      <section class="c-head">
        <div>
          <h1>${h(o.code)}</h1>
          <div class="c-head-meta">${U.badge(o.status)}<span>${t`Tạo lúc ${U.dateTime(o.createdAt, true)}`}</span></div>
        </div>
        <div class="c-head-actions c-actions">${actions(o)}</div>
      </section>
      ${banners(o)}
      <div class="row g-4 align-items-start">
        <div class="col-lg-7 vstack gap-4">
          <section class="card card-body">
            <div class="c-panel-head"><h2>${t("Lộ trình")}</h2><span class="c-chip">${icon("map", 14)} ${t`${o.distanceKm} km · khoảng ${o.durationMin} phút`}</span></div>
            ${U.route(o)}
          </section>
          <section class="card card-body"><h2 style="margin-bottom:16px">${t("Hành trình đơn hàng")}</h2>${U.timeline(o)}</section>
          <section class="card card-body"><h2 style="margin-bottom:14px">${t("Kiện hàng")}</h2>
            <dl class="c-pkg">
              <div><dt>${t("Loại")}</dt><dd>${h(U.packageLabel(o.pkg.type))}</dd></div>
              <div><dt>${t("Khối lượng, số kiện")}</dt><dd>${t`${o.pkg.weightKg} kg · ${o.pkg.quantity} kiện`}</dd></div>
              <div class="c-span-2"><dt>${t("Mô tả")}</dt><dd>${h(o.pkg.description)}</dd></div>
              ${o.pkg.note ? `<div class="c-span-2"><dt>${t("Ghi chú cho shipper")}</dt><dd>${h(o.pkg.note)}</dd></div>` : ""}
            </dl>
          </section>
        </div>
        <div class="col-lg-5 vstack gap-4">
          <section class="card card-body"><h2 style="margin-bottom:14px">${t("Chi phí")}</h2>${U.feeTable(o)}</section>
          <section class="card card-body"><h2 style="margin-bottom:14px">${t("Thanh toán")}</h2>${U.paymentSummary(o)}</section>
          ${o.shipper ? `<section class="card card-body"><h2 style="margin-bottom:14px">Shipper</h2>${U.shipperCard(o, { call: S.ON_THE_ROAD.includes(o.status) })}</section>` : ""}
          ${o.review ? `<section class="card card-body"><h2>${t("Đánh giá của bạn")}</h2><div style="margin:8px 0">${U.stars(o.review.rating)}</div>${o.review.tags.length ? `<div class="c-tags">${o.review.tags.map((tag) => `<span class="c-chip">${h(t(tag))}</span>`).join("")}</div>` : ""}${o.review.comment ? `<p style="margin:10px 0 0">${h(o.review.comment)}</p>` : ""}<p class="c-muted" style="margin:8px 0 0;font-size:12px">${U.dateTime(o.review.at, true)}</p></section>` : ""}
          ${reports.length ? `<section class="card card-body"><h2 style="margin-bottom:6px">${t("Báo cáo liên quan")}</h2>${reports.map((r) => `<a class="c-report-row" href="${url("reports", { id: r.id })}"><div><strong>${h(r.id)} · ${h(U.reportTypeLabel(r.type))}</strong><small>${U.dateTime(r.createdAt, true)}</small></div>${U.reportBadge(r.status)}${icon("chevron", 16)}</a>`).join("")}</section>` : ""}
        </div>
      </div>`;

    $$("[data-cancel]", el).forEach((b) => b.addEventListener("click", () => U.openCancel(o.code, {
      onDone: (policy) => U.toast(policy.kind === "return" ? t("Đã gửi yêu cầu hủy. Shipper sẽ mang hàng về trả bạn.") : policy.refund > 0 ? t`Đã hủy đơn. ${money(policy.refund, o)} đang được hoàn về.` : t("Đã hủy đơn hàng."))
    })));
    $$("[data-retry]", el).forEach((b) => b.addEventListener("click", () => { S.retryMatching(o.code); U.go("matching", { code: o.code }); }));
    $$("[data-reorder]", el).forEach((b) => b.addEventListener("click", () => reorder(o)));
  }

  /* Prefill the create form with this order's route and package. */
  function reorder(o) {
    const draft = {
      fields: {
        "pickup-name": o.pickup.name, "pickup-phone": o.pickup.phone, "pickup-detail": o.pickup.detail, "pickup-area": o.pickup.area,
        "delivery-name": o.delivery.name, "delivery-phone": o.delivery.phone, "delivery-detail": o.delivery.detail, "delivery-area": o.delivery.area,
        "pkg-description": o.pkg.description, "pkg-weight": String(o.pkg.weightKg), "pkg-quantity": String(o.pkg.quantity), "pkg-note": o.pkg.note
      },
      radios: { type: o.pkg.type, schedule: "now" },
      saved: { pickup: o.pickup.addressId || "", delivery: o.delivery.addressId || "" }
    };
    sessionStorage.setItem("somi-customer-draft", JSON.stringify(draft));
    U.go("create");
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-order]"), render, { lines: 4 }));
  document.addEventListener("somi:change", () => render($("[data-order]")));
})();
