const {chromium}=require('playwright');
const {spawn}=require('node:child_process');
const fs=require('node:fs'), assert=require('node:assert/strict');
const base='http://127.0.0.1:3095';
const server=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3095','tests/bootstrap_render_fixture.php'],{stdio:'ignore',windowsHide:true});
(async()=>{
 let browser;
 try {
  for(let i=0;i<40;i++){try{await new Promise((resolve,reject)=>require('node:http').get(base+'/__shipper-test',r=>{r.resume();r.on('end',resolve);}).on('error',reject));break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({headless:true,executablePath:['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync)});
  const context=await browser.newContext({permissions:['geolocation'],geolocation:{latitude:10.8035,longitude:106.68}});
  const page=await context.newPage();
  let available=false, items=[], staleRead=false, releaseStale, staleStarted;
  const writes=[], errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>localStorage.setItem('somi-language','vi'));
  await page.route('**/public/api/**',async route=>{
   const req=route.request(), path=new URL(req.url()).pathname;
   if(path.endsWith('/availability.php')){
    if(req.method()==='POST'){const body=req.postDataJSON();available=body.available;writes.push(body);}
    const state={availability:available,can_work:true,blockers:[]};
    if(req.method()==='GET'&&staleRead){staleRead=false;staleStarted();await new Promise(r=>releaseStale=r);}
    return route.fulfill({json:state});
   }
   if(path.endsWith('/offers.php'))return route.fulfill({json:{items}});
   if(path.endsWith('/dashboard.php'))return route.fulfill({json:{timezone:'Asia/Ho_Chi_Minh',start:'2026-11-13T00:00:00+07:00',end:'2026-11-14T00:00:00+07:00',completed_deliveries:0,delivery_seconds:0}});
   if(path.endsWith('/shipper/profile.php'))return route.fulfill({json:{profile:{full_name:'Test Shipper',customer_rating_count:0}}});
   if(path.endsWith('/deliveries.php'))return route.fulfill({json:{delivery:null,items:[]}});
   return route.fulfill({json:{items:[],totals:[],unread_count:0}});
  });
  await page.goto(base+'/__shipper-test');
  await page.waitForFunction(()=>!document.getElementById('availabilityToggle').disabled);
  // Simulate a slow offline read returning after the user has gone online.
  const started=new Promise(r=>staleStarted=r);staleRead=true;
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await started;
  await page.locator('#availabilityToggle').click();
  await page.waitForFunction(()=>document.getElementById('availabilityToggle').getAttribute('aria-pressed')==='true');
  releaseStale();
  await page.waitForTimeout(300);
  assert.equal(await page.locator('#availabilityToggle').getAttribute('aria-pressed'),'true','stale response cannot turn the UI off');
  await page.reload();
  await page.waitForFunction(()=>document.getElementById('availabilityToggle').getAttribute('aria-pressed')==='true');
  assert.ok(!writes.some(x=>x.available===false),'reload never sends an offline update');
  items=[{offer_id:'777',order_code:'AUTO-NEW-ORDER',seconds_left:60,distance_to_pickup_km:'0.5',pickup_address:'Pickup',delivery_address:'Dropoff',distance_km:3,estimated_duration_minutes:15,category_name_vi:'Test',estimated_weight_kg:1,estimated_earning_minor:'20000'}];
  await page.waitForFunction(()=>document.getElementById('overviewOrders').textContent.includes('AUTO-NEW-ORDER'),null,{timeout:12000});
  assert.equal(await page.locator('[data-offer-accept="777"]').count(),1,'new order appears without reload');
  await page.locator('#availabilityToggle').click();
  await page.waitForFunction(()=>document.getElementById('availabilityToggle').getAttribute('aria-pressed')==='false');
  await page.reload();
  await page.waitForFunction(()=>document.getElementById('availabilityToggle').getAttribute('aria-pressed')==='false');
  assert.equal(available,false,'explicit offline survives reload');
  assert.deepEqual(errors,[]);
  console.log('PASS: online/offline persists through reload, stale state ignored, new offers appear automatically.');
 }finally{await browser?.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
