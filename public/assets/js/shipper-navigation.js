"use strict";
(() => {
  const viewToPage = {
    dashboard: "dashboard", offers: "offers", detail: "active", active: "active",
    history: "history", earnings: "earnings", account: "account", registration: "account", notifications: "notifications",
    chat: "support", support: "support", cancel: "active", withdraw: "earnings", report: "earnings"
  };
  const knownPages = new Set(["dashboard", "offers", "active", "history", "earnings", "account", "notifications", "support"]);
  const pageToView = { dashboard: "dashboard", offers: "offers", active: "active", history: "history", earnings: "earnings", account: "account", notifications: "notifications", support: "support" };
  function navigate(requestedPage, updateUrl = true) {
    if (requestedPage === 'notifications') { window.SomiNotifications?.open(); return true; }
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
    document.querySelectorAll("#shipperNav [data-page]").forEach((button) => {
      const active = button.dataset.page === page;
      button.classList.toggle("active", active);
      if (active) button.setAttribute("aria-current", "page");
      else button.removeAttribute("aria-current");
    });
    document.getElementById("profileDropdown")?.classList.remove("open");
    document.getElementById("profileBtn")?.setAttribute("aria-expanded", "false");
    document.body.dataset.shipperView = pageToView[page];
    if (updateUrl && window.history?.pushState && new URLSearchParams(window.location.search).get("view") !== pageToView[page]) {
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
    const tabs = document.getElementById("shipperNav");
    const menu=document.querySelector('.shipper-menu-toggle');
    function closeMenu(restore=false){tabs?.classList.remove('is-open');menu?.setAttribute('aria-expanded','false');if(restore)menu?.focus();}
    menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));tabs?.classList.toggle('is-open',open);});
    document.addEventListener('pointerdown',e=>{if(!tabs?.contains(e.target)&&!menu?.contains(e.target))closeMenu();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu?.getAttribute('aria-expanded')==='true'){closeMenu(true);}});
    window.addEventListener('resize',()=>{if(window.innerWidth>760)closeMenu();});
    tabs?.addEventListener("click", (event) => {
      const link = event.target.closest("a[data-page]");
      if (!link || !tabs.contains(link) || event.defaultPrevented || event.button !== 0
          || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      closeMenu();
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
