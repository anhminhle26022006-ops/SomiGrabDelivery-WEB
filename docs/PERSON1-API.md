# Người 1 — API và vận hành / API and operations

Cập nhật 06/11/2026. Triển khai trong source hiện tại; xem [scope](BACKEND-SCOPE.md) và [API đơn](DELIVERY-API.md). SePay đã được chọn. Hướng dẫn bật sandbox: [SEPAY-SETUP.md](SEPAY-SETUP.md); kiểm tra cấu hình không lộ khóa bằng `php bin/sepay-check.php`. Người dùng duyệt **tạm khóa thanh toán USD**; bảng giá USD và snapshot cũ giữ nguyên. SMS chưa có nhà cung cấp thử nghiệm: dùng chế độ demo có cấu hình rõ ràng khi phát triển.

Implemented in the current source. SePay is selected; USD checkout is disabled by approval. USD quotes remain independent and are never silently converted. SMS has no selected test provider; explicit demo mode is available for development.

## Chạy backend / Run the backend

PHP 8+, PDO PostgreSQL, BCMath, cURL, OpenSSL, mbstring. Cấu hình môi trường theo [runtime.env.example](runtime.env.example); file mẫu trong docs không được tự nạp. PHP tự nạp `.env` ở root cho web/API/worker; biến môi trường server/process được ưu tiên.

```powershell
php database/migrate-customer-orders.php --apply
php -S 127.0.0.1:3000 index.php
php bin/person1-worker.php --limit=20
```

Runner áp dụng migration có ledger và advisory lock trong transaction, không tạo tài khoản, loại hàng hoặc policy thu nhập/điểm giả. Migration `20261106_person1_backend` bổ sung quyền, yêu cầu quốc tịch, biên nhận callback, đối soát, job, email và giới hạn đăng nhập.

Schedule the bounded CLI worker every minute in the same server environment. Multiple workers claim jobs with `FOR UPDATE SKIP LOCKED`, a 90-second lease and a fencing token. Failed jobs back off, stop after eight attempts, and can be retried by an authorized administrator. Do not expose the CLI through HTTP. Run promptly for verification emails: codes expire after five minutes; reset links expire after thirty minutes. Email payloads expire before the provider's 24-hour idempotency window and are purged after acceptance/expiry. `ACCEPTED` means the email provider accepted the request, not confirmed inbox delivery.

## Quy ước / Conventions

- Base: `/public/api/`. Session `PHPSESSID`; current workspace must have the endpoint's role and permission. Unsafe requests require same-origin Origin or Fetch Metadata. Browser input never supplies the acting user ID.
- Write bodies: `Content-Type: application/json`, object, maximum 64 KiB, depth 32; unsupported fields rejected. Auth endpoints retain their existing scalar-body contract.
- IDs and money ending `_id` / `_minor` are decimal strings in JSON. VND minor unit = đồng, USD minor unit = cent. Distance/weight and exact price components are decimal strings; do not use floating point for settlement.
- Success returns an endpoint-specific object. Errors: `{ "code": "...", "error": "..." }`; 401 session, 403 role/permission/origin, 404 unknown/foreign resource, 409 conflicting state/idempotency, 422 validation, 429 login throttle, 503 missing configuration/provider.
- `Idempotency-Key` for order/payment creation: 8–100 characters, letters/digits/`.`/`_`/`:`/`-`; retry with the same key after a timeout. GET payment reads the original transaction instead of creating another one.
- Transactions lock **order → payment → refund/offer/assignment**. Internal services assume the caller owns the transaction unless documented otherwise. External provider calls occur outside these locks.

## API tài khoản / Account APIs

