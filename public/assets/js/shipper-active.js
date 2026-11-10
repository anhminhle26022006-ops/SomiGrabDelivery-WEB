"use strict";
// Đơn đang giao: các mốc giao nhận, ảnh bằng chứng, hoàn hàng, GPS và lịch sử. Nối với API thật.
(() => {
  const api = window.SomiShipper;
  const { esc, toast } = api;
  const GPS_INTERVAL_MS = 15000; // backend chặn dưới 5 giây; gửi 15 giây một lần là đủ cho bản đồ theo dõi

  let delivery = null;
  let historyRows = [];
  let gpsWatch = null;
  let lastGpsSentAt = 0;
  let pendingEvidenceType = null;

  const STATUS = {
    SHIPPER_ASSIGNED: "ĐÃ NHẬN ĐƠN", PICKED_UP: "ĐÃ LẤY HÀNG", IN_DELIVERY: "ĐANG GIAO", RETURNING: "ĐANG HOÀN HÀNG",
    DELIVERED: "ĐÃ GIAO", RETURNED: "ĐÃ HOÀN", CANCELLED: "ĐÃ HỦY",
  };
  const STEPS = ["SHIPPER_ASSIGNED", "PICKED_UP", "IN_DELIVERY"];
  const STEP_LABELS = ["Đã nhận đơn", "Đã lấy hàng", "Đang giao", "Kết quả giao"];
  const REASONS = {
    NO_ANSWER: "Không liên lạc được với người nhận", REFUSED: "Người nhận từ chối nhận hàng",
    WRONG_ADDRESS: "Sai địa chỉ hoặc không tìm thấy địa chỉ", RECIPIENT_ABSENT: "Người nhận vắng mặt",
    PACKAGE_ISSUE: "Sự cố với hàng hóa hoặc bao bì", OTHER: "Lý do khác",
  };

  // ---------- Hiển thị ----------
  function progress(status) {
    const returning = status === "RETURNING";
    const reached = returning ? 3 : STEPS.indexOf(status);
    return `<div class="progress">${STEP_LABELS.map((label, i) => {
      const state = i < reached ? "done" : i === reached ? "current" : "";
      const text = i === 3 && returning ? "Hoàn hàng" : label;
      return `<div class="step ${state}"><div class="stepnum">${i < reached ? "✓" : i + 1}</div><strong>${text}</strong></div>`;
    }).join("")}</div>`;
  }

  // Nút bấm lấy từ next_actions của backend: giao diện không tự quyết định bước nào hợp lệ.
  function actionButtons(actions) {
    const photos = delivery.evidence.length;
    const map = {
      PICKUP: `<button class="primary blue" data-delivery-action="PICKUP">Đã lấy hàng</button>`,
      START_DELIVERY: `<button class="primary blue" data-delivery-action="START_DELIVERY">Bắt đầu giao</button>`,
      ADD_DELIVERY_PHOTO: `<button class="outline" data-evidence="DELIVERY_PHOTO">📷 Thêm ảnh giao hàng (${photos})</button>`,
      DELIVERED: `<button class="primary blue" data-delivery-action="DELIVERED">✓ Đã giao hàng thành công</button>`,
      FAIL: `<button class="outline" data-delivery-action="SHOW_FAIL">Không thể giao hàng…</button>`,
      ADD_RETURN_PHOTO: `<button class="outline" data-evidence="RETURN_PHOTO">📷 Thêm ảnh hoàn hàng (${photos})</button>`,
      RETURNED: `<button class="primary blue" data-delivery-action="RETURNED">✓ Đã trả hàng cho người gửi</button>`,
    };
    return actions.map((action) => map[action] || "").join("");
  }

  function helpText() {
    if (delivery.status === "RETURNING" && delivery.returned_at) return "Đã báo trả hàng. Đơn sẽ đóng khi người gửi xác nhận đã nhận lại.";
    if (delivery.status === "RETURNING") return "Mang hàng về điểm lấy, chụp ảnh lúc trả rồi xác nhận đã trả hàng.";
    if (delivery.status === "IN_DELIVERY" && !delivery.next_actions.includes("DELIVERED")) return "Cần ít nhất một ảnh giao hàng trước khi xác nhận giao thành công.";
    return "";
  }

  function render() {
    const box = document.getElementById("activeDelivery");
    const count = document.getElementById("activeCount");
    if (count) count.textContent = delivery ? "1 đơn đang xử lý" : "Không có đơn";
    if (!box) return;
    if (!delivery) {
      box.innerHTML = `<div class="empty"><strong>Bạn chưa có đơn đang giao</strong>Nhận một đơn ở mục Đơn giao phù hợp để bắt đầu.</div>`;
      return;
    }
    const d = delivery;
    box.innerHTML = `
      <div class="sectiontitle"><div><h2>${esc(d.order_code)}</h2><p>${esc(d.category_name_vi)}, ${esc(d.estimated_weight_kg)} kg, quãng đường ${esc(d.distance_km)} km</p></div><span class="pill">${STATUS[d.status] || esc(d.status)}</span></div>
      <div class="route"><div class="routepoints"><span class="dot"></span><span class="routebar" style="height:34px"></span><span class="dot red"></span></div>
        <div class="address"><strong>Điểm lấy hàng: ${esc(d.sender_name)}</strong><small>${esc(d.pickup_address)}</small><div style="height:18px"></div>
          <strong>Điểm giao hàng: ${esc(d.receiver_name)}</strong><small>${esc(d.delivery_address)}</small></div></div>
      ${d.customer_note ? `<p class="muted" style="font-size:12px">Ghi chú của khách: ${esc(d.customer_note)}</p>` : ""}
      ${progress(d.status)}
      <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:15px">
        <a class="outline" style="text-decoration:none" href="tel:${esc(d.sender_phone)}">☎ Gọi người gửi</a><a class="outline" style="text-decoration:none" href="tel:${esc(d.receiver_phone)}">☎ Gọi người nhận</a>
        ${actionButtons(d.next_actions)}
      </div>
      <div id="failPanel" class="hidden" style="margin-top:14px;padding:16px;border:1px solid #cfe0f5;border-radius:13px;background:#f7fbff">
        <label for="failureReason" style="display:block;font-weight:750;margin-bottom:6px">Lý do không thể giao</label>
        <select id="failureReason" class="outline" style="width:100%;margin-bottom:10px">${Object.entries(REASONS).map(([code, label]) => `<option value="${code}">${label}</option>`).join("")}</select>
        <label for="failureNote" style="display:block;font-weight:750;margin-bottom:6px">Ghi chú</label>
        <input id="failureNote" maxlength="500" placeholder="Mô tả ngắn tình huống..." style="width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;margin-bottom:10px">
        <button class="primary" data-delivery-action="FAIL">Ghi nhận và bắt đầu hoàn hàng</button>
      </div>
      <p class="muted" style="font-size:12px;margin-bottom:0">${esc(helpText())}</p>`;
  }

  function renderHistory(filter = "all") {
    const body = document.getElementById("historyRows");
    if (!body) return;
    document.querySelectorAll("[data-history-filter]").forEach((b) => b.classList.toggle("selected", b.dataset.historyFilter === filter));
    const rows = historyRows.filter((row) => filter === "all" || (filter === "done") === (row.assignment_status === "COMPLETED"));
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td><b>${esc(row.order_code)}</b></td><td>${esc(new Date(row.ended_at || row.assigned_at).toLocaleString("vi-VN"))}</td>
      <td>${esc(row.pickup_address)} đến ${esc(row.delivery_address)}</td><td>Xem ở mục Thu nhập</td>
      <td><span class="${row.assignment_status === "COMPLETED" ? "statusdone" : "tag"}">${STATUS[row.status] || esc(row.status)}</span></td></tr>`).join("")
      : `<tr><td colspan="5" class="muted">Chưa có đơn nào.</td></tr>`;
  }

  // ---------- Dữ liệu ----------
  function show(result) {
    delivery = result.delivery;
    render();
    syncGps();
  }

  async function load() {
    try {
      show(await api.currentDelivery());
    } catch (error) {
      toast(error.message);
    }
  }

  async function act(action, button) {
    if (action === "SHOW_FAIL") return document.getElementById("failPanel")?.classList.toggle("hidden");
    const orderId = delivery.order_id;
    button.disabled = true;
    try {
      if (action === "PICKUP" || action === "START_DELIVERY") show(await api.deliveryAction(orderId, action));
      else if (action === "FAIL") {
        const reason = document.getElementById("failureReason").value;
        const note = document.getElementById("failureNote").value.trim();
        if (reason === "OTHER" && !note) return toast("Vui lòng ghi rõ lý do.");
        show(await api.failDelivery(orderId, reason, note));
        toast("Đã ghi nhận giao thất bại. Vui lòng hoàn hàng về điểm lấy.");
      } else if (action === "DELIVERED") {
        await api.completeDelivery(orderId);
        toast("Đã giao thành công.");
        await load();
      } else if (action === "RETURNED") {
        await api.confirmReturned(orderId);
        toast("Đã báo trả hàng.");
        await load();
      }
    } catch (error) {
      toast(error.message);
      load(); // trạng thái có thể đã đổi ở nơi khác (ví dụ Admin hủy phân công): đọc lại từ máy chủ
    } finally {
      button.disabled = false;
    }
  }

  // ---------- Ảnh bằng chứng ----------
  // Hiện tại chỉ gửi thông tin mô tả ảnh (loại, dung lượng, mã băm SHA-256) kèm một tham chiếu tạm.
  // Khi Storage của nhóm sẵn sàng, bước upload file sẽ chạy trước và trả về tham chiếu thật.
  async function submitEvidence(file) {
    if (!file || !delivery || !pendingEvidenceType) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) return toast("Chỉ nhận ảnh JPG, PNG hoặc WEBP.");
    if (file.size > 10 * 1024 * 1024) return toast("Ảnh không được lớn hơn 10 MB.");
    try {
      const digest = await crypto.subtle.digest("SHA-256", await file.arrayBuffer());
      const checksum = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
      const extension = { "image/jpeg": "jpg", "image/png": "png", "image/webp": "webp" }[file.type];
      show(await api.addEvidence({
        order_id: String(delivery.order_id), evidence_type: pendingEvidenceType,
        file_reference: `pending-upload/evidence/${delivery.order_id}/${checksum.slice(0, 24)}.${extension}`,
        mime_type: file.type, byte_size: file.size, checksum_sha256: checksum,
      }));
      toast("Đã ghi nhận ảnh bằng chứng.");
    } catch (error) {
      toast(error.message);
    }
  }

  // ---------- GPS ----------
  // Chỉ theo dõi vị trí khi đang có đơn; hết đơn thì dừng để không thu thập vị trí ngoài giờ giao.
  function syncGps() {
    const tracking = delivery && ["SHIPPER_ASSIGNED", "PICKED_UP", "IN_DELIVERY", "RETURNING"].includes(delivery.status) && !delivery.returned_at;
    if (!tracking) {
      if (gpsWatch !== null) navigator.geolocation.clearWatch(gpsWatch);
      gpsWatch = null;
      return;
    }
    if (gpsWatch !== null || !navigator.geolocation) return;
    gpsWatch = navigator.geolocation.watchPosition((p) => {
      if (Date.now() - lastGpsSentAt < GPS_INTERVAL_MS) return;
      lastGpsSentAt = Date.now();
      api.sendLocation({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy_meters: Math.round(p.coords.accuracy) })
        .catch((error) => { if (error.code === "NO_ACTIVE_DELIVERY") load(); });
    }, () => {}, { enableHighAccuracy: true, maximumAge: 10000 });
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.addEventListener("click", (event) => {
      const action = event.target.closest("[data-delivery-action]");
      if (action && delivery) return act(action.dataset.deliveryAction, action);
      const evidence = event.target.closest("[data-evidence]");
      if (evidence) {
        pendingEvidenceType = evidence.dataset.evidence;
        document.getElementById("evidenceFile")?.click();
      }
      const filter = event.target.closest("[data-history-filter]");
      if (filter) renderHistory(filter.dataset.historyFilter);
    });
    document.getElementById("evidenceFile")?.addEventListener("change", (event) => {
      submitEvidence(event.target.files[0]);
      event.target.value = "";
    });
    load();
    if (document.getElementById("historyRows")) {
      api.history().then((result) => { historyRows = result.items; renderHistory(); }).catch((error) => toast(error.message));
    }
  });
})();
