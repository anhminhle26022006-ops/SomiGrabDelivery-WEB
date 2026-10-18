/* Customer: Review. Only for COMPLETED orders the customer owns, once per order (BR-159 to BR-166). */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon, url } = U;
  const code = U.param("code");
  const LABELS = ["", t("Rất tệ"), t("Chưa tốt"), t("Tạm được"), t("Tốt"), t("Tuyệt vời")];
  // Tags are saved in Vietnamese and translated when shown.
  const TAGS = { good: ["Đúng giờ", "Cẩn thận với hàng", "Thân thiện", "Liên lạc rõ ràng"], bad: ["Giao trễ", "Hàng bị móp", "Không gọi trước", "Thái độ chưa tốt"] };

  function render(el) {
    const o = S.getOrder(code);
    if (o) $("[data-back]").href = url("order", { code: o.code });
    const check = S.reviewEligibility(o);
    if (!check.ok || !o || !o.shipper) {
      el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-gray">${icon("star", 28)}</span><h1>${t("Chưa thể đánh giá")}</h1><p>${h(check.ok ? t("Đơn này chưa có shipper để đánh giá.") : check.reason)}</p>
        ${o && o.review ? `<section class="card card-body" style="text-align:center">${U.stars(o.review.rating)}${o.review.comment ? `<p style="margin:10px 0 0">${h(o.review.comment)}</p>` : ""}</section>` : ""}
        <div class="c-actions" style="margin-top:18px"><a class="btn btn-dark" href="${o ? url("order", { code: o.code }) : url("orders")}">${o ? t("Về chi tiết đơn") : t("Về danh sách đơn")}</a></div></section>`;
      return;
    }
    let rating = 0;
    const tags = new Set();
    el.innerHTML = `<section class="c-state" style="max-width:620px">
      <h1>${t`Chuyến giao của ${h(o.shipper.name)} thế nào?`}</h1>
      <p>${t`Đơn ${h(o.code)} · ${h(o.pickup.area)} → ${h(o.delivery.area)}. Đánh giá giúp SOMI ưu tiên những shipper giao tốt.`}</p>
      <section class="card card-body">
        ${U.shipperCard(o, { call: false })}
        <div style="margin-top:22px;text-align:center">
          <div class="c-rate" role="radiogroup" aria-label="${t("Chấm điểm từ 1 đến 5 sao")}">${[1, 2, 3, 4, 5].map((i) => `<button type="button" role="radio" aria-checked="false" aria-label="${t`${i} sao, ${LABELS[i]}`}" data-star="${i}">${icon("star", 38)}</button>`).join("")}</div>
          <div class="c-rate-label" data-rate-label>${t("Chạm vào số sao để chấm điểm")}</div>
        </div>
        <div data-after hidden style="margin-top:20px">
          <p class="form-label c-label" style="margin:0 0 10px" data-tags-title></p>
          <div class="c-tags" data-tags></div>
          <div class="c-field" style="margin-top:18px">
            <div class="c-field-row"><label class="form-label" for="comment">${t("Nhận xét thêm (không bắt buộc)")}</label><span class="c-counter" data-counter>0/500</span></div>
            <textarea class="form-control" id="comment" maxlength="500" rows="3" placeholder="${t("Điều bạn hài lòng hoặc muốn shipper cải thiện")}"></textarea>
          </div>
        </div>
        <button type="button" class="btn btn-dark w-100" style="margin-top:20px" data-submit disabled>${t("Gửi đánh giá")}</button>
      </section>
    </section>`;

    const paint = (n) => $$("[data-star]", el).forEach((b) => b.classList.toggle("on", Number(b.dataset.star) <= n));
    $$("[data-star]", el).forEach((b) => {
      b.addEventListener("mouseenter", () => paint(Number(b.dataset.star)));
      b.addEventListener("mouseleave", () => paint(rating));
      b.addEventListener("click", () => {
        rating = Number(b.dataset.star);
        paint(rating);
        $$("[data-star]", el).forEach((x) => x.setAttribute("aria-checked", String(Number(x.dataset.star) === rating)));
        $("[data-rate-label]", el).textContent = LABELS[rating];
        $("[data-submit]", el).disabled = false;
        const set = rating >= 4 ? TAGS.good : TAGS.bad;
        tags.clear();
        $("[data-after]", el).hidden = false;
        $("[data-tags-title]", el).textContent = rating >= 4 ? t("Điều bạn thích nhất") : t("Điều chưa ổn");
        $("[data-tags]", el).innerHTML = set.map((tag) => `<button type="button" class="c-tag-toggle" aria-pressed="false" data-tag="${h(tag)}">${h(t(tag))}</button>`).join("");
        $$(".c-tag-toggle", el).forEach((tb) => tb.addEventListener("click", () => {
          const on = tb.getAttribute("aria-pressed") !== "true";
          tb.setAttribute("aria-pressed", String(on));
          const tag = tb.dataset.tag || "";
          if (on) tags.add(tag); else tags.delete(tag);
        }));
      });
    });
    const comment = $("#comment", el);
    comment.addEventListener("input", () => { $("[data-counter]", el).textContent = `${comment.value.length}/500`; });
    $("[data-submit]", el).addEventListener("click", () => {
      try {
        S.submitReview(o.code, { rating, tags: [...tags], comment: comment.value.trim() });
        el.innerHTML = `<section class="c-state"><span class="c-state-icon c-tone-green">${icon("check", 30)}</span><h1>${t("Cảm ơn bạn đã đánh giá")}</h1>
          <p>${U.stars(rating)}</p>
          <p>${rating <= 2 ? t("Rất tiếc chuyến giao chưa tốt. Nếu hàng hư hỏng hoặc shipper có hành vi chưa đúng, hãy gửi báo cáo để SOMI xử lý.") : t("Đánh giá của bạn được tính vào điểm trung bình của shipper.")}</p>
          <div class="c-actions">${rating <= 2 ? `<a class="btn btn-dark" href="${url("report", { code: o.code })}">${t("Báo cáo sự cố")}</a>` : ""}<a class="btn ${rating <= 2 ? "btn-outline-dark" : "btn-dark"}" href="${url("order", { code: o.code })}">${t("Về chi tiết đơn")}</a></div></section>`;
      } catch (err) { U.toast(U.errorMessage(err), "danger"); }
    });
  }

  document.addEventListener("DOMContentLoaded", () => U.load($("[data-review]"), render, { lines: 3 }));
})();