| Endpoint | Methods | Contract |
|---|---|---|
| `account/profile.php` | GET, PUT | All active roles; GET `{profile}`, PUT `{full_name}` only. Email/phone are not changed without verification. |
| `account/change-password.php` | POST | `{current_password,password}` → `{reauthenticate:true}`; caller signs in again. |
| `account/nationality-request.php` | GET, POST | Customer/Shipper; POST `{nationality_code,reason}`; only one pending request per user. |
| Existing `auth/*.php` | Existing methods | Register, login by email/phone, logout, workspace switch, phone/email verification and reset URLs preserved. `send-email-code.php` now returns `EMAIL_CODE_QUEUED`. |

Email language uses stored `users.app_language`. Approved nationality changes set VN→vi, other supported countries→en for future activity; existing order snapshots remain unchanged. Login throttles both IP and normalized identifier in hashed database buckets. Phone demo responses explicitly identify `delivery_mode: DEMO`; real SMS must use a configured adapter and never return the code.

## Customer, payment và refund

| Endpoint | Methods | Contract |
|---|---|---|
| `customer/payments.php?order_id=...` | GET, POST | Customer owner only. POST `{method:"GATEWAY"|"CARD"|"BANK_TRANSFER"}` + Idempotency-Key → `{payment}`. All three use SePay; BANK_TRANSFER is SePay checkout, not a browser-confirmed bank payment. |
| `customer/checkout.php?order_id=...` | GET | Owner, unpaid/payable order only. Returns an escaped HTML form signed on the server, with a button submitting to SePay. Never marks the order paid. |
| `payments/callback.php` | POST | SePay IPN; authenticate `X-Secret-Key` using the separate IPN secret. No browser session/origin required. Valid ORDER_PAID/CAPTURED/APPROVED PAYMENT, matching invoice, full amount and VND required; response HTTP 200 `{success:true,...}`. |
| `customer/refunds.php?order_id=...` | GET, POST | Owner only. GET `{items}`. POST `{reason:"CUSTOMER_CANCELLATION"|"RETURN_FEE_DEDUCTION"}` reserves the eligible snapshot-based refund; caller cannot choose amount or destination. |

SePay invoice: `SOMI-TEST-P<payment_id>` or `SOMI-LIVE-P<payment_id>`. Merchant ID, merchant signing secret, environment and public HTTPS URL must stay consistent for outstanding transactions. Separate databases are recommended for sandbox/production; restarting invoice IDs against the same merchant requires a new deployment-specific invoice namespace before use.

IPN receipt and transaction ledger are atomic with payment/order updates. Duplicate event IDs with identical data succeed once; altered repeats fail. Reconciliation calls SePay's authenticated order-detail API by invoice. Successful browser redirects do **not** confirm payment. A late payment on a cancelled order emits `RefundRequested` and cannot reopen matching. `TRANSACTION_VOID` is not mapped to refund success: it returns an explicit unsupported-event error for investigation.

USD POST payment returns `422 PAYMENT_CURRENCY_UNSUPPORTED`, before inserting a payment. Frontend shows this restriction in VI/EN. No exchange rate or conversion is invented.

Refunds reserve captured gross amount once, deduct the snapshotted cancellation/return fee, and keep ledger/history. A repeated business request returns the same reservation; a second key cannot pay twice. The worker sets SePay refunds to `PROCESSING` with `SEPAY_REFUND_REVIEW_REQUIRED`: an operator must complete the original-method refund using a supported provider procedure, then submit evidence through the Admin reconciliation API below. This release **does not automatically transfer SePay refunds**. The published Bank API v2 refund endpoints are a different integration (with bank/account eligibility) and must not receive Gateway IDs. `ADMIN_ADJUSTMENT` is blocked until an explicit policy is approved. Failed/refund-processing states are never shown as money returned.

## Admin APIs

All require an active Admin workspace, a matching permission and audited sensitive changes. Lists return at most 100 rows; account/order/audit/email lists support descending `before_id` cursors where applicable.

