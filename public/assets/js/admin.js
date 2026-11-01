/* Existing Admin preview controls. No API writes or generated business data. */
(() => {
  "use strict";
  if (document.body.dataset.page !== "admin") return;
  const rows = [...document.querySelectorAll(".users-table-card tbody tr")];
  let filter = "all";
  const search = document.querySelector(".search-box input");
  const problemType = document.getElementById("problemTypeSelect");
  function applyFilter() {
    const query = (search?.value || "").trim().toLocaleLowerCase();
    rows.forEach(row => { row.hidden = (filter !== "all" && row.dataset.status !== filter) || !row.textContent.toLocaleLowerCase().includes(query) || (problemType?.selectedIndex > 0 && !row.textContent.toLocaleLowerCase().includes(problemType.selectedOptions[0].textContent.toLocaleLowerCase())); });
    const empty = document.getElementById("admin-empty-state");
    if (empty) empty.hidden = rows.some(row => !row.hidden);
  }
  function filterTable(value, button) {
    filter = value;
    button?.closest(".filter-tabs")?.querySelectorAll(".tab-btn").forEach(item => item.classList.toggle("active", item === button));
    applyFilter();
  }
  ["filterTable", "filterCustomerTable", "filterShipperTable", "filterOrderTable", "filterComplaintTable", "filterDisputeTable", "filterPaymentTable", "filterReviewTable", "filterAuditTable"].forEach(name => { window[name] = filterTable; });
  ["searchTable", "searchCustomerTable", "searchShipperTable", "searchOrderTable", "searchComplaintTable", "searchDisputeTable", "searchPaymentTable", "searchReviewTable", "searchAuditTable"].forEach(name => { window[name] = applyFilter; });
  search?.addEventListener("input", applyFilter);
  problemType?.addEventListener("change", applyFilter);
  const body = rows[0]?.parentElement;
  if (body) {
    const row = document.createElement("tr"); row.id = "admin-empty-state"; row.hidden = true;
    const cell = document.createElement("td"); cell.colSpan = rows[0].cells.length; cell.textContent = "No matching records.";
    row.append(cell); body.append(row);
  }
  const sort = document.querySelector("#sortSelect, select[id$=SortSelect]");
  if (sort && body) sort.addEventListener("change", () => {
    const direction = sort.selectedIndex === 1 ? 1 : -1;
    [...rows].sort((a, b) => direction * ((Date.parse(a.querySelector(".date-text")?.textContent || "") || 0) - (Date.parse(b.querySelector(".date-text")?.textContent || "") || 0))).forEach(row => body.append(row));
  });
  let returnFocus = null;
  const modal = document.getElementById("userModal");
  window.openModal = id => {
    const row = rows.find(row => row.dataset.userid === id);
    if (!modal || !row) return;
    returnFocus = document.activeElement;
    document.getElementById("modalTitle").textContent = `Users · ${id}`;
    const content = document.getElementById("modalBodyContent"); content.replaceChildren();
    const note = document.createElement("p"); note.textContent = "Read-only preview of the displayed record. Account changes are not connected to the server."; content.append(note);
    const list = document.createElement("dl");
    [...row.cells].slice(0, -1).forEach((cell, index) => {
      const label = document.createElement("dt"), value = document.createElement("dd");
      label.textContent = row.closest("table").tHead.rows[0].cells[index].textContent;
      value.textContent = cell.innerText.trim(); list.append(label, value);
    });
    content.append(list); modal.classList.add("show"); modal.setAttribute("role", "dialog"); modal.setAttribute("aria-modal", "true"); modal.setAttribute("aria-labelledby", "modalTitle"); modal.querySelector(".modal-close").focus();
  };
  window.closeModal = () => { modal?.classList.remove("show"); returnFocus?.focus(); };
  modal?.addEventListener("click", event => { if (event.target === modal) window.closeModal(); });
  document.addEventListener("keydown", event => { if (event.key === "Escape" && modal?.classList.contains("show")) window.closeModal(); });
  window.filterNotificationTab = (category, button) => {
    button?.closest(".filter-tabs")?.querySelectorAll(".tab-btn").forEach(item => item.classList.toggle("active", item === button));
    document.querySelectorAll(".notification-item").forEach(item => { item.hidden = category !== "all" && !(item.dataset.category || "").split(/\s+/).includes(category); });
  };
  window.markAllRead = () => {
    document.querySelectorAll(".notification-item").forEach(item => {
      item.classList.remove("unread"); item.dataset.category = (item.dataset.category || "").split(/\s+/).filter(value => value !== "unread").join(" ");
      item.querySelector(".unread-dot")?.remove();
    });
    document.querySelectorAll(".filter-tabs .tab-count").forEach(count => { count.textContent = "0"; });
    const active = document.querySelector(".filter-tabs .tab-btn.active");
    if (active?.getAttribute("onclick")?.includes("'unread'")) window.filterNotificationTab("unread", active);
  };
  if (document.querySelector(".users-control-panel")) {
    const notice = document.createElement("p"); notice.className = "notice-banner"; notice.setAttribute("role", "status");
    notice.textContent = "Preview data: these records are samples. Filtering and read states apply only to this page; server management is not connected.";
    document.querySelector(".users-control-panel").before(notice);
  }
})();
