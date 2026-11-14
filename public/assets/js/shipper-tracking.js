(() => {
  'use strict';
  const panel = document.querySelector('[data-delivery-map-panel]');
  const gps = panel?.querySelector('[data-gps-status]') || document.createElement('p');
  const routeStatus = panel?.querySelector('[data-route-status]') || document.createElement('p');
  const badge = panel?.querySelector('[data-gps-badge]') || document.createElement('span');
  let order = null, map = null, watch = null, timer = null, latest = null;
  let sending = false, lastSent = 0, routeOrder = null, revision = 0;
  const active = d => d && ['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'].includes(d.status) && !d.returned_at;
  const stops = () => [
    {id:'pickup',label:'Điểm lấy hàng',latitude:order.pickup_latitude,longitude:order.pickup_longitude},
    {id:'delivery',label:'Điểm giao hàng',latitude:order.delivery_latitude,longitude:order.delivery_longitude}
  ];
  function paintPosition() {
    if (order) map?.points(latest ? [...stops(),{...latest,id:'user',label:'Bạn đang ở đây'}] : stops());
  }
  function stop() {
    if (watch !== null) navigator.geolocation?.clearWatch(watch);
    watch = null; clearInterval(timer); timer = null;
  }
  function locationError(error) {
    badge.textContent = 'GPS chưa sẵn sàng';
    gps.textContent = error.code === 1
      ? 'Chưa được phép truy cập GPS. Hãy cho phép vị trí trong trình duyệt để khách hàng theo dõi chuyến giao.'
      : 'Chưa lấy được vị trí hiện tại. Kiểm tra GPS và kết nối mạng; hệ thống sẽ tự thử lại.';
    gps.dataset.state = 'warning';
  }
  async function sendPosition(point) {
    if (!active(order)) return;
    latest = {latitude:point.coords.latitude,longitude:point.coords.longitude,accuracy_meters:Math.round(point.coords.accuracy)};
    paintPosition();
    if (sending || Date.now() - lastSent < 15000) return;
    const token = revision;
    sending = true;
    try {
      const result = await window.SomiShipper.sendLocation(latest);
      if (token !== revision) return;
      lastSent = Date.now();
      if(result.arrived_at&&!order.arrived_at)document.dispatchEvent(new CustomEvent('somi:delivery-refresh'));
      gps.textContent = 'Đang chia sẻ vị trí · Cập nhật lúc ' + new Date(result.recorded_at || Date.now()).toLocaleTimeString('vi-VN');
      gps.dataset.state = 'live';
      badge.textContent = 'Đang chia sẻ GPS';
    } catch (error) {
      if (token !== revision) return;
      gps.textContent = error.code === 'LOCATION_RATE_LIMITED' ? 'Đang chờ lượt cập nhật vị trí tiếp theo…' : 'Chưa gửi được GPS: ' + error.message;
      gps.dataset.state = 'warning';
      badge.textContent = 'GPS gián đoạn';
      if (error.code === 'NO_ACTIVE_DELIVERY') document.dispatchEvent(new CustomEvent('somi:delivery-refresh'));
    } finally { sending = false; }
  }
  function start() {
    if (!active(order) || watch !== null) return;
    if (!navigator.geolocation) { gps.textContent = 'Trình duyệt không hỗ trợ GPS.'; return; }
    watch = navigator.geolocation.watchPosition(sendPosition,locationError,{enableHighAccuracy:true,maximumAge:5000,timeout:15000});
    // Stationary devices may not emit watchPosition callbacks; request a fresh fix periodically.
    timer = setInterval(() => navigator.geolocation.getCurrentPosition(sendPosition,locationError,{enableHighAccuracy:true,maximumAge:5000,timeout:12000}),15000);
  }
  document.addEventListener('somi:delivery-update', async event => {
    const next = event.detail;
    if (String(next?.order_id) !== String(order?.order_id)) {
      revision++; stop(); latest = null; lastSent = 0; routeOrder = null;
      map?.clearRoute();
    }
    order = next;
    if (panel) panel.hidden = !order;
    if (!order) { stop(); return; }
    if (!panel) { if (active(order)) start(); else stop(); return; }
    if (!map) map = window.SomiMaps?.create(panel.querySelector('#shipperDeliveryMap'));
    paintPosition();
    const pickup = order.status === 'SHIPPER_ASSIGNED' || order.status === 'RETURNING';
    panel.querySelector('[data-navigation-step]').textContent = order.status === 'RETURNING' ? 'Quay về điểm lấy để hoàn hàng' : pickup ? 'Di chuyển đến điểm lấy hàng' : order.arrived_at ? 'Đã đến nơi · Chụp ảnh minh chứng và xác nhận giao hàng' : 'Di chuyển đến điểm giao hàng';
    const lat = pickup ? order.pickup_latitude : order.delivery_latitude;
    const lng = pickup ? order.pickup_longitude : order.delivery_longitude;
    panel.querySelector('[data-open-navigation]').href = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(lat + ',' + lng) + '&travelmode=driving';
    if (active(order)) start(); else { stop(); gps.textContent = 'Đã dừng chia sẻ vị trí cho đơn này.'; badge.textContent = 'Đã dừng GPS'; }
    if (!map || routeOrder === String(order.order_id)) return;
    const token = revision; routeOrder = String(order.order_id);
    routeStatus.textContent = 'Đang tải tuyến đường…';
    try {
      const response = await fetch('public/api/shipper/route.php?order_id=' + encodeURIComponent(order.order_id),{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(20000)});
      const data = await response.json();
      if (token !== revision) return;
      if (!response.ok) throw new Error('Tuyến đường chưa sẵn sàng.');
      map.route(data.route); routeStatus.textContent = 'Tuyến lấy hàng → giao hàng. Dùng “Mở chỉ đường” để dẫn đường đến điểm tiếp theo.';
    } catch (error) { if (token === revision) routeStatus.textContent = 'Chưa tải được tuyến đường. Các điểm lấy/giao vẫn hiển thị; bạn có thể mở chỉ đường.'; }
  });
  window.addEventListener('pagehide',stop);
  window.addEventListener('pageshow',e=>{if(e.persisted)start();});
})();
