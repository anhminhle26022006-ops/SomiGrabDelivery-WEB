/* Customer: Notifications (BR-231 to BR-237) */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$, h, icon } = U;
  const TABS = [["all", t("Tất cả")], ["unread", t("Chưa đọc")], ["order", t("Đơn hàng")], ["payment", t("Thanh toán")], ["report", t("Báo cáo")]];
  const ICON = { order: "truck", payment: "wallet", report: "report", account: "user" };
  let tab = "all";

  function dayLabel(iso) {
    const d = new Date(iso); d.setHours(0, 0, 0, 0);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const diff = Math.round((today.getTime() - d.getTime()) / 864e5);
    return diff === 0 ? t("Hôm nay") : diff === 1 ? t("Hôm qua") : t("Trước đó");
  }

  function render(withLoading = false) {
    const all = S.listNotifications();
    const unread = all.filter((n) => !n.read).length;
    $("[data-tabs]").innerHTML = TABS.map(([k, l]) => {
      const count = k === "all" ? all.length : k === "unread" ? unread : all.filter((n) => n.type === k).length;
      return `<button type="button" role="tab" data-tab="${k}" aria-selected="${k === tab}" class="nav-link${k === tab ? " active" : ""}">${l} <b>${count}</b></button>`;
    }).join("");
    $$("[data-tab]").forEach((b) => b.addEventListener("click", () => { tab = b.dataset.tab || "all"; render(); }));
    $("[data-mark-all]").disabled = unread === 0;

    const fill = (el) => {
      const shown = all.filter((n) => tab === "all" || (tab === "unread" ? !n.read : n.type === tab));
      if (!shown.length) {
        el.innerHTML = U.empty({ title: tab === "unread" ? t("Bạn đã đọc hết thông báo") : t("Chưa có thông báo"), text: t("Thông báo mới về đơn hàng sẽ xuất hiện ở đây.") });
        return;
      }
      let current = "";
      el.innerHTML = shown.map((n) => {
        const label = dayLabel(n.at);
        const head = label !== current ? `<h3 class="c-day">${label}</h3>` : "";
        current = label;
        return `${head}<button type="button" class="c-note ${n.read ? "" : "is-unread"}" data-id="${n.id}">
          <span class="c-note-icon">${icon(ICON[n.type], 18)}</span>
          <span><strong>${h(tx(n.title))}</strong><p>${h(tx(n.body))}</p></span>
          <time datetime="${n.at}">${U.relTime(n.at)}</time>
          ${n.read ? "" : `<span class="visually-hidden">${t("Chưa đọc")}</span>`}
        </button>`;
      }).join("");
      $$("[data-id]", el).forEach((b) => b.addEventListener("click", () => {
        const n = all.find((x) => x.id === b.dataset.id);
        if (!n) return;
        S.markRead(n.id);
        if (n.link) U.go(n.link.view, { code: n.link.code, id: n.link.id });
      }));
    };
    if (withLoading) U.load($("[data-list]"), fill, { lines: 5 }); else fill($("[data-list]"));
  }

  document.addEventListener("DOMContentLoaded", () => {
    $("[data-mark-all]").addEventListener("click", () => { S.markAllRead(); U.toast(t("Đã đánh dấu đọc tất cả thông báo.")); });
    render(true);
  });
  document.addEventListener("somi:change", () => render());
})();
