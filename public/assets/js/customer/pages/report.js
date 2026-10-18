/* Customer: Report a problem (BR-168 to BR-176). Damaged or lost packages open a dispute. */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  const MAX_FILES = 3;
  const MAX_SIZE = 10 * 1024 * 1024;
  const OK_TYPES = ["image/png", "image/jpeg", "application/pdf"];
  const files = [];

  function render(el) {
    const orders = S.reportableOrders();
    const pre = U.param("code");
    if (pre) $("[data-back]").href = url("order", { code: pre });
    if (!orders.length) {
      el.innerHTML = `<section class="card card-body">${U.empty({ title: t("Chưa có đơn nào để báo cáo"), text: t("Báo cáo chỉ gửi được cho đơn đã thanh toán hoặc đã gửi cho shipper.") })}</section>`;
      return;
    }
    el.innerHTML = `<div class="row g-4 align-items-start">
      <div class="col-lg-7"><form class="card card-body" data-form novalidate>
        <div class="c-field"><label class="form-label" for="r-order">${t("Đơn hàng")}</label>
          <select class="form-select" id="r-order">${orders.map((o) => `<option value="${o.code}" ${o.code === pre ? "selected" : ""}>${h(o.code)} · ${h(o.pickup.area)} → ${h(o.delivery.area)} · ${h(S.STATUS[o.status].label)}</option>`).join("")}</select></div>
        <fieldset class="c-field" style="border:0;padding:0;margin:18px 0 0"><legend class="form-label c-label" style="margin-bottom:8px">${t("Bạn muốn báo về")}</legend>
          <div class="c-tiles">
            <label class="c-tile"><input type="radio" name="about" value="ORDER" checked><strong>${t("Đơn hàng, kiện hàng")}</strong><small>${t("Hàng hư, mất, sai địa chỉ, phí")}</small></label>
            <label class="c-tile"><input type="radio" name="about" value="SHIPPER"><strong>Shipper</strong><small>${t("Thái độ, giao trễ, không liên lạc")}</small></label>
          </div></fieldset>
        <div class="c-field" style="margin-top:18px"><label class="form-label" for="r-type">${t("Loại sự cố")}</label><select class="form-select" id="r-type"><option value="">${t("Chọn loại sự cố")}</option>${S.REPORT_TYPES.map((x) => `<option value="${x.value}">${h(x.label)}</option>`).join("")}</select></div>
        <div class="alert alert-warning c-banner" data-dispute-note hidden style="margin:12px 0 0">${icon("shield")}<div><p>${t("Với hàng hư hỏng hoặc thất lạc khi đơn đang giao, giao thất bại hoặc hoàn hàng, đơn sẽ chuyển sang trạng thái khiếu nại cho đến khi SOMI có kết luận.")}</p></div></div>
        <div class="c-field" style="margin-top:18px">
          <div class="c-field-row"><label class="form-label" for="r-desc">${t("Mô tả chi tiết")}</label><span class="c-counter" data-counter>0/1000</span></div>
          <textarea class="form-control" id="r-desc" maxlength="1000" rows="5" placeholder="${t("Chuyện gì đã xảy ra, lúc nào, bạn mong SOMI hỗ trợ thế nào")}"></textarea>
        </div>
        <div class="c-field" style="margin-top:18px">
          <span class="form-label c-label">${t("Bằng chứng (không bắt buộc)")}</span>
          <label class="c-drop" style="position:relative">${icon("upload", 24)}<strong>${t("Chọn ảnh hoặc tệp PDF")}</strong><span>${t`PNG, JPG hoặc PDF, tối đa ${MAX_FILES} tệp, mỗi tệp không quá 10 MB`}</span>
            <input type="file" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" multiple data-file></label>
          <small class="c-field-error" data-file-error></small>
          <div class="c-files" data-files></div>
        </div>
        <div class="c-form-actions"><a class="btn btn-outline-dark" href="${pre ? url("order", { code: pre }) : url("reports")}">${t("Hủy")}</a><button type="submit" class="btn btn-dark">${t("Gửi báo cáo")}</button></div>
      </form></div>
      <div class="col-lg-5 c-sticky"><aside class="card card-body">
        <h2>${t("Quy trình xử lý")}</h2>
        <ol class="c-timeline" style="margin-top:14px">
          <li class="c-tl-item is-next"><span class="c-tl-dot"></span><div><strong>${t("Đã tiếp nhận")}</strong><small>${t("Báo cáo được gửi đến bộ phận hỗ trợ")}</small></div></li>
          <li class="c-tl-item is-next"><span class="c-tl-dot"></span><div><strong>${t("Đang xem xét")}</strong><small>${t("SOMI đối chiếu lịch sử đơn, liên hệ shipper nếu cần")}</small></div></li>
          <li class="c-tl-item is-next"><span class="c-tl-dot"></span><div><strong>${t("Có kết luận")}</strong><small>${t("Giải quyết hoặc từ chối, kèm giải thích")}</small></div></li>
        </ol>
        <p class="c-muted" style="font-size:13px;margin:0">${t("Bạn sẽ nhận thông báo mỗi khi trạng thái báo cáo thay đổi.")}</p>
      </aside></div>
    </div>`;

    const form = $("[data-form]", el);
    const type = $("#r-type", el);
    const desc = $("#r-desc", el);
    const order = $("#r-order", el);
    const aboutShipper = $("input[name=about][value=SHIPPER]", el);
    const shipperTile = aboutShipper.closest(".c-tile");
    const syncShipperOption = (o) => {
      aboutShipper.disabled = !o.shipper;
      if (shipperTile) {
        shipperTile.title = o.shipper ? "" : t("Đơn này chưa có shipper nhận");
        shipperTile.classList.toggle("is-disabled", !o.shipper);
      }
      if (!o.shipper && aboutShipper.checked) $("input[name=about][value=ORDER]", el).checked = true;
    };
    const DISPUTE_TYPES = ["DAMAGED_PACKAGE", "LOST_PACKAGE"];
    const DISPUTE_FROM = ["IN_TRANSIT", "DELIVERY_FAILED", "RETURNING", "RETURNED"];
    const disputeNote = () => {
      const o = S.getOrder(order.value);
      if (!o) return;
      syncShipperOption(o);
      $("[data-dispute-note]", el).hidden = !(DISPUTE_TYPES.includes(type.value) && DISPUTE_FROM.includes(o.status));
    };
    type.addEventListener("change", () => { U.setError(type, ""); disputeNote(); });
    order.addEventListener("change", disputeNote);
    $$("input[name=about]", el).forEach((r) => r.addEventListener("change", () => {
      if (r.value === "SHIPPER" && !type.value) type.value = "SHIPPER_PROBLEM";
    }));
    desc.addEventListener("input", () => { $("[data-counter]", el).textContent = `${desc.value.length}/1000`; U.setError(desc, ""); });
    $("[data-file]", el).addEventListener("change", (e) => {
      const input = e.target;
      addFiles(Array.from(input.files || []), el);
      input.value = "";
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const a = U.setError(type, type.value ? "" : t("Chọn loại sự cố."));
      const b = U.setError(desc, desc.value.trim().length >= 10 ? "" : t("Mô tả ít nhất 10 ký tự để bộ phận hỗ trợ hiểu vấn đề."));
      if (!a || !b) return;
      try {
        const res = S.submitReport({
          orderCode: order.value, about: $("input[name=about]:checked", el).value, type: type.value, description: desc.value,
          files: files.map((f) => ({ name: f.name, size: f.size, kind: f.type.startsWith("image/") ? "image" : "pdf" }))
        });
        el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-green">${icon("check", 30)}</span>
          <h1>${t`Đã gửi báo cáo ${h(res.report.id)}`}</h1>
          <p>${t`Bộ phận hỗ trợ sẽ xem xét và phản hồi trong vòng 24 giờ.${res.disputed ? t` Đơn ${h(res.report.orderCode)} đã chuyển sang trạng thái khiếu nại.` : ""}`}</p>
          <div class="c-actions"><a class="btn btn-dark" href="${url("reports", { id: res.report.id })}">${t("Xem báo cáo")}</a><a class="btn btn-outline-dark" href="${url("order", { code: res.report.orderCode })}">${t("Về chi tiết đơn")}</a></div></section>`;
      } catch (err) { U.toast(U.errorMessage(err), "danger"); }
    });
    disputeNote();
  }

  function addFiles(list, el) {
    const err = $("[data-file-error]", el);
    const problems = [];
    list.forEach((f) => {
      if (files.length >= MAX_FILES) { problems.push(t`Chỉ đính kèm tối đa ${MAX_FILES} tệp.`); return; }
      if (!OK_TYPES.includes(f.type)) { problems.push(t`${f.name}: chỉ nhận PNG, JPG hoặc PDF.`); return; }
      if (f.size > MAX_SIZE) { problems.push(t`${f.name}: lớn hơn 10 MB.`); return; }
      files.push(f);
    });
    err.textContent = [...new Set(problems)].join(" ");
    drawFiles(el);
  }
  function drawFiles(el) {
    const box = $("[data-files]", el);
    box.innerHTML = files.map((f, i) => `<div class="c-file">${f.type.startsWith("image/") ? `<img alt="" data-preview="${i}">` : `<span class="c-pay-logo c-logo-cod" style="width:40px;flex:none">PDF</span>`}<span>${h(f.name)}</span><small>${(f.size / 1024 / 1024).toFixed(1)} MB</small><button type="button" aria-label="${t`Bỏ ${h(f.name)}`}" data-remove="${i}">${icon("x", 16)}</button></div>`).join("");
    $$("[data-preview]", box).forEach((img) => { img.src = URL.createObjectURL(files[Number(img.dataset.preview)]); });
    $$("[data-remove]", box).forEach((b) => b.addEventListener("click", () => { files.splice(Number(b.dataset.remove), 1); $("[data-file-error]", el).textContent = ""; drawFiles(el); }));
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-report]"), render, { lines: 4 }));
})();
