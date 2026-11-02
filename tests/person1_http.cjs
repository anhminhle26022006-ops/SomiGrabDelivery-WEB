/* Real HTTP/password login tests against the isolated PostgreSQL test server. */
const fs = require('fs');
const assert = require('assert/strict');
const fixtures = JSON.parse(fs.readFileSync(__dirname + '/.runtime/person1-fixtures.json', 'utf8'));
const base = 'http://127.0.0.1:3034';
const results = [];
const cookies = {};
async function request(kind, path, body, method = body ? 'POST' : 'GET', extra = {}) {
  const headers = { ...extra };
  if (cookies[kind]) headers.Cookie = cookies[kind];
  if (body) headers['Content-Type'] = 'application/json';
  const response = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined, redirect: 'manual', signal: AbortSignal.timeout(10000) });
  const cookie = response.headers.get('set-cookie');
  if (cookie) cookies[kind] = cookie.split(';')[0];
  const text = await response.text();
  let value; try { value = JSON.parse(text); } catch { value = text; }
  return { status: response.status, value, headers: response.headers };
}
async function test(name, operation) {
  try { await operation(); results.push({ test: name, result: 'PASS' }); }
  catch (e) { results.push({ test: name, result: 'FAIL', message: e.message }); }
}
const eq = async (kind, path, body, status, method, extra) => {
  const result = await request(kind, path, body, method, extra);
  assert.equal(result.status, status, `${path}: expected ${status}, got ${result.status}`);
  return result.value;
};
(async () => {
  await test('unauthenticated API is 401 JSON', () => eq('anon','/public/api/customer/addresses.php',null,401));
  for (const kind of ['customer','other','admin','shipper']) {
    await test(`real password login ${kind}`, async () => {
      const data = await eq(kind,'/public/api/auth/login.php',{identifier:fixtures.users[kind].email,password:fixtures.password,role:kind==='other'?'CUSTOMER':kind.toUpperCase()},200);
      assert.equal(data.status,'SIGNED_IN');
    });
  }
  await test('incorrect password rejected',()=>eq('invalid','/public/api/auth/login.php',{identifier:fixtures.users.customer.email,password:'incorrect-test-password',role:'CUSTOMER'},401));
  await test('Customer cannot log in as Admin',()=>eq('invalid','/public/api/auth/login.php',{identifier:fixtures.users.customer.email,password:fixtures.password,role:'ADMIN'},401));
  await test('profile returns actual identity and no hash', async()=>{
    const {data}=await eq('customer','/public/api/auth/profile.php',null,200);
    assert.equal(Number(data.user_id),fixtures.users.customer.id);assert.ok(!('password_hash' in data));assert.ok(data.roles.includes('CUSTOMER'));
  });
  await test('real phone password login',()=>eq('phone','/public/api/auth/login.php',{identifier:fixtures.users.customer.phone,password:fixtures.password,role:'CUSTOMER'},200));
  await test('profile update real database',async()=>{
    const {data}=await eq('customer','/public/api/auth/profile.php',{full_name:'Updated Test Customer',app_language:'vi'},200);assert.equal(data.full_name,'Updated Test Customer');
  });
  await test('profile rejects role injection',()=>eq('customer','/public/api/auth/profile.php',{full_name:'Test',app_language:'vi',role:'ADMIN'},422));
  await test('reset request response does not enumerate accounts on provider failure',async()=>{
    const known=await eq('anon','/public/api/auth/request-password-reset.php',{email:fixtures.users.customer.email},200);
    const unknown=await eq('anon','/public/api/auth/request-password-reset.php',{email:'nonexistent-person1@example.invalid'},200);
    assert.deepEqual(known,unknown);
  });
  await test('wrong workspace rejected',()=>eq('shipper','/public/api/customer/addresses.php',null,403));
  await test('customer cannot read admin accounts',()=>eq('customer','/public/api/admin/accounts.php',null,403));
  await test('customer cannot change categories',()=>eq('customer','/public/api/admin/categories.php',{category_id:fixtures.category_id,restriction_type:'PROHIBITED',active:true},403));
  let address;
  const draft={action:'save',label:'HTTP Test',address_detail:'Test street',province:'Test',district:'',ward:'',latitude:10.1,longitude:106.1,is_default:true};
  await test('HTTP address create',async()=>{const {data}=await eq('customer','/public/api/customer/addresses.php',draft,201);address=data;assert.ok(data.address_id);});
  await test('HTTP address list persists',async()=>{const {data}=await eq('customer','/public/api/customer/addresses.php',null,200);assert.ok(data.some(x=>x.address_id===address.address_id));});
  await test('HTTP address ownership edit',()=>eq('other','/public/api/customer/addresses.php',{...draft,address_id:address.address_id},404));
  await test('HTTP address ownership delete',()=>eq('other','/public/api/customer/addresses.php',{action:'archive',address_id:address.address_id},404));
  await test('HTTP coordinate boundary',()=>eq('customer','/public/api/customer/addresses.php',{...draft,longitude:181},422));
  await test('HTTP method rejected',()=>eq('customer','/public/api/customer/addresses.php',null,405,'DELETE'));
  await test('cross-origin mutation rejected',()=>eq('customer','/public/api/customer/addresses.php',draft,403,'POST',{Origin:'https://attacker.invalid'}));
  await test('order own detail and history',async()=>{const {data}=await eq('customer',`/public/api/customer/orders.php?order_id=${fixtures.order_id}`,null,200);assert.equal(data.status,'CANCELLED');assert.ok(Array.isArray(data.history));});
  await test('order IDOR hidden',()=>eq('other',`/public/api/customer/orders.php?order_id=${fixtures.order_id}`,null,404));
  await test('array ID validation',()=>eq('customer','/public/api/customer/orders.php?order_id[]=1',null,422));
  await test('repeat cancel is idempotent',()=>eq('customer','/public/api/customer/orders.php',{action:'cancel',order_id:fixtures.order_id,reason:'Test retry'},200));
  await test('real quote fails closed without approved policy',async()=>{const d=await eq('customer','/public/api/customer/quote.php',{total:1},409);assert.equal(d.code,'BLOCKED_BY_BUSINESS_DECISION');});
  await test('real create rejects browser price',()=>eq('customer','/public/api/customer/create-order.php',{total_charge_minor:1},409));
  await test('customer categories actual catalog',async()=>{const {data}=await eq('customer','/public/api/customer/categories.php',null,200);assert.ok(data.some(x=>Number(x.category_id)===fixtures.category_id));});
  await test('admin lookup customer',async()=>{const {data}=await eq('admin','/public/api/admin/accounts.php?role=CUSTOMER&q=Updated',null,200);assert.ok(data.some(x=>Number(x.user_id)===fixtures.users.customer.id));});
  await test('admin lookup shipper',async()=>{const {data}=await eq('admin','/public/api/admin/accounts.php?role=SHIPPER',null,200);assert.ok(data.some(x=>Number(x.user_id)===fixtures.users.shipper.id));});
  await test('admin order detail',()=>eq('admin',`/public/api/admin/orders.php?order_id=${fixtures.order_id}`,null,200));
  await test('admin order list',()=>eq('admin','/public/api/admin/orders.php',null,200));
  await test('admin category restriction persists',async()=>{await eq('admin','/public/api/admin/categories.php',{category_id:fixtures.category_id,restriction_type:'RESTRICTED',active:true},200);const {data}=await eq('admin','/public/api/admin/categories.php',null,200);assert.equal(data.find(x=>Number(x.category_id)===fixtures.category_id).restriction_type,'RESTRICTED');});
  await test('admin creates inert pricing draft',async()=>{const data=await eq('admin','/public/api/admin/pricing.php',{currency_code:'USD',configuration:{return_fee_minor:123,successful_delivery_percentage:10}},201);assert.equal(data.activation,'BLOCKED_BY_BUSINESS_DECISION');});
  await test('admin invalid minor price rejected',()=>eq('admin','/public/api/admin/pricing.php',{currency_code:'USD',configuration:{return_fee_minor:1.1}},422));
  await test('all policy draft sections persist without activation',async()=>{
    const configuration={distance_bands:[{min_km:0,max_km:1,amount_minor:100}],time_bands:[{day_of_week:null,starts_at_local:'22:00',ends_at_local:'06:00',multiplier:1.1}],weather_bands:[{provider_condition_code:'TEST_ONLY',multiplier:1.2}],cancellation_rules:[{after_status:'SHIPPER_ASSIGNED',fee_minor:5}],return_fee_minor:6,successful_delivery_percentage:7,failed_return_fixed_minor:8,score_policy:{deduction_points:1,lock_threshold:2,recovery_points:3}};
    const d=await eq('admin','/public/api/admin/pricing.php',{currency_code:'VND',configuration},201);
    assert.deepEqual(JSON.parse(d.data.configuration),configuration);assert.equal(d.activation,'BLOCKED_BY_BUSINESS_DECISION');
  });
  await test('invalid pricing time rejected',()=>eq('admin','/public/api/admin/pricing.php',{currency_code:'VND',configuration:{time_bands:[{day_of_week:null,starts_at_local:'25:00',ends_at_local:'06:00',multiplier:1}]}},422));
  await test('invalid Shipper percentage rejected',()=>eq('admin','/public/api/admin/pricing.php',{currency_code:'USD',configuration:{successful_delivery_percentage:101}},422));
  let nationality;
  await test('nationality request persists',async()=>{const {data}=await eq('customer','/public/api/auth/nationality-request.php',{requested_country_code:'US',reason:'Isolated test request'},201);nationality=data.request_id;});
  await test('duplicate pending nationality conflict',()=>eq('customer','/public/api/auth/nationality-request.php',{requested_country_code:'GB',reason:'Test'},409));
  await test('nationality unknown country validation',()=>eq('other','/public/api/auth/nationality-request.php',{requested_country_code:'ZZ',reason:'Test'},422));
  await test('nationality approval blocked by identity policy',()=>eq('admin','/public/api/admin/nationality-requests.php',{request_id:nationality,decision:'APPROVED',note:'Test'},409));
  await test('nationality admin rejection persists',()=>eq('admin','/public/api/admin/nationality-requests.php',{request_id:nationality,decision:'REJECTED',note:'Test'},200));
  await test('KYC NEEDS_INFO supported',()=>eq('admin','/public/api/admin/review-shipper.php',{shipper_id:fixtures.users.shipper.id,decision:'NEEDS_INFO',note:'Test additional evidence'},200));
  await test('KYC rejection persisted',()=>eq('admin','/public/api/admin/review-shipper.php',{shipper_id:fixtures.users.shipper_reject.id,decision:'REJECTED',note:'Test rejected evidence'},200));
  await test('KYC approve from needs info',()=>eq('admin','/public/api/admin/review-shipper.php',{shipper_id:fixtures.users.shipper.id,decision:'APPROVED',note:''},200));
  await test('KYC stale review returns 409',()=>eq('admin','/public/api/admin/review-shipper.php',{shipper_id:fixtures.users.shipper.id,decision:'REJECTED',note:'Test'},409));
  await test('Shipper can add Customer workspace without losing Shipper role',async()=>{
    await eq('shipper','/public/api/auth/switch-workspace.php',{target:'CUSTOMER'},200);
    const {data}=await eq('shipper','/public/api/auth/profile.php',null,200);
    assert.ok(data.roles.includes('SHIPPER')&&data.roles.includes('CUSTOMER'));
  });
  await test('admin suspend account',()=>eq('admin','/public/api/admin/accounts.php',{user_id:fixtures.users.other.id,account_status:'SUSPENDED',reason:'Test'},200));
  await test('suspended session revoked on next request',()=>eq('other','/public/api/auth/profile.php',null,401));
  await test('admin restore account',()=>eq('admin','/public/api/admin/accounts.php',{user_id:fixtures.users.other.id,account_status:'ACTIVE',reason:'Test restore'},200));
  await test('suspend then restore revokes even unvisited old sessions',async()=>{
    await eq('other','/public/api/auth/login.php',{identifier:fixtures.users.other.email,password:fixtures.password,role:'CUSTOMER'},200);
    await eq('admin','/public/api/admin/accounts.php',{user_id:fixtures.users.other.id,account_status:'SUSPENDED',reason:'Test revoke'},200);
    await eq('admin','/public/api/admin/accounts.php',{user_id:fixtures.users.other.id,account_status:'ACTIVE',reason:'Test restore'},200);
    await eq('other','/public/api/auth/profile.php',null,401);
  });
  await test('customer live workspace renders',async()=>{const r=await request('customer','/?page=customer&view=backend');assert.equal(r.status,200);assert.ok(r.value.includes('data-person1-workspace'));assert.ok(!r.value.includes('data-demo-toggle'));});
  await test('admin live workspace renders',async()=>{const r=await request('admin','/?page=admin&view=backend');assert.equal(r.status,200);assert.ok(r.value.includes('data-person1-workspace'));});
  await test('self suspension rejected',()=>eq('admin','/public/api/admin/accounts.php',{user_id:fixtures.users.admin.id,account_status:'SUSPENDED',reason:'Test'},409));
  await test('HTTP address archive',()=>eq('customer','/public/api/customer/addresses.php',{action:'archive',address_id:address.address_id},200));
  await test('logout invalidates session',async()=>{await eq('customer','/public/api/auth/logout.php',{},200);await eq('customer','/public/api/auth/profile.php',null,401);});
  fs.writeFileSync(__dirname+'/../docs/person1-http-results.json',JSON.stringify(results,null,2));
  const failures=results.filter(x=>x.result==='FAIL');console.log(JSON.stringify({checks:results.length,failures:failures.length,failed:failures}));if(failures.length)process.exitCode=1;
})().catch(e=>{console.error(e.message);process.exitCode=1;});
