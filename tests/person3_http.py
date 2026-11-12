"""Loopback-only HTTP guards. Never authenticates or accesses application data."""
import json
import os
import subprocess
import time
import urllib.error
import urllib.request

php = os.environ.get('SOMI_TEST_PHP', 'C:/xampp/php/php.exe')
base = 'http://127.0.0.1:3079'
server = subprocess.Popen([php, '-S', '127.0.0.1:3079', 'index.php'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
checks = 0

def request(path, expected, method='GET', origin=None):
    global checks
    headers = {'Content-Type': 'application/json'}
    if origin:
        headers['Origin'] = origin
    req = urllib.request.Request(base + '/public/api/' + path, method=method, headers=headers, data=b'{}' if method == 'POST' else None)
    try:
        response = urllib.request.urlopen(req, timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    assert response.status == expected, (path, method, response.status, expected)
    payload = json.loads(response.read())
    assert payload.get('code'), payload
    checks += 1

try:
    for _ in range(30):
        try:
            request('interactions.php?resource=tickets', 401)
            break
        except urllib.error.URLError:
            time.sleep(0.1)
    else:
        raise RuntimeError('Local PHP server did not start')
    assert server.poll() is None, 'PHP test server exited; refusing to test another process'
    for path in ['interactions.php?resource=chat&order_id=1', 'interactions.php?resource=notifications', 'storage.php', 'shipper/earnings.php', 'admin/payouts.php', 'shipper/bank-account.php', 'support/replies.php?ticket_id=1', 'admin/person3-jobs.php']:
        request(path, 401)
    for path in ['interactions.php?resource=tickets', 'storage.php', 'admin/payouts.php', 'shipper/bank-account.php', 'support/replies.php', 'geo/places.php', 'admin/person3-jobs.php']:
        request(path, 403, method='POST')
        request(path, 401, method='POST', origin=base)
    request('shipper/earnings.php', 405, method='POST', origin=base)
    print(f'Person3 HTTP: {checks} checks passed (unauthenticated, loopback only).')
finally:
    server.terminate()
    server.wait(timeout=5)
