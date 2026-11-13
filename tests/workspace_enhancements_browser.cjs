const {chromium}=require('playwright'),{spawn}=require('node:child_process'),fs=require('node:fs'),assert=require('node:assert/strict');
const base='http://127.0.0.1:3093',php=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3093','tests/workspace_render_fixture.php'],{stdio:'ignore',windowsHide:true});
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEElEQVR4nGP432AARAwQCgAybga9zvya1gAAAABJRU5ErkJggg==','base64');
(async()=>{let browser;try{
 for(let i=0;i<50;i++){try{await fetch(base+'/__workspace-test');break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage(),errors=[],calls=[];let avatar=null,failWeather=false,failAvatar=false,uploads=0;
 page.on('pageerror',e=>errors.push(e.message));await page.addInitScript(()=>localStorage.setItem('somi-language','vi'));
 await page.route('**/public/api/**',async route=>{const req=route.request(),url=new URL(req.url()),path=url.pathname;calls.push(path+url.search);const profile={full_name:'Fixture An',user_id:'77',email:'fixture@example.invalid',phone:'0900000077',vehicle_type:'Xe máy',vehicle_registration:'59-TEST',verification_status:'APPROVED',customer_rating_count:8,customer_rating_average:'4.8',app_language:'vi'};
  if(path.endsWith('/account/avatar.php')){
   if(url.searchParams.has('content'))return route.fulfill({contentType:'image/png',body:png});
   if(req.method()==='POST'){uploads++;if(failAvatar)return route.fulfill({status:503,json:{error:'Fixture upload failed'}});assert.ok(req.headers()['content-type'].startsWith('multipart/form-data;'));assert.ok(req.postDataBuffer().includes(Buffer.from('avatar.jpg')));avatar='public/api/account/avatar.php?content=1&v='+uploads;}
   if(req.method()==='DELETE')avatar=null;return route.fulfill({json:{avatar_url:avatar}});
  }
  if(path.endsWith('/account/weather.php')){if(failWeather)return route.fulfill({status:503,json:{error:'Weather unavailable'}});const now=new Date();return route.fulfill({json:{location:{name_vi:'Khu vực công khai'},weather:{temperature_c:30,feels_like_c:33,humidity_percent:74,wind_kmh:12,weather_code:2,provider:'Open-Meteo',observed_at:now.toISOString(),hours:[0,1,2,3].map(i=>({time:new Date(now.getTime()+i*3600000).toISOString(),temperature_c:30-i,rain_probability:[15,30,75,65][i],weather_code:i>1?61:2}))}}});}
  if(path.endsWith('/profile.php'))return route.fulfill({json:{profile,account:{code:'77',phone:profile.phone,email:profile.email},application:{residential_address:'Fixture address'}}});
  if(path.endsWith('/shipper/dashboard.php'))return route.fulfill({json:{range:'today',current:{deliveries:5,acceptance_rate_percent:83,offers_accepted:5,offers_decided:6},previous:{deliveries:4},deliveries_change_percent:25,rating:{average:'4.8',count:8},delivery_seconds:5400}});
  if(path.endsWith('/kyc.php'))return route.fulfill({json:{can_resubmit:false,documents:[]}});
  if(path.endsWith('/availability.php'))return route.fulfill({json:{availability:false,can_work:true,blockers:[]}});
  if(path.endsWith('/deliveries.php'))return route.fulfill({json:{delivery:null,items:[]}});
  if(path.endsWith('/bank-account.php'))return route.fulfill({json:{bank_account:null}});
  if(path.endsWith('/addresses.php'))return route.fulfill({json:{items:[{address_id:'1',is_default:true,address_detail:'Fixture pickup',latitude:'10.8',longitude:'106.7'}]}});
  return route.fulfill({json:{items:[],totals:[],unread_count:0,next_cursor:null}});
 });
 for(const role of ['customer','shipper']){
  await page.goto(base+'/__workspace-test?role='+role);await page.waitForFunction(()=>document.querySelector('[data-weather-temperature]')?.textContent==='30°');
  const before=await page.locator('[data-workspace-weather]').boundingBox();
  if(role==='customer'){const grid=await page.locator('[data-live]').boundingBox();assert.ok(before.y<grid.y,'customer weather precedes dashboard');}
  else{const active=await page.locator('#overviewActive').boundingBox();assert.ok(before.x>active.x,'shipper weather sits beside active delivery');assert.equal(await page.locator('.cards.row > div').count(),5);const metrics=await page.locator('.cards.row > div').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect()));assert.ok(metrics.every(m=>m.height<200&&Math.abs(m.y-metrics[0].y)<1),'five compact metrics share one row');await page.locator('[data-shift-checklist] input').first().check();assert.equal(await page.locator('[data-shift-ready]').textContent(),'1 / 3');}
  await page.locator('[data-weather-hour="2"]').click();assert.equal(await page.locator('[data-weather-temperature]').textContent(),'28°');assert.match(await page.locator('[data-weather-advice]').textContent(),/mưa/i);
  await page.locator('[data-weather-area]').selectOption('east');await page.waitForFunction(()=>document.querySelector('[data-weather-temperature]').textContent==='30°');assert.ok(calls.some(p=>p.endsWith('weather.php?area=east')));
  failWeather=true;await page.locator('[data-weather-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-weather-status]').textContent.includes('Chưa tải'));assert.ok(await page.locator('.ww-body').isHidden());failWeather=false;await page.locator('[data-weather-refresh]').click();await page.waitForFunction(()=>document.querySelector('.ww-body').hidden===false);
  for(const width of [1440,768,390]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(100);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),role+' layout '+width);}
  fs.mkdirSync('tests/.runtime/workspace-enhancements',{recursive:true});await page.screenshot({path:'tests/.runtime/workspace-enhancements/'+role+'-mobile.png',fullPage:true});
  await page.goto(base+'/__workspace-test?role='+role+'&view=account');await page.locator('[data-avatar-input]').waitFor();
  await page.locator('[data-avatar-input]').setInputFiles({name:'photo.png',mimeType:'image/png',buffer:png});await page.locator('[data-avatar-save]').waitFor({state:'visible'});await page.locator('[data-avatar-save]').click();await page.waitForFunction(()=>document.querySelector('[data-avatar-status]').textContent.includes('Đã lưu'));
  await page.reload();await page.waitForFunction(()=>!!document.querySelector('.workspace-avatar-image'));assert.ok(await page.locator('[data-avatar-remove]').isVisible());
  await page.locator('[data-avatar-input]').setInputFiles({name:'bad.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg/>')});await page.waitForFunction(()=>document.querySelector('[data-avatar-status]').textContent.includes('Chọn JPEG'));
  failAvatar=true;await page.locator('[data-avatar-input]').setInputFiles({name:'photo.png',mimeType:'image/png',buffer:png});await page.locator('[data-avatar-save]').waitFor({state:'visible'});await page.locator('[data-avatar-save]').click();await page.waitForFunction(()=>document.querySelector('[data-avatar-status]').textContent.includes('Fixture upload failed'));assert.ok(await page.locator('.workspace-avatar-image').first().isVisible());failAvatar=false;
  await page.locator('[data-avatar-cancel]').click();await page.locator('[data-avatar-remove]').click();await page.waitForFunction(()=>document.querySelector('[data-avatar-status]').textContent.includes('Đã xóa'));await page.reload();assert.equal(await page.locator('.workspace-avatar-image').count(),0);
  await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/__workspace-test?role='+role);await page.waitForFunction(()=>document.querySelector('[data-weather-temperature]')?.textContent==='30°');await page.screenshot({path:'tests/.runtime/workspace-enhancements/'+role+'-desktop.png',fullPage:true});
 }
 const unauthorized=await page.request.get(base+'/public/api/account/avatar.php?content=1');assert.equal(unauthorized.status(),401);const weatherUnauthorized=await page.request.get(base+'/public/api/account/weather.php');assert.equal(weatherUnauthorized.status(),401);
 assert.deepEqual(errors,[]);console.log('PASS: Customer variant 2, Shipper variant 1, weather/error/retry, avatar upload/reload/remove/failure, privacy and responsive layouts.');
}finally{await browser?.close();php.kill();}})().catch(e=>{console.error(e);process.exitCode=1;});
