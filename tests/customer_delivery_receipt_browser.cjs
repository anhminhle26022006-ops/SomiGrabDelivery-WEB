const fs=require('node:fs'),cp=require('node:child_process'),assert=require('node:assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));let mode='photos';
  const order={order_id:'7',order_code:'CUSTOMER-RECEIPT-7',status:'RETURNED',currency_code:'VND',total_charge_minor:'30000',pickup_address:'141 Tầm Danh, Thành phố Hồ Chí Minh',delivery_address:'25 Nguyễn Thị Minh Khai, Thành phố Hồ Chí Minh',sender_name:'Người gửi',sender_phone:'0900000001',receiver_name:'Người nhận',receiver_phone:'0900000002',estimated_weight_kg:'1.5',distance_km:'3.2',parcel_description:'Tài liệu',created_at:'2026-11-14T01:00:00Z',pricing_snapshot:{components:{total_charge_minor:'30000'}},status_history:[{new_status:'RETURNED',created_at:'2026-11-14T02:00:00Z',note:'Khách đã nhận lại hàng'}]};
  await page.route('https://customer-proof.test/**',async route=>{
   const u=new URL(route.request().url());
   if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:cp.execFileSync('C:/xampp/php/php.exe',['-r',"$_SERVER['REQUEST_URI']='/__workspace-test';$_GET['role']='customer';$_GET['view']='order';require 'tests/workspace_render_fixture.php';"],{encoding:'utf8'})});
   if(u.pathname.includes('/assets/'))return fs.existsSync(u.pathname.slice(1))?route.fulfill({path:u.pathname.slice(1)}):route.fulfill({body:''});
   if(u.pathname.endsWith('/storage.php'))return route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aM3kAAAAASUVORK5CYII=','base64')});
   let data={items:[]};
   if(u.pathname.endsWith('/orders.php'))data={order};
   if(u.pathname.endsWith('/payments.php'))data={payment:{status:'PAID',amount_minor:'30000',currency_code:'VND'}};
   if(u.pathname.endsWith('/delivery-proof.php')){
    if(mode==='error')return route.fulfill({status:503,json:{code:'SERVICE_UNAVAILABLE'}});
    data={deliveries:[{shipper_name:'Fixture Shipper',picked_up_at:'2026-11-14T01:10:00Z',arrived_at:'2026-11-14T01:30:00Z',returned_at:'2026-11-14T01:55:00Z',customer_return_confirmed_at:'2026-11-14T02:00:00Z',failure_reason_code:'NO_ANSWER',failure_note:'Không liên hệ được người nhận'}],evidence:mode==='empty'?[]:[{file_id:'42',evidence_type:'DELIVERY_PHOTO',uploaded_at:'2026-11-14T01:30:00Z'},{file_id:'43',evidence_type:'RETURN_PHOTO',uploaded_at:'2026-11-14T01:55:00Z'}]};
   }
   return route.fulfill({json:data});
  });
  fs.mkdirSync('tests/.runtime/customer-receipt',{recursive:true});
  for(const lang of ['vi','en']){
   await page.addInitScript(lang=>localStorage.setItem('somi-language',lang),lang);
   for(const width of [1440,390]){
    await page.setViewportSize({width,height:900});await page.goto('https://customer-proof.test/?page=customer&view=order&id=7');
    await page.locator('[data-customer-proof]').first().waitFor();
    assert.equal(await page.locator('[data-customer-proof]').count(),2);
    const panel=page.locator('[data-customer-proof-panel]');
    assert.ok((await panel.textContent()).includes(lang==='vi'?'Đã đến điểm giao':'Arrived at drop-off'));
    assert.ok((await panel.textContent()).includes(lang==='vi'?'Lý do giao không thành công':'Delivery issue'));
    await panel.scrollIntoViewIfNeeded();await page.screenshot({path:`tests/.runtime/customer-receipt/${lang}-${width}.png`});
    await page.locator('[data-customer-proof]').last().click();await page.locator('[data-customer-proof-dialog][open]').waitFor();
    assert.ok((await page.locator('[data-customer-proof-dialog] img').getAttribute('src')).includes('file_id=43'));
    await page.locator('[data-customer-proof-dialog] button').click();
    assert.equal(new URL(page.url()).searchParams.get('id'),'7','closing image stays on the same order');
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'fits mobile viewport');
   }
  }
  mode='empty';await page.reload();await page.locator('[data-customer-proof-panel]').waitFor();assert.equal(await page.locator('[data-customer-proof]').count(),0);
  mode='error';await page.reload();await page.locator('[data-proof-retry]').waitFor();assert.ok((await page.locator('[data-order]').textContent()).includes(order.order_code),'photo failure does not hide order');
  mode='photos';await page.locator('[data-proof-retry]').click();await page.locator('[data-customer-proof]').first().waitFor();
  assert.deepEqual(errors,[]);console.log('PASS: customer delivery/return proof, milestones, image back, empty/retry, Vietnamese/English and mobile layouts.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
