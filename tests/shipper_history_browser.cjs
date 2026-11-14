const fs=require('node:fs'),cp=require('node:child_process'),assert=require('node:assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>localStorage.setItem('somi-language','vi'));
  const rows=[{assignment_id:'11',assignment_status:'COMPLETED',order_id:'7',order_code:'HISTORY-7',status:'DELIVERED',pickup_address:'Pickup street',delivery_address:'Delivery street',assigned_at:'2026-11-14T01:00:00Z',ended_at:'2026-11-14T02:00:00Z',gross_amount_minor:'9007199254740993',currency_code:'VND',payout_status:'PENDING'},{assignment_id:'10',assignment_status:'RETURNED',order_code:'RETURN-6',status:'RETURNED',assigned_at:'2026-11-13T01:00:00Z',pickup_address:'Pickup',delivery_address:'Return'}];
  let fail=false;
  await page.route('https://history.test/**',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.pathname==='/'){const html=cp.execFileSync('C:/xampp/php/php.exe',['-r',"$_SERVER['REQUEST_URI']='/__workspace-test';$_GET['role']='shipper';$_GET['view']='history';require 'tests/workspace_render_fixture.php';"],{encoding:'utf8'});return route.fulfill({contentType:'text/html',body:html});}
   if(u.pathname.includes('/assets/'))return fs.existsSync(u.pathname.slice(1))?route.fulfill({path:u.pathname.slice(1)}):route.fulfill({body:''});
   if(u.pathname.endsWith('/storage.php'))return route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aM3kAAAAASUVORK5CYII=','base64')});
   if(u.searchParams.has('assignment_id')){
    if(fail)return route.fulfill({status:503,json:{code:'SERVICE_UNAVAILABLE'}});
    const r=rows.find(r=>r.assignment_id===u.searchParams.get('assignment_id'));
    return route.fulfill({json:{receipt:{...r,category_name_vi:'Tài liệu',category_name_en:'Documents',estimated_weight_kg:'1.5',distance_km:'3.2',parcel_description:'<img src=x onerror="window.unsafe=true">',receiver_name:'Receiver',receiver_phone:'0900000001',sender_name:'Sender',sender_phone:'0900000002',picked_up_at:r.assigned_at,arrived_at:r.ended_at,delivered_at:r.ended_at,timeline:[],cancellations:[],evidence:r.assignment_id==='11'?[{file_id:'42',evidence_type:'DELIVERY_PHOTO',uploaded_at:r.ended_at}]:[]}}});
   }
   let data={items:[],totals:[]};
   if(u.searchParams.has('history'))data={items:rows,next_before_id:null};
   else if(u.pathname.endsWith('/deliveries.php'))data={delivery:null};
   else if(u.pathname.endsWith('/availability.php'))data={availability:false,can_work:true,blockers:[]};
   else if(u.pathname.endsWith('/profile.php'))data={profile:{full_name:'History fixture',phone:'0900000001',email:'fixture@example.invalid',app_language:'vi'}};
   return route.fulfill({json:data});
  });
  fs.mkdirSync('tests/.runtime/shipper-history',{recursive:true});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:900});await page.goto('https://history.test/');
   await page.locator('[data-history-detail="11"]').waitFor();
   assert.ok((await page.locator('#historyRows').textContent()).includes('9.007.199.254.740.993'),'money remains exact');
   await page.locator('[data-history-filter=done]').click();
   await page.locator('[data-history-detail="11"]').click();
   await page.locator('[data-history-image]').waitFor();
   assert.equal(await page.locator('[data-history-dialog]').evaluate(d=>d.open),true);
   assert.equal(await page.evaluate(()=>window.unsafe),undefined,'notes are escaped');
   assert.ok((await page.locator('[data-history-content]').textContent()).includes('Chờ chi trả'));
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'page fits viewport');
   await page.screenshot({path:`tests/.runtime/shipper-history/receipt-${width}.png`});
   await page.locator('[data-history-image]').click();await page.locator('[data-history-photo][open]').waitFor();
   await page.locator('[data-close-history-photo]').click();
   assert.equal(await page.locator('[data-history-dialog]').evaluate(d=>d.open),true,'photo back returns to receipt');
   await page.locator('[data-close-history]').last().click();
   assert.equal(await page.locator('[data-history-filter=done]').getAttribute('class'),'outline selected','closing preserves list filter');
   await page.locator('[data-history-filter=returned]').click();await page.locator('[data-history-detail="10"]').click();
   await page.getByText('Không có ảnh minh chứng còn trong thời hạn lưu trữ cho lần giao này.',{exact:true}).waitFor();
   await page.keyboard.press('Escape');assert.equal(await page.locator('[data-history-dialog]').evaluate(d=>d.open),false);
  }
  fail=true;await page.locator('[data-history-detail="10"]').click();await page.getByRole('button',{name:'Thử lại',exact:true}).waitFor();
  fail=false;await page.getByRole('button',{name:'Thử lại',exact:true}).click();await page.getByText('Không có ảnh minh chứng còn trong thời hạn lưu trữ cho lần giao này.',{exact:true}).waitFor();
  assert.deepEqual(errors,[]);console.log('PASS: shipper history receipts, private photo viewer, exact earnings, filters, back/escape, retry and mobile layout.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
