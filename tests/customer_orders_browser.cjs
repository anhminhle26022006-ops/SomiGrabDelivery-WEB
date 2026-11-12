const fs=require('fs'),path=require('path'),http=require('http'),cp=require('child_process'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),runtime=path.join(__dirname,'.runtime','customer-orders');fs.mkdirSync(runtime,{recursive:true});
const browserPath=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
assert.ok(browserPath,'Chrome or Edge required');
// Render real Customer fragments/scripts; all API responses and mutations are fixtures.
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,'http://127.0.0.1'),script=u.pathname.slice(1);
 if(['maps.js','places.js','create-route.js','server.js'].includes(script)){res.setHeader('Content-Type','application/javascript; charset=utf-8');return res.end(fs.readFileSync(path.join(root,'public/assets/js/customer',script)));}
 if(script==='bootstrap.css'||script==='customer.css'){res.setHeader('Content-Type','text/css');return res.end(fs.readFileSync(path.join(root,'public/assets',script==='bootstrap.css'?'vendor/bootstrap/bootstrap.min.css':'css/modules/customer.css')));}
 if(u.pathname!=='/'){res.writeHead(404);return res.end();}
 const view=['create','orders','order','tracking'].includes(u.searchParams.get('view'))?u.searchParams.get('view'):'orders';
 const fragment=fs.readFileSync(path.join(root,'app/Views/customer/pages',view+'.php'),'utf8').replace(/<\?php.*?\?>/gs,'');
 const lang=u.searchParams.get('lang')==='en'?'en':'vi';
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end(`<!doctype html><html lang="${lang}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/customer.css"></head><body data-page="customer" data-c-view="${view}"><div class="c-app"><div><span data-locale-current></span><button data-set-currency="VND">VND</button><button data-set-currency="USD">USD</button></div><main class="c-main" data-customer-server data-geoapify-key="${u.searchParams.has('noMap')?'':'fixture-browser-key'}">${fragment}</main></div>${u.searchParams.has('embedded')?'<script type="application/json" id="customer-profile-data">{"profile":{"user_id":"1","full_name":"Embedded Customer","phone":"0900000001","app_language":"vi"}}</script>':''}<script defer src="/maps.js"></script><script defer src="/places.js"></script><script defer src="/create-route.js"></script><script defer src="/server.js"></script></body></html>`);
});
let browser,ws,checks=0;const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const check=(ok,label)=>{assert.ok(ok,label);checks++;};
(async()=>{
 await new Promise(r=>server.listen(3063,'127.0.0.1',r));
 browser=cp.spawn(browserPath,['--headless=new','--remote-debugging-port=9363','--user-data-dir='+path.join(runtime,'profile'),'--no-first-run','--no-default-browser-check','about:blank'],{stdio:'ignore',windowsHide:true});
 let targets;for(let i=0;i<60&&!targets;i++){try{targets=await(await fetch('http://127.0.0.1:9363/json')).json();}catch{await sleep(100);}}assert.ok(targets,'CDP available');
 ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let sequence=0;const pending=new Map(),errors=[];
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence,timer=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(50);}throw Error('Waiting for '+expression);};
 const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
 await send('Page.enable');await send('Runtime.enable');
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`(${function(){
  if(new URLSearchParams(location.search).has('resetCurrency'))localStorage.removeItem('somi-currency');
  window.fixtureRequests=[];window.fixtureReturnHanded=false;window.fixtureLocationMode='ready';window.fixtureCancelConflict=false;window.fixtureQuoteError=null;
  window.fixtureGpsMode=new URL(location.href).searchParams.get('gps')||'ready';window.fixtureMarkers=[];window.fixtureMapEvents={};window.fixturePendingReverse=[];window.fixtureHoldReverse=false;window.fixturePendingRoute=[];window.fixtureHoldRoute=false;
  window.fixtureGpsOptions=[];
  window.fixtureHoldSearch=false;window.fixtureSearchPending=[];window.fixtureEmptyAutocomplete=false;
  Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(resolve,reject,options){fixtureGpsOptions.push(options);setTimeout(()=>fixtureGpsMode==='ready'||fixtureGpsMode==='retry'&&fixtureGpsOptions.length>1?resolve({coords:{latitude:10.8,longitude:106.68,accuracy:10}}):reject({code:fixtureGpsMode==='denied'?1:fixtureGpsMode==='timeout'?3:2}),0);}}});
  window.L={
   map(){return{setView(){return this;},fitBounds(){},invalidateSize(){},remove(){fixtureMarkers.forEach(m=>m.removed=true);},on(name,fn){fixtureMapEvents[name]=fn;}};},
   tileLayer(){return{addTo(){}};},
   circleMarker(position,options){const layer={position,options,removed:false,addTo(){fixtureMarkers.push(this);return this;},setLatLng(p){this.position=p;},bindTooltip(label){this.label=label.textContent;},bringToFront(){this.raised=true;},remove(){this.removed=true;}};return layer;},
   geoJSON(feature){return{addTo(){window.fixtureRoute=feature;return this;},getBounds(){return[];},remove(){window.fixtureRoute=null;}};}
  };
  const snapshot={currency_code:'VND',context:{distance_km:'4.500',weather_condition:'LIGHT_RAIN'},parcel:{weight_kg:'3.500'},cancellation_fees:{PENDING_PAYMENT:'0'},components:{base_fee_minor:'20000',distance_component_minor:'6000',weight_component_minor:'1250',time_multiplier:'1.2000',weather_multiplier:'1.1000',total_charge_minor:'35970'}};
  const history=[{old_status:null,new_status:'PENDING_PAYMENT',created_at:'2026-11-09T01:00:00Z'}];
  window.fixtureOrder={order_id:'7',order_code:'SOMI-FIXTURE-007',status:'PENDING_PAYMENT',currency_code:'VND',total_charge_minor:'35970',pickup_address:'Frozen pickup',pickup_latitude:'10.8',pickup_longitude:'106.68',delivery_address:'Frozen delivery',delivery_latitude:'10.83',delivery_longitude:'106.70',sender_name:'Sender',sender_phone:'0900000001',receiver_name:'Receiver',receiver_phone:'0900000002',distance_km:'4.500',estimated_weight_kg:'3.500',parcel_description:'Fixture parcel',created_at:'2026-11-09T01:00:00Z',pricing_snapshot:snapshot,status_history:history};
  window.fetch=async(url,options={})=>{
   const u=new URL(url,location.href),body=options.body?JSON.parse(options.body):null,method=options.method||'GET';fixtureRequests.push({path:u.pathname,search:u.search,method,body,key:options.headers?.['Idempotency-Key']});let data={};
   if(u.pathname.endsWith('/account/profile.php'))data={profile:{full_name:'Fixture Customer',phone:'0900000001',app_language:new URLSearchParams(location.search).get('accountLang')==='en'?'en':'vi'}};
   else if(u.pathname.endsWith('/shipper-location.php')){if(fixtureLocationMode==='error')return new Response(JSON.stringify({code:'AUTHENTICATION_REQUIRED'}),{status:401});data={order_id:'7',status:fixtureOrder.status,location:fixtureLocationMode==='empty'?null:{latitude:'10.81',longitude:'106.69',recorded_at:new Date().toISOString()}};}
   else if(u.pathname.endsWith('/confirm-return.php')){if(!fixtureReturnHanded)return new Response(JSON.stringify({code:'RETURN_NOT_HANDED_OVER'}),{status:409});fixtureOrder.status='RETURNED';data={order_id:'7',status:'RETURNED'};}
   else if(u.pathname.endsWith('/addresses.php'))data={items:[{address_id:'1',label:'Home',address_detail:'Saved fixture address',latitude:'10.77',longitude:'106.70',is_default:true}]};
   else if(u.pathname.endsWith('/parcel-categories.php'))data={items:[{category_id:'1',restriction_type:'ALLOWED',name_vi:'Fixture category'}]};
   else if(u.pathname.endsWith('/places.php')){
    const address={country_code:'VN',province:'TP.HCM',address_detail:'45 Phan Dang Luu - current location',latitude:String(body.latitude??10.83),longitude:String(body.longitude??106.70)};
    if(body.action==='reverse'&&fixtureHoldReverse)await new Promise(resolve=>fixturePendingReverse.push(resolve));
    if(body.action==='reverse')data={address};
    else if(['autocomplete','geocode'].includes(body.action)){if(fixtureHoldSearch)await new Promise(resolve=>fixtureSearchPending.push(resolve));data={items:fixtureEmptyAutocomplete&&body.action==='autocomplete'?[]:[{text:body.input,address:{...address,address_detail:body.input,latitude:'10.83',longitude:'106.70'}}]};}
    else {if(fixtureHoldRoute)await new Promise(resolve=>fixturePendingRoute.push(resolve));data={route:{type:'Feature',geometry:{type:'LineString',coordinates:[[106.68,10.8],[106.70,10.83]]},properties:{distance_m:4500,duration_s:780}}};}
   }
   else if(u.pathname.endsWith('/quotes.php')){
    if(fixtureQuoteError)return new Response(JSON.stringify({code:fixtureQuoteError}),{status:503});
    data={quote:{quote_id:'0123456789abcdef0123456789abcdef',expires_at:'2099-01-01T00:00:00Z',snapshot:{...snapshot,currency_code:body.currency_code||'VND',components:{...snapshot.components,total_charge_minor:body.currency_code==='USD'?'171':'35970'}}}};
   }else if(u.pathname.endsWith('/cancel-order.php')){
    if(fixtureCancelConflict)return new Response(JSON.stringify({code:'ORDER_NOT_CANCELLABLE'}),{status:409});
    fixtureOrder.status='CANCELLED';history.push({old_status:'PENDING_PAYMENT',new_status:'CANCELLED',note:body.reason,created_at:'2026-11-09T01:02:00Z'});data={order:fixtureOrder};
   }else if(u.pathname.endsWith('/orders.php')){
    // Simulate an uncertain create response to verify retry uses the same key.
    if(method==='POST')return new Response(JSON.stringify({code:'SERVICE_UNAVAILABLE'}),{status:503});
    data=u.searchParams.has('id')?{order:fixtureOrder}:{items:u.searchParams.has('before_id')?[fixtureOrder]:[{...fixtureOrder,order_id:'8',order_code:'SOMI-FIXTURE-008',status:'DELIVERED',receiver_name:'Other recipient'}],next_before_id:u.searchParams.has('before_id')?null:'8'};
   }else if(u.pathname.endsWith('/payments.php'))data={payment:null};
   else if(u.pathname.endsWith('/refunds.php'))data={items:[]};
   else throw Error('Unexpected API fixture request');
   return new Response(JSON.stringify(data),{status:200});
  };
  window.form=()=>document.querySelector('[data-create-form]');
  window.field=n=>form().elements.namedItem(n);
 }.toString()})();`});
 async function load(view){await send('Page.navigate',{url:'http://127.0.0.1:3063/?view='+view});await waitFor(`document.querySelector('[data-customer-server]')?.dataset.loaded==='true'&&document.body.dataset.cView===${JSON.stringify(view.split('&')[0])}`);}
 await load('orders&accountLang=en&resetCurrency=1');
 check(await evaluate(`document.querySelector('[data-locale-current]').textContent==='VI · USD'&&!document.querySelector('[data-set-currency="VND"]').disabled`),'currency defaults to stored profile independently of display language and selector is enabled');
 await click('[data-set-currency="VND"]');
 check(await evaluate(`document.querySelector('[data-locale-current]').textContent==='VI · VND'&&localStorage.getItem('somi-currency')==='VND'`),'English account can select and persist VND without changing language');
 await load('orders');
 check(await evaluate(`document.querySelectorAll('.c-order-row').length===1&&!fixtureRequests.some(r=>r.search.includes('before_id='))`),'history renders first page without waiting for every cursor');
 await click('[data-more-orders]');await waitFor(`document.querySelectorAll('.c-order-row').length===2`);
 check(await evaluate(`!document.querySelector('[data-more-orders]')`),'history loads next page on request');
 check(await evaluate(`fixtureRequests.some(r=>r.search.includes('before_id=8'))`),'history requests next cursor');
 await click('[data-tab="done"]');check(await evaluate(`document.querySelectorAll('.c-order-row').length===1&&document.querySelector('.c-order-row').textContent.includes('008')`),'filter delivered orders');
 await click('[data-tab="all"]');await evaluate(`document.querySelector('[data-search]').value='Receiver';document.querySelector('[data-search]').dispatchEvent(new Event('input'))`);
 check(await evaluate(`document.querySelectorAll('.c-order-row').length===1&&document.querySelector('.c-order-row').textContent.includes('007')`),'search recipient');
 await load('orders&embedded=1');
 check(await evaluate(`!fixtureRequests.some(r=>r.path.endsWith('/account/profile.php'))&&document.querySelectorAll('.c-order-row').length===1`),'server-rendered profile avoids a redundant authenticated profile API request');
 await load('order&id=7');
 check(await evaluate(`document.querySelector('[data-order]').textContent.includes('Frozen pickup')&&document.querySelector('[data-order]').textContent.includes('Frozen delivery')`),'detail shows frozen addresses');
 check(await evaluate(`document.querySelector('.c-fees-total').textContent.includes('35.970')`),'detail displays accepted total');
 await click('[data-set-currency="USD"]');
 check(await evaluate(`document.querySelector('.c-fees-total').textContent.includes('35.970')&&fixtureOrder.currency_code==='VND'`),'changing selected currency does not reprice an existing order');
 await load('order&id=7');
 check(await evaluate(`document.querySelector('[data-locale-current]').textContent==='VI · USD'&&document.querySelector('.c-fees-total').textContent.includes('35.970')`),'currency selection survives navigation while order retains original money');
 await click('[data-set-currency="VND"]');
 check(await evaluate(`document.querySelectorAll('.c-tl-item').length===1&&!!document.querySelector('[data-cancel]')`),'initial timeline and cancellation available');
 await evaluate(`fixtureCancelConflict=true;document.querySelector('[name=reason]').value='Changed plans';document.querySelector('[data-cancel]').requestSubmit()`);
 await waitFor(`document.querySelector('[data-cancel-error] [role=alert]')`);
 check(await evaluate(`document.querySelector('[data-cancel-error]').textContent.includes('không thể hủy')&&!document.querySelector('[data-cancel] button').disabled`),'assignment race explains cancellation rejection and allows refresh');
 await evaluate(`fixtureCancelConflict=false;document.querySelector('[data-cancel]').requestSubmit()`);
 await waitFor(`!document.querySelector('[data-cancel]')&&document.querySelectorAll('.c-tl-item').length===2`);
 check(await evaluate(`document.querySelector('.c-timeline').textContent.includes('Changed plans')&&document.querySelector('.c-fees-total').textContent.includes('35.970')`),'cancel adds reason to timeline and retains price');
 await evaluate(`fixtureOrder.status='SHIPPER_ASSIGNED';document.querySelector('[data-refresh]').click()`);
 await waitFor(`document.querySelector('[data-order]').textContent.includes('Đã phân công')`);
 check(await evaluate(`!document.querySelector('[data-cancel]')`),'assigned order has no customer cancellation action');
 await waitFor(`fixtureMarkers.some(m=>m.label==='Shipper'&&!m.removed)`);
 check(await evaluate(`fixtureRequests.some(r=>r.path.endsWith('/shipper-location.php')&&r.search==='?order_id=7')&&document.querySelector('[data-location-status]').textContent.includes('cập nhật')`),'active order requests authorized GPS and renders marker/timestamp');
 await evaluate(`fixtureOrder.status='RETURNING';document.querySelector('[data-refresh]').click()`);await waitFor(`!!document.querySelector('[data-confirm-return]')`);
 await click('[data-confirm-return] button');
 check(await evaluate(`!fixtureRequests.some(r=>r.path.endsWith('/confirm-return.php'))`),'return confirmation requires explicit received checkbox');
 await evaluate(`document.querySelector('[name=received]').checked=true;document.querySelector('[data-confirm-return]').requestSubmit()`);
 await waitFor(`!!document.querySelector('[data-return-message] .alert-danger')`);
 check(await evaluate(`document.querySelector('[data-return-message]').textContent.includes('chưa xác nhận')&&!document.querySelector('[data-confirm-return] button').disabled`),'handover conflict keeps confirmation usable');
 await evaluate(`fixtureReturnHanded=true;document.querySelector('[data-confirm-return]').requestSubmit()`);await waitFor(`!document.querySelector('[data-confirm-return]')`);
 check(await evaluate(`fixtureRequests.filter(r=>r.path.endsWith('/confirm-return.php')).every(r=>r.method==='POST'&&r.body.order_id==='7')&&!document.querySelector('[data-route-map]')&&document.querySelector('[data-order]').textContent.includes('Đã hoàn hàng')`),'successful return uses API and removes live tracking');
 await evaluate(`fixtureOrder.status='SHIPPER_ASSIGNED';fixtureLocationMode='empty';document.querySelector('[data-refresh]').click()`);await waitFor(`document.querySelector('[data-location-status]')?.textContent.includes('chưa chia sẻ')`);
 check(await evaluate(`!fixtureMarkers.some(m=>m.label==='Shipper'&&!m.removed)`),'missing GPS clears shipper marker');
 await evaluate(`fixtureLocationMode='error';document.querySelector('[data-refresh]').click()`);await waitFor(`document.querySelector('[data-location-status]')?.textContent.includes('hết hạn')`);
 await load('tracking&id=7');await evaluate(`fixtureOrder.status='IN_DELIVERY';document.querySelector('[data-refresh]').click()`);await waitFor(`!!window.fixtureRoute&&!!document.querySelector('[data-location-status]')`);
 check(await evaluate(`fixtureRequests.some(r=>r.body?.action==='route')&&fixtureMarkers.some(m=>m.label==='Shipper'&&!m.removed)`),'tracking retains route and live shipper GPS together');
 await load('order&id=7&lang=en');await evaluate(`fixtureOrder.status='RETURNING';document.querySelector('[data-refresh]').click()`);await waitFor(`!!document.querySelector('[data-confirm-return]')`);
 check(await evaluate(`document.querySelector('[data-confirm-return] button').textContent==='Confirm return received'`),'return confirmation supports English');
 await load('create');
 await waitFor(`document.querySelector('[data-current-location]').textContent.includes('45 Phan')`);
 check(await evaluate(`document.querySelectorAll('[data-create-map]').length===1&&document.querySelectorAll('.somi-leaflet-map').length===1`),'create uses one shared map');
 check(await evaluate(`field('pickup-detail').value==='Saved fixture address'`),'background GPS does not overwrite saved pickup');
 await click('[data-use-location]');
 check(await evaluate(`field('pickup-detail').value.includes('current location')&&field('pickup-latitude').value==='10.8000000'&&document.querySelector('#pickup-saved').value===''`),'GPS fills reverse-geocoded address and clears saved reference');
 check(await evaluate(`fixtureMarkers.some(m=>!m.removed&&m.label==='Bạn đang ở đây'&&m.raised)`),'current location marker remains visible above overlapping pickup');
 await evaluate(`{const s=document.querySelector('#delivery-saved');s.value='1';s.dispatchEvent(new Event('change'));}`);
 await waitFor(`!document.querySelector('[data-route-summary]').hidden`);
 check(await evaluate(`!!fixtureRoute&&document.querySelector('[data-route-distance]').textContent==='4,5 km'&&document.querySelector('[data-route-duration]').textContent.includes('13 phút')`),'selecting both endpoints draws route and immediately shows road distance and duration');
 await evaluate(`field('pickup-detail').value='';field('pickup-detail').dispatchEvent(new Event('input',{bubbles:true}))`);
 check(await evaluate(`!document.querySelector('[data-map-use-location]').hidden&&document.querySelector('[data-route-summary]').hidden&&document.querySelector('[data-route-status]').textContent.includes('Chọn điểm lấy A')&&!fixtureRoute`),'GPS plus delivery is not silently treated as a pickup; missing pickup has a clear action');
 await click('[data-map-use-location]');await waitFor(`!document.querySelector('[data-route-summary]').hidden`);
 check(await evaluate(`field('pickup-detail').value.includes('current location')&&!!fixtureRoute&&document.querySelector('[data-map-use-location]').hidden`),'map action explicitly selects GPS as pickup and connects A to B');
 await evaluate(`field('pickup-detail').value='New pickup address';field('pickup-detail').dispatchEvent(new Event('input',{bubbles:true}))`);
 check(await evaluate(`field('pickup-latitude').value===''&&fixtureMarkers.some(m=>!m.removed&&m.label==='Bạn đang ở đây')&&!fixtureMarkers.some(m=>!m.removed&&m.label==='A · Điểm lấy')`),'typing clears stale pickup but preserves current location');
 await waitFor(`document.querySelector('#pickup-detail').getAttribute('aria-expanded')==='true'`);
 await evaluate(`field('pickup-detail').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));field('pickup-detail').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))`);
 await waitFor(`field('pickup-latitude').value==='10.83'`);
 check(await evaluate(`fixtureMarkers.some(m=>!m.removed&&m.label==='Bạn đang ở đây')&&fixtureMarkers.some(m=>!m.removed&&m.label==='A · Điểm lấy'&&m.position[0]===10.83)`),'manual search shows GPS and new pickup simultaneously');
 await evaluate(`const select=document.querySelector('[data-side=delivery] [data-saved]');select.value='1';select.dispatchEvent(new Event('change'));field('delivery-name').value='Receiver';field('delivery-phone').value='0900000002';field('pkg-weight').value='3.5';field('pkg-quantity').value='1'`);
 const submit=()=>evaluate(`form().requestSubmit()`);
 await submit();await waitFor(`document.querySelector('[data-pickup-confirm]').open`);
 check(await evaluate(`!document.querySelector('[data-step="1"]').hidden&&document.querySelector('[data-pickup-confirm-message]').textContent.includes('hơn 1 km')`),'remote pickup requires confirmation before proceeding');
 await click('[data-pickup-cancel]');check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open&&!document.querySelector('[data-step="1"]').hidden`),'cancel remote pickup stays on route step');
 await submit();await waitFor(`document.querySelector('[data-pickup-confirm]').open`);await click('[data-pickup-accept]');await waitFor(`!document.querySelector('[data-step="2"]').hidden`);
 await submit();await submit();
 await waitFor(`!document.querySelector('[data-step="4"]').hidden`);
 check(await evaluate(`document.querySelector('[data-quote]').textContent.includes('35.970')&&document.querySelector('[data-quote]').textContent.includes('Mưa nhẹ')`),'quote shows accepted price and weather');
 check(await evaluate(`fixtureRequests.filter(r=>r.path.endsWith('/quotes.php')).at(-1).body.currency_code==='VND'`),'quote request includes selected VND currency');
 await click('[data-set-currency="USD"]');
 check(await evaluate(`!document.querySelector('[data-step="3"]').hidden&&!document.querySelector('[data-quote]').textContent.includes('35.970')&&field('delivery-name').value==='Receiver'`),'currency change invalidates quote and preserves form fields');
 await submit();await waitFor(`!document.querySelector('[data-step="4"]').hidden`);
 check(await evaluate(`fixtureRequests.filter(r=>r.path.endsWith('/quotes.php')).at(-1).body.currency_code==='USD'&&document.querySelector('[data-quote]').textContent.includes('1,71')`),'USD choice requests its own quote and renders minor units correctly');
 await click('[data-set-currency="VND"]');await submit();await waitFor(`!document.querySelector('[data-step="4"]').hidden`);
 check(await evaluate(`document.querySelector('[data-quote]').textContent.includes('35.970')`),'switching back obtains a new VND quote before order creation');

 await evaluate(`field('agree').checked=true`);await submit();
 await waitFor(`fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST').length===1&&!document.querySelector('[data-next]').disabled`);
 await submit();await waitFor(`fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST').length===2`);
 check(await evaluate(`(()=>{const writes=fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST');return writes[0].key===writes[1].key&&!!writes[0].key&&Object.keys(writes[0].body).join(',')==='quote_id';})()`),'create retries same quote/key without client price or address');
 await evaluate(`field('pkg-weight').value='4';field('pkg-weight').dispatchEvent(new Event('input',{bubbles:true}))`);
 check(await evaluate(`document.querySelector('[data-step="4"]').hidden&&!document.querySelector('[data-quote]').textContent.includes('35.970')`),'parcel edit invalidates accepted quote');
 for(const [code,message] of [['WEATHER_SERVICE_PAUSED','thời tiết nguy hiểm'],['ROUTE_NOT_FOUND','tuyến xe máy'],['PRICING_CONTEXT_UNAVAILABLE','chưa sẵn sàng']]){
  await evaluate(`fixtureQuoteError=${JSON.stringify(code)}`);await submit();
  await waitFor(`document.querySelector('[data-create-error]').textContent.includes(${JSON.stringify(message)})`);
  check(await evaluate(`document.querySelector('[data-step="4"]').hidden&&!document.querySelector('[data-next]').disabled`),'quote error blocks confirmation: '+code);
 }
 await load('create');await waitFor(`document.querySelector('[data-current-location]').textContent.includes('45 Phan')`);
 await evaluate(`const select=document.querySelector('#delivery-saved');select.value='1';select.dispatchEvent(new Event('change'));field('delivery-name').value='Receiver';field('delivery-phone').value='0900000002'`);
 await click('[data-use-location]');await submit();await waitFor(`!document.querySelector('[data-step="2"]').hidden`);
 check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open`),'pickup at GPS location proceeds without a warning');
 await click('[data-prev]');
 // Coordinate edits also invalidate prior confirmation, even when text stays the same.
 await evaluate(`field('pickup-latitude').value='10.802';field('pickup-latitude').dispatchEvent(new Event('input',{bubbles:true}))`);
 await submit();await waitFor(`!document.querySelector('[data-step="2"]').hidden`);
 check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open`),'pickup less than 1 km from GPS needs no confirmation');
 await click('[data-prev]');
 // Adjacent seven-decimal coordinates on either side of the 1 km boundary.
 await evaluate(`field('pickup-latitude').value='10.8089932';field('pickup-latitude').dispatchEvent(new Event('input',{bubbles:true}))`);
 await submit();await waitFor(`!document.querySelector('[data-step="2"]').hidden`);
 check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open`),'just below 1 km proceeds without confirmation');await click('[data-prev]');
 await evaluate(`field('pickup-latitude').value='10.8089933';field('pickup-latitude').dispatchEvent(new Event('input',{bubbles:true}))`);
 await submit();await waitFor(`document.querySelector('[data-pickup-confirm]').open`);
 check(await evaluate(`!document.querySelector('[data-step="1"]').hidden`),'just above 1 km requires confirmation');await click('[data-pickup-cancel]');
 await evaluate(`field('pickup-latitude').value='10.83';field('pickup-latitude').dispatchEvent(new Event('input',{bubbles:true}))`);
 await submit();await waitFor(`document.querySelector('[data-pickup-confirm]').open`);await click('[data-pickup-accept]');await waitFor(`!document.querySelector('[data-step="2"]').hidden`);await click('[data-prev]');
 await evaluate(`field('pickup-latitude').value='10.84';field('pickup-latitude').dispatchEvent(new Event('input',{bubbles:true}))`);
 await submit();await waitFor(`document.querySelector('[data-pickup-confirm]').open`);
 check(await evaluate(`!document.querySelector('[data-step="1"]').hidden`),'moving pickup requires a new distance confirmation');
 await evaluate(`document.querySelector('[data-pickup-confirm]').dispatchEvent(new Event('cancel',{cancelable:true}))`);
 check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open`),'Escape cancellation returns to the form');
 await evaluate(`fixtureHoldReverse=true;void fixtureMapEvents.click({latlng:{lat:10.82,lng:106.72}})`);
 await waitFor(`fixturePendingReverse.length===1`);
 await evaluate(`{const s=document.querySelector('#pickup-saved');s.value='1';s.dispatchEvent(new Event('change'));fixturePendingReverse.shift()();}`);
 await sleep(120);
 check(await evaluate(`field('pickup-detail').value==='Saved fixture address'&&field('pickup-latitude').value==='10.77'`),'late reverse-geocoding cannot overwrite a newer address choice');
 await evaluate(`{fixtureHoldRoute=true;const s=document.querySelector('#pickup-saved');s.value='1';s.dispatchEvent(new Event('change'));}`);
 await waitFor(`fixturePendingRoute.length>=1`);
 await evaluate(`field('pickup-detail').value='Unselected replacement';field('pickup-detail').dispatchEvent(new Event('input',{bubbles:true}));fixturePendingRoute.splice(0).forEach(resolve=>resolve())`);await sleep(120);
 check(await evaluate(`!fixtureRoute&&field('pickup-latitude').value===''&&document.querySelector('[data-route-summary]').hidden`),'late route cannot restore an invalidated address, stale geometry or stale distance');
 for(const mode of ['denied','unavailable','timeout']){
  await load('create&gps='+mode);await waitFor(`!document.querySelector('[data-use-location]').disabled`);
  check(await evaluate(`document.querySelector('[data-location-status]').dataset.locationError===${JSON.stringify({denied:'PERMISSION_DENIED',unavailable:'POSITION_UNAVAILABLE',timeout:'TIMEOUT'}[mode])}`),'GPS '+mode+' gives a specific error');
  check(await evaluate(`fixtureGpsOptions.length===${mode==='denied'?1:2}`),'GPS '+mode+' retries only acquisition failures');
  await evaluate(`const s=document.querySelector('#delivery-saved');s.value='1';s.dispatchEvent(new Event('change'));field('delivery-name').value='Receiver';field('delivery-phone').value='0900000002'`);await submit();await waitFor(`!document.querySelector('[data-step="2"]').hidden`);
  check(await evaluate(`!document.querySelector('[data-pickup-confirm]').open`),'manual entry works when GPS '+mode);
 }
 await load('create&gps=retry');await waitFor(`document.querySelector('[data-current-location]').textContent.includes('45 Phan')`);
 check(await evaluate(`fixtureGpsOptions.length===2&&fixtureGpsOptions[0].enableHighAccuracy===false&&fixtureGpsOptions[1].enableHighAccuracy===true`),'one automatic retry recovers temporary location acquisition failure');
 check(await evaluate(`document.querySelector('[data-location-status]').textContent.includes('10 m')`),'location accuracy is visible to the user');
 await load('create&noMap=1');await waitFor(`!document.querySelector('[data-use-location]').disabled`);await click('[data-use-location]');
 check(await evaluate(`field('pickup-detail').value.includes('current location')&&document.querySelector('[data-create-map]').textContent.includes('chưa sẵn sàng')`),'missing map key preserves geolocation and address entry');
 await load('create&lang=en');await waitFor(`!document.querySelector('[data-use-location]').disabled`);
 check(await evaluate(`document.querySelector('[data-location-title]').textContent==='Your current location'&&document.querySelector('[data-map-side=pickup]').textContent==='A · Pickup'&&document.querySelector('#pickup-confirm-title').textContent.includes('Pickup')`),'new controls and confirmation support English');
 await load('create');await waitFor(`!document.querySelector('[data-use-location]').disabled`);
 check(await evaluate(`getComputedStyle(document.querySelector('[data-quote]')).display==='none'&&getComputedStyle(document.querySelector('[data-create-map-panel]')).display!=='none'`),'route step shows map instead of an empty quote');
 for(const width of [1440,768,375,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await sleep(50);
  check(await evaluate(`document.documentElement.scrollWidth<=innerWidth+1&&document.querySelector('[data-create-map]').getBoundingClientRect().height>=280`),'create layout fits '+width+' pixels');
 }
 const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(runtime,'create-mobile.png'),Buffer.from(shot.data,'base64'));
 await evaluate(`window.searchCount=()=>fixtureRequests.filter(r=>r.body?.action==='autocomplete').length;window.startSearchCount=searchCount();fixtureHoldSearch=true;field('delivery-detail').value='Old request';field('delivery-detail').dispatchEvent(new Event('input',{bubbles:true}))`);
 await waitFor(`fixtureSearchPending.length===1`);
 await evaluate(`field('delivery-detail').value='Intermediate query';field('delivery-detail').dispatchEvent(new Event('input',{bubbles:true}))`);await sleep(450);
 await evaluate(`field('delivery-detail').value='157/5 Nguyễn Gia Trí';field('delivery-detail').dispatchEvent(new Event('input',{bubbles:true}))`);await sleep(450);
 check(await evaluate(`searchCount()===startSearchCount+1&&field('delivery-detail').getAttribute('aria-busy')==='true'`),'slow autocomplete never accumulates requests while typing');
 await evaluate(`fixtureSearchPending.shift()()`);await waitFor(`fixtureSearchPending.length===1&&searchCount()===startSearchCount+2`);
 check(await evaluate(`fixtureRequests.filter(r=>r.body?.action==='autocomplete').at(-1).body.input==='157/5 Nguyễn Gia Trí'`),'after slow response only the latest typed address is searched');
 await evaluate(`fixtureHoldSearch=false;fixtureSearchPending.shift()()`);await waitFor(`field('delivery-detail').getAttribute('aria-expanded')==='true'`);
 await evaluate(`field('delivery-detail').dispatchEvent(new Event('input',{bubbles:true}))`);await sleep(450);
 check(await evaluate(`searchCount()===startSearchCount+2&&field('delivery-detail').getAttribute('aria-expanded')==='true'`),'repeated address displays cached suggestions without another request');
 await evaluate(`fixtureEmptyAutocomplete=true;field('delivery-detail').value='Full address fallback';field('delivery-detail').dispatchEvent(new Event('input',{bubbles:true}))`);
 await waitFor(`fixtureRequests.some(r=>r.body?.action==='geocode')&&field('delivery-detail').getAttribute('aria-expanded')==='true'`);
 check(await evaluate(`!document.querySelector('[data-step="1"]').hidden&&document.querySelector('#delivery-detail').parentElement.querySelector('[role=option]').textContent==='Full address fallback'`),'empty autocomplete automatically shows geocoding suggestions without Enter');
 await evaluate(`field('delivery-detail').dispatchEvent(new Event('blur'));field('delivery-detail').dispatchEvent(new Event('focus'))`);
 check(await evaluate(`field('delivery-detail').getAttribute('aria-expanded')==='true'`),'returning to a filled address automatically reopens suggestions');
 check(errors.length===0,'no browser exceptions: '+errors.join('; '));
 const result={passed:checks,live_database_writes:0,provider_requests:0};fs.writeFileSync(path.join(runtime,'results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();browser?.kill();server.close();});
