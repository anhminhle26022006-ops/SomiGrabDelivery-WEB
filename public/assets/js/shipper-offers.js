"use strict";
// Trạng thái nhận đơn và danh sách offer, nối với API thật (thay cho mảng orders demo cũ).
(() => {
  const api = window.SomiShipper;
  const { esc, money, toast } = api;
  const POLL_MS = 5000;        // hỏi offer mới mỗi 5 giây khi đang bật nhận đơn
  const HEARTBEAT_MS = 120000; // gửi lại vị trí mỗi 2 phút để hệ thống ghép đơn theo vị trí còn mới

  let available = false;
  let offers = [];
  let offerNotice = null;
  let pollTimer = null;
  let heartbeatTimer = null;
  let loadPending = false;
  let heartbeatTask = null;
  let toggling = false;
  let stateVersion = 0;
  let restoreVersion = 0;
  let feedMessage = '';
  let feedError = false;
  let offersRendered = false;
  const online = value => [true, 1, '1', 't', 'true'].includes(value);

  const BLOCKERS = {
    ACCOUNT_NOT_ACTIVE: "tài khoản chưa ở trạng thái hoạt động",
    KYC_NOT_APPROVED: "hồ sơ đang chờ Admin duyệt",
    IDENTITY_NOT_APPROVED: "giấy tờ định danh chưa được duyệt",
    SCORE_LOCKED: "điểm tin cậy thấp hơn ngưỡng cho phép",
  };

  // ---------- Trạng thái nhận đơn ----------
  function paintAvailability(state) {
    const next = online(state.availability);
    if (available !== next) stateVersion++;
    available = next;
    const on = available;
    document.getElementById("availabilityToggle")?.classList.toggle("on", on);
    const toggle = document.getElementById("availabilityToggle");
    if (toggle) { toggle.disabled = false; toggle.setAttribute('aria-pressed', String(on)); }
    for (const id of ["availabilityPill", "offerAvailability"]) {
      const pill = document.getElementById(id);
      if (!pill) continue;
      pill.dataset.online=String(on);
      pill.textContent = on ? "ĐANG NHẬN ĐƠN" : "ĐÃ TẮT NHẬN ĐƠN";
      pill.style.background = on ? "#e6f8ee" : "#eef1f5";
      pill.style.color = on ? "#07864b" : "#64748b";
    }
    const help = document.getElementById("availabilityHelp");
    if (help) {
      help.textContent = !state.can_work
        ? "Chưa thể bật nhận đơn: " + state.blockers.map((b) => BLOCKERS[b] || b).join(", ") + "."
        : on ? (state.location_fresh === false ? "GPS chưa có hoặc đã cũ. Cập nhật vị trí để hệ thống tìm đơn trong bán kính 5 km." : "Đang tìm đơn trong bán kính 5 km. Hãy giữ quyền vị trí và mở trang khi nhận đơn.") : "Bạn sẽ không nhận đơn mới khi trạng thái đang tắt.";
    }
    const refreshLocation = document.getElementById('refreshMatchingLocation');
    if (refreshLocation) refreshLocation.hidden = !on;
    clearInterval(pollTimer);
    clearInterval(heartbeatTimer);
    if (on) {
      pollTimer = setInterval(loadOffers, POLL_MS);
      heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS);
      loadOffers();
    } else {
      offers = [];
      renderOffers();
      feedStatus('');
    }
  }

  async function toggleAvailability() {
    const button = document.getElementById("availabilityToggle");
    if (toggling) return;
    toggling = true;
    restoreVersion++;
    button.disabled = true;
    try {
      if (heartbeatTask) await heartbeatTask;
      if (available) {
        paintAvailability(await api.setAvailability({ available: false }));
        toast("Đã tắt nhận đơn.");
      } else {
        const point = await api.position();
        paintAvailability(await api.setAvailability({ available: true, latitude: point.latitude, longitude: point.longitude }));
        toast("Đã bật nhận đơn.");
      }
    } catch (error) {
      toast(error.message);
    } finally {
      toggling = false;
      button.disabled = false;
      if (available) loadOffers();
    }
  }

  function heartbeat() {
    if (!available || toggling || heartbeatTask) return heartbeatTask;
    const version = stateVersion;
    heartbeatTask = (async () => {
      try {
        const point = await api.position();
        if (!available || toggling || version !== stateVersion) return;
        const state = await api.setAvailability({ available: true, latitude: point.latitude, longitude: point.longitude });
        if (available && version === stateVersion && !toggling) paintAvailability(state);
        if (available && version === stateVersion) await loadOffers();
      } catch (error) {
        if (error.code === "SHIPPER_NOT_ELIGIBLE") api.availability().then(paintAvailability).catch(() => {});
        else {
          const help = document.getElementById("availabilityHelp");
          if (help) help.textContent = "Không cập nhật được GPS: " + error.message;
        }
      } finally { heartbeatTask = null; }
    })();
    return heartbeatTask;
  }

  // ---------- Danh sách offer ----------
  function feedStatus(message, error = false) {
    feedMessage = message; feedError = error;
    for (const id of ['allOrders', 'overviewOrders']) {
      const target = document.getElementById(id); if (!target) continue;
      let status = target.previousElementSibling;
      if (!status?.hasAttribute('data-offer-feed-status')) {
        status = document.createElement('p'); status.dataset.offerFeedStatus = '';
        status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        target.before(status);
      }
      status.className = error ? 'alert alert-warning' : 'muted';
      status.textContent = message; status.hidden = !message;
    }
  }

  function removalMessage(previous, result) {
    const removed = previous.filter(old => !result.items.some(item => String(item.offer_id) === String(old.offer_id)));
    if (!removed.length) return '';
    return removed.map(old => {
      const update = (result.recent_updates || []).find(item => String(item.offer_id) === String(old.offer_id));
      const reason = update?.response_note==='SEARCH_TIMEOUT'?'đã hết 5 phút tìm shipper, hệ thống xử lý hủy và hoàn tiền':{EXPIRED:'hết thời gian nhận lời mời',LOST_RACE:'đã có shipper khác nhận',CANCELLED:'lời mời đã bị hủy',REJECTED:'bạn đã từ chối lời mời',ACCEPTED:'bạn đã nhận đơn',ORDER_UNAVAILABLE:'đơn không còn tìm shipper'}[update?.status] || 'lời mời không còn khả dụng';
      return `${old.order_code}: ${reason}.`;
    }).join(' ');
  }

  async function loadOffers() {
    if (!available || toggling || loadPending || document.hidden) return;
    if (!document.getElementById("allOrders") && !document.getElementById("overviewOrders")) return;
    const version = stateVersion;
    loadPending = true;
    try {
      const state = await api.availability();
      if (version !== stateVersion || toggling) return;
      if (online(state.availability) !== available || !state.can_work) {
        paintAvailability(state);
        return;
      }
      const result = await api.refreshOffers();
      if (!available || toggling || version !== stateVersion) return;
      if (!Array.isArray(result.items)) throw new Error('Máy chủ chưa trả được danh sách đơn hợp lệ. Đang thử lại.');
      const changed = removalMessage(offers, result);
      // Polling must not replace an unchanged button under a tap.
      const content = items => JSON.stringify(items.map(({seconds_left, ...item}) => item));
      const unchanged = offersRendered && content(offers) === content(result.items);
      offers = result.items;
      offerNotice = result.notice;
      if (!unchanged) renderOffers();
      feedStatus(changed || (offers.length || feedError ? '' : feedMessage));
    } catch (error) {
      if (error.status === 401) clearInterval(pollTimer);
      if (version === stateVersion && !toggling) {
        if (error.status === 401 || error.status === 403) {
          offers = []; renderOffers();
        }
        feedStatus(error.message + (error.status === 401 || error.status === 403 ? '' : ' Danh sách sẽ tự tải lại; các đơn đang chờ vẫn được giữ.'), true);
      }
    } finally { loadPending = false; }
  }

  function earningText(offer) {
    return offer.estimated_earning_minor === null ? "Chưa công bố" : money(offer.estimated_earning_minor);
  }

  function pickupSchedule(offer){const s=offer.pickup_schedule;if(s?.mode!=='later')return '';const label=new Intl.DateTimeFormat(document.body.dataset.shipperLang==='en'?'en-US':'vi-VN',{dateStyle:'short',timeStyle:'short'}).format(new Date(s.at));return `<p class="s-pickup-schedule">Hẹn lấy hàng: <strong>${esc(label)}</strong></p>`;}
  function offerCard(offer) {
    const id = esc(offer.offer_id);
    return `<article class="order" data-offer="${id}">
      <div class="orderhead"><div><span class="orderid">${esc(offer.order_code)}</span><span class="tag">Đang chờ nhận đơn</span></div>
        <span class="muted" style="font-size:11px">Cách điểm lấy hàng ${esc(api.distance(offer.distance_to_pickup_km))} km</span></div>
      <div class="route"><div class="routepoints"><span class="dot"></span><span class="routebar"></span><span class="dot red"></span></div>
        <div class="address"><strong>Điểm lấy hàng</strong><small>${esc(offer.pickup_address)}</small><div style="height:12px"></div><strong>Điểm giao hàng</strong><small>${esc(offer.delivery_address)}</small></div>
        <div class="pay"><small>Thu nhập dự kiến</small><strong>${esc(earningText(offer))}</strong><small class="feehint">Đối soát sau khi giao thành công</small></div></div>
      ${pickupSchedule(offer)}<div class="orderfoot"><div class="mini">⌖ <span><b>${esc(api.distance(offer.distance_km))} km</b><br>Quãng đường</span></div>
        <div class="mini">◷ <span><b>${esc(offer.estimated_duration_minutes ?? "?")} phút</b><br>Ước tính</span></div>
        <div class="mini">▣ <span><b>${esc(offer.category_name_vi)}</b><br>${esc(offer.estimated_weight_kg)} kg</span></div>
        <div class="orderactions"><button class="outline" data-offer-detail="${id}">Xem chi tiết</button><button class="outline rejectbtn" data-offer-reject="${id}">Từ chối</button><button class="primary blue" data-offer-accept="${id}">Nhận đơn</button></div></div>
    </article>`;
  }

  function sorted() {
    const list = [...offers];
    const sort = document.getElementById("offerSort")?.value;
    if (sort === "pay") list.sort((a, b) => Number(b.estimated_earning_minor ?? 0) - Number(a.estimated_earning_minor ?? 0));
    if (sort === "distance") list.sort((a, b) => Number(a.distance_to_pickup_km) - Number(b.distance_to_pickup_km));
    return list;
  }

  function renderOffers() {
    offersRendered = true;
    const list = sorted();
    const empty = `<div class="empty"><strong>Chưa có đơn phù hợp</strong>${available ? (offerNotice === "OWN_ORDER_EXCLUDED" ? "Bạn không thể nhận đơn do chính tài khoản này tạo. Hãy dùng tài khoản Shipper khác đã được duyệt và bật GPS gần điểm lấy hàng." : "Chỉ hiện đơn đã thanh toán, trong bán kính 5 km và phù hợp với hồ sơ. Đơn hẹn giờ xuất hiện trước giờ lấy 30 phút. Hãy giữ GPS và trạng thái nhận đơn bật.") : "Hãy bật trạng thái nhận đơn để nhận đơn mới."}</div>`;
    const all = document.getElementById("allOrders");
    if (all) all.innerHTML = list.length ? list.map(offerCard).join("") : empty;
    const overview = document.getElementById("overviewOrders");
    if (overview) overview.innerHTML = list.length ? list.slice(0, 2).map(offerCard).join("") : empty;
  }

  function openDetail(offer) {
    const body = document.getElementById("orderDetailBody");
    if (!body || !offer) return;
    const id = esc(offer.offer_id);
    document.getElementById("orderDetailTitle").textContent = "Chi tiết đơn " + offer.order_code;
    body.innerHTML = `${pickupSchedule(offer)}<div class="detail-summary"><div><h3>${esc(offer.order_code)}</h3><p>${esc(api.distance(offer.distance_km))} km, khoảng ${esc(offer.estimated_duration_minutes ?? "?")} phút</p></div>
        <div class="detail-earning"><small>Thu nhập dự kiến</small><b>${esc(earningText(offer))}</b><span>Đối soát sau khi giao thành công</span></div></div>
      <div class="detail-grid"><section class="detailbox"><h3>Hàng vận chuyển</h3><b>${esc(offer.category_name_vi)}</b><span>${esc(offer.estimated_weight_kg)} kg</span><p>${esc(offer.parcel_description || "Không có mô tả thêm.")}</p></section>
        <section class="detailbox"><h3>Liên hệ</h3><p>Tên và số điện thoại người gửi, người nhận chỉ hiển thị sau khi bạn nhận đơn.</p></section></div>
      <section class="detailbox locationbox"><h3>Địa điểm</h3><div class="address"><strong>Điểm lấy hàng</strong><small>${esc(offer.pickup_address)}</small><div style="height:12px"></div><strong>Điểm giao hàng</strong><small>${esc(offer.delivery_address)}</small></div></section>
      <div class="detail-actions"><button class="outline rejectbtn" data-offer-reject="${id}">Từ chối đơn</button><button class="primary blue" data-offer-accept="${id}">Nhận đơn</button></div>`;
    document.getElementById("orderDetailModal").classList.remove("hidden");
  }

  async function accept(offerId, button) {
    button.disabled = true;
    try {
      await api.acceptOffer(offerId);
      stateVersion++; // An older poll must not resurrect the invitation after acceptance.
      document.dispatchEvent(new CustomEvent('somi:delivery-refresh'));
      offers = []; renderOffers();
      feedStatus('');
      window.closeOrderDetail?.();
      toast("Đã nhận đơn. Mở Đơn đang giao để bắt đầu.");
      window.go?.("active");
    } catch (error) {
      toast(error.message);
      loadOffers(); // đơn có thể đã hết hạn hoặc người khác nhận: tải lại danh sách thật
    } finally {
      button.disabled = false;
    }
  }

  async function reject(offerId, button) {
    button.disabled = true;
    try {
      await api.rejectOffer(offerId);
      stateVersion++;
      window.closeOrderDetail?.();
      offers = offers.filter((offer) => String(offer.offer_id) !== String(offerId));
      renderOffers();
      toast("Đã từ chối đơn.");
    } catch (error) {
      toast(error.message);
      loadOffers();
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("availabilityToggle")?.addEventListener("click", toggleAvailability);
    document.getElementById('refreshMatchingLocation')?.addEventListener('click', async event => {
      const button = event.currentTarget; button.disabled = true;
      try { await heartbeat(); } finally { button.disabled = false; }
    });
    document.getElementById("offerSort")?.addEventListener("change", renderOffers);
    document.addEventListener("click", (event) => {
      const button = event.target.closest("[data-offer-accept],[data-offer-reject],[data-offer-detail]");
      if (!button) return;
      if (button.dataset.offerAccept) accept(button.dataset.offerAccept, button);
      else if (button.dataset.offerReject) reject(button.dataset.offerReject, button);
      else openDetail(offers.find((offer) => String(offer.offer_id) === button.dataset.offerDetail));
    });
    // Ignore reads started before a toggle or a newer restore request.
    async function restoreAvailability() {
      if (toggling) return;
      const request = ++restoreVersion;
      const version = stateVersion;
      try {
        const state = await api.availability();
        if (request !== restoreVersion || version !== stateVersion || toggling) return;
        paintAvailability(state);
        if (available) heartbeat();
      } catch (error) {
        if (request !== restoreVersion || toggling) return;
        const help = document.getElementById('availabilityHelp');
        if (help) help.textContent = error.message;
        toast(error.message);
      }
    }
    restoreAvailability();
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) restoreAvailability();
    });
    window.addEventListener('pageshow', event => {
      if (event.persisted) restoreAvailability();
    });
    window.addEventListener('online', () => { if (available) { heartbeat(); loadOffers(); } });
    window.addEventListener('pagehide', () => {
      clearInterval(pollTimer); clearInterval(heartbeatTimer);
      // Leave persisted availability unchanged when navigating or reloading.
    });
  });
})();
