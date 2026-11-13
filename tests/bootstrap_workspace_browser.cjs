const {chromium} = require('playwright');
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const php = spawn(process.env.SOMI_TEST_PHP || 'C:/xampp/php/php.exe',
  ['-S', '127.0.0.1:3087', 'tests/bootstrap_render_fixture.php'], {stdio: 'ignore', windowsHide: true});
const base = 'http://127.0.0.1:3087';
(async () => {
  let browser;
  try {
    for (let i=0; i<40; i++) {
      try { if ((await fetch(base)).ok) break; } catch {}
      await new Promise(r => setTimeout(r, 100));
    }
    const executablePath = ['C:/Program Files/Google/Chrome/Application/chrome.exe',
      'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
    browser = await chromium.launch({headless:true, executablePath});
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', route => {
      const url = route.request().url();
      if (!url.startsWith(base)) return route.abort();
      if (url.includes('/public/api/')) return route.fulfill({json:{items:[],totals:[],profile:{},eligibility:{blockers:[]},delivery:null,availability:false,can_work:true,blockers:[]}});
      return route.continue();
    });
    fs.mkdirSync('tests/.runtime/bootstrap', {recursive:true});
    let checks = 0;
    for (const width of [1440, 390]) {
      await page.setViewportSize({width, height:1000});
      await page.goto(base, {waitUntil:'networkidle'});
      assert.equal(await page.locator('link[href*="bootstrap.min.css"]').count(), 1, (await page.content()).slice(0,1200)); checks++;
      const cards = await page.locator('.feature-grid > div').evaluateAll(nodes => nodes.map(n => ({x:n.offsetLeft,y:n.offsetTop,w:n.offsetWidth})));
      assert.equal(cards.length, 3);
      assert.equal(cards[0].y === cards[1].y, width > 768); checks++;
      await page.locator('[data-legal="terms"]').click();
      assert.ok(await page.locator('[data-legal-modal] .somi-modal').isVisible()); checks++;
      await page.locator('[data-legal-modal] [data-modal-close]').last().click();
      assert.ok(!(await page.locator('[data-legal-modal]').isVisible())); checks++;
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); checks++;
      await page.evaluate(() => scrollTo(0, 0));
      await page.screenshot({path:`tests/.runtime/bootstrap/home-${width}.png`,fullPage:true});
      await page.goto(base+'/__shipper-test', {waitUntil:'networkidle'});
      const metrics = await page.locator('.cards.row > div').evaluateAll(nodes => nodes.map(n => ({x:n.offsetLeft,y:n.offsetTop,w:n.offsetWidth})));
      assert.equal(metrics.length, 4);
      assert.equal(metrics[0].y === metrics[3].y, width > 1200); checks++;
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); checks++;
      await page.evaluate(() => document.querySelector('#contactModal').classList.remove('hidden'));
      assert.ok(await page.locator('#contactModal .somi-modal').isVisible()); checks++;
      await page.evaluate(() => document.querySelector('#contactModal').classList.add('hidden'));
      assert.equal(await page.locator('.toast').count(), 0, 'No API fixture errors'); checks++;
      await page.screenshot({path:`tests/.runtime/bootstrap/shipper-${width}.png`,fullPage:true});
    }
    assert.deepEqual(errors, []); checks++;
    console.log(`Bootstrap workspaces: ${checks} checks passed.`);
  } finally { await browser?.close(); php.kill(); }
})().catch(e => { console.error(e); process.exitCode=1; });
