(() => {
  'use strict';
  const root = document.querySelector('[data-customer-server]'); if (!root) return;
  const vi = (window.SomiI18n?.lang || document.documentElement.lang) === 'vi', t = (vn,en) => vi?vn:en;
  const h = s => String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const url = (view,id) => '?'+new URLSearchParams({page:'customer',view,...(id?{id:String(id)}:{})});
  const money = (minor,currency) => new Intl.NumberFormat(vi?'vi-VN':'en-US',{style:'currency',currency}).format(Number(minor)/(currency==='USD'?100:1));
  const link = (view,label,id) => `<a class="btn btn-outline-dark" href="${h(url(view,id))}">${h(label)}</a>`;
  const view = document.body.dataset.cView, params = new URLSearchParams(location.search);
  let profile, currency='VND', detailVersion=0, checkoutPending=false;
  const paymentChoices=new Map(),paymentKeys=new Map();
  function embeddedProfile(){try{const data=JSON.parse(document.getElementById('customer-profile-data')?.textContent||'null');if(data?.profile&&typeof data.profile.full_name==='string'&&['vi','en'].includes(data.profile.app_language))return data.profile;}catch{}return null;}
  function savedCurrency(){try{const saved=localStorage.getItem('somi-currency');if(['VND','USD'].includes(saved))return saved;}catch{}return profile?.app_language==='en'?'USD':'VND';}
  let mapHandles=[],lookupHandles=[],stopTracking=()=>{};
  window.addEventListener('pagehide',()=>{stopTracking();resetMaps();});
  window.addEventListener('pageshow',e=>{if(e.persisted&&['order','tracking','matching','payment'].includes(view))run(detail);});
  const resetMaps=()=>{mapHandles.forEach(m=>m?.destroy());mapHandles=[];lookupHandles.forEach(l=>l.destroy());lookupHandles=[];};
  function addressMap(container,input,apply,invalidate){
    const canvas=document.createElement('div');canvas.className='somi-leaflet-map';container.append(canvas);
    const status=document.createElement('small');status.className='c-hint d-block';status.setAttribute('role','status');container.append(status);
    let revision=0;const map=window.SomiMaps?.create(canvas,{onPick:async p=>{const current=++revision;invalidate();map?.clear();status.textContent=t('Đang xác định địa chỉ…','Finding address…');try{const data=await api('customer/places.php','POST',{action:'reverse',latitude:p.lat.toFixed(7),longitude:p.lng.toFixed(7),language:vi?'vi':'en'});if(current!==revision||!canvas.isConnected)return;input.value=data.address.address_detail;apply(data.address);map?.pin(data.address.latitude,data.address.longitude);status.textContent='';}catch(e){if(current===revision)status.textContent=errorMessage(e);}}});
    const onInput=()=>{revision++;status.textContent='';map?.clear();};
    input.addEventListener('input',onInput);
    const handle={pin(a){revision++;status.textContent='';if(a?.latitude!=null&&a?.longitude!=null&&a.latitude!==''&&a.longitude!=='')map?.pin(a.latitude,a.longitude);else map?.clear();},destroy(){revision++;input.removeEventListener('input',onInput);map?.destroy();}};
    mapHandles.push(handle);return handle;
  }
  const statusLabels = {
    PENDING_PAYMENT:['Chờ thanh toán','Awaiting payment'],SEARCHING_SHIPPER:['Đang tìm shipper','Finding a shipper'],NO_SHIPPER_FOUND:['Chưa tìm được shipper','No shipper found'],SHIPPER_ASSIGNED:['Shipper đã nhận đơn','Shipper accepted your order'],PICKED_UP:['Đã lấy hàng','Picked up'],IN_DELIVERY:['Đang giao hàng','In delivery'],DELIVERED:['Đã giao','Delivered'],RETURNING:['Đang hoàn hàng','Returning'],RETURNED:['Đã hoàn hàng','Returned'],CANCELLED:['Đã hủy','Cancelled']
  };
  const paymentState = s => ({PENDING:['Chờ xử lý','Pending'],PROCESSING:['Đang xử lý','Processing'],PAID:['Đã thanh toán','Paid'],FAILED:['Thất bại','Failed'],CANCELLED:['Đã hủy','Cancelled'],PARTIALLY_REFUNDED:['Đã hoàn một phần','Partially refunded'],REFUNDED:['Đã hoàn tiền','Refunded'],SUCCEEDED:['Thành công','Succeeded']})[s]?.[vi?0:1]||s;
  const state = s => statusLabels[s]?.[vi?0:1] || s;
  async function api(path,method='GET',body=null,key=null) {
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),30000);
    try {
      const headers={'Accept':'application/json'};if(body!==null)headers['Content-Type']='application/json';if(key)headers['Idempotency-Key']=key;
      const response=await fetch('public/api/'+path,{method,credentials:'same-origin',headers,body:body===null?undefined:JSON.stringify(body),signal:controller.signal});
      const data=await response.json().catch(()=>({}));
      if(!response.ok){const error=new Error(data.error||t('Không thể xử lý yêu cầu.','Request failed.'));error.code=data.code;error.status=response.status;throw error;}return data;
    } catch(e){if(e.name==='AbortError')throw new Error(t('Yêu cầu quá thời gian. Bạn có thể thử lại.','Request timed out. You can retry.'));throw e;} finally{clearTimeout(timer);}
  }
  function errorMessage(error) {
    const errors={INVALID_PICKUP_TIME:t('Giờ lấy hàng không còn hợp lệ.','Pickup time is no longer valid.'),SCHEDULE_OUT_OF_RANGE:t('Hẹn lấy hàng trước ít nhất 30 phút và trong 7 ngày tới.','Schedule pickup 30 minutes to 7 days ahead.'),SCHEDULE_EXPIRED:t('Giờ hẹn đã qua. Vui lòng chọn giờ mới và lấy lại báo giá.','The appointment has passed. Choose a new time and request a new quote.'),RETURN_NOT_HANDED_OVER:t('Shipper chưa xác nhận đã trả hàng. Vui lòng kiểm tra và tải lại trạng thái.','The shipper has not confirmed handover yet. Please check and refresh.'),INVALID_ORDER_STATE:t('Trạng thái đơn đã thay đổi. Vui lòng tải lại.','Order status changed. Please refresh.'),ORDER_NOT_FOUND:t('Không tìm thấy đơn hàng của bạn. Vui lòng mở lại danh sách đơn.','Your order was not found. Please return to your order list.'),ORDER_NOT_CANCELLABLE:t('Đơn đã được nhận hoặc đã chuyển trạng thái nên không thể hủy. Vui lòng tải trạng thái mới.','This order has been assigned or changed status and can no longer be cancelled. Please refresh its status.'),WEATHER_SERVICE_PAUSED:t('Tạm ngừng nhận đơn do thời tiết nguy hiểm tại điểm lấy hàng. Vui lòng thử lại sau.','New deliveries are paused due to unsafe weather at pickup. Please try again later.'),ROUTE_NOT_FOUND:t('Không tìm được tuyến xe máy giữa hai điểm. Vui lòng chọn lại vị trí lấy/giao.','No motorcycle route was found. Please choose different pickup or delivery locations.'),PAYMENT_CURRENCY_UNSUPPORTED:t('Thanh toán USD tạm khóa. SePay chỉ hỗ trợ VND.','USD payments are temporarily disabled. SePay only supports VND.'),PRICING_CONTEXT_UNAVAILABLE:t('Dịch vụ tuyến đường/thời tiết chưa sẵn sàng. Vui lòng thử lại sau.','Routing/weather is unavailable. Please try again later.'),WEIGHT_NOT_SUPPORTED:t('Khối lượng tối đa là 30 kg.','Maximum weight is 30 kg.'),OUTSIDE_SERVICE_AREA:t('Hai điểm phải thuộc TP.HCM và tuyến đường dưới 40 km.','Both locations must be in Ho Chi Minh City and the route shorter than 40 km.'),QUOTE_EXPIRED:t('Báo giá đã hết hạn. Vui lòng lấy báo giá mới.','Quote expired. Please request a new quote.'),PAYMENT_PROVIDER_UNAVAILABLE:t('Thanh toán trực tuyến chưa sẵn sàng.','Online payments are not available yet.'),BANK_NOT_CONFIGURED:t('Chuyển khoản chưa sẵn sàng.','Bank transfer is not available yet.'),AUTHENTICATION_REQUIRED:t('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.','Your session expired. Please sign in again.')};
    return errors[error.code]||(error.code?t('Không thể xử lý yêu cầu. Vui lòng kiểm tra thông tin hoặc thử lại sau.','Unable to process the request. Please check your information or try again later.'):error.message);
  }
  function report(error,node=root) {if(node===root){let box=$('[data-live-message]');if(!box){box=document.createElement('div');box.dataset.liveMessage='';root.prepend(box);}node=box;}node.innerHTML=`<div class="alert alert-danger" role="alert">${h(errorMessage(error))}</div>`+(error.status===401?link('orders',t('Đăng nhập lại','Sign in again')):'');}
  const $ = (selector,scope=root) => scope.querySelector(selector);
  const $$ = (selector,scope=root) => [...scope.querySelectorAll(selector)];
  const date = value => value?new Date(value).toLocaleString(vi?'vi-VN':'en-US'):'';
  const pickupSchedule = o => {const s=o.schedule||o.pricing_snapshot?.schedule;return s?.mode==='later'?{...s,dispatch_at:s.dispatch_at||new Date(Date.parse(s.at)-15*60000).toISOString()}:s;};
  const appointmentDate = at => new Date(at).toLocaleString(vi?'vi-VN':'en-GB',{timeZone:'Asia/Ho_Chi_Minh'})+' (UTC+7)';
  const scheduleLabel = o => {const sch=pickupSchedule(o);if(sch?.mode!=='later')return '';return `<p class="c-hint" data-pickup-appointment>${h(t('Hẹn lấy hàng: ','Scheduled pickup: '))}${h(appointmentDate(sch.at))}</p>`;};
  const orderBadge = o => {const sch=pickupSchedule(o);return o.status==='SEARCHING_SHIPPER'&&sch?.mode==='later'&&Date.parse(sch.dispatch_at)>Date.now()?`<span class="c-chip">${h(t('Chờ lịch lấy hàng','Awaiting pickup schedule'))}</span>`:badge(o.status);};
  const groups={all:null,active:['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'],done:['DELIVERED'],problem:['NO_SHIPPER_FOUND','RETURNING','RETURNED'],cancelled:['CANCELLED']};
  const badge = value => `<span class="c-chip">${h(state(value))}</span>`;
  const empty = (title,text='') => `<div class="c-empty"><strong>${h(title)}</strong>${text?`<p>${h(text)}</p>`:''}</div>`;
  const row = o => `<a class="c-order-row" href="${h(url('order',o.order_id))}"><div class="c-order-code"><strong>${h(o.order_code)}</strong><small>${h(date(o.created_at))}</small></div><div class="c-order-route"><span>${h(o.pickup_address)} → ${h(o.delivery_address)}</span><small>${h(o.receiver_name||'')}</small>${scheduleLabel(o)}</div>${orderBadge(o)}<span class="c-order-amount">${h(money(o.total_charge_minor,o.currency_code))}</span><span aria-hidden="true">›</span></a>`;
  function scheduleInfo(o){const schedule=o.schedule||o.pricing_snapshot?.schedule;return schedule?.mode==='later'?`<p class="c-schedule-note"><strong>${h(t('Hẹn lấy hàng: ','Scheduled pickup: '))}${h(appointmentDate(schedule.at))}</strong><br><small>${h(t('Hệ thống bắt đầu tìm shipper trước giờ hẹn 15 phút, sau khi thanh toán thành công.','Matching starts 15 minutes before pickup, after successful payment.'))}</small></p>`:'';}
  function route(o) {
    return scheduleInfo(o)+`<div class="c-route">${[['A',t('Lấy hàng','Pickup'),o.pickup_address,o.sender_name,o.sender_phone],['B',t('Giao đến','Delivery'),o.delivery_address,o.receiver_name,o.receiver_phone]].map(([letter,title,address,name,phone])=>`<div class="c-stop"><span class="c-stop-mark c-stop-${letter.toLowerCase()}">${letter}</span><div><small>${h(title)}</small><strong>${h(address)}</strong>${name?`<span>${h(name)} · ${h(phone)}</span>`:''}</div></div>`).join('')}</div>`;
  }
  function fees(o) {
    const parts=o.pricing_snapshot?.components||{};
    const labels={base_fee_minor:t('Cước cơ bản','Base fee'),distance_component_minor:t('Phần quãng đường','Distance component'),weight_component_minor:t('Phần trọng lượng','Weight component')};
    return `<dl class="c-fees">${Object.entries(labels).filter(([key])=>parts[key]!==undefined).map(([key,label])=>`<div><dt>${h(label)}</dt><dd>${h(money(parts[key],o.currency_code))}</dd></div>`).join('')}<div><dt>${h(t('Hệ số giờ / thời tiết','Time / weather multiplier'))}</dt><dd>${h(parts.time_multiplier||'1')} × ${h(parts.weather_multiplier||'1')}</dd></div><div class="c-fees-total"><dt>${h(t('Tổng phí','Total'))}</dt><dd>${h(money(o.total_charge_minor,o.currency_code))}</dd></div></dl>${o.pricing_snapshot?.context?.weather_condition?'<p class="c-quote-note">'+h(({NORMAL:t('Thời tiết bình thường','Normal weather'),LIGHT_RAIN:t('Mưa nhẹ','Light rain'),RAIN:t('Trời mưa','Rain')})[o.pricing_snapshot.context.weather_condition]||o.pricing_snapshot.context.weather_condition)+' · <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a></p>':''}`;
  }
  async function allOrders(){let items=[],cursor=null;const seen=new Set();do{const data=await api('customer/orders.php?limit=100'+(cursor?'&before_id='+encodeURIComponent(cursor):''));items.push(...data.items);cursor=data.next_before_id;if(cursor&&seen.has(cursor))throw new Error(t('Không thể tải tiếp danh sách đơn.','Unable to load the next page.'));seen.add(cursor);}while(cursor);return items;}
  function glyph(name){const paths={pin:'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z M12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6',box:'m3 7 9-4 9 4-9 4-9-4Z M3 7v10l9 4 9-4V7 M12 11v10 M7 5l9 4',check:'m5 12 4 4L19 6',edit:'m16 3 5 5-12 12-6 1 1-6L16 3Z M14 5l5 5',trash:'M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7',arrow:'M5 12h14 m-6-6 6 6-6 6'};return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'+(paths[name]||paths.pin)+'"/></svg>';}
  function dashboardDelivery(live){
    if(!live)return `<section class="c-delivery-hero c-delivery-empty"><span class="c-delivery-package">${glyph('box')}</span><span class="c-eyebrow">${h(t('MỘT CHUYẾN GỬI MỚI','YOUR NEXT DELIVERY'))}</span><h2>${h(t('Gửi đi điều bạn quan tâm.','Deliver what matters.'))}</h2><p>${h(t('Bạn chưa có đơn nào đang giao','No deliveries in progress'))}. ${h(t('SOMI sẵn sàng cho chuyến gửi tiếp theo của bạn.','SOMI is ready for your next delivery.'))}</p><a class="btn btn-light" href="${h(url('create'))}">${h(t('Gửi một kiện hàng','Send a package'))} ${glyph('arrow')}</a></section>`;
    const stage=({PENDING_PAYMENT:0,SEARCHING_SHIPPER:0,NO_SHIPPER_FOUND:0,SHIPPER_ASSIGNED:1,PICKED_UP:1,IN_DELIVERY:2,RETURNING:2})[live.status]??0;
    return `<section class="c-delivery-hero"><div class="c-delivery-top"><span class="c-eyebrow">${h(t('ĐƠN ĐANG THEO DÕI','YOUR ACTIVE DELIVERY'))}</span><span class="c-delivery-status"><i></i>${h(state(live.status))}</span></div><h2>${h(t('Hành trình kiện hàng của bạn','Your parcel’s journey'))}</h2><span class="c-delivery-code">${h(live.order_code)}</span><div class="c-delivery-route">${[['pickup',t('Điểm lấy hàng','Pickup'),live.pickup_address],['dropoff',t('Điểm giao hàng','Drop-off'),live.delivery_address]].map(([type,label,address])=>`<div class="c-delivery-stop ${type}"><span class="c-delivery-dot"></span><div><small>${h(label)}</small><p title="${h(address)}">${h(address)}</p></div></div>`).join('')}</div><ol class="c-delivery-progress">${[t('Tạo đơn','Created'),t('Nhận hàng','Pickup'),t('Giao hàng','In transit'),t('Hoàn tất','Complete')].map((label,i)=>`<li class="${i<stage?'is-done':i===stage?'is-current':''}" ${i===stage?'aria-current="step"':''}><span>${i<stage?'✓':i+1}</span>${h(label)}</li>`).join('')}</ol><div class="c-delivery-footer"><span>${glyph('box')}${h(t('Theo dõi từng bước giao hàng','Follow every delivery milestone'))}</span><a class="btn btn-light" href="${h(url('order',live.order_id))}">${h(t('Xem chi tiết','View details'))}${glyph('arrow')}</a></div></section>`;
  }
  async function dashboard(){
    const [items,addresses]=await Promise.all([allOrders(),api('customer/addresses.php')]);
    $('[data-greeting]').textContent=t('Xin chào, ','Hello, ')+profile.full_name.split(/\s+/).pop();
    const live=items.find(o=>groups.active.includes(o.status));
    $('[data-live]').innerHTML=dashboardDelivery(live);
    $('[data-recent]').innerHTML=items.slice(0,5).map(row).join('')||empty(t('Bạn chưa có đơn hàng nào','No orders yet'));
    const waiting=items.filter(o=>['PENDING_PAYMENT','NO_SHIPPER_FOUND'].includes(o.status));
    $('[data-todo]').innerHTML=waiting.map(o=>`<a href="${h(url(o.status==='PENDING_PAYMENT'?'payment':'order',o.order_id))}"><span class="c-todo-icon c-tone-amber">↗</span><div><strong>${h(o.order_code)}</strong><small>${h(state(o.status))}</small></div><span>›</span></a>`).join('')||`<div class="c-all-clear"><span>${glyph('check')}</span><strong>${h(t('Mọi thứ đã sẵn sàng!','You’re all caught up!'))}</strong><p>${h(t('Không có việc nào cần xử lý.','Nothing needs your attention.'))}</p></div>`;
    const now=new Date(),month=items.filter(o=>{const d=new Date(o.created_at);return d.getFullYear()===now.getFullYear()&&d.getMonth()===now.getMonth();});
    $('[data-figures]').innerHTML=[[month.length,t('Đơn đã tạo','Orders created')],[month.filter(o=>o.status==='DELIVERED').length,t('Đã giao','Delivered')],[month.filter(o=>o.status==='CANCELLED').length,t('Đã hủy','Cancelled')]].map(([n,label])=>`<div class="c-month-stat"><strong class="c-month-number">${n}</strong><span>${h(label)}</span></div>`).join('');
    const a=addresses.items.find(a=>a.is_default);
    $('[data-default-address]').innerHTML=`<div class="c-default-heading"><span class="c-address-symbol">${glyph('pin')}</span><div><h2>${h(t('Điểm lấy hàng quen thuộc','Your usual pickup spot'))}</h2><p class="c-panel-sub">${h(a?.label||t('Địa chỉ mặc định','Default address'))}</p></div></div><p class="c-default-street">${h(a?.address_detail||t('Bạn chưa có địa chỉ mặc định.','No default address yet.'))}</p><a class="c-link" href="${h(url('addresses'))}">${h(t('Quản lý sổ địa chỉ','Manage addresses'))} →</a>`;
  }
  async function orders(){
    const firstPage=await api('customer/orders.php?limit=50');const items=[...firstPage.items];let cursor=firstPage.next_before_id,loadingMore=false;const seenCursors=new Set();let tab=params.get('tab')||'all';if(!Object.hasOwn(groups,tab))tab='all';
    const titles={all:t('Tất cả','All'),active:t('Đang xử lý','In progress'),done:t('Đã giao','Delivered'),problem:t('Cần chú ý','Needs attention'),cancelled:t('Đã hủy','Cancelled')};
    function render(){
      $('[data-tabs]').innerHTML=Object.entries(titles).map(([key,label])=>`<button type="button" role="tab" data-tab="${key}" aria-selected="${key===tab}" class="nav-link${key===tab?' active':''}">${h(label)} <b>${items.filter(o=>!groups[key]||groups[key].includes(o.status)).length}</b></button>`).join('');
      $$('[data-tab]').forEach(b=>b.onclick=()=>{tab=b.dataset.tab;render();});
      const q=$('[data-search]').value.toLocaleLowerCase().trim(),from=$('[data-from]').value,to=$('[data-to]').value;
      const list=items.filter(o=>{const d=new Date(o.created_at),day=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;return (!groups[tab]||groups[tab].includes(o.status))&&(!q||`${o.order_code} ${o.pickup_address} ${o.delivery_address} ${o.receiver_name||''} ${o.parcel_description||''}`.toLocaleLowerCase().includes(q))&&(!from||day>=from)&&(!to||day<=to);});
      $('[data-list]').innerHTML=list.map(row).join('')||empty(items.length?t('Không có đơn phù hợp','No matching orders'):t('Bạn chưa có đơn hàng nào','No orders yet'));
      $('[data-clear]').hidden=!q&&!from&&!to;
      let more=$('[data-load-more]');if(!more){more=document.createElement('div');more.dataset.loadMore='';$('[data-list]').after(more);}
      more.innerHTML=`<p class="c-hint">${h(t('Đã tải ','Loaded '))}${items.length}${h(cursor?t(' đơn. Bộ lọc áp dụng cho các đơn đã tải.',' orders. Filters apply to loaded orders.') : t(' đơn.',' orders.'))}</p>`+(cursor?`<button class="btn btn-outline-dark" data-more-orders ${loadingMore?'disabled':''}>${h(t('Tải thêm đơn','Load more orders'))}</button>`:'');
      const button=$('[data-more-orders]');if(button)button.onclick=async()=>{if(loadingMore)return;loadingMore=true;render();try{if(seenCursors.has(String(cursor)))throw new Error(t('Không thể tải tiếp danh sách đơn.','Unable to load the next page.'));const previous=cursor;const data=await api('customer/orders.php?limit=50&before_id='+encodeURIComponent(cursor));const ids=new Set(items.map(o=>String(o.order_id)));items.push(...data.items.filter(o=>!ids.has(String(o.order_id))));seenCursors.add(String(previous));cursor=data.next_before_id;}catch(e){report(e);}finally{loadingMore=false;render();}};
    }
    ['[data-search]','[data-from]','[data-to]'].forEach(s=>$(s).addEventListener('input',render));
    $('[data-clear]').onclick=()=>{['[data-search]','[data-from]','[data-to]'].forEach(s=>$(s).value='');render();};render();
    let active=true,timer;
    stopTracking=()=>{active=false;clearTimeout(timer);};
    async function refresh(){
      try{
        if(!active||document.hidden||loadingMore)return;
        const data=await api('customer/orders.php?limit=50');
        if(!active)return;
        const updated=new Map(items.map(o=>[String(o.order_id),o]));
        data.items.forEach(o=>updated.set(String(o.order_id),o));
        const next=[...updated.values()].sort((a,b)=>Number(b.order_id)-Number(a.order_id));
        if(JSON.stringify(next)!==JSON.stringify(items)){items.splice(0,items.length,...next);render();}
      }catch(e){if(e.status===401)stopTracking();}
      finally{if(active)timer=setTimeout(refresh,5000);}
    }
    timer=setTimeout(refresh,5000);
  }
  const trackedStatuses=['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'];
  function trackShipper(o,section){
    const hint=$('[data-location-status]',section),canvas=$('[data-route-map]',section);
    const map=window.SomiMaps?.create(canvas);mapHandles.push(map);
    if(!map&&!canvas.textContent)canvas.textContent=t('Bản đồ chưa sẵn sàng. Trạng thái đơn vẫn được cập nhật tự động.','Map unavailable. Order status still updates automatically.');
    const stops=[{id:'pickup',label:t('Điểm lấy','Pickup'),latitude:o.pickup_latitude,longitude:o.pickup_longitude},{id:'delivery',label:t('Điểm giao','Delivery'),latitude:o.delivery_latitude,longitude:o.delivery_longitude}];
    map?.points(stops);
    const scheduledAt=o.pricing_snapshot?.schedule?.mode==='later'?Date.parse(o.pricing_snapshot.schedule.at):null;
    const waitingAtStart=o.status==='SEARCHING_SHIPPER'&&scheduledAt>Date.now()+15*60000;
    let active=true,timer=null;
    stopTracking=()=>{active=false;clearTimeout(timer);};
    async function poll(){
      if(!active||!section.isConnected)return;
      try{
        if(document.hidden)return;
        const data=await api('customer/shipper-location.php?order_id='+encodeURIComponent(o.order_id));
        if(!active||!section.isConnected)return;
        if(data.status!==o.status||(data.arrived_at||null)!==(o.arrived_at||null)||(waitingAtStart&&Date.now()>=scheduledAt-15*60000)){stopTracking();await detail();return;}
        const point=data.location;
        map?.points(point?[...stops,{...point,id:'user',label:t('Shipper','Shipper')}]:stops);
        hint.textContent=o.arrived_at&&o.status==='IN_DELIVERY'?t('Shipper đã đến điểm giao. Đang chờ bàn giao và ảnh minh chứng.','Your shipper has arrived. Awaiting handover and delivery proof.'):point?t('Vị trí Shipper cập nhật lúc: ','Shipper location updated: ')+date(point.recorded_at)+(Date.now()-new Date(point.recorded_at).getTime()>120000?t(' · Vị trí đã cũ',' · Location may be outdated'):''):(waitingAtStart?t('Đã ghi nhận lịch lấy hàng. Bắt đầu tìm shipper trước giờ hẹn 15 phút.','Pickup scheduled. Matching starts 15 minutes before pickup.'):o.status==='SEARCHING_SHIPPER'?t('Đang tìm shipper. Trạng thái tự cập nhật mỗi 5 giây.','Finding a shipper. Status updates every 5 seconds.'):trackedStatuses.includes(o.status)?t('Đã có shipper. Đang chờ vị trí GPS đầu tiên.','Shipper accepted your order. Waiting for the first GPS location.'):t('Lộ trình của đơn hàng.','Order route.'));
      }catch(e){if(active){map?.points(stops);hint.textContent=errorMessage(e);if(e.status===401||e.status===403||e.status===404)stopTracking();}}
      finally{if(active)timer=setTimeout(poll,5000);}
    }
    if(!['DELIVERED','RETURNED','CANCELLED'].includes(o.status))poll();
    if(map&&o.pickup_latitude!=null&&o.delivery_latitude!=null){
      api('customer/places.php','POST',{action:'route',pickup:{latitude:o.pickup_latitude,longitude:o.pickup_longitude},delivery:{latitude:o.delivery_latitude,longitude:o.delivery_longitude}}).then(data=>{if(active&&section.isConnected)map.route(data.route);}).catch(e=>{if(active&&section.isConnected){const message=document.createElement('p');message.className='c-hint';message.textContent=errorMessage(e);section.append(message);}});
    }
  }
  function deliveryProof(proof){
    const title=h(t('Giao nhận và ảnh minh chứng','Delivery receipt and photos'));
    if(proof.error)return `<section class="card card-body" data-customer-proof-panel><h2>${title}</h2><p role="status">${h(errorMessage(proof.error))}</p><button type="button" class="btn btn-outline-dark" data-proof-retry>${h(t('Thử tải lại ảnh minh chứng','Retry delivery proof'))}</button></section>`;
    const steps=[['picked_up_at',t('Đã lấy hàng','Picked up')],['delivery_started_at',t('Bắt đầu giao','Delivery started')],['arrived_at',t('Đã đến điểm giao','Arrived at drop-off')],['delivered_at',t('Giao hàng thành công','Delivered')],['return_started_at',t('Bắt đầu hoàn hàng','Return started')],['returned_at',t('Shipper đã trả hàng','Returned by shipper')],['customer_return_confirmed_at',t('Bạn đã xác nhận nhận lại','Return confirmed by you')]];
    const reasons={NO_ANSWER:t('Không liên lạc được người nhận','Recipient unreachable'),REFUSED:t('Người nhận từ chối','Recipient refused'),WRONG_ADDRESS:t('Sai địa chỉ','Incorrect address'),RECIPIENT_ABSENT:t('Người nhận vắng mặt','Recipient absent'),PACKAGE_ISSUE:t('Sự cố hàng hóa','Parcel issue'),OTHER:t('Lý do khác','Other reason')};
    return `<section class="card card-body" data-customer-proof-panel><div class="c-panel-head"><h2>${title}</h2><span class="c-chip">${(proof.evidence||[]).length} ${h(t('ảnh','photos'))}</span></div>${(proof.deliveries||[]).map(d=>`<div class="c-receipt-attempt"><h3>${h(t('Người giao: ','Shipper: '))}${h(d.shipper_name||'—')}</h3><dl class="c-receipt-milestones">${steps.filter(([key])=>d[key]).map(([key,label])=>`<div><dt>${h(label)}</dt><dd>${h(date(d[key]))}</dd></div>`).join('')}</dl>${d.failure_reason_code?`<p class="c-receipt-reason"><strong>${h(t('Lý do giao không thành công: ','Delivery issue: '))}</strong>${h(reasons[d.failure_reason_code]||d.failure_reason_code)}${d.failure_note?' · '+h(d.failure_note):''}</p>`:''}</div>`).join('')}
    ${(proof.evidence||[]).length?`<div class="c-proof-gallery">${proof.evidence.map(e=>{const label=e.evidence_type==='RETURN_PHOTO'?t('Ảnh hoàn hàng','Return photo'):t('Ảnh giao hàng','Delivery photo');const src='public/api/storage.php?content=1&disposition=inline&file_id='+encodeURIComponent(e.file_id);return `<button type="button" class="c-proof-photo" data-customer-proof="${h(src)}" aria-label="${h(label)}"><img src="${h(src)}" alt="${h(label)}" loading="lazy"><span>${h(label)}</span><small>${h(date(e.uploaded_at))}</small></button>`;}).join('')}</div>`:`<p class="c-panel-sub">${h(t('Chưa có ảnh minh chứng được lưu hoặc ảnh đã hết thời hạn lưu trữ.','No photo evidence has been saved, or its retention period has ended.'))}</p>`}</section>`;
  }
  root.addEventListener('click',event=>{
    if(event.target.closest('[data-proof-retry]'))return run(detail);
    const button=event.target.closest('[data-customer-proof]');if(!button)return;
    let dialog=document.querySelector('[data-customer-proof-dialog]');
    if(!dialog){dialog=document.createElement('dialog');dialog.className='c-proof-dialog';dialog.dataset.customerProofDialog='';dialog.setAttribute('aria-label',t('Ảnh minh chứng','Photo evidence'));dialog.innerHTML=`<header><strong>${h(t('Ảnh minh chứng giao nhận','Delivery photo evidence'))}</strong><button class="btn btn-outline-dark" type="button">${h(t('Quay lại chi tiết đơn','Back to order details'))}</button></header><img alt="${h(t('Ảnh minh chứng','Photo evidence'))}"><p role="status" hidden></p>`;document.body.append(dialog);$('button',dialog).onclick=()=>dialog.close();$('img',dialog).onerror=()=>{const status=$('p',dialog);status.hidden=false;status.textContent=t('Không tải được ảnh. Đóng và mở lại để thử lại.','Unable to load the photo. Close and reopen to retry.');};}
    $('p',dialog).hidden=true;$('img',dialog).src=button.dataset.customerProof;dialog.showModal();
  });

  async function detail(){
    const version=++detailVersion;
    stopTracking();resetMaps();
    let id=params.get('id');if(!id&&params.get('code')){const found=(await allOrders()).find(o=>o.order_code===params.get('code'));id=found?.order_id;}
    if(!id)throw new Error(t('Mở đơn từ danh sách đơn hàng của bạn.','Open an order from your order list.'));
    const [data,payments,refunds,proof]=await Promise.all([api('customer/orders.php?id='+encodeURIComponent(id)),api('customer/payments.php?order_id='+encodeURIComponent(id)),api('customer/refunds.php?order_id='+encodeURIComponent(id)),api('customer/delivery-proof.php?order_id='+encodeURIComponent(id)).catch(error=>({error}))]);
    if(version!==detailVersion)return;
    const o=data.order,p=payments.payment,target=$('[data-order],[data-payment],[data-matching],[data-tracking]');
    const scheduledWaiting=o.status==='SEARCHING_SHIPPER'&&o.pricing_snapshot?.schedule?.mode==='later'&&Date.parse(o.pricing_snapshot.schedule.at)>Date.now()+15*60000;
    const back=$('[data-back]');if(back)back.href=url('order',id);
    const canCancel=['PENDING_PAYMENT','SEARCHING_SHIPPER'].includes(o.status);
    target.innerHTML=`<section class="c-head"><div><h1>${h(o.order_code)}</h1><div class="c-head-meta">${orderBadge(o)}<span>${h(date(o.created_at))}</span></div></div><div class="c-head-actions c-actions">${view!=='payment'&&o.status==='PENDING_PAYMENT'?link('payment',t('Thanh toán','Pay'),id):''}<button class="btn btn-outline-dark" data-refresh>${h(t('Tải trạng thái mới','Refresh status'))}</button></div></section><div class="row g-4 align-items-start"><div class="col-lg-7 vstack gap-4"><section class="card card-body"><div class="c-panel-head"><h2>${h(t('Lộ trình','Route'))}</h2><span class="c-chip">${h(o.distance_km)} km</span></div>${route(o)}</section><section class="card card-body"><h2>${h(t('Hành trình đơn hàng','Order timeline'))}</h2><ol class="c-timeline">${(o.status_history||[]).map(s=>`<li class="c-tl-item is-done"><span class="c-tl-dot"></span><div><strong>${h(state(s.new_status))}</strong><small>${h(date(s.created_at))}</small>${s.note?`<p>${h(s.note)}</p>`:''}</div></li>`).join('')}</ol></section><section class="card card-body"><h2>${h(t('Kiện hàng','Parcel'))}</h2><dl class="c-pkg"><div><dt>${h(t('Khối lượng','Weight'))}</dt><dd>${h(o.estimated_weight_kg)} kg</dd></div><div class="c-span-2"><dt>${h(t('Mô tả','Description'))}</dt><dd>${h(o.parcel_description||'—')}</dd></div>${o.customer_note?`<div class="c-span-2"><dt>${h(t('Ghi chú','Note'))}</dt><dd>${h(o.customer_note)}</dd></div>`:''}</dl></section></div><div class="col-lg-5 vstack gap-4"><section class="card card-body${view==='payment'?' c-dark':''}"><h2>${h(t('Chi phí','Charges'))}</h2>${fees(o)}</section><section class="card card-body" data-payment-card><h2>${h(t('Thanh toán','Payment'))}</h2>${p?`<p>${h(paymentState(p.status))} · ${h(money(p.amount_minor,p.currency_code))}</p>`:`<p>${h(t('Chưa tạo yêu cầu thanh toán.','No payment request yet.'))}</p>`}</section></div></div>`;
    $('.col-lg-7',target).insertAdjacentHTML('beforeend',deliveryProof(proof));
    $('[data-customer-proof-panel]',target).querySelectorAll('img').forEach(img=>img.addEventListener('error',()=>{const text=document.createElement('span');text.textContent=t('Không tải được ảnh. Bấm để thử lại.','Image unavailable. Select to retry.');img.replaceWith(text);},{once:true}));
    const card=$('[data-payment-card]');
    if(o.status==='PENDING_PAYMENT'&&!p&&o.currency_code==='VND'){
      const demo=profile.email?.endsWith('@demo.somi.invalid'),selected=paymentChoices.get(String(id))||'GATEWAY';
      card.insertAdjacentHTML('beforeend',`<form data-pay><fieldset class="c-payment-methods"><legend>${h(t('Chọn phương thức thanh toán','Choose a payment method'))}</legend><div class="c-tiles">${[['GATEWAY',t('QR ngân hàng','Bank QR'),t('Quét mã bằng ứng dụng ngân hàng.','Scan with your banking app.')],['CARD',t('Thẻ thanh toán','Payment card'),t('Thanh toán bằng thẻ trên trang bảo mật.','Pay by card on the secure checkout page.')]].map(([method,label,hint])=>`<label class="c-tile"><input type="radio" name="method" value="${method}" ${selected===method?'checked':''} ${checkoutPending?'disabled':''}><strong>${h(label)}</strong><small>${h(hint)}</small></label>`).join('')}</div></fieldset><p class="c-hint">${h(demo?t('DEMO — mô phỏng thanh toán, không thu tiền thật.','DEMO — simulated payment; no real charge.'):t('Bạn sẽ chuyển đến SePay để quét QR hoặc nhập thông tin thẻ. Chỉ xác nhận đã thanh toán sau khi hệ thống nhận kết quả hợp lệ.','Continue to SePay to scan a QR or enter your card details. Payment is confirmed after a verified result.'))}</p><div class="c-form-actions"><button class="btn btn-dark" type="submit" ${checkoutPending?'disabled':''}>${h(checkoutPending?t('Đang mở thanh toán…','Opening checkout…'):t('Tiến hành thanh toán','Proceed to payment'))}</button></div><div data-payment-error role="status"></div></form>`);
      $('[data-pay]',card).addEventListener('change',event=>{if(event.target.name==='method')paymentChoices.set(String(id),event.target.value);});
    }
    if(o.status==='PENDING_PAYMENT'&&o.currency_code==='USD')card.insertAdjacentHTML('beforeend',`<p class="c-panel-sub">${h(t('Thanh toán USD tạm khóa. SePay chỉ hỗ trợ VND.','USD payments are temporarily disabled. SePay only supports VND.'))}</p>`);
    if(p?.checkout_url&&o.status==='PENDING_PAYMENT'&&['PENDING','PROCESSING'].includes(p.status))card.insertAdjacentHTML('beforeend',`<a class="btn btn-dark" href="public/api/customer/checkout.php?order_id=${encodeURIComponent(id)}">${h(p.method==='CARD'?t('Tiếp tục thanh toán bằng thẻ','Continue card payment'):p.provider==='SEPAY'&&p.method==='GATEWAY'?t('Mở mã QR thanh toán','Open payment QR'):t('Mở trang thanh toán','Open checkout'))}</a>`);
    if(p?.status==='PAID')card.insertAdjacentHTML('beforeend',`<p>${h(t('Đã nhận thanh toán; admin chi trả shipper theo kết quả giao hàng và hoàn tiền theo chính sách hủy/hoàn đơn.','Payment received; admin handles shipper payouts and customer refunds according to the delivery outcome and cancellation/return policy.'))}</p>`);
    if(p?.bank_instructions){const b=p.bank_instructions;card.insertAdjacentHTML('beforeend',`<p>${h(t('Chuyển đúng số tiền và nội dung bên dưới. Admin kiểm tra sao kê rồi xác nhận, sau đó hệ thống mới tìm shipper.','Transfer the exact amount and reference below. Matching starts after admin verifies receipt.'))}</p><dl><dt>${h(b.bank)}</dt><dd>${h(b.account_name)} · ${h(b.account_number)}</dd><dt>${h(t('Nội dung chuyển khoản','Transfer reference'))}</dt><dd>${h(b.transfer_reference)}</dd></dl>`);}
    const right=card.parentElement;
    const sch=pickupSchedule(o);
    if(sch?.mode==='later'){
      const waiting=o.status==='SEARCHING_SHIPPER'&&Date.parse(sch.dispatch_at)>Date.now();
      right.insertAdjacentHTML('afterbegin',`<section class="card card-body" data-schedule-status><h2>${h(t('Lịch lấy hàng','Pickup appointment'))}</h2>${scheduleLabel(o)}<p>${h(waiting?t('Đã lưu lịch. Bắt đầu tìm shipper lúc ','Appointment saved. Matching starts at '):t('Ưu tiên tìm shipper theo giờ lấy hàng đã hẹn. Tình trạng thực tế được cập nhật tại đây.','This order is prioritized for its pickup appointment. Actual progress is updated here.'))}${waiting?h(appointmentDate(sch.dispatch_at)):''}</p><p class="c-hint">${h(t('Cần thanh toán được xác nhận để tìm shipper. Giờ hẹn là mục tiêu, phụ thuộc shipper nhận đơn.','Verified payment is required. The appointment is a target and depends on shipper availability.'))}</p></section>`);
    }
    if(refunds.items.length)right.insertAdjacentHTML('beforeend',`<section class="card card-body"><h2>${h(t('Hoàn tiền','Refunds'))}</h2>${refunds.items.map(r=>`<p>${h(paymentState(r.status))} · ${h(money(r.refund_amount_minor,r.currency_code))}</p>`).join('')}</section>`);
    if(canCancel)right.insertAdjacentHTML('beforeend',`<section class="card card-body"><h2>${h(t('Hủy đơn','Cancel order'))}</h2><p class="c-panel-sub">${h(t('Phí hủy theo báo giá','Cancellation fee from your quote'))}: ${h(money(o.pricing_snapshot?.cancellation_fees?.[o.status]||'0',o.currency_code))}</p><form data-cancel><div class="c-field"><label class="form-label" for="cancel-reason">${h(t('Lý do hủy','Reason'))}</label><textarea id="cancel-reason" class="form-control" name="reason" required maxlength="500"></textarea></div><div class="c-form-actions"><button class="btn btn-outline-dark">${h(t('Hủy đơn','Cancel order'))}</button></div><div data-cancel-error></div></form></section>`);
    if(o.status==='NO_SHIPPER_FOUND')right.insertAdjacentHTML('afterbegin',`<section class="card card-body"><h2>${h(state(o.status))}</h2><p>${h(t('Đã hết lượt tìm Shipper. Hệ thống sẽ tự hủy đơn và tạo yêu cầu hoàn toàn bộ phí. Khoản hoàn chờ đối soát trước khi được xác nhận hoàn tất.','Matching is exhausted. Your order will be cancelled automatically with a full refund request. Completion awaits reconciliation.'))}</p></section>`);
    if(o.status==='RETURNING')right.insertAdjacentHTML('afterbegin',`<section class="card card-body"><h2>${h(t('Nhận lại hàng hoàn','Receive returned parcel'))}</h2><p>${h(t('Chỉ xác nhận khi bạn đã nhận và kiểm tra hàng Shipper trả lại.','Confirm only after receiving and checking the returned parcel.'))}</p><form data-confirm-return><label class="form-check"><input class="form-check-input" type="checkbox" required name="received"> ${h(t('Tôi đã nhận lại hàng','I have received the parcel back'))}</label><button class="btn btn-dark mt-3">${h(t('Xác nhận nhận hàng hoàn','Confirm return received'))}</button><div data-return-message role="status"></div></form></section>`);
    if(o.status==='CANCELLED'&&(o.status_history||[]).some(s=>s.old_status==='NO_SHIPPER_FOUND')){const note=document.createElement('p');note.className='alert alert-warning';note.textContent=t('Đơn tự hủy vì không tìm được shipper phù hợp trong 5 phút. Yêu cầu hoàn toàn bộ tiền đã được tạo; hãy theo dõi trạng thái hoàn tiền bên dưới.','The system cancelled this order because no eligible shipper was found within 5 minutes. A full refund has been requested; track its status below.');target.prepend(note);}
    const returnForm=$('[data-confirm-return]');if(returnForm)returnForm.onsubmit=async e=>{e.preventDefault();if(!returnForm.reportValidity())return;const button=$('button',returnForm);if(button.disabled)return;button.disabled=true;try{await api('customer/confirm-return.php','POST',{order_id:String(id)});await detail();}catch(error){report(error,$('[data-return-message]',returnForm));button.disabled=false;}};
    $('[data-refresh]').onclick=()=>run(detail);
    async function openCheckout(selectedMethod) {
      let payment=p;
      if (!payment) {
        const method=selectedMethod||'GATEWAY',keyId=String(id)+':'+method;
        if(!paymentKeys.has(keyId))paymentKeys.set(keyId,crypto.randomUUID());
        try { payment=(await api('customer/payments.php?order_id='+encodeURIComponent(id),'POST',{method},paymentKeys.get(keyId))).payment; }
        catch(error) {
          if(error.code!=='PAYMENT_ALREADY_EXISTS')throw error;
          payment=(await api('customer/payments.php?order_id='+encodeURIComponent(id))).payment;
          if(payment&&payment.method!==selectedMethod){await detail();$('[data-payment-card]')?.insertAdjacentHTML('beforeend',`<p role="status">${h(t('Đơn đã có yêu cầu thanh toán. Vui lòng tiếp tục yêu cầu hiện tại để tránh thanh toán trùng.','A payment request already exists. Continue that request to avoid duplicate payment.'))}</p>`);return;}
        }
      }
      if(payment?.checkout_url&&['PENDING','PROCESSING'].includes(payment.status)) {
        // Use the local authenticated signing endpoint; SePay return URLs carry no checkout flag.
        location.assign('public/api/customer/checkout.php?order_id='+encodeURIComponent(id));
      } else await detail();
    }
    const pay=$('[data-pay]');if(pay)pay.onsubmit=async e=>{
      e.preventDefault();if(checkoutPending)return;
      const method=new FormData(pay).get('method');if(!['GATEWAY','CARD'].includes(method))return;
      paymentChoices.set(String(id),method);checkoutPending=true;
      for(const control of pay.elements)control.disabled=true;
      try{await openCheckout(method);}catch(error){report(error,$('[data-payment-error]')||$('[data-payment-card]')||card);}
      finally{checkoutPending=false;const current=$('[data-pay]');if(current){for(const control of current.elements)control.disabled=false;$('button[type=submit]',current).textContent=t('Tiến hành thanh toán','Proceed to payment');}}
    };
    // Legacy checkout links now show the choice screen. Opening a payment page
    // must not create a transaction or choose a method on the customer's behalf.
    if(params.get('checkout')==='1'){
      params.delete('checkout');const current=new URL(location.href);current.searchParams.delete('checkout');history.replaceState(null,'',current);
    }
    const cancel=$('[data-cancel]');if(cancel)cancel.onsubmit=async e=>{e.preventDefault();const button=$('button',cancel);if(button.disabled)return;button.disabled=true;try{await api('customer/cancel-order.php?id='+id,'POST',{reason:cancel.elements.reason.value});await detail();}catch(error){report(error,$('[data-cancel-error]',cancel));button.disabled=false;}};
    if(view==='payment'){
      const left=$('.col-lg-7',target),summary=$('.col-lg-5 .c-dark',target);left.replaceChildren(card);
      if(summary){summary.insertAdjacentHTML('afterbegin',`<p class="c-quote-note">${h(o.order_code)}</p>${route(o)}`);summary.parentElement.classList.add('c-sticky');}
    }
    if(view==='matching'){
      const panel=document.createElement('section');panel.className='c-state';
      panel.innerHTML=`${o.status==='SEARCHING_SHIPPER'&&!scheduledWaiting?'<div class="c-radar" aria-hidden="true"><i></i><i></i><i></i><span>↗</span></div>':''}<h1>${h(scheduledWaiting?t('Đã ghi nhận lịch lấy hàng','Pickup scheduled'):o.status==='SEARCHING_SHIPPER'?t('Đang tìm shipper gần bạn','Finding a shipper near you'):state(o.status))}</h1><p>${h(o.order_code)}</p>${o.status==='SEARCHING_SHIPPER'&&!scheduledWaiting?`<p class="c-hint">${h(t('Hệ thống tìm shipper tối đa 5 phút. Nếu chưa ai nhận, đơn tự hủy và yêu cầu hoàn toàn bộ tiền.','Matching lasts up to 5 minutes. If nobody accepts, the order is cancelled and a full refund is requested.'))}</p>`:''}<div class="c-actions">${link('order',t('Xem chi tiết đơn','View order details'),id)}</div>`;
      $('.c-actions',panel).append($('[data-refresh]',target));const routeCard=$('.col-lg-7 .card',target);if(routeCard)panel.append(routeCard);const cancelForm=$('[data-cancel]',target);if(cancelForm)panel.append(cancelForm.closest('section'));target.replaceChildren(panel);
    }
    if(['order','tracking','matching','payment'].includes(view)&&!['DELIVERED','RETURNED','CANCELLED'].includes(o.status)){
      const section=document.createElement('section');section.className='card card-body';
      const milestones=[['SEARCHING_SHIPPER',t('Tìm shipper','Matching')],['SHIPPER_ASSIGNED',t('Đã nhận đơn','Accepted')],['PICKED_UP',t('Đã lấy hàng','Picked up')],['IN_DELIVERY',t('Đang giao','In transit')],['DELIVERED',t('Đã giao','Delivered')]];
      const current=scheduledWaiting?-1:milestones.findIndex(([status])=>status===o.status);
      section.classList.add('c-live-tracking');
      section.innerHTML='<div class="c-live-heading"><h2>'+h(t('Theo dõi giao hàng','Live delivery tracking'))+'</h2><span class="c-chip">'+h(state(o.status))+'</span></div><ol class="c-live-progress">'+milestones.map(([,label],i)=>'<li class="'+(i<current?'is-done':i===current?'is-current':'')+'"><span>'+(i<current?'✓':i+1)+'</span>'+h(label)+'</li>').join('')+'</ol><div class="somi-leaflet-map" data-route-map></div><p class="c-hint" role="status" data-location-status>'+h(t('Đang tải vị trí…','Loading location…'))+'</p>';
      ($('.col-lg-7',target)||target).prepend(section);trackShipper(o,section);
    }
  }

  async function create(){
    const form=$('[data-create-form]'),sidebar=$('[data-quote]');form.dataset.create='';
    const [addressData,categoryData]=await Promise.all([api('customer/addresses.php'),api('customer/parcel-categories.php')]);
    const addresses=addressData.items,categories=categoryData.items.filter(c=>c.restriction_type==='ALLOWED');
    if(!categories.length){$('[data-create-blocked]').hidden=false;$('[data-create-blocked]').innerHTML=empty(t('Chưa có loại hàng được phép','No parcel categories available'),t('Vui lòng liên hệ hỗ trợ để được mở danh mục hàng.','Please contact support to enable parcel categories.'));$('[data-create-form-wrap]').hidden=true;return;}
    let step=1,quote=null,key=null,revision=0,busy=false,restoring=false;
    let routePicker;
    const draftKey='somi-create-draft:'+String(profile.user_id||profile.email||'current');
    let completed=false;
    function saveDraft(){
      if(completed||restoring||!routePicker)return;
      const fields={};
      for(const control of form.elements){
        if(!control.name||['file','password','submit','button'].includes(control.type))continue;
        if(control.type==='radio'){if(control.checked)fields[control.name]=control.value;}
        else fields[control.name]=control.type==='checkbox'?control.checked:control.value;
      }
      try{sessionStorage.setItem(draftKey,JSON.stringify({fields,step,currency,quote,key,route:routePicker.snapshot(),savedAt:Date.now()}));}catch{}
    }
    window.addEventListener('pagehide',saveDraft);
    document.addEventListener('somi:before-preference-change',saveDraft);
    const error=document.createElement('div');error.dataset.createError='';error.setAttribute('role','alert');form.append(error);
    const field=name=>form.elements.namedItem(name);
    function quoteView(){sidebar.innerHTML=quote?`<h2>${h(t('Báo giá của bạn','Your quote'))}</h2>${fees({pricing_snapshot:quote.snapshot,currency_code:quote.snapshot.currency_code,total_charge_minor:quote.snapshot.components.total_charge_minor})}<p class="c-quote-note">${h(quote.snapshot.context.distance_km)} km · ${h(quote.snapshot.parcel.weight_kg)} kg</p><p class="c-quote-note">${h(t('Hiệu lực đến','Valid until'))}: ${h(date(quote.expires_at))}</p>`:`<h2>${h(t('Phí giao hàng','Delivery quote'))}</h2><p class="c-quote-note">${h(t('Hoàn tất thông tin để nhận báo giá trước khi xác nhận.','Complete the details to receive a quote before confirming.'))}</p>`;}
    function invalidate(){if(restoring)return;revision++;quote=null;key=null;quoteView();if(step===4)show(3);saveDraft();}
    function show(n){step=n;$('[data-create-map-panel]').hidden=n!==1;sidebar.hidden=n===1;$$('[data-step]',form).forEach(el=>el.hidden=Number(el.dataset.step)!==step);$$('[data-stepper] li').forEach((el,i)=>{el.classList.toggle('is-current',i+1===step);el.classList.toggle('is-done',i+1<step);});$('[data-prev]',form).hidden=step===1;$('[data-next]',form).textContent=step===4?t('Xác nhận tạo đơn','Confirm order'):step===3?t('Lấy báo giá','Get quote'):t('Tiếp tục','Continue');if(step===4)$('[data-next]',form).dataset.confirm='';else delete $('[data-next]',form).dataset.confirm;}
    routePicker=window.SomiCreateRoute.attach({root,form,addresses,profile,api,t,onChange:invalidate});
    mapHandles.push(routePicker);
    $('[data-types]').innerHTML=categories.map((c,i)=>`<label class="c-tile"><input type="radio" name="type" value="${h(c.category_id)}" ${i===0?'checked':''}><strong>${h(vi?c.name_vi:c.name_en)}</strong></label>`).join('');
    field('pkg-weight').min='0.001';field('pkg-weight').step='0.001';field('pkg-weight').max='30';$('[data-weight-hint]').textContent=t('Tổng khối lượng tối đa 30 kg.','Maximum combined weight: 30 kg.');
    const later=$('input[name=schedule][value=later]',form),scheduleAt=field('schedule-at');
    later.disabled=false;
    later.closest('label').querySelector('small').textContent=t('Tối thiểu 30 phút, tối đa 7 ngày. Giờ Việt Nam (UTC+7).','30 minutes to 7 days ahead. Vietnam time (UTC+7).');
    $('input[name=schedule][value=now]',form).closest('label').querySelector('small').textContent=t('Tìm shipper sau khi xác nhận thanh toán.','Matching begins after verified payment.');
    const localAppointmentInput=ms=>new Date(ms+7*3600000).toISOString().slice(0,16);
    function syncSchedule(){const scheduled=later.checked;$('[data-later]',form).hidden=!scheduled;scheduleAt.required=scheduled;scheduleAt.min=localAppointmentInput(Date.now()+31*60000);scheduleAt.max=localAppointmentInput(Date.now()+7*86400000);if(scheduled&&!scheduleAt.value)scheduleAt.value=localAppointmentInput(Date.now()+3600000);}
    function readSchedule(){if(!later.checked)return {mode:'now',at:null};const value=scheduleAt.value;const when=/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(value)?Date.parse(value+(value.length===16?':00':'')+'+07:00'):NaN;if(!Number.isFinite(when)||when<Date.now()+30*60000||when>Date.now()+7*86400000)throw new Error(t('Chọn giờ lấy hàng sau ít nhất 30 phút và trong 7 ngày tới (UTC+7).','Choose pickup 30 minutes to 7 days ahead, in Vietnam time (UTC+7).'));return {mode:'later',at:new Date(when).toISOString()};}
    $$('input[name=schedule]',form).forEach(input=>input.addEventListener('change',syncSchedule));syncSchedule();
    form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);$('[data-prev]',form).onclick=()=>{if(!busy)show(Math.max(1,step-1));};
    function point(side){const saved=$(`[data-side="${side}"] [data-saved]`).value,name=field(side+'-name').value.trim(),phone=field(side+'-phone').value.replace(/\s/g,'');if(!name||!/^\+?[0-9]{8,15}$/.test(phone))throw new Error(t('Kiểm tra tên và số điện thoại người gửi/người nhận.','Check sender/recipient names and phone numbers.'));const p={contact_name:name,contact_phone:phone};if(saved)p.saved_address_id=saved;else{const lat=field(side+'-latitude').value,lng=field(side+'-longitude').value,address=field(side+'-detail').value.trim();if(!address||!lat||!lng||!field(side+'-latitude').checkValidity()||!field(side+'-longitude').checkValidity()){$(`[data-side="${side}"] details`).open=true;throw new Error(t('Tìm và chọn một địa chỉ hoặc ghim hợp lệ cho điểm lấy/giao.','Search and select a valid pickup/delivery address or map pin.'));}p.address=routePicker.address(side);}return p;}
    function parcel(){const weight=field('pkg-weight').value,quantity=field('pkg-quantity').value;if(!/^\d+(?:\.\d{1,3})?$/.test(weight)||!/^\d+$/.test(quantity)||Number(quantity)<1||Number(quantity)>1000)throw new Error(t('Kiểm tra khối lượng và số kiện.','Check parcel weight and quantity.'));const [whole,fraction='']=weight.split('.'),grams=(BigInt(whole)*1000n+BigInt(fraction.padEnd(3,'0')))*BigInt(quantity);if(grams<=0n||grams>30000n)throw new Error(t('Tổng khối lượng phải lớn hơn 0 và không vượt 30 kg.','Combined weight must be greater than 0 and at most 30 kg.'));return {category_id:form.elements.type.value,weight_kg:`${grams/1000n}.${String(grams%1000n).padStart(3,'0')}`,description:(quantity==='1'?'':`[${quantity} ${t('kiện','parcels')}] `)+field('pkg-description').value};}
    function setBusy(value){busy=value;document.querySelectorAll('[data-set-currency]').forEach(el=>el.disabled=value);for(const control of form.elements)control.disabled=value;if(!value)later.disabled=false;}
    form.onsubmit=async e=>{e.preventDefault();if(busy)return;error.innerHTML='';try{
      if(step===1){point('pickup');point('delivery');if(await routePicker.confirmPickup())show(2);return;}
      if(step===2){parcel();show(3);return;}
      if(step===3){point('pickup');point('delivery');if(!await routePicker.confirmPickup())return;const body={currency_code:currency,pickup:point('pickup'),delivery:point('delivery'),parcel:parcel(),customer_note:field('pkg-note').value,schedule:readSchedule()};const version=revision;setBusy(true);try{const data=await api('customer/quotes.php','POST',body);if(version!==revision)return;quote=data.quote;key=crypto.randomUUID();$('[data-summary]').innerHTML=route({pickup_address:field('pickup-detail').value,delivery_address:field('delivery-detail').value,sender_name:body.pickup.contact_name,sender_phone:body.pickup.contact_phone,receiver_name:body.delivery.contact_name,receiver_phone:body.delivery.contact_phone})+`<p class="c-panel-sub">${h(body.parcel.description)} · ${h(body.parcel.weight_kg)} kg</p>`+scheduleLabel({schedule:quote.snapshot.schedule})+`<p class="c-hint">${h(t('Giá được chốt theo báo giá đã xác nhận.','The price is fixed by the confirmed quote.'))}</p>`;quoteView();show(4);}finally{setBusy(false);}return;}
      if(!quote||Date.parse(quote.expires_at)<=Date.now()){invalidate();throw new Error(t('Báo giá đã hết hạn. Vui lòng lấy báo giá mới.','Quote expired. Please request a new quote.'));}
      if(!field('agree').checked)throw new Error(t('Vui lòng xác nhận điều kiện kiện hàng.','Please confirm the parcel declaration.'));
      if(!await routePicker.confirmPickup())return;
      setBusy(true);try{const result=await api('customer/orders.php','POST',{quote_id:quote.quote_id},key);completed=true;try{sessionStorage.removeItem(draftKey);}catch{}location.href=url('payment',result.order.order_id);}catch(err){if(['QUOTE_EXPIRED','SCHEDULE_EXPIRED'].includes(err.code))invalidate();throw err;}finally{setBusy(false);}
    }catch(err){report(err,error);}};
    // Agreement does not affect the quote or the creation idempotency key.
    field('agree').addEventListener('input',e=>e.stopPropagation());field('agree').addEventListener('change',e=>e.stopPropagation());
    root.addEventListener('currencychange',invalidate);
    let saved=null;
    try{saved=JSON.parse(sessionStorage.getItem(draftKey)||'null');}catch{}
    if(saved&&Date.now()-saved.savedAt<2*60*60*1000&&saved.fields){
      restoring=true;
      routePicker.restore(saved.route);
      for(const control of form.elements){
        if(!control.name||!Object.hasOwn(saved.fields,control.name)||['file','password','submit','button'].includes(control.type))continue;
        if(control.type==='radio')control.checked=control.value===saved.fields[control.name];
        else if(control.type==='checkbox')control.checked=Boolean(saved.fields[control.name]);
        else control.value=saved.fields[control.name];
      }
      let scheduleMatches=false;try{const requested=readSchedule(),quoted=saved.quote?.snapshot?.schedule;scheduleMatches=requested.mode===(quoted?.mode||'now')&&(requested.mode==='now'||Date.parse(requested.at)===Date.parse(quoted?.at));}catch{}
      quote=saved.currency===currency&&scheduleMatches&&Date.parse(saved.quote?.expires_at)>Date.now()?saved.quote:null;
      key=quote?saved.key:null;
      step=Math.max(1,Math.min(4,Number(saved.step)||1));if(step===4&&!quote)step=3;
      if(step===4)$('[data-summary]').innerHTML=route({pickup_address:field('pickup-detail').value,delivery_address:field('delivery-detail').value,sender_name:field('pickup-name').value,sender_phone:field('pickup-phone').value,receiver_name:field('delivery-name').value,receiver_phone:field('delivery-phone').value})+scheduleLabel({schedule:quote?.snapshot?.schedule});
      restoring=false;
    }
    syncSchedule();
    form.addEventListener('input',saveDraft);form.addEventListener('change',saveDraft);
    syncSchedule();quoteView();show(step);
    form.addEventListener('submit',()=>queueMicrotask(saveDraft));
  }
  async function account(){
    $('[data-profile]').innerHTML=`<span class="c-avatar">${h(profile.full_name.split(/\s+/).slice(-2).map(s=>s[0]).join(''))}</span><h2>${h(profile.full_name)}</h2><p class="c-panel-sub">${h(profile.email)}</p><span class="c-chip">${h(t('Tài khoản đang hoạt động','Active account'))}</span>`;
    const form=$('[data-profile-form]');form.elements.fullName.value=profile.full_name;form.elements.email.value=profile.email;form.elements.email.readOnly=true;
    const profileError=document.createElement('div');form.append(profileError);const actions=$('[data-profile-actions]');form.elements.fullName.oninput=()=>actions.hidden=form.elements.fullName.value===profile.full_name;
    $('[data-profile-cancel]').onclick=()=>{form.elements.fullName.value=profile.full_name;actions.hidden=true;profileError.innerHTML='';};
    form.onsubmit=async e=>{e.preventDefault();const button=$('[type=submit]',form);if(button.disabled)return;button.disabled=true;try{profile=(await api('account/profile.php','PUT',{full_name:form.elements.fullName.value})).profile;await account();header();}catch(err){report(err,profileError);}finally{button.disabled=false;}};
    $('[data-phone-panel]').innerHTML=`<h2>${h(t('Số điện thoại','Phone number'))}</h2><p>${h(profile.phone)}</p><p class="c-panel-sub">${h(profile.phone_verified_at?t('Đã xác minh','Verified'):t('Chưa xác minh','Not verified'))}</p>`;
    $('[data-fx-note]').textContent=t('Tiền tệ áp dụng cho báo giá và đơn mới. Đơn đã tạo giữ nguyên tiền tệ lúc đặt.','Currency applies to quotes and new orders. Existing orders keep their original currency.');
    $('[data-password-meta]').textContent=t('Sau khi đổi mật khẩu, bạn cần đăng nhập lại.','Sign in again after changing your password.');
    const password=$('[data-password-form]'),passwordError=document.createElement('div');password.append(passwordError);
    password.onsubmit=async e=>{e.preventDefault();const button=$('[type=submit]',password);if(button.disabled)return;const next=$('#newPassword').value;if(next.length<8||next!==$('#confirmPassword').value){report(new Error(t('Mật khẩu mới phải có ít nhất 8 ký tự và khớp xác nhận.','Use at least 8 characters and matching confirmation.')),passwordError);return;}button.disabled=true;try{await api('account/change-password.php','POST',{current_password:$('#currentPassword').value,password:next});await api('auth/logout.php','POST',{});location.href='?page=auth';}catch(err){report(err,passwordError);}finally{button.disabled=false;}};
    let bankPanel=$('[data-refund-bank]');if(!bankPanel){bankPanel=document.createElement('section');bankPanel.className='card card-body';bankPanel.dataset.refundBank='';$('.col-lg-8.vstack').append(bankPanel);}
    const bank=(await api('customer/bank-account.php')).bank_account;
    const bankReview=bank?.needs_reentry?t('Không đọc được dữ liệu ngân hàng cũ. Vui lòng lưu lại thông tin.','Old bank details are unreadable. Please save your bank account again.'):bank?.review_status==='PENDING'?t('Chờ admin duyệt','Awaiting admin approval'):bank?.review_status==='REJECTED'?t('Cần chỉnh sửa','Changes requested'):bank?t('Đã duyệt','Approved'):'';
    bankPanel.innerHTML=`<h2>${h(t('Ngân hàng nhận hoàn tiền','Refund bank account'))}</h2><p>${bank?h(bank.bank_name+' · '+bank.account_holder+' · '+bank.account_number):h(t('Chưa lưu tài khoản','No bank account saved'))}</p><p>${h(bankReview)} ${h(bank?.review_note||'')}</p><p>${h(t('Admin hoàn tiền thủ công khi đơn đủ điều kiện. Chỉ trạng thái Thành công mới xác nhận đã hoàn.','Admin refunds eligible orders manually. Only Succeeded confirms completion.'))}</p><form data-refund-bank-form>${[['bank_name',t('Ngân hàng','Bank'),'text'],['account_holder',t('Chủ tài khoản','Account holder'),'text'],['account_number',t('Số tài khoản','Account number'),'text'],['current_password',t('Mật khẩu hiện tại','Current password'),'password']].map(([name,label,type])=>`<label class="form-label d-block">${h(label)}<input class="form-control" name="${name}" type="${type}" required maxlength="${name==='account_number'?34:name==='current_password'?1024:name==='bank_name'?120:150}" ${name==='account_number'?'pattern="[0-9]{6,34}" inputmode="numeric"':''} autocomplete="${type==='password'?'current-password':'off'}"></label>`).join('')}<button class="btn btn-dark">${h(t('Lưu tài khoản','Save account'))}</button><div data-bank-error role="status"></div></form>`;
    const bankForm=$('[data-refund-bank-form]');
    for(const field of ['bank_name','account_holder','account_number'])bankForm.elements[field].value=bank?.[field]||'';
    const bankButtons=document.createElement('div');bankButtons.className='c-form-actions';bankButtons.innerHTML=`<button type="button" class="btn btn-outline-dark" data-edit-bank>${h(t('Chỉnh sửa ngân hàng','Edit bank account'))}</button><button type="button" class="btn btn-outline-dark" data-cancel-bank>${h(t('Hủy chỉnh sửa','Cancel editing'))}</button>`;bankForm.before(bankButtons);
    function editBank(editing){bankForm.hidden=!editing;bankButtons.querySelector('[data-edit-bank]').hidden=editing;bankButtons.querySelector('[data-cancel-bank]').hidden=!editing||!bank;}
    bankButtons.querySelector('[data-edit-bank]').onclick=()=>editBank(true);
    bankButtons.querySelector('[data-cancel-bank]').onclick=()=>{for(const field of ['bank_name','account_holder','account_number'])bankForm.elements[field].value=bank?.[field]||'';bankForm.elements.current_password.value='';editBank(false);};
    editBank(!bank||bank.needs_reentry);
    bankForm.onsubmit=async e=>{e.preventDefault();if(!bankForm.reportValidity())return;const button=$('button',bankForm);if(button.disabled)return;button.disabled=true;try{await api('customer/bank-account.php','POST',Object.fromEntries(new FormData(bankForm)));await account();$('[data-refund-bank]').insertAdjacentHTML('afterbegin',`<p role="status">${h(t('Đã lưu thông tin ngân hàng.','Bank account saved.'))}</p>`);}catch(error){report(error,$('[data-bank-error]',bankForm));}finally{button.disabled=false;}};
    const addresses=await api('customer/addresses.php');$('[data-address-count]').textContent=t('Địa chỉ đã lưu: ','Saved addresses: ')+addresses.items.length;
    if(!$('[data-nationality]')){
      const section=document.createElement('section');section.className='card card-body';section.innerHTML=`<h2>${h(t('Yêu cầu đổi quốc tịch','Request nationality change'))}</h2><form data-nationality><div class="row g-3"><div class="c-field col-md-4"><label class="form-label">${h(t('Mã quốc gia','Country code'))}<input class="form-control" name="country" pattern="[A-Z]{2}" maxlength="2" required></label></div><div class="c-field col-md-8"><label class="form-label">${h(t('Lý do','Reason'))}<input class="form-control" name="reason" maxlength="1000" required></label></div></div><div class="c-form-actions"><button class="btn btn-outline-dark">${h(t('Gửi yêu cầu','Submit request'))}</button></div><div data-error></div></form>`;$('.col-lg-8.vstack').append(section);
      const nationality=$('[data-nationality]');nationality.onsubmit=async e=>{e.preventDefault();const button=$('button',nationality);if(button.disabled)return;button.disabled=true;try{await api('account/nationality-request.php','POST',{nationality_code:nationality.elements.country.value,reason:nationality.elements.reason.value});$('[data-error]',nationality).textContent=t('Đã gửi yêu cầu xét duyệt.','Request submitted for review.');}catch(err){report(err,$('[data-error]',nationality));}finally{button.disabled=false;}};
    }
  }
  async function addresses(){
    resetMaps();
    const data=await api('customer/addresses.php'),container=$('[data-addresses]');
    const total=$('[data-address-total]');if(total)total.textContent=data.items.length+' '+t('địa chỉ đã lưu','saved addresses');
    container.innerHTML=`<div class="c-address-grid">${data.items.map(a=>`<article class="c-address-card ${a.is_default?'is-default':''}"><div class="c-address-card-head"><span class="c-address-symbol">${glyph('pin')}</span><div><span class="c-address-kicker">${h(t('ĐỊA CHỈ ĐÃ LƯU','SAVED ADDRESS'))}</span><h2>${h(a.label||t('Địa chỉ','Address'))}</h2></div>${a.is_default?`<span class="c-address-default">${glyph('check')}${h(t('Mặc định','Default'))}</span>`:''}</div><p class="c-address-street">${h(a.address_detail)}</p>${a.province?`<p class="c-address-area">${glyph('pin')}${h(a.province)}</p>`:''}<div class="c-address-card-actions"><button class="btn c-address-edit" data-edit="${h(a.address_id)}">${glyph('edit')}${h(t('Sửa','Edit'))}</button><button class="btn c-address-delete" data-archive="${h(a.address_id)}">${glyph('trash')}${h(t('Xóa','Delete'))}</button></div></article>`).join('')}</div>${!data.items.length?`<div class="c-address-empty"><span class="c-address-symbol">${glyph('pin')}</span><h2>${h(t('Một nơi quen, nhiều chuyến gửi','Save a place. Send with ease.'))}</h2><p>${h(t('Thêm địa chỉ đầu tiên để tạo đơn nhanh hơn trong lần tới.','Add your first address to speed up your next delivery.'))}</p></div>`:''}<section class="card card-body mt-4 c-address-editor" data-address-editor hidden></section>`;
    const editor=$('[data-address-editor]');
    function edit(a={}){resetMaps();editor.hidden=false;editor.innerHTML=`<h2>${h(a.address_id?t('Sửa địa chỉ','Edit address'):t('Thêm địa chỉ','Add address'))}</h2><form data-address-form><div class="row g-3">${[['label',t('Tên gợi nhớ','Label')],['address_detail',t('Địa chỉ','Address')],['province',t('Tỉnh/thành','City')],['district',t('Quận/huyện','District')],['ward',t('Phường/xã','Ward')],['latitude',t('Vĩ độ','Latitude')],['longitude',t('Kinh độ','Longitude')]].map(([name,label])=>`<div class="c-field col-md-6"><label class="form-label">${h(label)}<input class="form-control" name="${name}" value="${h(a[name]??'')}" ${['address_detail','latitude','longitude'].includes(name)?'required':''} ${name==='latitude'?'type="number" min="-90" max="90" step="0.0000001"':name==='longitude'?'type="number" min="-180" max="180" step="0.0000001"':`maxlength="${name==='label'?60:name==='address_detail'?255:120}"`}></label></div>`).join('')}</div><label class="form-check c-check mt-3"><input class="form-check-input" name="is_default" type="checkbox" ${a.is_default?'checked':''}>${h(t('Đặt làm mặc định','Set as default'))}</label><div class="c-form-actions"><button class="btn btn-dark">${h(t('Lưu địa chỉ','Save address'))}</button><button class="btn btn-outline-dark" type="button" data-close>${h(t('Hủy','Cancel'))}</button></div><div data-error></div></form>`;
      const form=$('[data-address-form]');
      let picker;const apply=address=>{for(const [name,value] of Object.entries(address)){const input=form.elements.namedItem(name);if(input)input.value=value??'';}picker?.pin(address);};
      const lookup=window.SomiPlaces.attach(form.elements.address_detail,{api,language:vi?'vi':'en',onInvalidate(){form.elements.latitude.value='';form.elements.longitude.value='';picker?.pin(null);},onSelect:apply});
      lookupHandles.push(lookup);
      const invalidateLocation=()=>{lookup.reset();form.elements.latitude.value='';form.elements.longitude.value='';picker?.pin(null);};
      for(const name of ['province','district','ward'])form.elements[name].addEventListener('input',invalidateLocation);
      for(const name of ['latitude','longitude'])form.elements[name].addEventListener('input',()=>{lookup.reset();const latitude=form.elements.latitude,longitude=form.elements.longitude;picker?.pin(latitude.value&&longitude.value&&latitude.checkValidity()&&longitude.checkValidity()?{latitude:latitude.value,longitude:longitude.value}:null);});
      picker=addressMap(form,form.elements.address_detail,apply,()=>{lookup.reset();form.elements.latitude.value='';form.elements.longitude.value='';});picker.pin(a);
      $('[data-close]',form).onclick=()=>{resetMaps();editor.hidden=true;};form.onsubmit=async e=>{e.preventDefault();const button=$('[type=submit],button:not([type])',form);if(button.disabled)return;button.disabled=true;const body=Object.fromEntries(new FormData(form));body.is_default=form.elements.is_default.checked;body.country_code='VN';try{await api('customer/addresses.php'+(a.address_id?'?id='+a.address_id:''),a.address_id?'PUT':'POST',body);await addresses();}catch(err){report(err,$('[data-error]',form));button.disabled=false;}};editor.scrollIntoView({block:'nearest',behavior:'smooth'});
    }
    $('[data-add]').onclick=()=>edit();$$('[data-edit]').forEach(b=>b.onclick=()=>edit(data.items.find(a=>String(a.address_id)===b.dataset.edit)));
    $$('[data-archive]').forEach(b=>b.onclick=async()=>{if(b.disabled||!confirm(t('Xóa địa chỉ đã lưu này? Đơn cũ vẫn giữ địa chỉ ban đầu.','Delete this saved address? Existing orders keep their original address.')))return;b.disabled=true;try{await api('customer/addresses.php?id='+b.dataset.archive,'DELETE',{});await addresses();}catch(err){report(err);b.disabled=false;}});
  }
  function header(){
    if(profile.email?.endsWith('@demo.somi.invalid')&&!$('[data-demo-banner]')){const banner=document.createElement('p');banner.dataset.demoBanner='';banner.className='alert alert-info';banner.textContent=t('Tài khoản DEMO: thanh toán giả lập, không thu hoặc chuyển tiền thật.','DEMO account: simulated payments, no real charges or transfers.');root.prepend(banner);}
    document.querySelectorAll('[data-set-lang]').forEach(el=>{el.classList.toggle('active',el.dataset.setLang===(vi?'vi':'en'));el.setAttribute('aria-pressed',String(el.dataset.setLang===(vi?'vi':'en')));});
    document.querySelectorAll('[data-set-currency]').forEach(el=>el.classList.toggle('active',el.dataset.setCurrency===currency));
    document.querySelectorAll('[data-account-name]').forEach(el=>el.textContent=profile.full_name);
    document.querySelectorAll('[data-account-initials]').forEach(el=>el.textContent=profile.full_name.trim().split(/\s+/).slice(-2).map(n=>n[0]).join('').toUpperCase());
    document.querySelectorAll('[data-locale-current]').forEach(el=>el.textContent=(vi?'VI':'EN')+' · '+currency);
    document.querySelectorAll('[data-set-currency]').forEach(el=>{el.disabled=false;el.setAttribute('aria-pressed',String(el.dataset.setCurrency===currency));el.title=t('Áp dụng cho báo giá và đơn mới','Applies to quotes and new orders');});
    document.querySelectorAll('[data-unread]').forEach(el=>el.hidden=true);
  }
  async function run(fn){root.setAttribute('aria-busy','true');try{await fn();}catch(err){report(err);}finally{root.setAttribute('aria-busy','false');root.dataset.loaded='true';}}
  $$('form').forEach(form=>form.addEventListener('submit',e=>e.preventDefault()));
  document.querySelectorAll('[data-set-lang]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.setLang===(vi?'vi':'en'))return;if(window.SomiI18n?.setLanguage){window.SomiI18n.setLanguage(button.dataset.setLang);return;}document.dispatchEvent(new Event('somi:before-preference-change'));try{localStorage.setItem('somi-language',button.dataset.setLang);}catch{}location.reload();}));
  document.querySelectorAll('[data-set-currency]').forEach(button=>button.addEventListener('click',()=>{
    const next=button.dataset.setCurrency;if(!profile||button.disabled||!['VND','USD'].includes(next)||next===currency)return;
    currency=next;try{localStorage.setItem('somi-currency',currency);}catch{}
    if(window.SomiI18n)window.SomiI18n.currency=currency;
    header();root.dispatchEvent(new Event('currencychange'));
  }));
  if(!window.bootstrap?.Dropdown)document.querySelectorAll('[data-bs-toggle=dropdown]').forEach(button=>button.addEventListener('click',()=>{const menu=button.parentElement.querySelector('.dropdown-menu');if(menu){const open=menu.classList.toggle('show');button.setAttribute('aria-expanded',String(open));}}));
  document.querySelectorAll('[data-copy-vi][data-copy-en]').forEach(el=>el.textContent=vi?el.dataset.copyVi:el.dataset.copyEn);
  const flag=document.querySelector('[data-locale-flag]'),selectedFlag=document.querySelector(`[data-set-lang="${vi?'vi':'en'}"] .language-flag-svg`);if(flag&&selectedFlag)flag.replaceChildren(selectedFlag.cloneNode(true));
  run(async()=>{profile=embeddedProfile()||(await api('account/profile.php')).profile;currency=savedCurrency();if(window.SomiI18n)window.SomiI18n.currency=currency;header();if(view==='dashboard'){
      await dashboard();
      let active=true,timer=null;
      stopTracking=()=>{active=false;clearTimeout(timer);};
      const refresh=async()=>{try{if(active&&!document.hidden)await dashboard();}catch(e){if(e.status===401)stopTracking();}finally{if(active)timer=setTimeout(refresh,10000);}};
      timer=setTimeout(refresh,10000);
    }else if(view==='orders')await orders();else if(view==='create')await create();else if(view==='account')await account();else if(view==='addresses')await addresses();else await detail();});
})();
