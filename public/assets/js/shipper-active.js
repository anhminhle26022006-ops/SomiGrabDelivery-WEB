"use strict";
// Đơn đang giao: các mốc giao nhận, ảnh bằng chứng, hoàn hàng, GPS và lịch sử. Nối với API thật.
(() => {
  const api = window.SomiShipper;
  const { esc, toast } = api;

  const t=(vn,en)=>(window.SomiI18n?.lang||document.documentElement.lang)==='en'?en:vn;
  let cancelSubmission=null;
  let delivery;
  let loadPending = false, dataVersion = 0, pollTimer = null;
  let historyRows = [];
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
    const photos = delivery.evidence?.length || 0;
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
    const overview = document.getElementById("overviewDelivery");
    if (overview) {
      overview.innerHTML = delivery
        ? `<div class="orderid">${esc(delivery.order_code)} <span class="tag">${esc(STATUS[delivery.status] || delivery.status)}</span></div>
          <div class="address" style="margin-top:12px"><strong>Điểm lấy hàng</strong><small>${esc(delivery.pickup_address)}</small><strong>Điểm giao hàng</strong><small>${esc(delivery.delivery_address)}</small></div>
          ${progress(delivery.status)}<button class="primary" data-goto="active">Mở đơn đang giao →</button>`
        : `<div class="empty"><strong>Bạn chưa có đơn đang giao</strong>Nhận một đơn ở mục Đơn giao phù hợp để bắt đầu.</div>`;
    }
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
        <button class="outline" type="button" data-open-order-chat>${esc(t('Chat với khách hàng','Chat with customer'))}</button>
        ${actionButtons(d.next_actions)}
        ${['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY'].includes(d.status)&&d.cancellation_request?.decision!=='PENDING'?`<button class="outline" type="button" data-delivery-action="SHOW_CANCEL">${esc(t('Yêu cầu hủy / dừng đơn','Request cancellation / stop'))}</button>`:''}
      </div>
      ${d.cancellation_request?`<p role="status">${esc(t('Yêu cầu gần nhất: ','Latest request: '))}${esc(d.cancellation_request.decision)} ${esc(d.cancellation_request.decision_note||'')}</p>`:''}
      <form id="cancelDeliveryPanel" class="hidden delivery-request-form" data-shipper-cancel-form>
        <div class="delivery-form-head"><h3>Yêu cầu hủy / dừng đơn</h3><button type="button" data-delivery-action="SHOW_CANCEL" aria-label="Đóng yêu cầu">✕</button></div>
        <p>${esc(t('Nếu chưa lấy hàng, bạn có thể hủy nhận đơn để hệ thống tìm shipper khác. Điểm tin cậy được tính theo quy định. Nếu đã lấy hàng hoặc gặp sự cố khách quan, hãy gửi yêu cầu và giữ hàng an toàn trong lúc chờ duyệt.','Before pickup: cancelling due to your fault deducts policy points and restarts matching. External issues or cancellation after pickup require Admin review; keep the parcel safe.'))}</p>
        <label>${esc(t('Phân loại','Reason type'))}<select name="reason_code" class="outline"><option value="SHIPPER_FAULT">${esc(t('Lỗi của tôi','My fault'))}</option><option value="EXTERNAL_ISSUE">${esc(t('Sự cố khách quan, cần xác minh','External issue requiring review'))}</option></select></label>
        <label style="display:block">${esc(t('Lý do','Reason'))}<textarea name="reason" required maxlength="1000" style="display:block;width:100%"></textarea></label>
        <label><input type="checkbox" name="acknowledged" required> ${esc(t('Tôi đã đọc điều kiện và xác nhận nội dung trên','I have read the conditions and confirm these details'))}</label>
        <button class="primary" type="submit" data-cancellation-submit>${esc(t('Gửi yêu cầu','Submit request'))}</button>
      </form>
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
    dataVersion++;
    if (JSON.stringify(delivery) === JSON.stringify(result.delivery)) return;
    delivery = result.delivery;
    document.dispatchEvent(new CustomEvent("somi:delivery-chat", {detail:delivery}));
    render();
    syncGps();
  }

  async function load() {
    try {
      if (loadPending) return;
      loadPending = true;
      const version = dataVersion;
      const result = await api.currentDelivery();
      if (version === dataVersion) show(result);
    } catch (error) {
      const overview = document.getElementById("overviewDelivery");
      if (overview) overview.textContent = error.message;
      if (!delivery) toast(error.message);
    } finally { loadPending = false; }
  }

  async function act(action, button) {
    if (action === "SHOW_CANCEL") return document.getElementById("cancelDeliveryPanel")?.classList.toggle("hidden");
    if (action === "SHOW_FAIL") return document.getElementById("failPanel")?.classList.toggle("hidden");
    const orderId = delivery.order_id;
    dataVersion++;
    button.disabled = true;
    try {
      if(action==='REQUEST_CANCEL'){
        const form=document.querySelector('[data-shipper-cancel-form]');if(!form.reportValidity())return;
        const body={order_id:String(orderId),reason_code:form.elements.reason_code.value,reason:form.elements.reason.value.trim()};
        const fingerprint=JSON.stringify(body);if(cancelSubmission?.fingerprint!==fingerprint)cancelSubmission={fingerprint,key:crypto.randomUUID()};
        const result=await api.requestCancellation({...body,client_key:cancelSubmission.key});
        toast(result.request.decision==='PENDING'?t('Đã gửi yêu cầu, chờ Admin duyệt.','Request sent for Admin review.'):t('Đã hủy phân công. Đơn được tìm Shipper khác.','Assignment cancelled. Matching restarted.'));await load();
      } else if (action === "PICKUP" || action === "START_DELIVERY") show(await api.deliveryAction(orderId, action));
      else if (action === "FAIL") {
        const reason = document.getElementById("failureReason").value;
        const note = document.getElementById("failureNote").value.trim();
        if (reason === "OTHER" && !note) return toast("Vui lòng ghi rõ lý do.");
        show(await api.failDelivery(orderId, reason, note));
        toast("Đã ghi nhận giao thất bại. Vui lòng hoàn hàng về điểm lấy.");
      } else if (action === "DELIVERED") {
        const result=await api.completeDelivery(orderId);
        toast(result.settlement?.payout_id?t('Đã giao thành công, yêu cầu chi trả đang chờ chuyển khoản thủ công.','Delivered. Payout is awaiting manual transfer.'):t('Đã giao thành công. Xem thu nhập và kiểm tra tài khoản ngân hàng đã xác minh.','Delivered. Review earnings and verify your payout bank account.'));
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
  // Upload ảnh riêng tư trước; backend nhận file_id và tự xác minh owner/đơn.
  async function submitEvidence(file) {
    if (!file || !delivery || !pendingEvidenceType) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) return toast("Chỉ nhận ảnh JPG, PNG hoặc WEBP.");
    if (file.size > 5 * 1024 * 1024) return toast("Ảnh không được lớn hơn 5 MiB.");
    try {
      const uploaded=await api.uploadPrivate(file,'EVIDENCE',delivery.order_id);
      show(await api.addEvidence({
        order_id: String(delivery.order_id), evidence_type: pendingEvidenceType,
        reference_file_id: uploaded.file_id,
      }));
      toast("Đã ghi nhận ảnh bằng chứng.");
    } catch (error) {
      toast(error.message);
    }
  }

  // ---------- GPS ----------
  // Chỉ theo dõi vị trí khi đang có đơn; hết đơn thì dừng để không thu thập vị trí ngoài giờ giao.
  function syncGps() {
    document.dispatchEvent(new CustomEvent('somi:delivery-update', {detail: delivery}));
  }

  document.addEventListener('submit',e=>{if(!e.target.matches('[data-shipper-cancel-form]'))return;e.preventDefault();const button=e.target.querySelector('[data-cancellation-submit]');if(!button.disabled)act('REQUEST_CANCEL',button);});

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
    pollTimer = setInterval(() => { if (!document.hidden) load(); }, 5000);
    document.addEventListener('somi:delivery-refresh', load);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
    window.addEventListener('online', load);
    window.addEventListener('pagehide', () => clearInterval(pollTimer));
    window.addEventListener('pageshow', e => { if (e.persisted) { load(); pollTimer = setInterval(() => { if (!document.hidden) load(); }, 5000); } });
    if (document.getElementById("historyRows")) {
      api.history().then((result) => { historyRows = result.items; renderHistory(); }).catch((error) => toast(error.message));
    }
  });
})();
