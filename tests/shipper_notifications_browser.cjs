const {chromium}=require('playwright'),{spawn}=require('node:child_process'),fs=require('node:fs'),assert=require('node:assert/strict');
const base='http://127.0.0.1:3093',server=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3093','tests/bootstrap_render_fixture.php'],{stdio:'ignore',windowsHide:true});
(async()=>{let browser;try{
 for(let i=0;i<40;i++){try{await new Promise((resolve,reject)=>require('node:http').get(base+'/__shipper-test',r=>{r.resume();r.on('end',resolve);}).on('error',reject));break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true,executablePath:['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync)});
 const page=await browser.newPage({viewport:{width:1440,height:900}}),errors=[],writes=[];
 page.on('pageerror',e=>errors.push(e.message));await page.addInitScript(()=>{try{localStorage.setItem('somi-language','vi');}catch{}});
 let fail=false,items=[{notification_id:'20',order_id:'777',title_vi:'Đơn hàng đã được cập nhật',title_en:'Order updated',message_vi:'Đơn SOMI-777 đang chờ xác nhận giao hàng.',message_en:'Order SOMI-777 is awaiting delivery confirmation.',created_at:new Date().toISOString(),read_at:null},{notification_id:'19',title_vi:'Thông báo tài khoản',title_en:'Account notice',message_vi:'<img src=x onerror=alert(1)>',message_en:'Test',created_at:new Date().toISOString(),read_at:null}];
 await page.route('**/public/api/**',async r=>{const req=r.request(),u=new URL(req.url());let data={items:[],totals:[],unread_count:0};
  if(u.searchParams.get('resource')==='notifications'){
   if(fail)return r.fulfill({status:503,json:{error:'Unavailable'}});
   if(req.method()==='POST'){const b=req.postDataJSON();writes.push(b);items.forEach(n=>{if(b.all||n.notification_id===b.notification_id)n.read_at=new Date().toISOString();});data={updated_count:1};}
   else data={items:u.searchParams.has('before_id')?[{...items[1],notification_id:'18'}]:items,unread_count:items.filter(n=>!n.read_at).length,next_cursor:u.searchParams.has('before_id')?null:'19'};
  }else if(u.pathname.endsWith('/availability.php'))data={availability:false,can_work:true,blockers:[]};
  else if(u.pathname.endsWith('/dashboard.php'))data={timezone:'Asia/Ho_Chi_Minh',start:'2026-11-13T00:00:00+07:00',end:'2026-11-14T00:00:00+07:00',completed_deliveries:0,delivery_seconds:0};
  else if(u.pathname.endsWith('/profile.php'))data={profile:{full_name:'Người giao hàng',customer_rating_count:0}};
  else if(u.pathname.endsWith('/deliveries.php'))data={delivery:null};
  return r.fulfill({json:data});
 });
 await page.goto(base+'/__shipper-test?view=dashboard');await page.waitForFunction(()=>document.getElementById('bellCount').textContent==='2');
 const startUrl=page.url();await page.locator('#bellBtn').click();await page.waitForSelector('#shipperNotificationPopup:not([hidden])');
 assert.equal(page.url(),startUrl,'opening notifications does not navigate');
 assert.equal(await page.locator('.notification-popup-item').count(),2);assert.equal(await page.locator('.notification-popup-item img').count(),0,'API content escaped');
 let box=await page.locator('#shipperNotificationPopup').boundingBox();assert.ok(box.width<=420&&box.height<700,'compact popup');
 await page.locator('[data-notification-read="20"]').click();await page.waitForFunction(()=>document.getElementById('bellCount').textContent==='1');
 await page.locator('[data-notification-filter="unread"]').click();assert.equal(await page.locator('.notification-popup-item').count(),1);
 await page.locator('[data-notification-read-all]').click();await page.waitForFunction(()=>document.getElementById('bellCount').hidden);
 assert.ok(writes.some(b=>b.notification_id==='20'&&b.read===true));assert.ok(writes.some(b=>b.all===true&&b.through_id==='20'));
 await page.keyboard.press('Escape');assert.equal(await page.locator('#bellBtn').getAttribute('aria-expanded'),'false');assert.equal(await page.locator('#bellBtn').evaluate(n=>n===document.activeElement),true);
 await page.locator('[data-goto="notifications"]').click();assert.equal(page.url(),startUrl);
 fail=true;await page.locator('[data-notification-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-notification-status]').textContent.includes('Chưa tải được'));
 fail=false;await page.locator('[data-notification-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-notification-status]').hidden);
 await page.locator('[data-notification-filter="all"]').click();await page.locator('[data-notification-more]').click();await page.waitForFunction(()=>document.querySelectorAll('.notification-popup-item').length===3);
 fs.mkdirSync('tests/.runtime/notification-popup',{recursive:true});await page.screenshot({path:'tests/.runtime/notification-popup/desktop.png'});
 await page.setViewportSize({width:390,height:844});box=await page.locator('#shipperNotificationPopup').boundingBox();assert.ok(box.x>=0&&box.x+box.width<=390&&box.height<700);
 await page.screenshot({path:'tests/.runtime/notification-popup/mobile.png'});
 await page.mouse.click(4,800);assert.equal(await page.locator('#bellBtn').getAttribute('aria-expanded'),'false');
 await page.goto(base+'/__shipper-test?view=notifications');await page.waitForSelector('#shipperNotificationPopup:not([hidden])');assert.equal(await page.locator('#page-dashboard').count(),1,'legacy URL uses dashboard and popup');
 assert.deepEqual(errors,[]);console.log('PASS: compact popup, API list/read/read-all/pagination, badge, no navigation, escaping, retry, Escape/outside close and mobile.');
 }finally{await browser?.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
