"use strict";
// Trang Tổng quan của Shipper: số liệu theo kỳ và thẻ "Đơn đang giao", lấy từ API thật.
// Thay cho các con số minh họa trước đây (5 đơn, 4h 30m, 4.9 sao, đơn SOMI-240126).
(() => {
  const api = window.SomiShipper;
  if (!api || !document.getElementById("page-dashboard")) return;
  const { esc } = api;
  const RANGE_KEY = "somi-shipper-date-range";
  const REFRESH_MS = 60000;

  // Câu so sánh của từng kỳ: [tiếng Anh, tiếng Việt].
  const COMPARISON = {
    today: ["vs. yesterday", "so với hôm qua"], yesterday: ["vs. the day before", "so với hôm kia"],
    week: ["vs. last week", "so với tuần trước"], month: ["vs. last month", "so với tháng trước"],
  };
  const STATUS = {
    SHIPPER_ASSIGNED: ["ASSIGNED", "ĐÃ NHẬN ĐƠN"], PICKED_UP: ["PICKED UP", "ĐÃ LẤY HÀNG"],
    IN_DELIVERY: ["IN DELIVERY", "ĐANG GIAO"], RETURNING: ["RETURNING", "ĐANG HOÀN HÀNG"],
  };
  const STEPS = ["SHIPPER_ASSIGNED", "PICKED_UP", "IN_DELIVERY"];
  const STEP_LABELS = [["Accepted", "Đã nhận"], ["Picked up", "Đã lấy hàng"], ["In delivery", "Đang giao"], ["Result", "Kết quả"]];

  let range = "today";
  let summary = null;
  let delivery;          // undefined: chưa tải xong, null: không có đơn
  let requestId = 0;

  const vi = () => document.body.dataset.shipperLang !== "en";
  const t = (pair) => pair[vi() ? 1 : 0];
  const set = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };

  function savedRange() {
    try { return localStorage.getItem(RANGE_KEY) || "today"; } catch (error) { return "today"; }
  }

  // ---------- Bốn thẻ số liệu ----------
  function renderSummary() {
    if (!summary) return;
    const now = summary.current;
    set("dashDeliveries", esc(now.deliveries));

    // Mũi tên và màu chỉ xuất hiện khi kỳ trước có đơn để so sánh.
    const change = summary.deliveries_change_percent;
    const comparison = `<span class="muted">${esc(t(COMPARISON[summary.range]))}</span>`;
    if (change === null) {
      set("dashDeliveriesTrend", `<span class="muted">${esc(t(["No deliveries in the previous period", "Kỳ trước chưa có đơn"]))}</span>`);
    } else {
      const arrow = change > 0 ? "↑" : change < 0 ? "↓" : "→";
      const color = change < 0 ? "#b42318" : "";
      set("dashDeliveriesTrend", `<span style="color:${color}">${arrow} ${Math.abs(change)}%</span> ${comparison}`);
    }

    if (now.acceptance_rate_percent === null) {
      set("dashAcceptance", "—");
      set("dashAcceptanceNote", esc(t(["No offers yet in this period", "Chưa có đơn nào được gửi trong kỳ này"])));
    } else {
      set("dashAcceptance", `${esc(now.acceptance_rate_percent)}%`);
      set("dashAcceptanceNote", esc(vi()
        ? `Nhận ${now.offers_accepted} trên ${now.offers_decided} đơn được gửi`
        : `Accepted ${now.offers_accepted} of ${now.offers_decided} offers`));
    }

    const rating = summary.rating;
    if (rating.average === null) {
      set("dashRating", "—");
      set("dashRatingNote", esc(t(["No ratings yet", "Chưa có đánh giá"])));
    } else {
      set("dashRating", `${esc(rating.average)} <span style="font-size:12px;font-weight:600">/ 5</span>`);
      set("dashRatingNote", esc(vi() ? `${rating.count} đánh giá` : `${rating.count} ratings`));
    }
  }

  async function loadSummary() {
    // Người dùng có thể đổi kỳ liên tục: chỉ hiển thị kết quả của lần gọi mới nhất.
    const id = ++requestId;
    try {
      const result = await api.dashboard(range);
      if (id !== requestId) return;
      summary = result;
      renderSummary();
    } catch (error) {
      if (id !== requestId) return;
      ["dashDeliveries", "dashAcceptance", "dashRating"].forEach((target) => set(target, "—"));
      set("dashDeliveriesTrend", `<span class="muted">${esc(error.message)}</span>`);
    }
  }

  // ---------- Thẻ "Đơn đang giao" ----------
  function progress(status) {
    const returning = status === "RETURNING";
    const reached = returning ? 3 : STEPS.indexOf(status);
    return `<div class="progress">${STEP_LABELS.map((label, i) => {
      const state = i < reached ? "done" : i === reached ? "current" : "";
      const text = i === 3 && returning ? t(["Returning", "Hoàn hàng"]) : t(label);
      return `<div class="step ${state}"><div class="stepnum">${i < reached ? "✓" : i + 1}</div><strong>${esc(text)}</strong></div>`;
    }).join("")}</div>`;
  }

  function renderDelivery() {
    if (delivery === undefined) return;
    if (delivery === null) {
      set("overviewActive", `<div class="empty"><strong>${esc(t(["No active delivery", "Bạn chưa có đơn đang giao"]))}</strong>${esc(t(["Accept an offer to get started.", "Nhận một đơn phù hợp để bắt đầu."]))}</div>`);
      return;
    }
    const d = delivery;
    set("overviewActive", `
      <div class="orderid">${esc(d.order_code)} <span class="tag">${esc(t(STATUS[d.status] || [d.status, d.status]))}</span></div>
      <div class="route" style="margin-top:12px"><div class="routepoints"><span class="dot"></span><span class="routebar"></span><span class="dot red"></span></div>
        <div class="address"><strong>${esc(t(["Pickup", "Điểm lấy hàng"]))}</strong><small>${esc(d.pickup_address)}</small><div style="height:14px"></div>
          <strong>${esc(t(["Drop-off", "Điểm giao hàng"]))}</strong><small>${esc(d.delivery_address)}</small></div>
        <div class="pay"><small>${esc(t(["Distance", "Quãng đường"]))}</small><strong>${esc(Number(d.distance_km).toLocaleString(vi() ? "vi-VN" : "en-US", { maximumFractionDigits: 1 }))} km</strong></div></div>
      ${progress(d.status)}
      <div style="display:flex;justify-content:flex-end;margin-top:12px"><button class="primary" data-goto="active">${esc(t(["Open active delivery", "Mở đơn đang giao"]))} →</button></div>`);
  }

  async function loadDelivery() {
    try {
      delivery = (await api.currentDelivery()).delivery;
      renderDelivery();
    } catch (error) {
      set("overviewActive", `<div class="empty"><strong>${esc(error.message)}</strong></div>`);
    }
  }

  // ---------- Khởi động ----------
  // Trang phát somi:shipper-range mỗi khi đổi kỳ hoặc đổi ngôn ngữ (xem app/Views/shipper/index.php).
  document.addEventListener("somi:shipper-range", (event) => {
    const next = event.detail?.range;
    if (!COMPARISON[next]) return;
    if (next === range && summary) { renderSummary(); renderDelivery(); return; } // chỉ đổi ngôn ngữ
    range = next;
    loadSummary();
  });

  range = COMPARISON[savedRange()] ? savedRange() : "today";
  loadSummary();
  loadDelivery();
  // Tự làm mới khi tab đang mở, để số đơn và đơn đang giao không bị cũ sau khi vừa giao xong.
  setInterval(() => { if (!document.hidden) { loadSummary(); loadDelivery(); } }, REFRESH_MS);
})();
