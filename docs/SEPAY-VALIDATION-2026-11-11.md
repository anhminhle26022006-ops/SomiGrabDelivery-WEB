# SePay technical validation — 2026-11-11

Scope: retain LIVE configuration; user performs any real-money transaction manually. No checkout, valid payment notification, refund or fund movement was initiated by these checks.

## Verified

- Local configuration: production, no format/extension problems.
- SePay LIVE merchant API: authenticated GET `/v1/order?per_page=1&page=1` returned HTTP 200 with expected response shape. No order/customer data or credentials were printed.
- Hosting IPN `https://somigrabdelivery.online/public/api/payments/callback.php`: GET rejected with 405 METHOD_NOT_ALLOWED; malformed POST without secret rejected with 401 INVALID_CALLBACK_SIGNATURE; malformed POST with local secret rejected with 400 INVALID_JSON. This confirms the IPN secret matches hosting without a valid event or business database access.
- Automated local isolated tests: SePay 40 checks; Customer orders 101 checks; Person 1 backend 74 checks. All passed. Coverage includes form signing, callback authentication, mismatched amount/currency/reference, duplicate processing, ledger/order transitions, late payments and refund handling. Provider calls are mocked and database writes use local PostgreSQL TEMP tables.
- `.env.render` synchronized with the current local LIVE SePay variables and excluded from Git/Docker.

## Pending manual payment

1. Log in as a customer on the hosted SOMI app and create a VND order.
2. Open SePay checkout; for LIVE its host must be `pay.sepay.vn`. The user completes the actual payment.
3. In SePay IPN logs verify delivery to SOMI returns HTTP 200.
4. Refresh SOMI: payment should be PAID, the order should leave PENDING_PAYMENT and enter SEARCHING_SHIPPER or a later matching state.
5. Record the SOMI order code and SePay transaction reference for read-only reconciliation. Do not share keys, card details or OTPs.

Actual SePay-to-SOMI payment round trip remains unverified. The tests do not independently establish that hosting uses the same merchant/mode as local. They do establish that the hosted endpoint recognizes the same IPN secret.

Reusable diagnostics: `php bin/sepay-connection-check.php`. Provider API: https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/danh-sach-don-hang ; IPN: https://developer.sepay.vn/vi/cong-thanh-toan/IPN .
