const fs=require('fs'),assert=require('node:assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage({viewport:{width:1100,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));let checks=0;
 const check=(v,label)=>{assert.ok(v,label);checks++;};let bank={bank_code:'MB',bank_name:'MB Bank',account_holder:'TEST CUSTOMER',account_number:'000123456789',account_number_masked:'••••6789'},pending=null;
 await page.route('http://bank.test/**',async route=>{
  const req=route.request(),url=new URL(req.url());let data={items:[]};const body=req.method()==='POST'?req.postDataJSON():null;
  if(url.pathname==='/'){
   const ship=url.searchParams.get('page')==='shipper';
   const panel=fs.readFileSync('app/Views/shared/person3-panel.php','utf8').split('<link')[0];
   const customer=fs.readFileSync('app/Views/customer/pages/account.php','utf8').replace(/<\?php[\s\S]*?\?>/g,'');
   const profile={full_name:'Fixture Customer',email:'fixture@example.invalid',phone:'0900000000',app_language:'vi'};
   const html=ship?`<div class="p3-panel" data-shipper-bank-editor></div>${panel}`:`<main data-customer-server><div data-server-error></div>${customer}</main><script type="application/json" id="customer-profile-data">${JSON.stringify({profile})}</script>`;
   return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="vi"><meta charset="utf-8"><style>${fs.readFileSync('public/assets/css/person3.css','utf8')}</style><body data-page="${ship?'shipper':'customer'}" data-c-view="account" data-shipper-view="${ship?'account':''}">${html}<script>${fs.readFileSync(ship?'public/assets/js/person3.js':'public/assets/js/customer/server.js','utf8')}</script></body></html>`});
  }
  if(url.pathname.endsWith('bank-account.php')){
   if(body?.action==='REQUEST'){pending=body;data={request_id:'1'};}
   else if(body?.action==='CONFIRM'){bank={...pending};data={confirmed:true};}
   else {if(body)bank={...body};data={bank_account:bank};}
  }else if(url.searchParams.get('resource')==='notifications')data={items:[],unread_count:0};
  return route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.goto('http://bank.test/?page=customer&view=account');const customer=page.locator('[data-refund-bank]');await customer.getByText('MB Bank · TEST CUSTOMER · 000123456789',{exact:true}).waitFor();check(true,'customer sees saved full bank account');
 check(await customer.locator('[data-refund-bank-form]').isHidden(),'customer initially sees summary');await customer.locator('[data-edit-bank]').click();check(await customer.locator('[name=account_number]').inputValue()==='000123456789','customer edit prefilled with leading zeros');await customer.locator('[name=bank_name]').fill('Changed');await customer.locator('[data-cancel-bank]').click();await customer.locator('[data-edit-bank]').click();check(await customer.locator('[name=bank_name]').inputValue()==='MB Bank','cancel restores saved account');await customer.locator('[name=account_number]').fill('000999888777');await customer.locator('[name=current_password]').fill('FixturePassword123');await customer.getByRole('button',{name:'Lưu tài khoản',exact:true}).click();await customer.getByText('MB Bank · TEST CUSTOMER · 000999888777',{exact:true}).waitFor();check(bank.account_number==='000999888777','customer update saved and visible');
 bank={bank_code:'MB',bank_name:'MB Bank',account_holder:'TEST SHIPPER',account_number:'000333222111'};
 await page.goto('http://bank.test/?page=shipper&view=account');const shipper=page.locator('[data-shipper-bank-editor]');await shipper.getByText('MB Bank · TEST SHIPPER · 000333222111',{exact:true}).waitFor();check(true,'shipper bank is inline on account page');await shipper.locator('[data-edit-bank]').click();check(await shipper.locator('[name=account_number]').inputValue()==='000333222111','shipper edit prefilled');await shipper.locator('[name=account_number]').fill('000555666777');await shipper.locator('[name=current_password]').fill('FixturePassword123');await shipper.getByRole('button',{name:'Nhận mã OTP qua email'}).click();await shipper.locator('[data-bank-confirm]').waitFor({state:'visible'});check(pending.account_number==='000555666777','shipper submits edited destination for verification');await shipper.locator('[name=code]').fill('123456');await shipper.getByRole('button',{name:'Xác nhận tài khoản',exact:true}).click();await shipper.getByText('MB Bank · TEST SHIPPER · 000555666777',{exact:true}).waitFor();check(true,'shipper shows new bank after OTP confirmation');check(errors.length===0,'no browser runtime errors');
 await browser.close();console.log(`Bank account browser: ${checks} checks passed.`);
})().catch(e=>{console.error(e);process.exit(1)});
