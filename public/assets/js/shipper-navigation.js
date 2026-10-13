"use strict";
(() => {
  const viewToPage = {
    dashboard: "dashboard", offers: "offers", detail: "active", active: "active",
    history: "history", earnings: "earnings", account: "account", registration: "account", notifications: "notifications",
    chat: "active", support: "active", cancel: "active", withdraw: "earnings", report: "earnings"
  };
  const knownPages = new Set(["dashboard", "offers", "active", "history", "earnings", "account", "notifications"]);
  const pageToView = { dashboard: "dashboard", offers: "offers", active: "active", history: "history", earnings: "earnings", account: "account", notifications: "notifications" };
  function navigate(requestedPage, updateUrl = true) {
    const page = knownPages.has(requestedPage) ? requestedPage : "dashboard";
    const target = document.getElementById(`page-${page}`);
    const pages = [...document.querySelectorAll("main .page")];
    if (!target || !pages.includes(target)) {
      const url = new URL(window.location.href);
      url.searchParams.set("page", "shipper");
      url.searchParams.set("view", pageToView[page]);
      window.location.href = url.href;
      return true;
    }
    pages.forEach((item) => {
      const visible = item === target;
      item.classList.toggle("hidden", !visible);
      item.toggleAttribute("hidden", !visible);
      item.setAttribute("aria-hidden", String(!visible));
    });
    document.querySelectorAll("#sidebar [data-page]").forEach((button) => {
      const active = button.dataset.page === page;
      button.classList.toggle("active", active);
      if (active) button.setAttribute("aria-current", "page");
      else button.removeAttribute("aria-current");
    });
    document.getElementById("profileDropdown")?.classList.remove("open");
    document.getElementById("profileBtn")?.setAttribute("aria-expanded", "false");
    if (updateUrl && window.history?.pushState) {
      const url = new URL(window.location.href);
      url.searchParams.set("page", "shipper");
      url.searchParams.set("view", pageToView[page]);
      window.history.pushState({ shipperPage: page }, "", url);
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
    return true;
  }
  window.go = navigate;
  document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    sidebar?.addEventListener("click", (event) => {
      const link = event.target.closest("a[data-page]");
      if (!link || !sidebar.contains(link) || event.defaultPrevented || event.button !== 0
          || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (navigate(link.dataset.page)) event.preventDefault();
    });
    document.addEventListener("click", (event) => {
      const button = event.target.closest("[data-goto]");
      if (button) navigate(button.dataset.goto);
    });
    window.addEventListener("popstate", () => {
      const params = new URLSearchParams(window.location.search);
      navigate(viewToPage[params.get("view")] || "dashboard", false);
    });
  });
})();
