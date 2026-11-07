"""Read-only HTTP checks. Generate local sessions with reaudit_sessions.php first.
No login bypass is shipped to the application; fixtures stay in ignored .runtime.
"""
import json
import urllib.error
import urllib.request
from pathlib import Path

BASE = 'http://127.0.0.1:3047/public/api/'
fixtures = json.loads(Path('tests/.runtime/p1-sessions/fixtures.json').read_text())
checks = 0

def request(path, expected, role=None, method='GET', origin=None):
    global checks
    headers = {}
    if role:
        headers['Cookie'] = 'PHPSESSID=' + fixtures[role]
    if origin:
        headers['Origin'] = origin
    if method == 'POST':
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + path, data=b'{}' if method == 'POST' else None,
                                 headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=45)
        status, body = response.status, response.read()
    except urllib.error.HTTPError as error:
        status, body = error.code, error.read()
    assert status == expected, (path, status, expected)
    if body:
        json.loads(body)
    checks += 1
    return json.loads(body) if body else None

for endpoint in ['account/profile.php', 'account/nationality-request.php',
                 'customer/payments.php?order_id=1', 'customer/refunds.php?order_id=1',
                 'customer/checkout.php?order_id=1', 'admin/accounts.php', 'admin/orders.php',
                 'admin/policies.php', 'admin/payments.php', 'admin/refunds.php',
                 'admin/email-logs.php', 'admin/audit-logs.php', 'admin/jobs.php',
                 'admin/nationality-requests.php', 'admin/parcel-categories.php']:
    request(endpoint, 401)
for endpoint in ['admin/accounts.php', 'admin/orders.php', 'admin/policies.php',
                 'admin/payments.php', 'admin/email-logs.php']:
    request(endpoint, 403, 'customer')
request('account/profile.php', 200, 'customer')
request('customer/orders.php', 200, 'customer')
request('customer/orders.php?id=999999999999999999', 404, 'customer')
request('admin/jobs.php', 200, 'admin')
request('admin/email-logs.php', 200, 'admin')
policies = request('admin/policies.php', 200, 'admin')
if policies['pricing']:
    detail = request('admin/policies.php?id=' + str(policies['pricing'][0]['pricing_policy_id']), 200, 'admin')
    assert all(key in detail['policy'] for key in ['time_bands', 'weather_bands', 'cancellation_fees'])
request('customer/payments.php?order_id=1', 403, 'customer', 'POST', 'https://foreign.example')
request('account/profile.php', 405, 'customer', 'POST', 'http://127.0.0.1:3047')
# Earlier dev URLs remain compatible after the backend merge.
request('auth/profile.php', 401)
request('auth/nationality-request.php', 401)
request('customer/quote.php', 405)
request('customer/create-order.php', 405)
request('auth/profile.php', 200, 'customer')
request('auth/nationality-request.php', 200, 'customer')
request('customer/categories.php', 200, 'customer')
request('customer/addresses.php', 200, 'customer')
request('customer/orders.php?order_id=999999999999999999', 404, 'customer')
print(json.dumps({'result': 'PASS', 'checks': checks, 'business_writes': 0}))
