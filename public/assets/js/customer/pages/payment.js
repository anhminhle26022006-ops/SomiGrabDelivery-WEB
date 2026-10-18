/* Customer: Payment. COD or e-wallet through a simulated gateway (PaymentService stand-in). */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  const code = U.param("code");
  let root;
  let busy = false;
  // Every amount on this page belongs to one order, shown in that order's currency.
  let currency = "VND";
  const money = (amount) => U.money(amount, currency);

  const LOGO = { COD: ["cod", "COD"], MOMO: ["momo", "MoMo"], VNPAY: ["vnpay", "VN"], ZALOPAY: ["zalopay", "Zalo"], CARD: ["card", "VISA"] };
  const logo = (m) => `<span class="c-pay-logo c-logo-${LOGO[m][0]}" aria-hidden="true">${LOGO[m][1]}</span>`;

  function summary(o, purpose) {
    const due = S.payableAmount(o);
    const lines = purpose === "order"
      ? (o.feeLines || []).map((l) => `<div><dt>${h(tx(l.label))}</dt><dd>${money(l.amount)}</dd></div>`).join("")
      : `<div><dt>${t("Tổng phí của đơn")}</dt><dd>${money(S.charges(o))}</dd></div><div><dt>${t("Đã thanh toán")}</dt><dd>${money(o.payment.paid)}</dd></div>`;
    return `<aside class="card card-body c-dark">
      <h2>${purpose === "order" ? t("Tóm tắt đơn") : t("Khoản còn phải trả")}</h2>
      <p style="margin:2px 0 18px;color:#b7c3d8;font-size:13px">${h(o.code)} · ${h(U.packageLabel(o.pkg.type))}</p>
      ${U.route(o, { contacts: false })}
      <dl class="c-fees" style="margin-top:20px">${lines}<div class="c-fees-total"><dt>${purpose === "order" ? t("Cần thanh toán") : t("Còn phải trả")}</dt><dd>${money(due)}</dd></div></dl>
      ${purpose === "balance" ? `<p class="c-quote-note">${icon("alert", 16)}<span>${o.fees.return ? t`Gồm phí hoàn hàng ${money(o.fees.return)}. ` : ""}${o.fees.additional ? t`Gồm phí phát sinh ${money(o.fees.additional)}. ` : ""}${o.fees.cancellation ? t`Gồm phí hủy ${money(o.fees.cancellation)}.` : ""}</span></p>` : ""}
    </aside>`;
  }

  function chooser(o) {
    // render() only calls chooser when there is something to pay
    const purpose = S.paymentPurpose(o) || "order";
    const due = S.payableAmount(o);
    const failed = [...o.payment.attempts].reverse().find((a) => a.purpose === purpose && (a.status === "FAILED" || a.status === "CANCELLED"));
    const left = S.remainingPaymentAttempts(o);
    // COD is only possible before an online payment was started (PENDING -> MATCHING).
    // Methods are limited to the ones that can collect the order currency (USD: international card only).
    const codAllowed = purpose === "order" && o.status === "PENDING";
    const methods = S.methodsFor(o.currency).filter((key) => key !== "COD" || codAllowed).map((key) => [key, S.PAYMENT_METHODS[key]]);
    const fallback = methods.some(([k]) => k === "MOMO") ? "MOMO" : methods[0][0];
    const preselect = o.payment.method && methods.some(([k]) => k === o.payment.method) ? o.payment.method : fallback;
    const desc = {
      COD: t("Người nhận trả phí giao hàng bằng tiền mặt cho shipper. Không cần thanh toán trước."),
      MOMO: t("Mở app MoMo để xác nhận."),
      VNPAY: t("Quét mã QR bằng app ngân hàng."),
      ZALOPAY: t("Mở app ZaloPay để xác nhận."),
      CARD: t("Visa, Mastercard hoặc JCB. Nhận cả VND và USD.")
    };
    root.innerHTML = `
      <section class="c-head"><div><h1>${purpose === "order" ? t("Thanh toán đơn hàng") : t("Thanh toán khoản còn lại")}</h1>
        <p>${purpose === "order" ? t("Chọn cách thanh toán. SOMI chỉ bắt đầu tìm shipper sau bước này.") : t("Khoản này phát sinh trong quá trình giao. Thanh toán để hoàn tất đơn.")}</p></div></section>
      ${failed ? `<div class="alert alert-danger c-banner">${icon("alert")}<div><strong>${t("Lần thanh toán trước chưa thành công")}</strong><p>${h(tx(failed.reason))}. ${purpose === "order" ? t`Bạn còn ${left} lần thử trước khi đơn bị hủy tự động.` : t("Bạn có thể thử lại hoặc chọn ví khác.")}</p></div></div>` : ""}
      <div class="row g-4 align-items-start">
        <section class="col-lg-7"><div class="card card-body">
          <h2>${t("Phương thức thanh toán")}</h2>
          <p class="c-panel-sub">${t("Số tiền:")} <strong style="color:var(--c-navy)">${money(due)}</strong></p>
          <div class="c-pay-methods" role="radiogroup" aria-label="${t("Phương thức thanh toán")}">
            ${methods.map(([key, m]) => `<label class="c-tile"><input type="radio" name="method" value="${key}" ${key === preselect ? "checked" : ""}>${logo(key)}<span><strong>${h(m.label)}</strong><small>${desc[key]}</small></span><span class="c-radio-dot" aria-hidden="true"></span></label>`).join("")}
          </div>
          ${o.currency === "USD" ? `<p class="c-muted" style="font-size:12px;margin:12px 0 0">${t("Đơn tính bằng USD nên chỉ thanh toán được bằng thẻ quốc tế. COD và ví điện tử chỉ nhận VND.")}</p>` : ""}
          ${purpose === "order" && !codAllowed && o.currency === "VND" ? `<p class="c-muted" style="font-size:12px;margin:12px 0 0">${t("Đơn đã bắt đầu thanh toán trực tuyến nên không đổi sang COD được. Muốn trả tiền mặt, hãy hủy đơn này (miễn phí) và tạo lại.")}</p>` : ""}
          <button type="button" class="btn btn-dark w-100" style="margin-top:20px" data-pay></button>
          <p class="c-muted" style="font-size:12px;margin:12px 0 0;display:flex;gap:8px">${icon("shield", 16)}<span>${t("SOMI không lưu thông tin thẻ hay ví. Giao dịch được xác thực bằng chữ ký từ cổng thanh toán.")}</span></p>
          ${purpose === "order" ? `<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--c-line)"><button type="button" class="c-link" data-cancel-order>${t("Không muốn gửi nữa? Hủy đơn miễn phí")}</button></div>` : ""}
        </div></section>
        <div class="col-lg-5 c-sticky">${summary(o, purpose)}</div>
      </div>`;
    const label = () => {
      const m = $("input[name=method]:checked", root).value;
      $("[data-pay]", root).textContent = m === "COD" ? t("Xác nhận thanh toán khi giao") : t`Thanh toán ${money(due)} qua ${S.PAYMENT_METHODS[m].short}`;
    };
    $$("input[name=method]", root).forEach((r) => r.addEventListener("change", label));
    label();
    $("[data-pay]", root).addEventListener("click", () => pay(o.code));
    const cancel = $("[data-cancel-order]", root);
    if (cancel) cancel.addEventListener("click", () => U.openCancel(o.code));
  }

  function pay(orderCode) {
    if (busy) return; // guard against double clicks
    busy = true;
    const btn = $("[data-pay]", root);
    btn.disabled = true;
    const method = $("input[name=method]:checked", root).value;
    try {
      if (method === "COD") {
        S.chooseCod(orderCode);
        sessionStorage.setItem("somi-flash", t("Đã xác nhận thanh toán khi giao. SOMI đang tìm shipper."));
        U.go("matching", { code: orderCode });
        return;
      }
      const txn = S.beginOnlinePayment(orderCode, method);
      gateway(orderCode, txn);
    } catch (err) {
      U.toast(U.errorMessage(err), "danger");
      busy = false; btn.disabled = false;
    }
  }

  /* Mock of the provider's page. In production this is a redirect to MoMo/VNPAY/ZaloPay
     and the result arrives through a signed callback (BR-99 to BR-105). */
  function gateway(orderCode, txn) {
    const method = txn.method || "MOMO";
    const provider = S.PAYMENT_METHODS[method].label;
    U.modal({
      title: t`Cổng ${provider} (mô phỏng)`,
      // Closing the window without choosing behaves like abandoning the gateway.
      onDismiss: () => finish(orderCode, txn, "cancel"),
      body: `<div class="c-gateway">
        ${logo(method)}
        <div class="c-gateway-amount">${money(txn.amount)}</div>
        <p class="c-muted" style="margin:0">${t("Thanh toán cho SOMI Delivery")}</p>
        <div class="c-kv"><div><span>${t("Mã đơn")}</span><strong>${h(orderCode)}</strong></div><div><span>${t("Mã giao dịch")}</span><strong class="c-num">${h(txn.txn)}</strong></div><div><span>${t("Nội dung")}</span><strong>${txn.purpose === "order" ? t("Phí giao hàng") : t("Phí phát sinh")}</strong></div></div>
        <p class="c-demo-note">${t("Đây là màn hình giả lập phản hồi của cổng thanh toán. Chọn kết quả để kiểm tra từng nhánh.")}</p>
      </div>`,
      actions: [
        { label: t("Hủy giao dịch"), variant: "btn-outline-dark", onClick: ({ close }) => { close(); finish(orderCode, txn, "cancel"); } },
        { label: t("Từ chối"), variant: "btn-danger", onClick: ({ close }) => { close(); finish(orderCode, txn, "decline"); } },
        { label: t("Duyệt thanh toán"), variant: "btn-dark", onClick: ({ close }) => { close(); finish(orderCode, txn, "approve"); } }
      ]
    });
  }

  function finish(orderCode, txn, outcome) {
    const { order, duplicate } = S.resolveOnlinePayment(orderCode, txn.txn, outcome);
    busy = false;
    if (duplicate) return;
    if (outcome === "approve") {
      success(order, txn);
    } else if (order.status === "CANCELLED") {
      cancelledState(order);
    } else {
      chooser(order);
      U.toast(outcome === "cancel" ? t("Bạn đã hủy giao dịch.") : t("Thanh toán bị từ chối."), "danger");
    }
  }

  function success(order, txn) {
    const next = txn.purpose === "order" ? ["matching", t("Đang chuyển sang bước tìm shipper")] : S.ON_THE_ROAD.includes(order.status) ? ["tracking", t("Đang quay lại màn theo dõi đơn")] : ["order", t("Đang quay lại chi tiết đơn")];
    root.innerHTML = `<section class="c-state">
      <span class="c-state-icon c-tone-green">${icon("check", 30)}</span>
      <h1>${t("Thanh toán thành công")}</h1>
      <p>${t`${money(txn.amount)} cho đơn ${h(order.code)} qua ${h(S.PAYMENT_METHODS[txn.method || "MOMO"].label)}. ${next[1]}...`}</p>
      <div class="c-actions"><a class="btn btn-dark" href="${url(next[0], { code: order.code })}">${t("Tiếp tục ngay")}</a></div>
      <section class="card card-body"><div class="c-kv"><div><span>${t("Mã giao dịch")}</span><strong class="c-num">${h(txn.txn)}</strong></div><div><span>${t("Thời gian")}</span><strong>${U.dateTime(order.payment.paidAt, true)}</strong></div><div><span>${t("Trạng thái đơn")}</span><strong>${h(S.STATUS[order.status].label)}</strong></div></div></section>
    </section>`;
    setTimeout(() => U.go(next[0], { code: order.code }), 2500);
  }

  function cancelledState(order) {
    root.innerHTML = `<section class="c-state">
      <span class="c-state-icon c-tone-red">${icon("x", 30)}</span>
      <h1>${t`Đơn ${h(order.code)} đã bị hủy`}</h1>
      <p>${t`${h(order.cancel ? tx(order.cancel.reason) : t("Thanh toán không thành công"))}. Bạn chưa bị trừ tiền. Bạn có thể tạo lại đơn với cùng thông tin.`}</p>
      <div class="c-actions"><a class="btn btn-dark" href="${url("order", { code: order.code })}">${t("Xem đơn đã hủy")}</a><a class="btn btn-outline-dark" href="${url("create")}" data-send-link>${t("Tạo đơn mới")}</a></div>
    </section>`;
  }

  function render(el) {
    root = el;
    const o = S.getOrder(code);
    if (o) currency = o.currency;
    $("[data-back]").href = o ? url("order", { code: o.code }) : url("orders");
    if (!o) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-gray">${icon("search", 28)}</span><h1>${t("Không tìm thấy đơn hàng")}</h1><p>${t`Mã ${h(code || t("(trống)"))} không tồn tại.`}</p><div class="c-actions"><a class="btn btn-dark" href="${url("orders")}">${t("Về danh sách đơn")}</a></div></section>`;
      return;
    }
    // Resume an unfinished gateway session after a reload instead of creating a second transaction.
    const pending = o.payment.attempts.find((a) => a.status === "PROCESSING");
    if (pending) { chooser(o); gateway(o.code, pending); return; }
    if (o.status === "CANCELLED" && o.payment.status === "FAILED") { cancelledState(o); return; }
    if (!S.paymentPurpose(o)) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-green">${icon("check", 28)}</span><h1>${t("Đơn này không còn khoản nào cần thanh toán")}</h1>
        <p>${o.payment.method === "COD" && S.GROUPS.active.includes(o.status) ? t("Đơn dùng hình thức thanh toán khi giao. Shipper sẽ thu tiền từ người nhận.") : t`Đã thanh toán ${money(o.payment.paid)} cho đơn ${h(o.code)}.`}</p>
        <div class="c-actions"><a class="btn btn-dark" href="${url("order", { code: o.code })}">${t("Xem chi tiết đơn")}</a></div></section>`;
      return;
    }
    chooser(o);
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-payment]"), render, { lines: 4 }));
})();
