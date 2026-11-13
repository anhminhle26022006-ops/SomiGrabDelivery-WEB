const {chromium} = require('playwright');
const {spawn} = require('node:child_process');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = 'http://127.0.0.1:3097';
const php = spawn(process.env.SOMI_TEST_PHP || 'C:/xampp/php/php.exe', ['-S', '127.0.0.1:3097', 'tests/bootstrap_render_fixture.php'], {stdio:'ignore', windowsHide:true});
(async () => {
  let browser;
  try {
    for (let i = 0; i < 40; i++) {
      try {
        await new Promise((resolve, reject) => require('node:http').get(base + '/__shipper-test', response => {response.resume(); response.on('end', resolve);}).on('error', reject));
        break;
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    const executablePath = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
    browser = await chromium.launch({headless:true, executablePath});
    const page = await browser.newPage({viewport:{width:390, height:844}});
    const errors = [], calls = [];
    let fail = false, active = true, name = 'Shipper API Test';
    page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => { localStorage.setItem('somi-language', 'vi'); });
    await page.route('**/public/api/**', async route => {
      const req = route.request(), url = new URL(req.url());
      calls.push({path:url.pathname, method:req.method(), body:req.postDataJSON()});
      const profile = {full_name:name, vehicle_type:'Xe máy', vehicle_registration:'59-API', verification_status:'APPROVED', customer_rating_average:'4.25', customer_rating_count:8};
      if (url.pathname.endsWith('/dashboard.php')) {
        if (fail) return route.fulfill({status:503, json:{error:'Fixture unavailable'}});
        const range = url.searchParams.get('range');
        if (range === 'week') await new Promise(r => setTimeout(r, 250));
        return route.fulfill({json:{range, timezone:'Asia/Ho_Chi_Minh', start:'2026-11-13T00:00:00+07:00', end:'2026-11-14T00:00:00+07:00', completed_deliveries:range === 'month' ? 90 : 12, delivery_seconds:5400}});
      }
      if (url.pathname.endsWith('/shipper/profile.php')) return route.fulfill({json:{profile}});
      if (url.pathname.endsWith('/account/profile.php')) {
        if (req.method() === 'PUT') name = req.postDataJSON().full_name;
        return route.fulfill({json:{profile:{full_name:name, user_id:77, phone:'0900000077', email:'fixture@example.invalid'}}});
      }
      if (url.pathname.endsWith('/kyc.php')) return route.fulfill({json:{can_resubmit:false}});
      if (url.pathname.endsWith('/availability.php')) return route.fulfill({json:{availability:false, can_work:true, blockers:[]}});
      if (url.pathname.endsWith('/deliveries.php')) return route.fulfill({json:{delivery:active ? {order_code:'API-77', status:'PICKED_UP', pickup_address:'<img src=x onerror=alert(1)>', delivery_address:'API destination', evidence:[], next_actions:[]} : null}});
      return route.fulfill({json:{items:[], totals:[]}});
    });
    await page.goto(base + '/__shipper-test');
    await page.waitForFunction(() => document.querySelector('[data-period-value="deliveries"]').textContent === '12');
    assert.equal(await page.locator('[data-period-value="hours"]').textContent(), '1 giờ 30 phút');
    await page.waitForFunction(() => document.querySelector('[data-p3-summary]').textContent.includes('Chưa có thu nhập'));
    assert.equal(await page.locator('#overviewNotices').textContent(), 'Chưa có thông báo.');
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('somi:language-change', {detail:{language:'en'}})));
    await page.waitForFunction(() => document.querySelector('[data-p3-summary]').textContent === 'No earnings recorded yet.');
    assert.equal(await page.locator('[data-period-value="hours"]').textContent(), '1h 30m');
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('somi:language-change', {detail:{language:'vi'}})));
    await page.waitForFunction(() => document.querySelector('[data-p3-summary]').textContent.includes('Chưa có thu nhập'));
    assert.equal(await page.locator('#overviewNotices').textContent(), 'Chưa có thông báo.');
    assert.equal(await page.locator('[data-period-value="hours"]').textContent(), '1 giờ 30 phút');
    assert.equal(await page.locator('[data-shipper-rating]').textContent(), '4.3 / 5');
    assert.ok((await page.locator('#overviewDelivery').textContent()).includes('API-77'));
    assert.equal(await page.locator('#overviewDelivery img').count(), 0, 'API address is escaped');
    assert.equal(await page.locator('#availabilityToggle').getAttribute('aria-pressed'), 'false');
    assert.equal(await page.locator('#shipperNav').isVisible(),false,'mobile navigation starts collapsed');
    await page.locator('.shipper-menu-toggle').click();
    assert.equal(await page.locator('#shipperNav').isVisible(),true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#shipperNav').isVisible(),false);
    await page.locator('.shipper-menu-toggle').click();
    await page.locator('#shipperNav [data-page="dashboard"]').click();
    assert.equal(await page.locator('.shipper-menu-toggle').getAttribute('aria-expanded'),'false');
    await page.setViewportSize({width:1440,height:900});
    assert.equal(await page.locator('#shipperNav').isVisible(),true,'desktop keeps its navigation');
    await page.setViewportSize({width:390,height:844});
    assert.equal(await page.locator('[data-shipper-name]').first().textContent(), name);
    await page.locator('[data-date-trigger]').click();
    await page.locator('[data-date-range="month"]').click();
    await page.waitForFunction(() => document.querySelector('[data-period-value="deliveries"]').textContent === '90');
    assert.equal(await page.evaluate(() => localStorage.getItem('somi-shipper-date-range')), 'month');
    await page.evaluate(() => {
      window.dispatchEvent(new CustomEvent('somi:shipper-period', {detail:{range:'week'}}));
      window.dispatchEvent(new CustomEvent('somi:shipper-period', {detail:{range:'month'}}));
    });
    await page.waitForTimeout(400);
    assert.equal(await page.locator('[data-period-value="deliveries"]').textContent(), '90', 'old response cannot overwrite selected month');
    fail = true;
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('somi:shipper-period', {detail:{range:'today'}})));
    await page.waitForFunction(() => document.querySelector('[data-dashboard-status]').textContent === 'Fixture unavailable');
    assert.equal(await page.locator('[data-period-value="deliveries"]').textContent(), '—');
    fail = false; active = false;
    await page.reload();
    await page.waitForFunction(() => document.getElementById('overviewDelivery').textContent.includes('chưa có đơn'));
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'mobile dashboard fits');
    await page.goto(base + '/__shipper-test?view=account');
    await page.waitForFunction(() => document.getElementById('shipperPhone').value === '0900000077');
    assert.equal(await page.locator('#shipperEmail').inputValue(), 'fixture@example.invalid');
    await page.locator('#shipperName').fill('Updated API Name');
    await page.locator('#saveProfileBtn').click();
    await page.waitForFunction(() => document.querySelector('[data-shipper-name]').textContent === 'Updated API Name');
    assert.ok(calls.some(c => c.path.endsWith('/account/profile.php') && c.method === 'PUT' && c.body.full_name === 'Updated API Name'));
    assert.deepEqual(errors, []);
    const unauthenticated = await page.request.get(base + '/public/api/shipper/dashboard.php');
    assert.equal(unauthenticated.status(), 401, 'dashboard requires a real authenticated session');
    console.log('PASS: Shipper dashboard/profile HTTP fixtures, date selection race, error/empty states, escaping and mobile layout.');
  } finally { await browser?.close(); php.kill(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
