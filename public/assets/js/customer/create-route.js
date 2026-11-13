/* Shared pickup/delivery map. Location permission never blocks manual entry. */
(() => {
  'use strict';
  window.SomiCreateRoute = {attach({root, form, addresses, profile, api, t, onChange}) {
    const $ = selector => root.querySelector(selector);
    const field = name => form.elements.namedItem(name);
    const states = {}, lookups = [];
    let active = 'pickup', user = null, currentAddress = null, locationVersion = 0;
    let routeVersion = 0, disposed = false, confirmed = null, locationPending = false;
    const language = document.documentElement.lang === 'vi' ? 'vi' : 'en';
    const distance = (a, b) => {
      const r = Math.PI / 180, x = (b.latitude-a.latitude)*r, y = (b.longitude-a.longitude)*r;
      const h = Math.sin(x/2)**2 + Math.cos(a.latitude*r)*Math.cos(b.latitude*r)*Math.sin(y/2)**2;
      return 6371*2*Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1-h)));
    };
    const formatKm = km => km.toLocaleString(language === 'vi' ? 'vi-VN' : 'en-US', {maximumFractionDigits: 2});
    function position(side) {
      const lat = field(side+'-latitude'), lng = field(side+'-longitude');
      return lat.value !== '' && lng.value !== '' && lat.checkValidity() && lng.checkValidity()
        ? {latitude: Number(lat.value), longitude: Number(lng.value)} : null;
    }
    const locationKey = () => JSON.stringify([user, position('pickup'), field('pickup-detail').value, $('#pickup-saved').value]);
    function render() {
      const pickup = position('pickup'), delivery = position('delivery'), items = [];
      if (user) items.push({id:'user', ...user, label:t('Bạn đang ở đây', 'You are here')});
      if (pickup) items.push({id:'pickup', ...pickup, label:t('A · Điểm lấy', 'A · Pickup')});
      if (delivery) items.push({id:'delivery', ...delivery, label:t('B · Điểm giao', 'B · Delivery')});
      map?.points(items);
      $('[data-map-use-location]').hidden = !!pickup || !currentAddress;
      $('[data-map-use-location]').disabled = locationPending;
      root.querySelectorAll('[data-map-side]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mapSide === active)));
      $('[data-active-point-title]').textContent = active === 'pickup' ? t('Điểm lấy hàng', 'Pickup location') : t('Điểm giao hàng', 'Delivery location');
      $('[data-active-point-address]').textContent = position(active) ? field(active+'-detail').value : t('Tìm và chọn địa chỉ để xác định vị trí.', 'Search and select an address to set its location.');
      const notice = $('[data-pickup-distance]'), km = user && pickup ? distance(user, pickup) : null;
      notice.hidden = km === null || km <= 1 + 1e-9;
      notice.textContent = confirmed === locationKey() ? t('Đã xác nhận lấy hàng tại địa chỉ khác vị trí của bạn.', 'Pickup at a different location confirmed.') : t('Điểm lấy cách vị trí của bạn ', 'Pickup is ')+ (km === null ? '' : formatKm(km)) + t(' km. Bạn sẽ cần xác nhận khi tiếp tục.', ' km from you. Please confirm when continuing.');
    }
    async function updateRoute() {
      const version = ++routeVersion;
      map?.clearRoute();
      $('[data-route-summary]').hidden = true;
      $('[data-route-distance]').textContent = '';
      $('[data-route-duration]').textContent = '';
      render();
      const pickup = position('pickup'), delivery = position('delivery');
      if (!pickup || !delivery) {
        $('[data-route-status]').textContent = !pickup && delivery
          ? t('Đã có điểm giao B. Chọn điểm lấy A: dùng vị trí hiện tại hoặc tìm địa chỉ lấy hàng để nối tuyến và tính quãng đường.', 'Delivery B is selected. Set pickup A using your current location or a pickup address to draw the route and calculate its distance.')
          : pickup && !delivery
          ? t('Đã có điểm lấy A. Tìm và chọn điểm giao B để nối tuyến và tính quãng đường.', 'Pickup A is selected. Search and select delivery B to draw the route and calculate its distance.')
          : t('Chọn điểm lấy A và điểm giao B để xem tuyến đường và số km. Chấm xanh chỉ là vị trí hiện tại của bạn.', 'Select pickup A and delivery B to see the route and distance. The blue dot shows your current location.');
        return;
      }
      $('[data-route-status]').textContent = t('Đang tìm tuyến xe máy…', 'Finding a motorcycle route…');
      try {
        const data = await api('customer/places.php', 'POST', {action:'route', pickup, delivery, language});
        if (disposed || version !== routeVersion) return;
        map?.route(data.route);
        const meters = data.route.properties.distance_m, seconds = data.route.properties.duration_s;
        if (typeof meters !== 'number' || !Number.isFinite(meters) || meters < 0 || typeof seconds !== 'number' || !Number.isFinite(seconds) || seconds < 0) throw new Error('Invalid route summary');
        $('[data-route-distance]').textContent = formatKm(meters/1000) + ' km';
        $('[data-route-duration]').textContent = t('Khoảng ', 'About ') + Math.max(1,Math.ceil(seconds/60)) + t(' phút', ' min');
        $('[data-route-summary]').hidden = false;
        $('[data-route-status]').textContent = map ? t('Quãng đường theo tuyến xe máy A → B, không phải đường chim bay. Phí giao được báo sau khi nhập kiện hàng.', 'Road distance along the motorcycle route A → B, not straight-line distance. Delivery fees are quoted after parcel details.') : t('Đã tính quãng đường. Bản đồ chưa sẵn sàng để hiển thị tuyến.', 'Road distance calculated. The map is currently unavailable to display the route.');
      } catch {
        if (version === routeVersion && !disposed) $('[data-route-status]').textContent = t('Chưa tải được tuyến đường. Hai địa chỉ vẫn được giữ; server sẽ kiểm tra tuyến khi báo giá.', 'The route could not be loaded. Your addresses are preserved; routing will be checked when requesting a quote.');
      }
    }
    function changed() { onChange(); updateRoute(); }
    function clear(side) {
      const s = states[side]; s.version++; s.address = null;
      field(side+'-latitude').value = ''; field(side+'-longitude').value = '';
      s.saved.value = ''; field(side+'-detail').readOnly = false;
      confirmed = null;
      changed();
    }
    function apply(side, address, savedId = '') {
      const s = states[side]; s.version++; s.lookup.reset(); s.address = address;
      s.saved.value = savedId; field(side+'-detail').readOnly = false;
      field(side+'-detail').value = address.address_detail;
      field(side+'-latitude').value = address.latitude;
      field(side+'-longitude').value = address.longitude;
      s.area.options[0].textContent = address.province || 'TP.HCM';
      active = side; confirmed = null; changed();
    }
    const map = window.SomiMaps?.create($('[data-create-map]'), {onUnavailable: () => {
      if (!disposed) $('[data-route-status]').textContent = t('Chưa tải được nền bản đồ. Bạn vẫn có thể tìm và chọn địa chỉ.', 'Map tiles could not be loaded. You can still search and select addresses.');
    }, onPick: async p => {
      const side = active, s = states[side];
      s.lookup.reset(); clear(side); const version = s.version;
      $('[data-route-status]').textContent = t('Đang xác định địa chỉ tại ghim…', 'Finding the address at this pin…');
      try {
        const data = await api('customer/places.php', 'POST', {action:'reverse', latitude:p.lat.toFixed(7), longitude:p.lng.toFixed(7), language});
        if (disposed || s.version !== version) return;
        apply(side, data.address);
      } catch {
        if (!disposed && s.version === version) $('[data-route-status]').textContent = t('Không xác định được địa chỉ tại ghim. Hãy tìm và chọn địa chỉ khác.', 'The address at this pin could not be resolved. Search for another address.');
      }
    }});
    for (const side of ['pickup','delivery']) {
      const container = $(`[data-side="${side}"]`), saved = container.querySelector('[data-saved]'), area = container.querySelector('[data-area]');
      saved.replaceChildren(new Option(t('Nhập địa chỉ mới', 'Enter a new address'), ''));
      for (const a of addresses) saved.add(new Option((a.label || a.address_detail)+' · '+a.address_detail, String(a.address_id)));
      area.replaceChildren(new Option(t('TP. Hồ Chí Minh', 'Ho Chi Minh City'), 'TP.HCM'));
      container.querySelector('[data-save-wrap]').hidden = true;
      const details = document.createElement('details'); details.className = 'c-location-details';
      const summary = document.createElement('summary'); summary.textContent = t('Thông tin vị trí chi tiết', 'Location details'); details.append(summary);
      const row = document.createElement('div'); row.className = 'row g-3 mt-1'; details.append(row);
      for (const [name, title, max] of [['latitude',t('Vĩ độ','Latitude'),90],['longitude',t('Kinh độ','Longitude'),180]]) {
        const wrap = document.createElement('div'); wrap.className = 'c-field col-sm-6';
        const label = document.createElement('label'); label.className = 'form-label'; label.htmlFor = side+'-'+name; label.textContent = title;
        const input = document.createElement('input'); input.className = 'form-control'; input.id = input.name = side+'-'+name; input.type = 'number'; input.step = '0.0000001'; input.min = -max; input.max = max;
        wrap.append(label, input); row.append(wrap);
        input.addEventListener('input', () => { states[side].version++; states[side].lookup.reset(); states[side].address = null; saved.value = ''; confirmed = null; active = side; updateRoute(); });
      }
      container.append(details);
      states[side] = {saved, area, version:0, address:null};
      const lookup = window.SomiPlaces.attach(field(side+'-detail'), {api, language,
        onInvalidate: () => {active = side; clear(side);}, onSelect: a => apply(side,a)});
      states[side].lookup = lookup; lookups.push(lookup);
      field(side+'-detail').addEventListener('focus', () => {active = side; render();});
      saved.addEventListener('change', () => {
        const a = addresses.find(a => String(a.address_id) === saved.value);
        if (a) apply(side, a, String(a.address_id));
        else {lookup.reset(); field(side+'-detail').value = ''; clear(side);}
      });
      field(side+'-detail').placeholder = side === 'pickup' ? t('Nhập địa chỉ lấy hàng, kể cả khi gửi hộ', 'Enter a pickup address, including for someone else') : t('Tìm và chọn địa chỉ người nhận', 'Search for the recipient’s address');
      const label = container.querySelector(`label[for="${side}-detail"]`); label.textContent = side === 'pickup' ? t('Địa chỉ lấy hàng', 'Pickup address') : t('Địa chỉ giao hàng', 'Delivery address');
    }
    field('pickup-name').value = profile.full_name; field('pickup-phone').value = profile.phone;
    const defaultAddress = addresses.find(a => a.is_default);
    if (defaultAddress) apply('pickup', defaultAddress, String(defaultAddress.address_id));
    root.querySelectorAll('[data-map-side]').forEach(button => {
      button.textContent = button.dataset.mapSide === 'pickup' ? t('A · Điểm lấy', 'A · Pickup') : t('B · Điểm giao', 'B · Delivery');
      button.onclick = () => {active = button.dataset.mapSide; render();};
    });
    $('[data-user-location-label]').textContent = t('Vị trí của bạn','Your location');
    $('[data-pickup-label]').textContent = t('A · Điểm lấy','A · Pickup');
    $('[data-delivery-label]').textContent = t('B · Điểm giao','B · Delivery');
    $('[data-route-summary-label]').textContent = t('Tuyến xe máy A → B', 'Motorcycle route A → B');
    $('[data-map-use-location]').textContent = t('Dùng vị trí hiện tại làm điểm lấy A', 'Use my current location as pickup A');
    $('[data-map-use-location]').onclick = () => $('[data-use-location]').click();
    $('[data-map-pick-hint]').textContent = t('Chọn A hoặc B rồi nhấn trên bản đồ để chỉnh ghim.', 'Choose A or B, then click the map to adjust that pin.');
    $('[data-location-title]').textContent = t('Vị trí hiện tại của bạn','Your current location');
    $('[data-location-action]').textContent = t('Dùng vị trí này làm điểm lấy →','Use this location for pickup →');
    async function locate(useForPickup = false) {
      if (locationPending) return;
      locationPending = true; const version = ++locationVersion;
      const selectionVersion = states.pickup.version;
      $('[data-current-location]').textContent = t('Đang lấy vị trí…','Getting your location…');
      const locationStatus = $('[data-location-status]');
      delete locationStatus.dataset.locationError;
      locationStatus.textContent = t('Đang chờ trình duyệt cung cấp vị trí. Cho phép truy cập vị trí nếu được hỏi.', 'Waiting for the browser to provide your location. Allow location access if prompted.');
      $('[data-use-location]').disabled = true;
      let stage = 'position';
      try {
        if (window.isSecureContext === false) throw {code:'INSECURE_CONTEXT'};
        if (!navigator.geolocation) throw {code:'UNSUPPORTED'};
        const request = options => new Promise((resolve,reject) => navigator.geolocation.getCurrentPosition(resolve,reject,options));
        let p;
        try {
          p = await request({enableHighAccuracy:false,timeout:12000,maximumAge:60000});
        } catch (error) {
          // Retry acquisition errors once; denied permission needs a user action.
          if (![2,3].includes(error.code) || disposed || version !== locationVersion) throw error;
          locationStatus.textContent = t('Chưa có kết quả định vị. Đang thử lại với độ chính xác cao hơn…', 'No location yet. Retrying with higher accuracy…');
          p = await request({enableHighAccuracy:true,timeout:20000,maximumAge:0});
        }
        if (disposed || version !== locationVersion) return;
        const latitude = Number(p.coords.latitude), longitude = Number(p.coords.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude)>90 || Math.abs(longitude)>180) throw new Error('unavailable');
        user = {latitude,longitude}; currentAddress = null; if(confirmed!==locationKey())confirmed=null; render();
        stage = 'address';
        $('[data-location-status]').textContent = t('Đã định vị. Đang tìm địa chỉ hiện tại…','Located. Looking up your current address…');
        const data = await api('customer/places.php','POST',{action:'reverse',latitude:latitude.toFixed(7),longitude:longitude.toFixed(7),language});
        if (disposed || version !== locationVersion) return;
        currentAddress = {...data.address,latitude:latitude.toFixed(7),longitude:longitude.toFixed(7)};
        $('[data-current-location]').textContent = currentAddress.address_detail;
        locationStatus.textContent = t('Chọn vị trí ở trên để tự điền, hoặc tìm địa chỉ khác bên dưới.','Select the current location to fill the address, or search for another address below.');
        if (Number.isFinite(p.coords.accuracy)) locationStatus.textContent += t(' Độ chính xác khoảng ', ' Approximate accuracy: ') + Math.ceil(p.coords.accuracy) + ' m.';
        if (useForPickup && states.pickup.version === selectionVersion) apply('pickup',currentAddress);
      } catch (error) {
        if (disposed || version !== locationVersion) return;
        $('[data-current-location]').textContent = user ? t('Đã định vị, chưa tìm được địa chỉ','Located, but address lookup failed') : t('Chưa có vị trí hiện tại','Current location is unavailable');
        const code = stage === 'address' ? 'ADDRESS_LOOKUP_FAILED' : ({1:'PERMISSION_DENIED',2:'POSITION_UNAVAILABLE',3:'TIMEOUT'})[error.code] || error.code || 'POSITION_UNAVAILABLE';
        locationStatus.dataset.locationError = code;
        const messages = {
          PERMISSION_DENIED: t('Bạn chưa cho phép định vị. Cho phép quyền Vị trí của trang rồi bấm thử lại. Bạn vẫn có thể tự nhập địa chỉ.', 'Location access was denied. Allow this site to use your location and retry, or enter an address.'),
          POSITION_UNAVAILABLE: t('Trình duyệt hoặc thiết bị chưa cung cấp được tọa độ. Kiểm tra dịch vụ Vị trí của thiết bị; nếu đang dùng trình duyệt nhúng, thử mở link này bằng Chrome hoặc Edge. Bạn vẫn có thể tự nhập địa chỉ.', 'The browser or device could not provide coordinates. Check device location services; if using an embedded browser, try opening this link in Chrome or Edge. You can still enter an address.'),
          TIMEOUT: t('Định vị đã quá thời gian chờ sau khi thử lại. Kiểm tra kết nối và quyền Vị trí, rồi bấm thử lại hoặc tự nhập địa chỉ.', 'Location acquisition timed out after retrying. Check your connection and location permission, then retry or enter an address.'),
          INSECURE_CONTEXT: t('Trình duyệt yêu cầu HTTPS hoặc localhost để định vị. Hãy mở trang qua kết nối được hỗ trợ hoặc tự nhập địa chỉ.', 'Geolocation requires HTTPS or localhost. Use a supported connection or enter an address.'),
          UNSUPPORTED: t('Trình duyệt này không hỗ trợ định vị. Mở bằng Chrome hoặc Edge, hoặc tự nhập địa chỉ.', 'This browser does not support geolocation. Use Chrome or Edge, or enter an address.'),
          ADDRESS_LOOKUP_FAILED: t('Đã có tọa độ nhưng chưa tìm được địa chỉ từ Geoapify. Bấm vị trí hiện tại để thử lại, hoặc tìm địa chỉ bên dưới.', 'Coordinates are available, but Geoapify could not resolve the address. Select current location to retry, or search for an address below.')
        };
        locationStatus.textContent = messages[code] || messages.POSITION_UNAVAILABLE;
      } finally {
        if (!disposed && version === locationVersion) {locationPending = false; $('[data-use-location]').disabled = false; render();}
      }
    }
    $('[data-use-location]').onclick = () => currentAddress ? apply('pickup',currentAddress) : locate(true);
    const dialog = $('[data-pickup-confirm]'); let settleConfirmation = null;
    $('#pickup-confirm-title').textContent = t('Điểm lấy hàng khác vị trí của bạn','Pickup differs from your location');
    $('[data-pickup-confirm-question]').textContent = t('Bạn đang tạo đơn hộ hoặc muốn shipper lấy hàng tại địa chỉ này?','Are you booking for someone else, or should the shipper collect from this address?');
    $('[data-pickup-cancel]').textContent = t('Kiểm tra lại địa chỉ','Check the address');
    $('[data-pickup-accept]').textContent = t('Đúng, lấy tại địa chỉ này','Yes, collect from this address');
    function finishConfirmation(accepted) {dialog.close(); const settle = settleConfirmation; settleConfirmation = null; settle?.(accepted);}
    $('[data-pickup-cancel]').onclick = () => finishConfirmation(false);
    $('[data-pickup-accept]').onclick = () => finishConfirmation(true);
    dialog.addEventListener('cancel', e => {e.preventDefault(); finishConfirmation(false);});
    dialog.addEventListener('close', () => {if(!dialog.open && settleConfirmation){const settle=settleConfirmation;settleConfirmation=null;settle(false);}});
    updateRoute(); locate();
    return {
      address(side) {return {...(states[side].address || {country_code:'VN',province:'TP.HCM'}), address_detail:field(side+'-detail').value.trim(), latitude:field(side+'-latitude').value, longitude:field(side+'-longitude').value};},
      restore(saved) {
        for (const side of ['pickup','delivery']) {
          const entry=saved?.[side];
          if(entry?.address)apply(side,entry.address,addresses.some(a=>String(a.address_id)===String(entry.savedId))?String(entry.savedId):'');
        }
        confirmed=saved?.confirmed||null;
        updateRoute();
      },
      snapshot() {return {...Object.fromEntries(['pickup','delivery'].map(side=>[side,{address:this.address(side),savedId:states[side].saved.value}])),confirmed};},
      async confirmPickup() {
        const pickup = position('pickup');
        if (!user || !pickup || distance(user,pickup)<=1+1e-9 || confirmed===locationKey()) return true;
        if (dialog.open) return false;
        const token = locationKey();
        $('[data-pickup-confirm-message]').textContent = t('Điểm lấy hàng cách vị trí hiện tại của bạn ','Pickup is ')+formatKm(distance(user,pickup))+t(' km (hơn 1 km).',' km from your current location (more than 1 km).');
        $('[data-pickup-confirm-address]').textContent = field('pickup-detail').value;
        const answer = new Promise(resolve => {settleConfirmation=resolve;}); dialog.showModal();
        if (!await answer || disposed || token!==locationKey()) return false;
        confirmed=token;render();return true;
      },
      destroy() {disposed=true;locationVersion++;routeVersion++;if(dialog.open)finishConfirmation(false);lookups.forEach(l=>l.destroy());map?.destroy();}
    };
  }};
})();
