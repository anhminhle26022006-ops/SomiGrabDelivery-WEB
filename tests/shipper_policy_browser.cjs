const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;};
 const page=await browser.newPage({viewport:{width:390,height:844}}),calls=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
 let mode='before',delivery=null,failOnce=false,hold=false,release;
 const makeDelivery=()=>({order_id:'7',order_code:'FIXTURE-7',status:mode==='before'?'SHIPPER_ASSIGNED':'IN_DELIVERY',category_name_vi:'Fixture parcel',estimated_weight_kg:'1',distance_km:'3',sender_name:'Sender',receiver_name:'Receiver',pickup_address:'Pickup',delivery_address:'Destination',sender_phone:'0900000001',receiver_phone:'0900000002',evidence:mode==='complete'?[{evidence_type:'DELIVERY_PHOTO'}]:[],next_actions:mode==='before'?['PICKUP']:mode==='complete'?['ADD_DELIVERY_PHOTO','DELIVERED','FAIL']:['FAIL']});
 await page.addInitScript(()=>{window.fixtureToasts=[];window.toast=m=>fixtureToasts.push(m);Object.defineProperty(navigator,'geolocation',{value:{watchPosition(){return 1;},clearWatch(){}}});});
 try {
 await page.route('http://127.0.0.1:3098/**',async route=>{
  const req=route.request(),u=new URL(req.url());if(u.pathname==='/'){
   const html=fs.readFileSync('app/Views/shipper/pages/active.php','utf8').replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'');
   return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="vi"><meta charset="utf-8"><style>.hidden{display:none}*{box-sizing:border-box}body{margin:8px}select,textarea{max-width:100%}</style><body>${html}<script>${fs.readFileSync('public/assets/js/shipper-api.js','utf8')}</script><script>${fs.readFileSync('public/assets/js/shipper-active.js','utf8')}</script></body></html>`});
  }
  const body=req.postDataJSON();calls.push({path:u.pathname,body});
  if(u.pathname.endsWith('/deliveries.php'))return route.fulfill({json:{delivery}});
  if(u.pathname.endsWith('/cancellation.php')){
   if(hold)await new Promise(r=>release=r);
   if(failOnce){failOnce=false;return route.fulfill({status:503,json:{code:'SERVICE_UNAVAILABLE'}});}
   const r={cancellation_request_id:'11',decision:mode==='before'?'NOT_REQUIRED':'PENDING',reason:body.reason};delivery=mode==='before'?null:{...delivery,cancellation_request:r};return route.fulfill({json:{request:r}});
  }
  if(u.pathname.endsWith('/complete-delivery.php')){delivery=null;return route.fulfill({json:{status:'DELIVERED',settlement:{payout_id:'21',transfer_status:'PROCESSING'}}});}
  return route.fulfill({json:{}});
 });
 async function load(next){mode=next;delivery=makeDelivery();await page.goto('http://127.0.0.1:3098/');await page.locator('[data-delivery-action="SHOW_CANCEL"]').waitFor();}
 async function fill(){await page.locator('[data-delivery-action="SHOW_CANCEL"]').click();await page.locator('[name=reason]').fill('Fixture cancellation');await page.locator('[name=acknowledged]').check();}
 await load('before');await page.locator('[data-delivery-action="SHOW_CANCEL"]').click();await page.locator('[data-cancellation-submit]').click();
 check(!calls.some(c=>c.path.endsWith('/cancellation.php')),'requires reason and explicit acknowledgement');
 await page.locator('[name=reason]').fill('Fixture cancellation');await page.locator('[name=acknowledged]').check();
 hold=true;await page.locator('[data-cancellation-submit]').click();await page.waitForFunction(()=>document.querySelector('[data-cancellation-submit]').disabled);await page.locator('[data-shipper-cancel-form]').evaluate(f=>f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
 check(calls.filter(c=>c.path.endsWith('/cancellation.php')).length===1,'double submit is blocked');hold=false;release();await page.waitForFunction(()=>document.getElementById('activeDelivery').textContent.includes('chưa có đơn'));
 const first=calls.find(c=>c.path.endsWith('/cancellation.php')).body;check(first.order_id==='7'&&first.reason_code==='SHIPPER_FAULT'&&first.client_key.length>=8,'cancellation uses actual API and stable client key');
 await load('after');await fill();await page.locator('[name=reason_code]').selectOption('EXTERNAL_ISSUE');await page.locator('[data-cancellation-submit]').click();await page.waitForFunction(()=>document.getElementById('activeDelivery').textContent.includes('PENDING'));
 check(await page.locator('[data-delivery-action="SHOW_CANCEL"]').count()===0,'pending request cannot be resubmitted');
 check(await page.locator('#activeDelivery').textContent().then(s=>s.includes('ĐANG GIAO')),'request keeps current delivery until Admin decision');
 await load('after');failOnce=true;await fill();await page.locator('[data-cancellation-submit]').click();await page.waitForFunction(()=>fixtureToasts.some(t=>t.includes('Yêu cầu')));await page.locator('[data-delivery-action="SHOW_CANCEL"]').waitFor();
 const failed=calls.filter(c=>c.path.endsWith('/cancellation.php')).at(-1).body;await fill();await page.locator('[data-cancellation-submit]').click();await page.waitForFunction(()=>document.getElementById('activeDelivery').textContent.includes('PENDING'));
 check(calls.filter(c=>c.path.endsWith('/cancellation.php')).at(-1).body.client_key===failed.client_key,'uncertain retry reuses idempotency key');
 await load('complete');await page.locator('[data-delivery-action="DELIVERED"]').click();await page.waitForFunction(()=>fixtureToasts.some(t=>t.includes('chờ chuyển khoản thủ công')));
 check(calls.some(c=>c.path.endsWith('/complete-delivery.php')),'delivery creates payout request without claiming money transferred');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile layout does not overflow');
 check(errors.length===0,'no browser exceptions: '+errors.join(';'));
 console.log(`PASS: ${checks} Shipper policy browser checks; HTTP fixtures; no provider calls.`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
