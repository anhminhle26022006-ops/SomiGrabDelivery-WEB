/* Customer: Order history with status tabs, search and date range */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t, tx } = window.SomiI18n;
  const { $, $$ } = U;

  const TABS = [
    ["all", t("Tất cả"), null],
    ["active", t("Đang xử lý"), S.GROUPS.active],
    ["done", t("Đã giao"), S.GROUPS.done],
    ["problem", t("Cần chú ý"), S.GROUPS.problem],
    ["cancelled", t("Đã hủy"), S.GROUPS.cancelled]
  ];
  let tab = U.param("tab") || "all";
  if (!TABS.some(([k]) => k === tab)) tab = "all";

  function filtered(orders, ignoreTab = false) {
    const q = $("[data-search]").value.trim().toLowerCase();
    const from = $("[data-from]").value;
    const to = $("[data-to]").value;
    return orders.filter((o) => {
      const group = (TABS.find(([k]) => k === tab) || TABS[0])[2];
      if (!ignoreTab && group && !group.includes(o.status)) return false;
      if (q) {
        const hay = `${o.code} ${o.delivery.name} ${o.delivery.area} ${o.pickup.area} ${o.pkg.description}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      const day = o.createdAt.slice(0, 10);
      const local = new Date(o.createdAt);
      const localDay = `${local.getFullYear()}-${String(local.getMonth() + 1).padStart(2, "0")}-${String(local.getDate()).padStart(2, "0")}`;
      if (from && (localDay || day) < from) return false;
      if (to && (localDay || day) > to) return false;
      return true;
    });
  }

  function renderTabs(orders) {
    const base = filtered(orders, true);
    $("[data-tabs]").innerHTML = TABS.map(([key, label, group]) => {
      const count = group ? base.filter((o) => group.includes(o.status)).length : base.length;
      return `<button type="button" role="tab" data-tab="${key}" aria-selected="${key === tab}" class="nav-link${key === tab ? " active" : ""}">${label} <b>${count}</b></button>`;
    }).join("");
    $$("[data-tab]").forEach((b) => b.addEventListener("click", () => {
      tab = b.dataset.tab || "all";
      const params = new URLSearchParams(location.search); params.set("tab", tab);
      history.replaceState(null, "", `?${params}`);
      render(false);
    }));
  }

  function render(withLoading = true) {
    const orders = S.listOrders();
    renderTabs(orders);
    const fill = (el) => {
      const list = filtered(orders);
      const filtering = $("[data-search]").value || $("[data-from]").value || $("[data-to]").value;
      $("[data-clear]").hidden = !filtering;
      el.innerHTML = list.length ? list.map(U.orderRow).join("")
        : orders.length === 0 ? U.empty({ title: t("Bạn chưa có đơn hàng nào"), text: t("Tạo đơn đầu tiên, SOMI sẽ báo phí trước khi bạn xác nhận."), action: `<a class="btn btn-dark" href="${U.url("create")}" data-send-link>${t("Gửi hàng mới")}</a>` })
        : U.empty({ title: t("Không có đơn phù hợp"), text: filtering ? t("Thử từ khóa khác hoặc bỏ bớt bộ lọc ngày.") : t("Chưa có đơn nào ở nhóm trạng thái này.") });
    };
    if (withLoading) U.load($("[data-list]"), fill, { lines: 5 }); else fill($("[data-list]"));
  }

  document.addEventListener("DOMContentLoaded", () => {
    let timer;
    $("[data-search]").addEventListener("input", () => { clearTimeout(timer); timer = window.setTimeout(() => render(false), 150); });
    $("[data-from]").addEventListener("change", () => {
      if ($("[data-to]").value && $("[data-from]").value > $("[data-to]").value) $("[data-to]").value = $("[data-from]").value;
      render(false);
    });
    $("[data-to]").addEventListener("change", () => {
      if ($("[data-from]").value && $("[data-to]").value < $("[data-from]").value) $("[data-from]").value = $("[data-to]").value;
      render(false);
    });
    $("[data-clear]").addEventListener("click", () => { $("[data-search]").value = ""; $("[data-from]").value = ""; $("[data-to]").value = ""; render(false); });
    render();
  });
  document.addEventListener("somi:change", () => render(false));
})();
