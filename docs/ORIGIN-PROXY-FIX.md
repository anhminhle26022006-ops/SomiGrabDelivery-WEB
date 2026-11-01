# HTTPS reverse proxy / Origin fix

The earlier POST guard compared the browser Origin with PHP's transport scheme. Behind HTTPS termination PHP can receive HTTP while the browser is on HTTPS, incorrectly rejecting a same-origin login with 403.

`RequestOrigin.php` now normalizes exact origins, including default ports. The API accepts the direct request origin or the public URL from SOMI_APP_URL / RENDER_EXTERNAL_URL. Where no public URL is available, the HTTP-to-HTTPS proxy case requires an identical authority and browser Fetch Metadata `Sec-Fetch-Site: same-origin`. Host suffixes, foreign ports, opaque origins and malformed origins remain rejected. Arbitrary X-Forwarded-* headers are not trusted. JSON-only POST and the cross-site Fetch Metadata guard remain enabled.

API and workspace cookies use the same Secure-cookie detection. No production environment variables or database records were modified. If a proxy rewrites the public hostname or clients omit Fetch Metadata, configure SOMI_APP_URL to the externally visible URL in that deployment.

Validation: 21 origin/cookie unit cases, 6 non-mutating HTTP API cases (including Secure cookie on the simulated HTTPS proxy request), and 28 public/API regression checks passed. PHP lint passed for the three changed application files. These tests simulate reverse-proxy request headers locally; live production deployment has not been verified.

Run: `C:/xampp/php/php.exe tests/request_origin.php`; with PHP server running on localhost:3033, `python tests/request_origin_http.py` and `python tests/audit_smoke.py http://127.0.0.1:3033`.
