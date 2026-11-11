"""Real multipart upload/download/retention against a loopback-only TEMP-table fixture."""
import hashlib
import json
import os
import secrets
import shutil
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

root = tempfile.mkdtemp(prefix='somi-person3-upload-')
env = dict(os.environ, SOMI_PERSON3_UPLOAD_TEST='1', SOMI_STORAGE_ROOT=root, SOMI_STORAGE_SIGNING_KEY=secrets.token_hex(32), SOMI_STORAGE_PRUNE_ORPHANS='1')
server = subprocess.Popen([os.environ.get('SOMI_TEST_PHP', 'C:/xampp/php/php.exe'), '-d', 'extension=pdo_pgsql', '-S', '127.0.0.1:3081', 'tests/person3_upload_fixture.php'], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
checks = 0

def upload(payload, expected=200, purpose='TICKET', extra=None):
    global checks
    boundary = secrets.token_hex(16)
    fields = {'purpose': purpose, **({'ticket_id': '1'} if purpose == 'TICKET' else {}), **(extra or {})}
    data = b''
    for key, value in fields.items():
        data += f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
    data += f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="fixture.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + payload + f'\r\n--{boundary}--\r\n'.encode()
    req = urllib.request.Request('http://127.0.0.1:3081/', data=data, headers={'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        response = urllib.request.urlopen(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    result = json.loads(response.read())
    assert response.status == expected, (response.status, result)
    checks += 1
    return result

try:
    # A readiness GET has no uploaded file and cannot write metadata/objects.
    for _ in range(30):
        try:
            urllib.request.urlopen('http://127.0.0.1:3081/', timeout=30)
        except urllib.error.HTTPError:
            break
        except urllib.error.URLError:
            time.sleep(0.1)
    assert server.poll() is None, 'Fixture server failed to start'
    pdf = b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'
    r = upload(pdf)
    assert r['download_hash'] == hashlib.sha256(pdf).hexdigest() == r['checksum_sha256']; checks += 1
    assert r['download_size'] == len(pdf) and r['object_removed'] and r['deleted_count'] == 1; checks += 1
    assert upload(b'<svg onload="alert(1)"></svg>', 422)['code'] == 'FILE_TYPE_INVALID'; checks += 1
    assert upload(b'', 422)['code'] == 'FILE_SIZE_INVALID'; checks += 1
    assert upload(pdf + b'x' * (5 * 1024 * 1024), 422)['code'] == 'FILE_SIZE_INVALID'; checks += 1
    assert upload(pdf, 404, extra={'ticket_id': '999'})['code'] == 'TICKET_NOT_FOUND'; checks += 1
    assert upload(pdf, 422, purpose='INVALID')['code'] == 'VALIDATION_ERROR'; checks += 1
    assert upload(pdf, 403, purpose='KYC')['code'] == 'SHIPPER_REQUIRED'; checks += 1
    assert upload(pdf, 403, purpose='EVIDENCE', extra={'order_id': '1'})['code'] == 'SHIPPER_REQUIRED'; checks += 1
    r = upload(pdf, purpose='CHAT', extra={'order_id': '1'})
    assert r['object_removed']; checks += 1
    assert not os.listdir(root), 'Fixture leaves no objects behind'; checks += 1
    orphan = os.path.join(root, secrets.token_hex(32))
    fresh = os.path.join(root, secrets.token_hex(32))
    ignored = os.path.join(root, 'keep-this.txt')
    for path in [orphan, fresh, ignored]:
        with open(path, 'wb') as handle:
            handle.write(b'fixture')
    os.utime(orphan, (time.time()-172800, time.time()-172800))
    r = upload(pdf)
    assert r['orphans_removed'] == 1 and not os.path.exists(orphan); checks += 1
    assert os.path.isfile(fresh) and os.path.isfile(ignored); checks += 1
    print(f'Person3 real multipart Storage: {checks} checks passed; TEMP metadata and temporary private volume only.')
finally:
    server.terminate()
    server.wait(timeout=5)
    shutil.rmtree(root)
