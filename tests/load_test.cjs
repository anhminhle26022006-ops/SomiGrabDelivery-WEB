/*
 * HTTP load test. Uses the isolated test fixtures (tests/person1_seed.php); never point it at production.
 *
 *   SOMI_LOAD_BASE=http://127.0.0.1:8081 SOMI_LOAD_USERS=50 SOMI_LOAD_SECONDS=10 node tests/load_test.cjs [label]
 *
 * Each virtual user signs in once (own session), then hammers each scenario for SOMI_LOAD_SECONDS.
 */
const fs = require('fs');
const path = require('path');

const base = (process.env.SOMI_LOAD_BASE || 'http://127.0.0.1:8081').replace(/\/$/, '');
const users = Number(process.env.SOMI_LOAD_USERS || 50);
const seconds = Number(process.env.SOMI_LOAD_SECONDS || 10);
const cookieName = process.env.SOMI_LOAD_COOKIE || 'PHPSESSID';
const label = process.argv[2] || 'run';
const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, '.runtime/person1-fixtures.json'), 'utf8'));

async function login() {
  const r = await fetch(base + '/public/api/auth/login.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ identifier: fixtures.users.customer.email, password: fixtures.password, role: 'CUSTOMER' }),
  });
  await r.text();
  // login regenerates the session id, so the last Set-Cookie is the live one.
  const cookie = (r.headers.getSetCookie().filter(c => c.startsWith(cookieName + '=')).at(-1) || '').split(';')[0];
  if (r.status !== 200 || !cookie.startsWith(cookieName + '=')) throw Error(`login failed ${r.status} ${cookie}`);
  return cookie;
}

async function hit(url, cookie, headers = {}) {
  const t = performance.now();
  const r = await fetch(base + url, { headers: { ...(cookie ? { Cookie: cookie } : {}), 'Accept-Encoding': 'gzip, br', ...headers }, redirect: 'manual' });
  const body = await r.arrayBuffer();
  return { ms: performance.now() - t, ok: r.status < 400, bytes: body.byteLength, status: r.status };
}

const pct = (sorted, p) => sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * p))] : 0;
async function run(name, cookies, request) {
  const lat = []; let errors = 0, bytes = 0; const statuses = {};
  const end = Date.now() + seconds * 1000;
  await Promise.all(cookies.map(async cookie => {
    while (Date.now() < end) {
      try {
        const r = await request(cookie);
        lat.push(r.ms); bytes += r.bytes; statuses[r.status] = (statuses[r.status] || 0) + 1; if (!r.ok) errors++;
      } catch (e) { errors++; const code = 'ERR_' + (e.cause?.code || e.name); statuses[code] = (statuses[code] || 0) + 1; }
    }
  }));
  lat.sort((a, b) => a - b);
  const out = { scenario: name, users: cookies.length, requests: lat.length, rps: +(lat.length / seconds).toFixed(1),
    p50_ms: +pct(lat, .5).toFixed(1), p95_ms: +pct(lat, .95).toFixed(1), p99_ms: +pct(lat, .99).toFixed(1),
    errors, avg_kb: +(bytes / Math.max(1, lat.length) / 1024).toFixed(1), statuses };
  console.log(JSON.stringify(out));
  return out;
}

(async () => {
  const cookies = [];
  for (let i = 0; i < users; i++) cookies.push(await login());
  const anon = Array(users).fill(null);
  const results = [];
  results.push(await run('static app.css', anon, () => hit('/public/assets/css/app.css')));
  results.push(await run('public home page', anon, () => hit('/?page=home')));
  results.push(await run('customer workspace page', cookies, c => hit('/?page=customer&view=dashboard', c)));
  results.push(await run('API profile (auth + 4 queries)', cookies, c => hit('/public/api/auth/profile.php', c)));
  // Dashboards fire several API calls at once with one session cookie.
  results.push(await run('4 parallel API calls per user', cookies, async c => {
    const t = performance.now();
    const all = await Promise.all(['/public/api/auth/profile.php', '/public/api/customer/addresses.php',
      '/public/api/customer/orders.php', '/public/api/customer/categories.php'].map(u => hit(u, c)));
    return { ms: performance.now() - t, ok: all.every(r => r.ok), bytes: all.reduce((s, r) => s + r.bytes, 0), status: all.find(r => !r.ok)?.status || 200 };
  }));
  const file = path.join(__dirname, '.runtime', `load-${label}.json`);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify({ base, users, seconds, label, results }, null, 2));
})().catch(e => { console.error(e.stack || e.message); process.exit(1); });
