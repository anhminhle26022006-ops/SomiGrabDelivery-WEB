/* Customer: Matching. The shipper must accept; the system never assigns on its own (BR-78, BR-79). */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx, timeOnly } = window.SomiI18n;
  const { $, h, icon, url } = U;
  const code = U.param("code");
  let timer;

  const pad = (n) => String(n).padStart(2, "0");

  function log(o) {
    if (!o.matchLog.length) return "";
    return `<section class="card card-body"><h3>${t("Tiến trình")}</h3><ol class="c-log">${[...o.matchLog].reverse().map((l) => `<li><time>${timeOnly(l.at)}</time><span>${h(tx(l.text))}</span></li>`).join("")}</ol></section>`;
  }

  function startedAt(o) {
    const last = [...o.history].reverse().find((e) => e.status === "MATCHING");
    return last ? Date.parse(last.at) : Date.now();
  }

  function render() {
    clearInterval(timer);
    const el = $("[data-matching]");
    const o = S.getOrder(code);
    if (!o) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-gray">${icon("search", 28)}</span><h1>${t("Không tìm thấy đơn hàng")}</h1><p>${t`Mã ${h(code || t("(trống)"))} không tồn tại.`}</p><div class="c-actions"><a class="btn btn-dark" href="${url("orders")}">${t("Về danh sách đơn")}</a></div></section>`;
      return;
    }
    $("[data-back]").href = url("order", { code: o.code });

    if (o.status === "MATCHING") {
      el.innerHTML = `<section class="c-state">
        <div class="c-radar" aria-hidden="true"><i></i><i></i><i></i><span>${icon("truck", 30)}</span></div>
        <h1>${t("Đang tìm shipper gần bạn")}</h1>
        <p>${t`Yêu cầu đã gửi tới các shipper đã xác minh quanh ${h(o.pickup.area)}. Shipper chủ động nhận đơn, thường trong vòng 1 đến 3 phút.`}</p>
        <p style="margin:-8px auto 18px">${t("Đã chờ")} <span class="c-elapsed" data-elapsed>00:00</span></p>
        <div class="c-actions"><a class="btn btn-outline-dark" href="${url("order", { code: o.code })}">${t("Xem chi tiết đơn")}</a><button type="button" class="btn btn-outline-dark" data-cancel>${t("Hủy đơn miễn phí")}</button></div>
        <p class="c-demo-note" style="margin-top:18px">${t("Bản demo: mở \"Mô phỏng demo\" ở góc màn hình để cho shipper nhận, từ chối hoặc không phản hồi.")}</p>
        <section class="card card-body"><div class="c-panel-head"><h3 style="margin:0">${h(o.code)}</h3>${U.badge(o.status)}</div>${U.route(o, { contacts: false })}</section>
        ${log(o)}
      </section>`;
      const since = startedAt(o);
      const tick = () => { const s = Math.max(0, Math.floor((Date.now() - since) / 1000)); $("[data-elapsed]").textContent = `${pad(Math.floor(s / 60))}:${pad(s % 60)}`; };
      tick(); timer = window.setInterval(tick, 1000);
      $("[data-cancel]").addEventListener("click", () => U.openCancel(o.code));
      return;
    }

    if (o.status === "NO_SHIPPER_FOUND") {
      el.innerHTML = `<section class="c-state">
        <span class="c-state-icon c-tone-red">${icon("alert", 30)}</span>
        <h1>${t("Chưa có shipper nhận đơn")}</h1>
        <p>${t("SOMI đã gửi yêu cầu và mở rộng phạm vi tìm kiếm nhưng chưa có shipper phù hợp. Bạn có thể tìm lại hoặc hủy đơn mà không mất phí.")}</p>
        <div class="c-actions"><button type="button" class="btn btn-dark" data-retry>${icon("refresh", 16)} ${t("Tìm lại shipper")}</button><button type="button" class="btn btn-outline-dark" data-cancel>${t("Hủy đơn miễn phí")}</button></div>
        ${log(o)}
      </section>`;
      $("[data-retry]").addEventListener("click", () => { S.retryMatching(o.code); U.toast(t("Đang tìm lại với phạm vi rộng hơn.")); });
      $("[data-cancel]").addEventListener("click", () => U.openCancel(o.code));
      return;
    }

    if (o.status === "ASSIGNED" && o.shipper) {
      const minutes = Math.max(5, Math.round(o.durationMin / 2));
      el.innerHTML = `<section class="c-state">
        <span class="c-state-icon c-tone-green">${icon("check", 30)}</span>
        <h1>${t`${h(o.shipper.name)} đã nhận đơn`}</h1>
        <p>${t`Shipper đang đến ${h(o.pickup.detail)}, dự kiến khoảng ${minutes} phút. Hãy chuẩn bị sẵn kiện hàng.`}</p>
        <div class="c-actions"><a class="btn btn-dark" href="${url("tracking", { code: o.code })}">${t("Theo dõi đơn")}</a><a class="btn btn-outline-dark" href="${url("order", { code: o.code })}">${t("Xem chi tiết đơn")}</a></div>
        <section class="card card-body">${U.shipperCard(o)}</section>
        ${log(o)}
      </section>`;
      return;
    }

    if (o.status === "CANCELLED") { U.go("order", { code: o.code }); return; }
    // Any other status has moved past matching.
    U.go(S.ON_THE_ROAD.includes(o.status) ? "tracking" : "order", { code: o.code });
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-matching]"), render, { lines: 3 }));
  document.addEventListener("somi:change", render);
})();
