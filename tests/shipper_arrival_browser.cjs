const fs=require('node:fs'),assert=require('node:assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:390,height:844}}),calls=[],errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  let delivery={order_id:'7',order_code:'ARRIVAL-FIXTURE',status:'IN_DELIVERY',category_name_vi:'Tài liệu',estimated_weight_kg:'1',distance_km:'3',sender_name:'Sender',receiver_name:'Receiver',sender_phone:'0900000001',receiver_phone:'0900000002',pickup_address:'Pickup',delivery_address:'Drop-off',pickup_latitude:10.8,pickup_longitude:106.68,delivery_latitude:10.776,delivery_longitude:106.688,arrived_at:null,evidence:[],next_actions:['ARRIVED','FAIL']};
  await page.addInitScript(()=>{
   window.fixtureToasts=[];window.toast=m=>fixtureToasts.push(m);
   Object.defineProperty(navigator,'geolocation',{value:{watchPosition(fn){window.fixtureGps=fn;return 1;},clearWatch(){window.fixtureGpsStopped=true;},getCurrentPosition(fn){fn({coords:{latitude:10.776,longitude:106.688,accuracy:8}});}}});
  });
  await page.route('https://arrival.test/**',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.pathname==='/'){
    const fragment=fs.readFileSync('app/Views/shipper/pages/active.php','utf8').replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'');
    return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="vi"><meta charset="utf-8"><style>.hidden{display:none}*{box-sizing:border-box}body{margin:8px}select,textarea{max-width:100%}</style><body>${fragment}<script>${fs.readFileSync('public/assets/js/shipper-api.js','utf8')}</script><script>${fs.readFileSync('public/assets/js/shipper-active.js','utf8')}</script><script>${fs.readFileSync('public/assets/js/shipper-tracking.js','utf8')}</script></body></html>`});
   }
   if(u.pathname.endsWith('/storage.php')){calls.push({path:u.pathname});return route.fulfill({json:{file_id:'42'}});}
   const body=req.postDataJSON();calls.push({path:u.pathname,body});
   if(u.pathname.endsWith('/location.php')){
    delivery={...delivery,arrived_at:'2026-11-14T07:30:00Z',next_actions:['ADD_DELIVERY_PHOTO','FAIL']};
    return route.fulfill({json:{order_id:'7',recorded_at:delivery.arrived_at,arrived_at:delivery.arrived_at}});
   }
   if(u.pathname.endsWith('/deliveries.php'))return route.fulfill({json:{delivery}});
   if(u.pathname.endsWith('/evidence.php')){
    assert.equal(body.reference_file_id,'42');
    delivery={...delivery,evidence:[{evidence_type:'DELIVERY_PHOTO',file_id:'42'}],next_actions:['ADD_DELIVERY_PHOTO','DELIVERED','FAIL']};
    return route.fulfill({json:{delivery}});
   }
   if(u.pathname.endsWith('/complete-delivery.php')){delivery=null;return route.fulfill({json:{status:'DELIVERED'}});}
   return route.fulfill({json:{items:[]}});
  });
  await page.goto('https://arrival.test/');
  await page.locator('[data-delivery-action=ARRIVED]').waitFor();
  assert.equal(await page.locator('[data-evidence=DELIVERY_PHOTO]').count(),0,'no evidence action before arrival');
  assert.equal(await page.locator('[data-delivery-action=DELIVERED]').count(),0,'no completion action before arrival');
  await page.waitForFunction(()=>typeof fixtureGps==='function');
  await page.evaluate(()=>fixtureGps({coords:{latitude:10.776,longitude:106.688,accuracy:8}}));
  await page.locator('[data-evidence=DELIVERY_PHOTO]').waitFor();
  assert.ok((await page.locator('#activeDelivery').textContent()).includes('ĐÃ ĐẾN NƠI'));
  assert.equal(await page.locator('[data-delivery-action=DELIVERED]').count(),0,'arrival alone cannot complete delivery');
  assert.equal(await page.locator('#evidenceFile').getAttribute('capture'),'environment','camera capture is available on phones');
  await page.locator('[data-evidence=DELIVERY_PHOTO]').click();
  await page.locator('#evidenceFile').setInputFiles({name:'proof.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aM3kAAAAASUVORK5CYII=','base64')});
  await page.locator('[data-delivery-action=DELIVERED]').waitFor();
  assert.equal(calls.filter(c=>c.path.endsWith('/complete-delivery.php')).length,0,'upload does not auto-complete');
  await page.locator('[data-delivery-action=DELIVERED]').click();
  await page.waitForFunction(()=>window.fixtureGpsStopped===true);
  assert.equal(calls.filter(c=>c.path.endsWith('/complete-delivery.php')).length,1);
  assert.deepEqual(errors,[]);
  console.log('PASS: mobile GPS arrival → private photo proof → explicit delivery completion; GPS stops afterwards.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
