const {chromium}=require('playwright'),{spawn}=require('node:child_process');
const fs=require('node:fs'),assert=require('node:assert/strict');
const base='http://127.0.0.1:3094';
const server=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3094','tests/bootstrap_render_fixture.php'],{stdio:'ignore',windowsHide:true,env:{...process.env,GEOAPIFY_BROWSER_API_KEY:'fixture-key'}});
(async()=>{let browser;try{
 for(let i=0;i<50;i++){try{await new Promise((resolve,reject)=>require('node:http').get(base+'/__shipper-test',r=>{r.resume();r.on('end',resolve);}).on('error',reject));break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true,executablePath:['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync)});
 const context=await browser.newContext({viewport:{width:1440,height:1000},permissions:['geolocation'],geolocation:{latitude:10.8035,longitude:106.68}});
 const errors=[];context.on('page',p=>p.on('pageerror',e=>errors.push(e.message)));
 await context.addInitScript(()=>{try{localStorage.setItem('somi-language','vi');}catch{}});
 let status='SEARCHING_SHIPPER',location=null,gpsWrites=0,messages=[],failSend=true;const chatWrites=[];
 const order=()=>({order_id:'777',order_code:'SOMI-LIVE-777',status,currency_code:'VND',total_charge_minor:'30000',created_at:new Date().toISOString(),pickup_latitude:10.799,pickup_longitude:106.68,delivery_latitude:10.786,delivery_longitude:106.695,pickup_address:'12 Nguyễn Văn Trỗi, TP.HCM',delivery_address:'241 Võ Văn Tần, TP.HCM',sender_name:'Người gửi',sender_phone:'0900000001',receiver_name:'Người nhận',receiver_phone:'0900000002',category_name_vi:'Tài liệu',estimated_weight_kg:'1',distance_km:'3.2',evidence:[],next_actions:status==='SHIPPER_ASSIGNED'?['PICKUP']:status==='PICKED_UP'?['START_DELIVERY']:['ADD_DELIVERY_PHOTO','FAIL'],status_history:[],pricing_snapshot:{components:{},cancellation_fees:{}},customer_note:'Gọi trước khi giao hàng'});
 const route={type:'Feature',geometry:{type:'LineString',coordinates:[[106.68,10.799],[106.685,10.793],[106.695,10.786]]},properties:{}};
 await context.route('https://maps.geoapify.com/**',r=>r.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP6kAAAAASUVORK5CYII=','base64')}));
 await context.route('**/public/api/**',async r=>{const req=r.request(),path=new URL(req.url()).pathname,body=req.method()==='POST'?req.postDataJSON():null;let result={items:[],totals:[],unread_count:0};
  if(new URL(req.url()).searchParams.get('resource')==='chat'){
   if(body?.body){chatWrites.push(body);if(failSend){failSend=false;return r.fulfill({status:503,json:{code:'SERVICE_UNAVAILABLE'}});}messages.unshift({message_id:'1',sender_id:'1',body:body.body,sent_at:new Date().toISOString(),read_at:null,attachments:[]});result={message:messages[0]};}
   else result=body?{read:true}:{items:messages,next_cursor:null};
  }
  else if(path.endsWith('/account/profile.php'))result={profile:{user_id:'1',full_name:'Khách hàng',app_language:'vi',phone:'0900000001'}};
  else if(path.endsWith('/availability.php'))result={availability:true,can_work:true,blockers:[]};
  else if(path.endsWith('/shipper/profile.php'))result={profile:{full_name:'Người giao hàng',customer_rating_count:0}};
  else if(path.endsWith('/dashboard.php'))result={timezone:'Asia/Ho_Chi_Minh',start:'2026-11-13T00:00:00+07:00',end:'2026-11-14T00:00:00+07:00',completed_deliveries:0,delivery_seconds:0};
  else if(path.endsWith('/offers.php'))result={items:status==='SEARCHING_SHIPPER'?[{...order(),offer_id:'999',seconds_left:60,distance_to_pickup_km:'0.5',estimated_duration_minutes:15,estimated_earning_minor:'24000'}]:[]};
  else if(path.endsWith('/accept-offer.php')){status='SHIPPER_ASSIGNED';result={created:true};}
  else if(path.endsWith('/deliveries.php')){if(body)status=body.action==='PICKUP'?'PICKED_UP':'IN_DELIVERY';result={delivery:status==='SEARCHING_SHIPPER'?null:order()};}
  else if(path.endsWith('/shipper/location.php')){gpsWrites++;location={...body,recorded_at:new Date().toISOString()};result={order_id:'777',recorded_at:location.recorded_at};}
  else if(path.endsWith('/shipper-location.php'))result={order_id:'777',status,location};
  else if(path.endsWith('/orders.php'))result={order:order(),items:[order()]};
  else if(path.endsWith('/payments.php'))result={payment:{status:'PAID',amount_minor:'30000',currency_code:'VND'}};
  else if(path.endsWith('/route.php')||path.endsWith('/places.php'))result={route};
  return r.fulfill({json:result});
 });
 await context.route('**/__customer-live**',async r=>{
  const fragment=fs.readFileSync('app/Views/customer/pages/matching.php','utf8').replace(/<\?php.*?\?>/gs,'');
  await r.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="vi"><head><link rel="stylesheet" href="/public/assets/vendor/leaflet/leaflet.css"><link rel="stylesheet" href="/public/assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/public/assets/css/modules/customer.css"></head><body data-page="customer" data-c-view="matching"><main data-customer-server data-geoapify-key="fixture-key">${fragment}</main><script defer src="/public/assets/vendor/leaflet/leaflet.js"></script><script defer src="/public/assets/js/customer/maps.js"></script><script defer src="/public/assets/js/customer/server.js"></script></body></html>`});
 });
 const customer=await context.newPage();await customer.goto(base+'/__customer-live?id=777');
 await customer.waitForSelector('.leaflet-container');
 const shipper=await context.newPage();await shipper.goto(base+'/__shipper-test?view=dashboard');
 await shipper.locator('[data-offer-accept="999"]').click();
 await shipper.waitForURL(u=>u.searchParams.get('view')==='active');await shipper.waitForSelector('#shipperDeliveryMap.leaflet-container');
 await customer.waitForFunction(()=>document.querySelector('.c-live-heading')?.textContent.includes('Shipper đã nhận đơn'),null,{timeout:12000});
 await customer.waitForFunction(()=>document.querySelector('[data-location-status]')?.textContent.includes('cập nhật lúc'),null,{timeout:15000});
 assert.ok(gpsWrites>0,'GPS sent after assignment');
 await shipper.locator('[data-delivery-action="PICKUP"]').click();
 await customer.waitForFunction(()=>document.querySelector('.c-live-heading')?.textContent.includes('Đã lấy hàng'),null,{timeout:12000});
 await shipper.locator('[data-delivery-action="START_DELIVERY"]').click();
 await customer.waitForFunction(()=>document.querySelector('.c-live-heading')?.textContent.includes('Đang giao'),null,{timeout:12000});
 const mapNode=await shipper.locator('#shipperDeliveryMap').evaluate(n=>{n.dataset.preserved='yes';return true;});
 await shipper.waitForTimeout(5500);assert.equal(await shipper.locator('#shipperDeliveryMap').getAttribute('data-preserved'),'yes','polling preserves map');
 await shipper.locator('[data-open-order-chat]').click();
 await shipper.waitForFunction(()=>document.activeElement?.matches('.support-composer textarea'));
 assert.ok(!new URL(shipper.url()).hash,'opening chat does not change navigation history');
 const input=shipper.locator('.support-composer textarea');
 await input.click();await shipper.keyboard.type('Tôi sắp đến điểm giao.');
 await shipper.locator('.support-composer [type=submit]').click();
 await shipper.waitForFunction(()=>document.querySelector('[data-chat-feedback]')?.textContent.includes('gián đoạn'));
 assert.equal(await input.inputValue(),'Tôi sắp đến điểm giao.','failed send preserves message');
 await shipper.locator('.support-composer [type=submit]').click();
 await shipper.waitForFunction(()=>document.querySelectorAll('[data-order-chat] .support-message').length===1);
 assert.equal(chatWrites[0].client_key,chatWrites[1].client_key,'retry retains idempotency key');
 assert.equal(await input.inputValue(),'');
 await input.fill('Tin nhắn đang soạn');
 messages.unshift({message_id:'2',sender_id:'2',body:'Tôi đang đợi bạn.',sent_at:new Date().toISOString(),read_at:null,attachments:[]});
 await shipper.waitForFunction(()=>document.querySelectorAll('[data-order-chat] .support-message').length===2,null,{timeout:12000});
 assert.equal(await input.inputValue(),'Tin nhắn đang soạn','polling retains draft');
 await shipper.locator('[data-delivery-action="SHOW_CANCEL"]').first().click();
 await shipper.locator('[data-shipper-cancel-form] textarea').fill('Xe gặp sự cố, cần hỗ trợ.');
 assert.equal(await shipper.locator('[data-shipper-cancel-form] textarea').inputValue(),'Xe gặp sự cố, cần hỗ trợ.');
 fs.mkdirSync('tests/.runtime/live-delivery',{recursive:true});
 await shipper.screenshot({path:'tests/.runtime/live-delivery/shipper-desktop.png',fullPage:true});
 await customer.screenshot({path:'tests/.runtime/live-delivery/customer-desktop.png',fullPage:true});
 await shipper.setViewportSize({width:390,height:844});await shipper.screenshot({path:'tests/.runtime/live-delivery/shipper-mobile.png',fullPage:true});
 assert.ok(await shipper.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'mobile no horizontal overflow');
 await context.setGeolocation({latitude:10.796,longitude:106.686});
 await customer.waitForFunction(()=>document.querySelector('[data-route-map]')?.textContent.includes('Shipper'),null,{timeout:12000});
 assert.deepEqual(errors,[]);
 console.log('PASS: real Leaflet maps, accept -> customer automatic status, GPS sharing, pickup/transit sync, stable map and mobile layout. No live database writes.');
 }finally{await browser?.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