| Endpoint | Methods | Body / result |
|---|---|---|
| `admin/accounts.php` | GET, POST | GET `?id=` detail/history or `?role=CUSTOMER|SHIPPER|ADMIN&q=&before_id=`; POST `{user_id,account_status:"ACTIVE"|"SUSPENDED",reason}`. No self-lock, reopening closed accounts, or activation without both verifications. |
| `admin/orders.php` | GET | `?id=` detail/history or `?customer_id=&status=&before_id=` list. |
| `admin/review-shipper.php` | POST | `{shipper_id,decision:"APPROVED"|"REJECTED"|"NEEDS_INFO",note}`. Identity data required; notes required for rejection/more information. Submission/upload belongs to Person 2/3. |
| `admin/nationality-requests.php` | GET, POST | POST `{request_id,decision:"APPROVED"|"REJECTED",note}`; review once, with audit. |
| `admin/parcel-categories.php` | GET, POST, PUT | POST/PUT `{category_code,name_vi,name_en,description,restriction_type:"ALLOWED"|"RESTRICTED"|"PROHIBITED",active:boolean}`; PUT uses `?id=`. No initial categories are invented. |
| `admin/policies.php` | GET, POST | GET policy headers; `?id=` returns pricing bands/fees as well. POST `{action:"PRICING"|"EARNING"|"SCORE",policy:{...}}` or `{action:"ACTIVATE_PRICING",pricing_policy_id}`. New pricing is inactive until activation. |
| `admin/payments.php` | GET, POST | GET payment list; POST `{payment_id}` queues authenticated provider reconciliation. |
| `admin/refunds.php` | GET, POST | GET ledger; POST `{order_id,reason}` uses the same reservation key as Customer/event consumer. |
| `admin/refund-reconciliation.php` | POST | Record an **already completed** SePay refund: `{refund_id,provider_refund_id,original_payment_reference,original_method_confirmed:true,evidence_reference,amount_minor,currency_code}`. Exact original payment, currency and reserved amount required; unique references, audit and RefundSucceeded. Does not initiate a transfer. |
| `admin/bank-reconciliation.php` | POST | Legacy MANUAL_BANK only: `{kind:"PAYMENT"|"REFUND",payment_id or refund_id,bank_reference,evidence_reference,amount_minor,currency_code,original_payment_reference (refund only)}`. New SePay payments use provider reconciliation instead. |
| `admin/audit-logs.php` | GET | Redacted audit records; no credentials/identity numbers. |
| `admin/email-logs.php` | GET | Email metadata; no OTP, reset URL or encrypted recipient payload. |
| `admin/jobs.php` | GET, POST | Metadata or POST `{job_id}` to retry FAILED jobs. Expired email is never resent. |

PRICING fields: `policy_name,currency_code,base_fee_minor,included_distance_km,per_km_minor,included_weight_kg,per_kg_minor,max_weight_kg,max_distance_km,return_fee_minor,time_bands,weather_bands,cancellation_fees`. Time bands `{day_of_week:null|0..6,start:"HH:mm:ss",end:"HH:mm:ss",multiplier}` must cover the entire week without overlap/gaps. Overnight bands belong to their starting day. Weather bands `{condition,label,multiplier}` must include NORMAL/LIGHT_RAIN/RAIN. Cancellation fees `{after_status,fee_minor}` are snapshotted; missing states retain zero. Limits cannot exceed the approved 40 km exclusive / 30 kg inclusive. Changes never reprice existing orders.

EARNING fields: `policy_type:SUCCESSFUL_DELIVERY_PERCENT,currency_code,percentage_rate` or `policy_type:FAILED_RETURN_FIXED,currency_code,fixed_amount_minor`. SCORE fields: `deduction_points,lock_threshold,recovery_points`. Values must be explicitly supplied, and superseded records keep their effective periods/audit. No default earning percentages, compensation or score numbers are seeded.

## Bàn giao Người 2/3 / Shared integration

