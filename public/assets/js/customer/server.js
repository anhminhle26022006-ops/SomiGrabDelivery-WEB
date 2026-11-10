(() => {
  'use strict';
  const root = document.querySelector('[data-customer-server]'); if (!root) return;
  const vi = (window.SomiI18n?.lang || document.documentElement.lang) === 'vi', t = (vn,en) => vi?vn:en;
  const h = s => String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const url = (view,id) => '?'+new URLSearchParams({page:'customer',view,...(id?{id:String(id)}:{})});
  const money = (minor,currency) => new Intl.NumberFormat(vi?'vi-VN':'en-US',{style:'currency',currency}).format(Number(minor)/(currency==='USD'?100:1));
  const link = (view,label,id) => `<a class="btn btn-outline-dark" href="${h(url(view,id))}">${h(label)}</a>`;
  const view = document.body.dataset.cView, params = new URLSearchParams(location.search);
  let profile, pendingKey=null;
  let mapHandles=[],lookupHandles=[];
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
    PENDING_PAYMENT:['Chờ thanh toán','Awaiting payment'],SEARCHING_SHIPPER:['Đang tìm người giao','Finding a shipper'],NO_SHIPPER_FOUND:['Chưa tìm được người giao','No shipper found'],SHIPPER_ASSIGNED:['Đã phân công','Shipper assigned'],PICKED_UP:['Đã lấy hàng','Picked up'],IN_DELIVERY:['Đang giao','In delivery'],DELIVERED:['Đã giao','Delivered'],RETURNING:['Đang hoàn','Returning'],RETURNED:['Đã hoàn hàng','Returned'],CANCELLED:['Đã hủy','Cancelled']
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
    const errors={ORDER_NOT_FOUND:t('Không tìm thấy đơn hàng của bạn. Vui lòng mở lại danh sách đơn.','Your order was not found. Please return to your order list.'),ORDER_NOT_CANCELLABLE:t('Đơn đã được nhận hoặc đã chuyển trạng thái nên không thể hủy. Vui lòng tải trạng thái mới.','This order has been assigned or changed status and can no longer be cancelled. Please refresh its status.'),WEATHER_SERVICE_PAUSED:t('Tạm ngừng nhận đơn do thời tiết nguy hiểm tại điểm lấy hàng. Vui lòng thử lại sau.','New deliveries are paused due to unsafe weather at pickup. Please try again later.'),ROUTE_NOT_FOUND:t('Không tìm được tuyến xe máy giữa hai điểm. Vui lòng chọn lại vị trí lấy/giao.','No motorcycle route was found. Please choose different pickup or delivery locations.'),PAYMENT_CURRENCY_UNSUPPORTED:t('Thanh toán USD tạm khóa. SePay chỉ hỗ trợ VND.','USD payments are temporarily disabled. SePay only supports VND.'),PRICING_CONTEXT_UNAVAILABLE:t('Dịch vụ tuyến đường/thời tiết chưa sẵn sàng. Vui lòng thử lại sau.','Routing/weather is unavailable. Please try again later.'),WEIGHT_NOT_SUPPORTED:t('Khối lượng tối đa là 30 kg.','Maximum weight is 30 kg.'),OUTSIDE_SERVICE_AREA:t('Hai điểm phải thuộc TP.HCM và tuyến đường dưới 40 km.','Both locations must be in Ho Chi Minh City and the route shorter than 40 km.'),QUOTE_EXPIRED:t('Báo giá đã hết hạn. Vui lòng lấy báo giá mới.','Quote expired. Please request a new quote.'),PAYMENT_PROVIDER_UNAVAILABLE:t('Thanh toán trực tuyến chưa sẵn sàng.','Online payments are not available yet.'),BANK_NOT_CONFIGURED:t('Chuyển khoản chưa sẵn sàng.','Bank transfer is not available yet.'),AUTHENTICATION_REQUIRED:t('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.','Your session expired. Please sign in again.')};
    return errors[error.code]||(error.code?t('Không thể xử lý yêu cầu. Vui lòng kiểm tra thông tin hoặc thử lại sau.','Unable to process the request. Please check your information or try again later.'):error.message);
  }
  function report(error,node=root) {if(node===root){let box=$('[data-live-message]');if(!box){box=document.createElement('div');box.dataset.liveMessage='';root.prepend(box);}node=box;}node.innerHTML=`<div class="alert alert-danger" role="alert">${h(errorMessage(error))}</div>`+(error.status===401?link('orders',t('Đăng nhập lại','Sign in again')):'');}
  const $ = (selector,scope=root) => scope.querySelector(selector);
  const $$ = (selector,scope=root) => [...scope.querySelectorAll(selector)];
  const date = value => value?new Date(value).toLocaleString(vi?'vi-VN':'en-US'):'';
  const groups={all:null,active:['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'],done:['DELIVERED'],problem:['NO_SHIPPER_FOUND','RETURNING','RETURNED'],cancelled:['CANCELLED']};
  const badge = value => `<span class="c-chip">${h(state(value))}</span>`;
  const empty = (title,text='') => `<div class="c-empty"><strong>${h(title)}</strong>${text?`<p>${h(text)}</p>`:''}</div>`;
  const row = o => `<a class="c-order-row" href="${h(url('order',o.order_id))}"><div class="c-order-code"><strong>${h(o.order_code)}</strong><small>${h(date(o.created_at))}</small></div><div class="c-order-route"><span>${h(o.pickup_address)} → ${h(o.delivery_address)}</span><small>${h(o.receiver_name||'')}</small></div>${badge(o.status)}<span class="c-order-amount">${h(money(o.total_charge_minor,o.currency_code))}</span><span aria-hidden="true">›</span></a>`;
  function route(o) {
    return `<div class="c-route">${[['A',t('Lấy hàng','Pickup'),o.pickup_address,o.sender_name,o.sender_phone],['B',t('Giao đến','Delivery'),o.delivery_address,o.receiver_name,o.receiver_phone]].map(([letter,title,address,name,phone])=>`<div class="c-stop"><span class="c-stop-mark c-stop-${letter.toLowerCase()}">${letter}</span><div><small>${h(title)}</small><strong>${h(address)}</strong>${name?`<span>${h(name)} · ${h(phone)}</span>`:''}</div></div>`).join('')}</div>`;
  }
  function fees(o) {
    const parts=o.pricing_snapshot?.components||{};
    const labels={base_fee_minor:t('Cước cơ bản','Base fee'),distance_component_minor:t('Phần quãng đường','Distance component'),weight_component_minor:t('Phần trọng lượng','Weight component')};
    return `<dl class="c-fees">${Object.entries(labels).filter(([key])=>parts[key]!==undefined).map(([key,label])=>`<div><dt>${h(label)}</dt><dd>${h(money(parts[key],o.currency_code))}</dd></div>`).join('')}<div><dt>${h(t('Hệ số giờ / thời tiết','Time / weather multiplier'))}</dt><dd>${h(parts.time_multiplier||'1')} × ${h(parts.weather_multiplier||'1')}</dd></div><div class="c-fees-total"><dt>${h(t('Tổng phí','Total'))}</dt><dd>${h(money(o.total_charge_minor,o.currency_code))}</dd></div></dl>${o.pricing_snapshot?.context?.weather_condition?'<p class="c-quote-note">'+h(({NORMAL:t('Thời tiết bình thường','Normal weather'),LIGHT_RAIN:t('Mưa nhẹ','Light rain'),RAIN:t('Trời mưa','Rain')})[o.pricing_snapshot.context.weather_condition]||o.pricing_snapshot.context.weather_condition)+' · <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a></p>':''}`;
  }
  async function allOrders(){let items=[],cursor=null;const seen=new Set();do{const data=await api('customer/orders.php?limit=100'+(cursor?'&before_id='+encodeURIComponent(cursor):''));items.push(...data.items);cursor=data.next_before_id;if(cursor&&seen.has(cursor))throw new Error(t('Không thể tải tiếp danh sách đơn.','Unable to load the next page.'));seen.add(cursor);}while(cursor);return items;}
  async function dashboard(){
    const [items,addresses]=await Promise.all([allOrders(),api('customer/addresses.php')]);
    $('[data-greeting]').textContent=t('Xin chào, ','Hello, ')+profile.full_name.split(/\s+/).pop();
    const live=items.find(o=>groups.active.includes(o.status));
    $('[data-live]').innerHTML=live?`<section class="card card-body c-dark c-start">${badge(live.status)}<h2>${h(live.order_code)}</h2><p>${h(live.pickup_address)} → ${h(live.delivery_address)}</p><div class="c-actions">${link('order',t('Xem chi tiết','View details'),live.order_id)}</div></section>`:`<section class="card card-body c-dark c-start"><h2>${h(t('Bạn chưa có đơn nào đang giao','No deliveries in progress'))}</h2><p>${h(t('Nhập điểm lấy, điểm giao và thông tin kiện hàng. SOMI báo phí trước khi bạn xác nhận.','Add the pickup, delivery and parcel details. Review your quote before confirming.'))}</p><a class="btn btn-light" href="${h(url('create'))}">${h(t('Gửi một kiện hàng','Send a package'))}</a></section>`;
    $('[data-recent]').innerHTML=items.slice(0,5).map(row).join('')||empty(t('Bạn chưa có đơn hàng nào','No orders yet'));
    const waiting=items.filter(o=>['PENDING_PAYMENT','NO_SHIPPER_FOUND'].includes(o.status));
    $('[data-todo]').innerHTML=waiting.map(o=>`<a href="${h(url(o.status==='PENDING_PAYMENT'?'payment':'order',o.order_id))}"><span class="c-todo-icon c-tone-amber">↗</span><div><strong>${h(o.order_code)}</strong><small>${h(state(o.status))}</small></div><span>›</span></a>`).join('')||`<p class="c-muted">${h(t('Không có việc nào cần xử lý.','Nothing needs your attention.'))}</p>`;
    const now=new Date(),month=items.filter(o=>{const d=new Date(o.created_at);return d.getFullYear()===now.getFullYear()&&d.getMonth()===now.getMonth();});
    $('[data-figures]').innerHTML=[[month.length,t('Đơn đã tạo','Orders created')],[month.filter(o=>o.status==='DELIVERED').length,t('Đã giao','Delivered')],[month.filter(o=>o.status==='CANCELLED').length,t('Đã hủy','Cancelled')]].map(([n,label])=>`<div><strong>${n}</strong><span>${h(label)}</span></div>`).join('');
    const a=addresses.items.find(a=>a.is_default);
    $('[data-default-address]').innerHTML=`<h2>${h(t('Địa chỉ mặc định','Default address'))}</h2><p class="c-panel-sub">${h(a?.address_detail||t('Bạn chưa có địa chỉ mặc định.','No default address yet.'))}</p><a class="c-link" href="${h(url('addresses'))}">${h(t('Quản lý sổ địa chỉ','Manage addresses'))}</a>`;
  }
  async function orders(){
    const items=await allOrders();let tab=params.get('tab')||'all';if(!Object.hasOwn(groups,tab))tab='all';
    const titles={all:t('Tất cả','All'),active:t('Đang xử lý','In progress'),done:t('Đã giao','Delivered'),problem:t('Cần chú ý','Needs attention'),cancelled:t('Đã hủy','Cancelled')};
    function render(){
      $('[data-tabs]').innerHTML=Object.entries(titles).map(([key,label])=>`<button type="button" role="tab" data-tab="${key}" aria-selected="${key===tab}" class="nav-link${key===tab?' active':''}">${h(label)} <b>${items.filter(o=>!groups[key]||groups[key].includes(o.status)).length}</b></button>`).join('');
      $$('[data-tab]').forEach(b=>b.onclick=()=>{tab=b.dataset.tab;render();});
      const q=$('[data-search]').value.toLocaleLowerCase().trim(),from=$('[data-from]').value,to=$('[data-to]').value;
      const list=items.filter(o=>{const d=new Date(o.created_at),day=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;return (!groups[tab]||groups[tab].includes(o.status))&&(!q||`${o.order_code} ${o.pickup_address} ${o.delivery_address} ${o.receiver_name||''} ${o.parcel_description||''}`.toLocaleLowerCase().includes(q))&&(!from||day>=from)&&(!to||day<=to);});
      $('[data-list]').innerHTML=list.map(row).join('')||empty(items.length?t('Không có đơn phù hợp','No matching orders'):t('Bạn chưa có đơn hàng nào','No orders yet'));
      $('[data-clear]').hidden=!q&&!from&&!to;
    }
    ['[data-search]','[data-from]','[data-to]'].forEach(s=>$(s).addEventListener('input',render));
    $('[data-clear]').onclick=()=>{['[data-search]','[data-from]','[data-to]'].forEach(s=>$(s).value='');render();};render();
  }
  async function detail(){
    let id=params.get('id');if(!id&&params.get('code')){const found=(await allOrders()).find(o=>o.order_code===params.get('code'));id=found?.order_id;}
    if(!id)throw new Error(t('Mở đơn từ danh sách đơn hàng của bạn.','Open an order from your order list.'));
    const [data,payments,refunds]=await Promise.all([api('customer/orders.php?id='+encodeURIComponent(id)),api('customer/payments.php?order_id='+encodeURIComponent(id)),api('customer/refunds.php?order_id='+encodeURIComponent(id))]);
    const o=data.order,p=payments.payment,target=$('[data-order],[data-payment],[data-matching],[data-tracking]');
    const back=$('[data-back]');if(back)back.href=url('order',id);
    const canCancel=['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND'].includes(o.status);
    target.innerHTML=`<section class="c-head"><div><h1>${h(o.order_code)}</h1><div class="c-head-meta">${badge(o.status)}<span>${h(date(o.created_at))}</span></div></div><div class="c-head-actions c-actions">${view!=='payment'&&o.status==='PENDING_PAYMENT'?link('payment',t('Thanh toán','Pay'),id):''}<button class="btn btn-outline-dark" data-refresh>${h(t('Tải trạng thái mới','Refresh status'))}</button></div></section><div class="row g-4 align-items-start"><div class="col-lg-7 vstack gap-4"><section class="card card-body"><div class="c-panel-head"><h2>${h(t('Lộ trình','Route'))}</h2><span class="c-chip">${h(o.distance_km)} km</span></div>${route(o)}</section><section class="card card-body"><h2>${h(t('Hành trình đơn hàng','Order timeline'))}</h2><ol class="c-timeline">${(o.status_history||[]).map(s=>`<li class="c-tl-item is-done"><span class="c-tl-dot"></span><div><strong>${h(state(s.new_status))}</strong><small>${h(date(s.created_at))}</small>${s.note?`<p>${h(s.note)}</p>`:''}</div></li>`).join('')}</ol></section><section class="card card-body"><h2>${h(t('Kiện hàng','Parcel'))}</h2><dl class="c-pkg"><div><dt>${h(t('Khối lượng','Weight'))}</dt><dd>${h(o.estimated_weight_kg)} kg</dd></div><div class="c-span-2"><dt>${h(t('Mô tả','Description'))}</dt><dd>${h(o.parcel_description||'—')}</dd></div>${o.customer_note?`<div class="c-span-2"><dt>${h(t('Ghi chú','Note'))}</dt><dd>${h(o.customer_note)}</dd></div>`:''}</dl></section></div><div class="col-lg-5 vstack gap-4"><section class="card card-body${view==='payment'?' c-dark':''}"><h2>${h(t('Chi phí','Charges'))}</h2>${fees(o)}</section><section class="card card-body" data-payment-card><h2>${h(t('Thanh toán','Payment'))}</h2>${p?`<p>${h(paymentState(p.status))} · ${h(money(p.amount_minor,p.currency_code))}</p>`:`<p>${h(t('Chưa tạo yêu cầu thanh toán.','No payment request yet.'))}</p>`}</section></div></div>`;
    const card=$('[data-payment-card]');
    if(o.status==='PENDING_PAYMENT'&&!p&&o.currency_code==='VND')card.insertAdjacentHTML('beforeend',`<form data-pay><div class="c-tiles">${[['BANK_TRANSFER',t('Chuyển khoản QR','QR bank transfer')],['CARD',t('Thẻ','Card')],['GATEWAY',t('Cổng thanh toán','Payment gateway')]].map(([method,label],i)=>`<label class="c-tile"><input type="radio" name="method" value="${method}" ${i===0?'checked':''}><strong>${h(label)}</strong><small>SePay</small></label>`).join('')}</div><div class="c-form-actions"><button class="btn btn-dark">${h(t('Tạo yêu cầu thanh toán','Create payment request'))}</button></div><div data-payment-error></div></form>`);
    if(o.status==='PENDING_PAYMENT'&&o.currency_code==='USD')card.insertAdjacentHTML('beforeend',`<p class="c-panel-sub">${h(t('Thanh toán USD tạm khóa. SePay chỉ hỗ trợ VND.','USD payments are temporarily disabled. SePay only supports VND.'))}</p>`);
    if(p?.checkout_url&&o.status==='PENDING_PAYMENT'&&['PENDING','PROCESSING'].includes(p.status))card.insertAdjacentHTML('beforeend',`<a class="btn btn-dark" href="${h(p.checkout_url)}">${h(t('Mở trang thanh toán','Open checkout'))}</a>`);
    const right=card.parentElement;
    if(refunds.items.length)right.insertAdjacentHTML('beforeend',`<section class="card card-body"><h2>${h(t('Hoàn tiền','Refunds'))}</h2>${refunds.items.map(r=>`<p>${h(paymentState(r.status))} · ${h(money(r.refund_amount_minor,r.currency_code))}</p>`).join('')}</section>`);
    if(canCancel)right.insertAdjacentHTML('beforeend',`<section class="card card-body"><h2>${h(t('Hủy đơn','Cancel order'))}</h2><p class="c-panel-sub">${h(t('Phí hủy theo báo giá','Cancellation fee from your quote'))}: ${h(money(o.pricing_snapshot?.cancellation_fees?.[o.status]||'0',o.currency_code))}</p><form data-cancel><div class="c-field"><label class="form-label" for="cancel-reason">${h(t('Lý do hủy','Reason'))}</label><textarea id="cancel-reason" class="form-control" name="reason" required maxlength="500"></textarea></div><div class="c-form-actions"><button class="btn btn-outline-dark">${h(t('Hủy đơn','Cancel order'))}</button></div><div data-cancel-error></div></form></section>`);
    $('[data-refresh]').onclick=()=>run(detail);
    const pay=$('[data-pay]');if(pay)pay.onsubmit=async e=>{e.preventDefault();const button=$('button',pay);if(button.disabled)return;button.disabled=true;pendingKey??=crypto.randomUUID();try{await api('customer/payments.php?order_id='+id,'POST',{method:pay.elements.method.value},pendingKey);await detail();}catch(error){report(error,$('[data-payment-error]',pay));button.disabled=false;}};
    const cancel=$('[data-cancel]');if(cancel)cancel.onsubmit=async e=>{e.preventDefault();const button=$('button',cancel);if(button.disabled)return;button.disabled=true;try{await api('customer/cancel-order.php?id='+id,'POST',{reason:cancel.elements.reason.value});await detail();}catch(error){report(error,$('[data-cancel-error]',cancel));button.disabled=false;}};
    if(view==='payment'){
      const left=$('.col-lg-7',target),summary=$('.col-lg-5 .c-dark',target);left.replaceChildren(card);
      if(summary){summary.insertAdjacentHTML('afterbegin',`<p class="c-quote-note">${h(o.order_code)}</p>${route(o)}`);summary.parentElement.classList.add('c-sticky');}
    }
    if(view==='matching'){
      const panel=document.createElement('section');panel.className='c-state';
      panel.innerHTML=`${o.status==='SEARCHING_SHIPPER'?'<div class="c-radar" aria-hidden="true"><i></i><i></i><i></i><span>↗</span></div>':''}<h1>${h(o.status==='SEARCHING_SHIPPER'?t('Đang tìm shipper gần bạn','Finding a shipper near you'):state(o.status))}</h1><p>${h(o.order_code)}</p><div class="c-actions">${link('order',t('Xem chi tiết đơn','View order details'),id)}</div>`;
      $('.c-actions',panel).append($('[data-refresh]',target));const routeCard=$('.col-lg-7 .card',target);if(routeCard)panel.append(routeCard);const cancelForm=$('[data-cancel]',target);if(cancelForm)panel.append(cancelForm.closest('section'));target.replaceChildren(panel);
    }
    if(view==='tracking'){
      resetMaps();const section=document.createElement('section');section.className='card card-body';
      section.innerHTML='<h2>'+h(t('Tuyến giao hàng','Delivery route'))+'</h2><div class="somi-leaflet-map" data-route-map></div><p class="c-hint">'+h(t('Chưa có vị trí shipper trực tiếp.','Live shipper location is not available yet.'))+'</p>';
      $('.col-lg-7',target).prepend(section);const map=window.SomiMaps?.create($('[data-route-map]',section));mapHandles.push(map);
      if(map&&o.pickup_latitude!=null&&o.delivery_latitude!=null){try{const data=await api('customer/places.php','POST',{action:'route',pickup:{latitude:o.pickup_latitude,longitude:o.pickup_longitude},delivery:{latitude:o.delivery_latitude,longitude:o.delivery_longitude}});map.route(data.route);}catch(e){const hint=document.createElement('p');hint.className='c-hint';hint.textContent=errorMessage(e);section.append(hint);}}

    }
  }
  async function create(){
    const form=$('[data-create-form]'),sidebar=$('[data-quote]');form.dataset.create='';
    const [addressData,categoryData]=await Promise.all([api('customer/addresses.php'),api('customer/parcel-categories.php')]);
    const addresses=addressData.items,categories=categoryData.items.filter(c=>c.restriction_type==='ALLOWED');
    if(!categories.length){$('[data-create-blocked]').hidden=false;$('[data-create-blocked]').innerHTML=empty(t('Chưa có loại hàng được phép','No parcel categories available'),t('Vui lòng liên hệ hỗ trợ để được mở danh mục hàng.','Please contact support to enable parcel categories.'));$('[data-create-form-wrap]').hidden=true;return;}
    let step=1,quote=null,key=null,revision=0,busy=false;
    const error=document.createElement('div');error.dataset.createError='';error.setAttribute('role','alert');form.append(error);
    const field=name=>form.elements.namedItem(name);
    function quoteView(){sidebar.innerHTML=quote?`<h2>${h(t('Báo giá của bạn','Your quote'))}</h2>${fees({pricing_snapshot:quote.snapshot,currency_code:quote.snapshot.currency_code,total_charge_minor:quote.snapshot.components.total_charge_minor})}<p class="c-quote-note">${h(quote.snapshot.context.distance_km)} km · ${h(quote.snapshot.parcel.weight_kg)} kg</p><p class="c-quote-note">${h(t('Hiệu lực đến','Valid until'))}: ${h(date(quote.expires_at))}</p>`:`<h2>${h(t('Phí giao hàng','Delivery quote'))}</h2><p class="c-quote-note">${h(t('Hoàn tất thông tin để nhận báo giá trước khi xác nhận.','Complete the details to receive a quote before confirming.'))}</p>`;}
    function invalidate(){revision++;quote=null;key=null;quoteView();if(step===4)show(3);}
    function show(n){step=n;$('[data-create-map-panel]').hidden=n!==1;sidebar.hidden=n===1;$$('[data-step]',form).forEach(el=>el.hidden=Number(el.dataset.step)!==step);$$('[data-stepper] li').forEach((el,i)=>{el.classList.toggle('is-current',i+1===step);el.classList.toggle('is-done',i+1<step);});$('[data-prev]',form).hidden=step===1;$('[data-next]',form).textContent=step===4?t('Xác nhận tạo đơn','Confirm order'):step===3?t('Lấy báo giá','Get quote'):t('Tiếp tục','Continue');if(step===4)$('[data-next]',form).dataset.confirm='';else delete $('[data-next]',form).dataset.confirm;}
    const routePicker=window.SomiCreateRoute.attach({root,form,addresses,profile,api,t,onChange:invalidate});
    mapHandles.push(routePicker);
    $('[data-types]').innerHTML=categories.map((c,i)=>`<label class="c-tile"><input type="radio" name="type" value="${h(c.category_id)}" ${i===0?'checked':''}><strong>${h(vi?c.name_vi:c.name_en)}</strong></label>`).join('');
    field('pkg-weight').min='0.001';field('pkg-weight').step='0.001';field('pkg-weight').max='30';$('[data-weight-hint]').textContent=t('Tổng khối lượng tối đa 30 kg.','Maximum combined weight: 30 kg.');
    const later=$('input[name=schedule][value=later]',form);later.disabled=true;later.closest('label').querySelector('small').textContent=t('Chưa hỗ trợ đặt lịch giao hàng.','Scheduled delivery is not available yet.');$('input[name=schedule][value=now]',form).closest('label').querySelector('small').textContent=t('Tìm shipper sau khi xác nhận thanh toán.','Matching begins after verified payment.');
    form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);$('[data-prev]',form).onclick=()=>{if(!busy)show(Math.max(1,step-1));};
    function point(side){const saved=$(`[data-side="${side}"] [data-saved]`).value,name=field(side+'-name').value.trim(),phone=field(side+'-phone').value.replace(/\s/g,'');if(!name||!/^\+?[0-9]{8,15}$/.test(phone))throw new Error(t('Kiểm tra tên và số điện thoại người gửi/người nhận.','Check sender/recipient names and phone numbers.'));const p={contact_name:name,contact_phone:phone};if(saved)p.saved_address_id=saved;else{const lat=field(side+'-latitude').value,lng=field(side+'-longitude').value,address=field(side+'-detail').value.trim();if(!address||!lat||!lng||!field(side+'-latitude').checkValidity()||!field(side+'-longitude').checkValidity()){$(`[data-side="${side}"] details`).open=true;throw new Error(t('Tìm và chọn một địa chỉ hoặc ghim hợp lệ cho điểm lấy/giao.','Search and select a valid pickup/delivery address or map pin.'));}p.address=routePicker.address(side);}return p;}
    function parcel(){const weight=field('pkg-weight').value,quantity=field('pkg-quantity').value;if(!/^\d+(?:\.\d{1,3})?$/.test(weight)||!/^\d+$/.test(quantity)||Number(quantity)<1||Number(quantity)>1000)throw new Error(t('Kiểm tra khối lượng và số kiện.','Check parcel weight and quantity.'));const [whole,fraction='']=weight.split('.'),grams=(BigInt(whole)*1000n+BigInt(fraction.padEnd(3,'0')))*BigInt(quantity);if(grams<=0n||grams>30000n)throw new Error(t('Tổng khối lượng phải lớn hơn 0 và không vượt 30 kg.','Combined weight must be greater than 0 and at most 30 kg.'));return {category_id:form.elements.type.value,weight_kg:`${grams/1000n}.${String(grams%1000n).padStart(3,'0')}`,description:(quantity==='1'?'':`[${quantity} ${t('kiện','parcels')}] `)+field('pkg-description').value};}
    function setBusy(value){busy=value;for(const control of form.elements)control.disabled=value;if(!value)later.disabled=true;}
    form.onsubmit=async e=>{e.preventDefault();if(busy)return;error.innerHTML='';try{
      if(step===1){point('pickup');point('delivery');if(await routePicker.confirmPickup())show(2);return;}
      if(step===2){parcel();show(3);return;}
      if(step===3){point('pickup');point('delivery');if(!await routePicker.confirmPickup())return;const body={pickup:point('pickup'),delivery:point('delivery'),parcel:parcel(),customer_note:field('pkg-note').value};const version=revision;setBusy(true);try{const data=await api('customer/quotes.php','POST',body);if(version!==revision)return;quote=data.quote;key=crypto.randomUUID();$('[data-summary]').innerHTML=route({pickup_address:field('pickup-detail').value,delivery_address:field('delivery-detail').value,sender_name:body.pickup.contact_name,sender_phone:body.pickup.contact_phone,receiver_name:body.delivery.contact_name,receiver_phone:body.delivery.contact_phone})+`<p class="c-panel-sub">${h(body.parcel.description)} · ${h(body.parcel.weight_kg)} kg</p>`;quoteView();show(4);}finally{setBusy(false);}return;}
      if(!quote||Date.parse(quote.expires_at)<=Date.now()){invalidate();throw new Error(t('Báo giá đã hết hạn. Vui lòng lấy báo giá mới.','Quote expired. Please request a new quote.'));}
      if(!field('agree').checked)throw new Error(t('Vui lòng xác nhận điều kiện kiện hàng.','Please confirm the parcel declaration.'));
      if(!await routePicker.confirmPickup())return;
      setBusy(true);try{const result=await api('customer/orders.php','POST',{quote_id:quote.quote_id},key);location.href=url('payment',result.order.order_id);}catch(err){if(err.code==='QUOTE_EXPIRED')invalidate();throw err;}finally{setBusy(false);}
    }catch(err){report(err,error);}};
    // Agreement does not affect the quote or the creation idempotency key.
    field('agree').addEventListener('input',e=>e.stopPropagation());field('agree').addEventListener('change',e=>e.stopPropagation());
    quoteView();show(1);
  }
  async function account(){
    $('[data-profile]').innerHTML=`<span class="c-avatar">${h(profile.full_name.split(/\s+/).slice(-2).map(s=>s[0]).join(''))}</span><h2>${h(profile.full_name)}</h2><p class="c-panel-sub">${h(profile.email)}</p><span class="c-chip">${h(t('Tài khoản đang hoạt động','Active account'))}</span>`;
    const form=$('[data-profile-form]');form.elements.fullName.value=profile.full_name;form.elements.email.value=profile.email;form.elements.email.readOnly=true;
    const profileError=document.createElement('div');form.append(profileError);const actions=$('[data-profile-actions]');form.elements.fullName.oninput=()=>actions.hidden=form.elements.fullName.value===profile.full_name;
    $('[data-profile-cancel]').onclick=()=>{form.elements.fullName.value=profile.full_name;actions.hidden=true;profileError.innerHTML='';};
    form.onsubmit=async e=>{e.preventDefault();const button=$('[type=submit]',form);if(button.disabled)return;button.disabled=true;try{profile=(await api('account/profile.php','PUT',{full_name:form.elements.fullName.value})).profile;await account();header();}catch(err){report(err,profileError);}finally{button.disabled=false;}};
    $('[data-phone-panel]').innerHTML=`<h2>${h(t('Số điện thoại','Phone number'))}</h2><p>${h(profile.phone)}</p><p class="c-panel-sub">${h(profile.phone_verified_at?t('Đã xác minh','Verified'):t('Chưa xác minh','Not verified'))}</p>`;
    $('[data-fx-note]').textContent=t('Tiền tệ của đơn mới theo hồ sơ tài khoản. Thay đổi quốc tịch cần được duyệt.','New order currency follows your account profile. Nationality changes require review.');
    $('[data-password-meta]').textContent=t('Sau khi đổi mật khẩu, bạn cần đăng nhập lại.','Sign in again after changing your password.');
    const password=$('[data-password-form]'),passwordError=document.createElement('div');password.append(passwordError);
    password.onsubmit=async e=>{e.preventDefault();const button=$('[type=submit]',password);if(button.disabled)return;const next=$('#newPassword').value;if(next.length<8||next!==$('#confirmPassword').value){report(new Error(t('Mật khẩu mới phải có ít nhất 8 ký tự và khớp xác nhận.','Use at least 8 characters and matching confirmation.')),passwordError);return;}button.disabled=true;try{await api('account/change-password.php','POST',{current_password:$('#currentPassword').value,password:next});await api('auth/logout.php','POST',{});location.href='?page=auth';}catch(err){report(err,passwordError);}finally{button.disabled=false;}};
    const addresses=await api('customer/addresses.php');$('[data-address-count]').textContent=t('Địa chỉ đã lưu: ','Saved addresses: ')+addresses.items.length;
    if(!$('[data-nationality]')){
      const section=document.createElement('section');section.className='card card-body';section.innerHTML=`<h2>${h(t('Yêu cầu đổi quốc tịch','Request nationality change'))}</h2><form data-nationality><div class="row g-3"><div class="c-field col-md-4"><label class="form-label">${h(t('Mã quốc gia','Country code'))}<input class="form-control" name="country" pattern="[A-Z]{2}" maxlength="2" required></label></div><div class="c-field col-md-8"><label class="form-label">${h(t('Lý do','Reason'))}<input class="form-control" name="reason" maxlength="1000" required></label></div></div><div class="c-form-actions"><button class="btn btn-outline-dark">${h(t('Gửi yêu cầu','Submit request'))}</button></div><div data-error></div></form>`;$('.col-lg-8.vstack').append(section);
      const nationality=$('[data-nationality]');nationality.onsubmit=async e=>{e.preventDefault();const button=$('button',nationality);if(button.disabled)return;button.disabled=true;try{await api('account/nationality-request.php','POST',{nationality_code:nationality.elements.country.value,reason:nationality.elements.reason.value});$('[data-error]',nationality).textContent=t('Đã gửi yêu cầu xét duyệt.','Request submitted for review.');}catch(err){report(err,$('[data-error]',nationality));}finally{button.disabled=false;}};
    }
  }
  async function addresses(){
    resetMaps();
    const data=await api('customer/addresses.php'),container=$('[data-addresses]');
    container.innerHTML=`<div class="row g-4">${data.items.map(a=>`<div class="col-md-6"><section class="card card-body"><h2>${h(a.label||t('Địa chỉ','Address'))}</h2><p>${h(a.address_detail)}</p><p class="c-panel-sub">${h([a.ward,a.district,a.province].filter(Boolean).join(', '))}</p>${a.is_default?`<span class="c-chip">${h(t('Mặc định','Default'))}</span>`:''}<div class="c-actions mt-3"><button class="btn btn-outline-dark" data-edit="${h(a.address_id)}">${h(t('Sửa','Edit'))}</button><button class="btn btn-outline-dark" data-archive="${h(a.address_id)}">${h(t('Xóa','Delete'))}</button></div></section></div>`).join('')}</div><section class="card card-body mt-4" data-address-editor hidden></section>`;
    if(!data.items.length)container.insertAdjacentHTML('afterbegin',empty(t('Chưa có địa chỉ đã lưu','No saved addresses yet')));
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
    document.querySelectorAll('[data-set-lang]').forEach(el=>el.classList.toggle('active',el.dataset.setLang===(vi?'vi':'en')));
    document.querySelectorAll('[data-set-currency]').forEach(el=>el.classList.toggle('active',el.dataset.setCurrency===(profile.app_language==='vi'?'VND':'USD')));
    document.querySelectorAll('[data-account-name]').forEach(el=>el.textContent=profile.full_name);
    document.querySelectorAll('[data-account-initials]').forEach(el=>el.textContent=profile.full_name.trim().split(/\s+/).slice(-2).map(n=>n[0]).join('').toUpperCase());
    document.querySelectorAll('[data-locale-current]').forEach(el=>el.textContent=(vi?'VI':'EN')+' · '+(profile.app_language==='vi'?'VND':'USD'));
    document.querySelectorAll('[data-set-currency]').forEach(el=>{el.disabled=true;el.title=t('Tiền tệ theo hồ sơ tài khoản','Currency follows the account profile');});
    document.querySelectorAll('[data-unread]').forEach(el=>el.hidden=true);
  }
  async function run(fn){root.setAttribute('aria-busy','true');try{await fn();}catch(err){report(err);}finally{root.setAttribute('aria-busy','false');root.dataset.loaded='true';}}
  $$('form').forEach(form=>form.addEventListener('submit',e=>e.preventDefault()));
  document.querySelectorAll('[data-set-lang]').forEach(button=>button.addEventListener('click',()=>{try{localStorage.setItem('somi-language',button.dataset.setLang);}catch{}location.reload();}));
  if(!window.bootstrap?.Dropdown)document.querySelectorAll('[data-bs-toggle=dropdown]').forEach(button=>button.addEventListener('click',()=>{const menu=button.parentElement.querySelector('.dropdown-menu');if(menu){const open=menu.classList.toggle('show');button.setAttribute('aria-expanded',String(open));}}));
  document.querySelectorAll('[data-copy-vi][data-copy-en]').forEach(el=>el.textContent=vi?el.dataset.copyVi:el.dataset.copyEn);
  const flag=document.querySelector('[data-locale-flag]'),selectedFlag=document.querySelector(`[data-set-lang="${vi?'vi':'en'}"] .language-flag-svg`);if(flag&&selectedFlag)flag.replaceChildren(selectedFlag.cloneNode(true));
  run(async()=>{profile=(await api('account/profile.php')).profile;header();if(view==='dashboard')await dashboard();else if(view==='orders')await orders();else if(view==='create')await create();else if(view==='account')await account();else if(view==='addresses')await addresses();else await detail();});
})();
