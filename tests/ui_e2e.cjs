/*
 * End-to-end UI test cases for every public page and every workspace view.
 * Runs against the isolated test server (scripts/test-person1.ps1 -ServeOnly, port 3034)
 * with fixtures from tests/person1_seed.php. Launches headless Chrome/Edge itself.
 *
 *   node tests/ui_e2e.cjs            # all widths (1440, 768, 375)
 *   SOMI_UI_BASE=http://127.0.0.1:3034 SOMI_UI_WIDTHS=1440 node tests/ui_e2e.cjs
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const cp = require('child_process');

const base = (process.env.SOMI_UI_BASE || 'http://127.0.0.1:3034').replace(/\/$/, '');
const widths = (process.env.SOMI_UI_WIDTHS || '1440,768,375').split(',').map(Number);
const cookieName = process.env.SOMI_UI_COOKIE || 'SOMI_PERSON1_TEST';
const cdpPort = Number(process.env.SOMI_UI_CDP_PORT || 9340);
const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, '.runtime/person1-fixtures.json'), 'utf8'));
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browserPath = [
  process.env.SOMI_UI_BROWSER,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
].find(p => p && fs.existsSync(p));
if (!browserPath) { console.error('No Chrome/Edge found; set SOMI_UI_BROWSER.'); process.exit(2); }

const views = {
  customer: ['dashboard', 'create', 'orders', 'order', 'payment', 'matching', 'tracking', 'review', 'report', 'reports', 'notifications', 'account', 'addresses', 'backend'],
  shipper: ['dashboard', 'offers', 'active', 'history', 'earnings', 'account', 'notifications'],
  admin: ['index', 'users', 'customers', 'shippers', 'orders', 'complaints', 'disputes', 'payments', 'reviews', 'notifications', 'audit-logs', 'backend'],
};
const publicPages = ['home', 'how', 'customers', 'shippers', 'pricing', 'auth', 'reset-password'];

async function login(kind) {
  const role = kind === 'other' ? 'CUSTOMER' : kind.toUpperCase();
  const response = await fetch(base + '/public/api/auth/login.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ identifier: fixtures.users[kind].email, password: fixtures.password, role }),
  });
  // login regenerates the session id, so the last Set-Cookie is the live one.
  const cookie = (response.headers.getSetCookie().filter(c => c.startsWith(cookieName + '=')).at(-1) || '').split(';')[0];
  if (response.status !== 200 || !cookie.startsWith(cookieName + '=')) throw Error(`Login ${kind} failed: ${response.status}`);
  return cookie.slice(cookieName.length + 1);
}

(async () => {
  const sessions = {};
  for (const kind of ['customer', 'shipper', 'admin']) sessions[kind] = await login(kind);

  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'somi-ui-'));
  const browser = cp.spawn(browserPath, ['--headless=new', `--remote-debugging-port=${cdpPort}`, `--user-data-dir=${profile}`,
    '--no-first-run', '--no-default-browser-check', '--disable-extensions', 'about:blank'], { stdio: 'ignore' });
  let targets;
  for (let i = 0; i < 80 && !targets; i++) { try { targets = await (await fetch(`http://127.0.0.1:${cdpPort}/json`)).json(); } catch { await sleep(250); } }
  if (!targets) throw Error('Browser CDP did not start');
  const ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(r => (ws.onopen = r));

  let id = 0, current = '', loadWaiter = null, loginRequests = 0;
  const pending = new Map(), problems = [];
  ws.onmessage = e => {
    const m = JSON.parse(e.data);
    if (m.id) { pending.get(m.id)?.(m); pending.delete(m.id); return; }
    const p = m.params || {};
    if (m.method === 'Page.loadEventFired' && loadWaiter) { loadWaiter(); loadWaiter = null; }
    if (m.method === 'Runtime.exceptionThrown') problems.push({ page: current, kind: 'exception', text: p.exceptionDetails.exception?.description?.split('\n')[0] || p.exceptionDetails.text });
    if (m.method === 'Runtime.consoleAPICalled' && p.type === 'error') problems.push({ page: current, kind: 'console.error', text: p.args.map(a => a.value ?? a.description).join(' ').slice(0, 200) });
    if (m.method === 'Network.requestWillBeSent' && p.request.url.includes('/auth/login.php')) loginRequests++;
    if (m.method === 'Network.responseReceived' && p.response.status >= 400 && p.response.url.startsWith(base)) problems.push({ page: current, kind: 'http', status: p.response.status, url: p.response.url.split('?')[0] });
    if (m.method === 'Network.loadingFailed' && !p.canceled) problems.push({ page: current, kind: 'network', text: p.errorText, type: p.type });
  };
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const n = ++id; const timer = setTimeout(() => { pending.delete(n); reject(Error(method + ' timeout')); }, 30000);
    pending.set(n, m => { clearTimeout(timer); m.error ? reject(Error(JSON.stringify(m.error))) : resolve(m.result); });
    ws.send(JSON.stringify({ id: n, method, params }));
  });
  const evaluate = async expression => (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;
  async function load(query) {
    current = query;
    const loaded = new Promise(r => (loadWaiter = r));
    const started = Date.now();
    const nav = await send('Page.navigate', { url: base + '/' + query });
    if (nav.errorText) throw Error(`${query}: ${nav.errorText}`);
    await Promise.race([loaded, sleep(15000)]);
    const elapsed = Date.now() - started;
    await sleep(250); // let deferred scripts and DOMContentLoaded handlers settle
    return elapsed;
  }
  const setViewport = width => send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
  const setSession = role => role
    ? send('Network.setCookie', { name: cookieName, value: sessions[role], url: base + '/' })
    : send('Network.deleteCookies', { name: cookieName, url: base + '/' });

  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  const results = [];
  const record = (test, ok, extra = {}) => results.push({ test, ...extra, result: ok === null ? 'BLOCKED' : ok ? 'PASS' : 'FAIL' });

  async function checkPage(query, expectedPage, width, label) {
    const before = problems.length;
    const ms = await load(query);
    const m = JSON.parse(await evaluate(`JSON.stringify({page:document.body?.dataset.page,scroll:document.documentElement.scrollWidth,
      title:document.title,main:!!document.querySelector('main,section,[class*="layout"],form'),
      broken:[...document.images].filter(i=>i.complete&&i.naturalWidth===0&&i.src&&!i.src.startsWith('data:')).map(i=>i.src)})`));
    const pageProblems = problems.slice(before).filter(p => p.page === query);
    record('renders expected page', m.page === expectedPage, { page: label, width, actual: m.page });
    record('no horizontal overflow', m.scroll <= width + 1, { page: label, width, scrollWidth: m.scroll });
    record('no JS errors / failed requests', pageProblems.length === 0, { page: label, width, problems: pageProblems.length ? pageProblems : undefined });
    record('no broken images', m.broken.length === 0, { page: label, width, broken: m.broken.length ? m.broken : undefined });
    record('loads under 3s', ms < 3000, { page: label, width, ms });
  }

  for (const width of widths) {
    await setViewport(width);
    await setSession(null);
    for (const page of publicPages) await checkPage('?page=' + page, page, width, page);
    for (const [role, list] of Object.entries(views)) {
      await setSession(role);
      for (const view of list) await checkPage(`?page=${role}&view=${view}`, role, width, `${role}/${view}`);
    }
  }

  // Access control in the browser: anonymous visitor is redirected to sign-in.
  await setViewport(1440); await setSession(null);
  for (const role of Object.keys(views)) {
    await load('?page=' + role);
    const href = await evaluate('location.search');
    record('anonymous workspace redirects to auth', href.includes('page=auth'), { page: role, href });
  }
  // A customer session cannot open the admin workspace.
  await setSession('customer');
  await load('?page=admin');
  record('customer cannot open admin workspace', (await evaluate('document.body.dataset.page')) !== 'admin', { page: 'admin' });

  // Interactions.
  await load('?page=customer');
  record('customer avatar menu opens', await evaluate(`(()=>{const b=document.querySelector('[data-c-avatar]');if(!b)return null;b.click();return document.querySelector('[data-c-menu]').classList.contains('show')})()`));
  await setSession('shipper');
  await load('?page=shipper');
  record('shipper avatar menu opens', await evaluate(`(()=>{const b=document.querySelector('#profileBtn');if(!b)return null;b.click();return document.querySelector('#profileDropdown').classList.contains('open')})()`));
  const navigated = await evaluate(`(()=>{const a=document.querySelector('#shipperNav [data-page="offers"]');if(!a)return false;a.click();return true})()`);
  for (let i = 0; navigated && i < 60 && !(await evaluate(`!!document.querySelector('#allOrders button[onclick^="openOrderDetail"]')`)); i++) await sleep(100);
  record('shipper tab bar navigates to offers', navigated && (await evaluate('new URLSearchParams(location.search).get("view")')) === 'offers');
  record('shipper offer detail modal opens', await evaluate(`(()=>{const b=document.querySelector('#allOrders button[onclick^="openOrderDetail"]');if(!b)return null;b.click();return !document.querySelector('#orderDetailModal').classList.contains('hidden')})()`));
  await setSession('admin');
  await load('?page=admin&view=users');
  record('admin filter tab activates', await evaluate(`(()=>{const b=document.querySelector('.filter-tabs .tab-btn:nth-child(2)');if(!b)return null;b.click();return b.classList.contains('active')})()`));
  record('admin search shows empty state', await evaluate(`(()=>{const input=document.querySelector('.search-box input');if(!input)return null;input.value='nonexistent-ui-search';input.dispatchEvent(new Event('input',{bubbles:true}));return [...document.querySelectorAll('.users-table-card tbody tr[data-status]')].every(r=>r.hidden)&&!document.querySelector('#admin-empty-state').hidden})()`));
  record('admin user modal opens and closes on Escape', await evaluate(`(()=>{const b=document.querySelector('.action-link-btn');if(!b)return null;b.click();const opened=document.querySelector('#userModal').classList.contains('show');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));return opened&&!document.querySelector('#userModal').classList.contains('show')})()`));

  // Language picker: same flag + code on the homepage, Customer and Shipper; switching
  // repeatedly keeps icon, text and document language in sync; the choice survives a reload.
  const pickerState = `(()=>{const t=document.querySelector('[data-language-trigger],[data-locale-btn]');const fill=t?.querySelector('svg rect')?.getAttribute('fill');
    const m=document.querySelector('[data-language-menu],[data-locale-menu]');
    return {flag:fill==='#da251d'?'vi':fill==='#17356b'?'en':'none',code:(t?.querySelector('[data-language-current],[data-locale-current]')?.textContent||'').trim().slice(0,2).toLowerCase(),
      doc:document.documentElement.lang,menuOpen:!!m&&!m.hidden&&getComputedStyle(m).display!=='none',chevron:!!t?.querySelector('.language-chevron')}})()`;
  const waitPicker = async lang => { let s; for (let i = 0; i < 60; i++) { try { s = await evaluate(pickerState); if (s && s.flag === lang && s.code === lang && s.doc === lang) break; } catch { /* page reloading */ } await sleep(100); } return s; };
  await setViewport(1440);
  for (const [role, url, name] of [[null, '?page=home', 'home'], ['customer', '?page=customer&view=dashboard', 'customer'], ['shipper', '?page=shipper&view=dashboard', 'shipper']]) {
    await setSession(role);
    await load(url);
    for (const lang of ['vi', 'en', 'vi', 'en', 'vi']) {
      // The Customer dropdown needs Bootstrap (deferred CDN script) after each reload.
      for (let i = 0; role === 'customer' && i < 50 && !(await evaluate('!!window.bootstrap && document.readyState === "complete"').catch(() => false)); i++) await sleep(100);
      // Customer's menu also holds currency and stays open when the current language is picked again.
      const opened = await evaluate(`(()=>{const m0=document.querySelector('[data-language-menu],[data-locale-menu]');const isOpen=!m0.hidden&&getComputedStyle(m0).display!=='none';if(!isOpen)document.querySelector('[data-language-trigger],[data-locale-btn]').click();const m=document.querySelector('[data-language-menu],[data-locale-menu]');return !m.hidden&&getComputedStyle(m).display!=='none'})()`);
      // Customer reloads after a language change so prices redraw; the others update in place.
      await evaluate(`document.querySelector(${JSON.stringify(role === 'customer' ? `[data-locale-menu] [data-set-lang="${lang}"]` : `[data-language-option="${lang}"]`)}).click()`);
      await sleep(role === 'customer' ? 600 : 100);
      const s = await waitPicker(lang);
      record('language picker shows selected flag and code', opened && s.flag === lang && s.code === lang && s.doc === lang && s.chevron, { page: name, lang, ...s, opened });
      if (role !== 'customer') record('language menu closes after choosing', !s.menuOpen, { page: name, lang });
    }
    await load(url);
    const reloaded = await waitPicker('vi');
    record('language and flag persist after reload', reloaded.flag === 'vi' && reloaded.code === 'vi', { page: name, ...reloaded });
    if (role !== 'customer') record('Escape closes the language menu', await evaluate(`(()=>{const t=document.querySelector('[data-language-trigger]');t.click();const m=document.querySelector('[data-language-menu]');const open=!m.hidden;document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));return open&&m.hidden})()`), { page: name });
  }

  // Shipper amounts: one VND format per language, same as Customer ("42.000 ₫" / "₫42,000"), never NaN.
  await setSession('shipper');
  for (const lang of ['vi', 'en']) {
    for (const view of ['dashboard', 'offers', 'active', 'history', 'earnings', 'notifications']) {
      await load(`?page=shipper&view=${view}`);
      await evaluate(`localStorage.setItem('somi-language','${lang}')`);
      await load(`?page=shipper&view=${view}`);
      await sleep(150);
      const m = JSON.parse(await evaluate(`(()=>{const t=document.querySelector('main').innerText;const all=(t.match(/₫/g)||[]).length;
        const ok=lang=>(t.match(lang==='vi'?/\\d{1,3}(?:\\.\\d{3})*\\s₫/g:/₫\\d{1,3}(?:,\\d{3})*(?![\\d.,])/g)||[]).length;
        return JSON.stringify({symbols:all,formatted:ok('${lang}'),nan:/NaN|undefined/.test(t),sample:(t.match(/[\\d.,]+\\s?₫|₫[\\d.,]+/)||[''])[0]})})()`));
      record('shipper amounts use one currency format', m.symbols > 0 && m.formatted === m.symbols && !m.nan, { page: `shipper/${view}`, lang, ...m });
    }
  }
  await evaluate(`localStorage.setItem('somi-language','vi')`);

  // Shipper tab bar: each tab becomes active and shows its page without a reload.
  await load('?page=shipper&view=dashboard');
  for (const tab of ['offers', 'active', 'history', 'earnings', 'dashboard']) {
    await evaluate(`document.querySelector('#shipperNav [data-page="${tab}"]').click()`);
    await sleep(150);
    const st = JSON.parse(await evaluate(`JSON.stringify({active:[...document.querySelectorAll('#shipperNav a.active')].map(a=>a.dataset.page),visible:!document.getElementById('page-${tab}')?.hidden,view:new URLSearchParams(location.search).get('view')})`));
    record('shipper tab switches page and active state', st.active.length === 1 && st.active[0] === tab && st.visible && st.view === tab, { tab, ...st });
  }
  // Each Shipper view renders one page, so the bell may load the notifications view.
  await evaluate(`document.getElementById('bellBtn').click()`);
  let bellOk = false; for (let i = 0; i < 60 && !bellOk; i++) { await sleep(100); bellOk = await evaluate(`new URLSearchParams(location.search).get('view')==='notifications'&&!!document.getElementById('page-notifications')&&!document.getElementById('page-notifications').hidden`).catch(() => false); }
  record('shipper bell opens notifications', bellOk);
  await load('?page=shipper&view=dashboard');
  record('shipper availability toggle works', await evaluate(`(()=>{const t=document.getElementById('availabilityToggle');const before=t.classList.contains('on');t.click();const changed=t.classList.contains('on')!==before;t.click();return changed&&t.classList.contains('on')===before&&getComputedStyle(t).display!=='none'})()`));
  await setViewport(375);
  await load('?page=shipper&view=dashboard');
  record('shipper availability visible on mobile', await evaluate(`(()=>{const r=document.getElementById('availabilityToggle').getBoundingClientRect();return r.width>0&&r.height>0})()`));
  record('shipper header fits one row on mobile', await evaluate(`(()=>{const b=document.querySelector('.topbar .brand').getBoundingClientRect(),a=document.querySelector('.top-actions').getBoundingClientRect();return Math.abs(b.top+b.height/2-(a.top+a.height/2))<12&&a.right<=innerWidth})()`));
  await setViewport(1440);
  await setSession('customer');
  await load('?page=customer&view=dashboard');
  record('customer bell opens notifications', await evaluate(`(()=>{const a=document.querySelector('.c-bell');return !!a&&new URL(a.href).searchParams.get('view')==='notifications'})()`));

  // Customer ↔ Shipper switch keeps role and session (runs last: it changes this session's workspace).
  await setSession('shipper');
  await load('?page=shipper&view=dashboard');
  await evaluate(`document.querySelector('[data-workspace-switch="CUSTOMER"]').click()`);
  let switched = ''; for (let i = 0; i < 80 && switched !== 'customer'; i++) { await sleep(100); try { switched = await evaluate('document.body?.dataset.page'); } catch { /* navigating */ } }
  record('Shipper → Customer workspace switch', switched === 'customer', { landed: switched });
  if (switched === 'customer') {
    await evaluate(`document.querySelector('[data-workspace-switch="SHIPPER"]').click()`);
    let back = ''; for (let i = 0; i < 80 && back !== 'shipper'; i++) { await sleep(100); try { back = await evaluate('document.body?.dataset.page'); } catch { /* navigating */ } }
    record('Customer → Shipper workspace switch', back === 'shipper', { landed: back });
  }

  // Sign-in form: busy feedback, double-submit guard, error recovery.
  await setSession(null);
  await load('?page=auth');
  const beforeLogin = loginRequests;
  record('login shows busy state immediately', await evaluate(`(()=>{const f=document.querySelector('[data-auth-form]');if(!f)return null;f.querySelector('[name="identifier"]').value='ui-nonexistent@example.invalid';f.querySelector('[name="password"]').value='InvalidTestPassword123';for(let i=0;i<3;i++)f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));return f.getAttribute('aria-busy')==='true'})()`));
  for (let i = 0; i < 60 && (await evaluate(`document.querySelector('[data-auth-form]').getAttribute('aria-busy')==='true'`)); i++) await sleep(100);
  record('triple submit sends one login request', loginRequests - beforeLogin === 1, { requests: loginRequests - beforeLogin });
  record('invalid login shows error and re-enables', await evaluate(`!document.querySelector('[data-auth-submit]').disabled && !document.querySelector('[data-form-error]').hidden`));

  // Real sign-in through the form lands in the right workspace.
  await load('?page=auth&next=customer');
  await evaluate(`(()=>{const f=document.querySelector('[data-auth-form]');f.querySelector('[name="identifier"]').value=${JSON.stringify(fixtures.users.customer.email)};f.querySelector('[name="password"]').value=${JSON.stringify(fixtures.password)};const r=f.querySelector('[name="role"][value="customer"],[name="role"][value="CUSTOMER"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}))}f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))})()`);
  let landed = '';
  for (let i = 0; i < 100 && landed !== 'customer'; i++) { await sleep(100); try { landed = await evaluate('document.body?.dataset.page'); } catch { /* navigating */ } }
  record('form login reaches customer workspace', landed === 'customer', { landed });

  await send('Browser.close').catch(() => {});
  browser.kill();
  try { fs.rmSync(profile, { recursive: true, force: true }); } catch { /* browser may still hold files */ }

  const failures = results.filter(r => r.result === 'FAIL');
  const loads = results.filter(r => r.ms !== undefined).map(r => r.ms).sort((a, b) => a - b);
  const summary = {
    base, widths, checks: results.length, passed: results.filter(r => r.result === 'PASS').length,
    failed: failures.length, blocked: results.filter(r => r.result === 'BLOCKED').length,
    page_load_ms: { p50: loads[Math.floor(loads.length / 2)], p95: loads[Math.floor(loads.length * 0.95)], max: loads.at(-1) },
  };
  fs.mkdirSync(path.join(__dirname, '.runtime'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, '.runtime/ui-e2e-results.json'), JSON.stringify({ summary, results }, null, 2));
  console.log(JSON.stringify({ ...summary, failures }, null, 1));
  if (failures.length) process.exitCode = 1;
})().catch(e => { console.error(e.stack || e.message); process.exit(1); });
