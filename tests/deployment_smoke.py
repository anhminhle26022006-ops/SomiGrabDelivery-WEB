"""Read-only local smoke checks; no login, application writes or vendor calls."""
import json
import urllib.error
import urllib.request

BASE = 'http://127.0.0.1:3047/'
results = []

def check(path, expected, method='GET', body=None, headers=None):
    req = urllib.request.Request(BASE + path, method=method, data=body, headers=headers or {})
    try:
        response = urllib.request.urlopen(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    content = response.read()
    ok = response.code == expected
    if path.startswith('public/api/'):
        try:
            json.loads(content)
        except ValueError:
            ok = False
    results.append({'path': path, 'expected': expected, 'actual': response.code, 'result': 'PASS' if ok else 'FAIL'})

for page in ['home', 'how', 'customers', 'shippers', 'pricing', 'auth', 'reset-password']:
    check('?page=' + page, 200)
for path in ['.env', '.env.example', 'app/Config/database.php', 'tests/person1_backend.php', 'database/SOMI_database.sql', 'docker/php-fpm-pool.conf', 'scripts/test-person1.ps1']:
    check(path, 404)
for path in ['account/profile.php', 'account/nationality-request.php', 'customer/addresses.php', 'customer/orders.php', 'customer/parcel-categories.php', 'customer/payments.php?order_id=1', 'customer/refunds.php?order_id=1', 'customer/checkout.php?order_id=1', 'admin/accounts.php', 'admin/policies.php', 'admin/jobs.php', 'admin/orders.php', 'admin/payments.php', 'admin/refunds.php', 'admin/audit-logs.php']:
    check('public/api/' + path, 401)
for path in ['auth/login.php', 'auth/register.php', 'customer/quote.php', 'customer/create-order.php', 'payments/callback.php']:
    check('public/api/' + path, 405)
for path in ['auth/login.php', 'auth/register.php', 'customer/payments.php']:
    check('public/api/' + path, 403, 'POST', b'{}', {'Content-Type':'application/json', 'Origin':'https://foreign.invalid'})
print(json.dumps({'checks': len(results), 'failures':[r for r in results if r['result']=='FAIL'], 'business_writes':0, 'vendor_calls':0}, indent=2))
raise SystemExit(any(r['result']=='FAIL' for r in results))