`public/api/_kernel.php::somi_run_api()` performs identity/session/workspace/permission/origin checks and transaction/error handling. `AccessControl::requireOrder()` enforces Customer ownership or an active Shipper assignment; `requireOffer()` enforces the addressed Shipper. Person 2 must also verify offer expiry, KYC, availability and order/payment state under the order lock before accepting. `auditAdminChatRead()` checks the related ticket and writes an audit in the caller's transaction; Person 3 must call it before returning any chat content.

Events are rows in `order_events`: `event_id,order_id,event_type,deduplication_key,payload,created_at`. Payload has `schema_version:1`, string IDs and money, order code and currency. Existing events: OrderCreated, OrderReadyForMatching, OrderCancelled, RefundRequested; payment adds PaymentConfirmed `{payment_id}`, refund adds RefundSucceeded `{payment_id,refund_id,refund_amount_minor}`. Person 2 publishes OrderDelivered, OrderReturning and OrderReturned through `OrderLifecycleService::event()` in the delivery transaction **after validating evidence/required confirmations**. Person 1 consumes these for customer email; OrderReturned also reserves a return refund. Every consumer needs its own `(consumer,event_id)` receipt; never mark one shared processed flag for all modules.

Use `EmailService::enqueue($db,$userId,$template,$dedupKey,$variables,$orderId)` inside the owning transaction, or publish the documented order event. Whitelisted templates: VERIFY_EMAIL, RESET_PASSWORD, OrderCreated, PaymentConfirmed, OrderCancelled, OrderDelivered, OrderReturning, OrderReturned, RefundSucceeded. Recipient and language come from the account; no arbitrary public email endpoint exists. Jobs carry only IDs; sensitive message payloads are encrypted server-side. Person 3 owns in-app notifications, not a second email worker.

## Kiểm thử / Validation

`php tests/person1_backend.php` uses session-local TEMP tables and a `pg_temp,pg_catalog` search path; provider and mail transports are fakes. It does not write business fixtures into the configured application's tables or contact payment/email providers. Run the existing customer/pricing/origin/frontend checks too. Passing these tests does not prove live merchant credentials, public IPN delivery, real refunds, inbox delivery, SMS delivery or Person 2/3 integration.

Official integration references: [SePay signed form](https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/form-thanh-toan), [IPN](https://developer.sepay.vn/vi/cong-thanh-toan/IPN), [sandbox](https://developer.sepay.vn/vi/cong-thanh-toan/sandbox), [reconciliation](https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/chi-tiet-don-hang), [separate Bank v2 refund eligibility](https://developer.sepay.vn/vi/sepay-api/v2/hoan-tien/hoan-tien-giao-dich).

## Kết quả tại workspace — 06/11/2026

- Migration Người 1 đã áp dụng. 74 kiểm tra service mới, 84 hồi quy Customer, 23 pricing provider, 21 origin, helper giá/auth frontend đều đạt.
- HTTP read-only: 29 kiểm tra; Customer browser fixture: 24; Pricing browser: 44; không có lỗi runtime. Browser Customer dùng fixture fetch để không tạo giao dịch thật. Syntax PHP/JS, whitespace và 65 liên kết tài liệu đã kiểm tra.
- Kiểm tra cấu hình chỉ đọc: Resend và identity encryption key có sẵn (message queue dùng fallback key); credentials SePay/public HTTPS URL, Google Maps và Open-Meteo chưa có. Có 0 danh mục hàng ALLOWED active. Nhóm cần nhập danh mục được duyệt bằng API Admin; không seed giả để vượt qua màn tạo đơn.
- Chưa gửi SMS/email thử, chưa gọi SePay sandbox/live hoặc chuyển tiền. Cần cấu hình merchant/IPN, scheduler worker, danh mục và nguồn route/weather, sau đó nghiệm thu đầu cuối với Người 2/3. Refund Gateway vận hành bằng operator + đối soát chứng từ như mô tả trên.

Source/backend checks passed; live-provider delivery, funds movement and full team integration remain separate acceptance steps.
