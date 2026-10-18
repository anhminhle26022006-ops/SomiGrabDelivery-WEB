/* Customer: My reports. Customers follow the status; only Admin changes it (BR-173). */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  const TABS = [["all", t("Tất cả")], ["OPEN", t("Đã tiếp nhận")], ["UNDER_REVIEW", t("Đang xem xét")], ["RESOLVED", t("Đã giải quyết")], ["REJECTED", t("Từ chối")]];
  let tab = "all";
  const typeLabel = U.reportTypeLabel;

  function list(el) {
    const reports = S.listReports();
    const shown = reports.filter((r) => tab === "all" || r.status === tab);
    el.innerHTML = `<section class="c-head"><div><h1>${t("Báo cáo của tôi")}</h1><p>${t("Theo dõi khiếu nại đã gửi và kết luận của SOMI.")}</p></div>
        <div class="c-head-actions"><a class="btn btn-dark" href="${url("report")}">${t("Báo cáo sự cố mới")}</a></div></section>
      <div class="c-toolbar"><div class="nav nav-pills c-tabs" role="tablist">${TABS.map(([k, l]) => `<button type="button" role="tab" data-tab="${k}" aria-selected="${k === tab}" class="nav-link${k === tab ? " active" : ""}">${l} <b>${k === "all" ? reports.length : reports.filter((r) => r.status === k).length}</b></button>`).join("")}</div></div>
      <section class="card card-body">${shown.length ? shown.map((r) => `<a class="c-report-row" href="${url("reports", { id: r.id })}"><div><strong>${h(r.id)} · ${h(typeLabel(r.type))}</strong><small>${t`Đơn ${h(r.orderCode)} · gửi ${U.relTime(r.createdAt).toLowerCase()}`}</small></div>${U.reportBadge(r.status)}${icon("chevron", 16)}</a>`).join("")
        : U.empty({ title: reports.length ? t("Không có báo cáo ở mục này") : t("Bạn chưa gửi báo cáo nào"), text: reports.length ? "" : t("Khi đơn gặp sự cố, bạn có thể báo cáo từ trang chi tiết đơn.") })}</section>`;
    $$("[data-tab]", el).forEach((b) => b.addEventListener("click", () => { tab = (b.dataset.tab || "all"); list(el); }));
  }

  function detail(el, id) {
    const r = S.getReport(id);
    if (!r) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-gray">${icon("search", 28)}</span><h1>${t("Không tìm thấy báo cáo")}</h1><p>${t`Mã ${h(id)} không tồn tại.`}</p><div class="c-actions"><a class="btn btn-dark" href="${url("reports")}">${t("Về danh sách báo cáo")}</a></div></section>`;
      return;
    }
    const steps = ["OPEN", "UNDER_REVIEW", r.status === "REJECTED" ? "REJECTED" : "RESOLVED"];
    const reached = (s) => r.history.find((x) => x.status === s);
    el.innerHTML = `<a class="c-back" href="${url("reports")}">${icon("back", 16)} ${t("Báo cáo của tôi")}</a>
      <section class="c-head"><div><h1>${h(r.id)}</h1><div class="c-head-meta">${U.reportBadge(r.status)}<span>${h(typeLabel(r.type))}</span><span>${t`Gửi lúc ${U.dateTime(r.createdAt, true)}`}</span></div></div>
        <div class="c-head-actions"><a class="btn btn-outline-dark" href="${url("order", { code: r.orderCode })}">${t`Xem đơn ${h(r.orderCode)}`}</a></div></section>
      <div class="row g-4 align-items-start">
        <div class="col-lg-7 vstack gap-4">
          <section class="card card-body"><h2>${t("Nội dung báo cáo")}</h2><p class="c-panel-sub">${t`Về ${r.about === "SHIPPER" ? "shipper" : t("đơn hàng, kiện hàng")}`}</p><p style="margin:0;white-space:pre-line">${h(r.description)}</p>
            ${r.files.length ? `<div class="c-files" style="margin-top:16px">${r.files.map((f) => `<div class="c-file">${icon(f.kind === "image" ? "box" : "report", 18)}<span>${h(f.name)}</span><small>${(f.size / 1024 / 1024).toFixed(1)} MB</small></div>`).join("")}</div>` : ""}</section>
          ${r.adminNote ? `<section class="card card-body"><h2 style="margin-bottom:12px">${t("Kết luận của SOMI")}</h2><div class="c-admin-note">${h(tx(r.adminNote))}</div></section>` : ""}
        </div>
        <div class="col-lg-5"><section class="card card-body"><h2 style="margin-bottom:16px">${t("Tiến trình xử lý")}</h2>
          <ol class="c-timeline">${steps.map((s, i) => { const hit = reached(s); const last = hit && (i === steps.length - 1 || !reached(steps[i + 1])); return `<li class="c-tl-item ${hit ? (last ? "is-current" : "is-done") : "is-next"} ${s === "REJECTED" ? "is-bad" : ""}"><span class="c-tl-dot">${hit && !last ? icon("check", 12) : ""}</span><div><strong>${h(S.REPORT_STATUS[s].label)}</strong>${hit ? `<small>${U.dateTime(hit.at, true)}</small>` : ""}</div></li>`; }).join("")}</ol>
          <p class="c-muted" style="font-size:12px;margin:0">${t("Trạng thái do bộ phận hỗ trợ cập nhật. Bạn sẽ nhận thông báo khi có thay đổi.")}</p>
        </section></div>
      </div>`;
  }

  function render(el) {
    const id = U.param("id");
    if (id) detail(el, id); else list(el);
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-reports]"), render, { lines: 4 }));
  document.addEventListener("somi:change", () => render($("[data-reports]")));
})();
