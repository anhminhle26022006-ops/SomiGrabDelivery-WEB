# Person 3 / Shipper integration — 12 November 2026

## Storage contract

1. Shipper uploads actual multipart bytes to `public/api/storage.php`: `file`, `purpose=KYC|EVIDENCE`, and `order_id` for evidence. Maximum 5 MiB; server checks actual MIME and ownership.
2. Delivery evidence POST: `{order_id,evidence_type:"DELIVERY_PHOTO"|"RETURN_PHOTO",reference_file_id}`.
3. KYC resubmission POST: `{documents:[{document_type,reference_file_id}],identity?:{...}}`.

The backend locks the file record in the caller's transaction. It checks owner, purpose, expiry and evidence order/assignment. Object key, MIME, size, checksum and original filename come from Storage metadata. No `pending-upload` path or client-supplied checksum can authorize completion. Repeating an evidence link does not create another evidence record. Legacy placeholders remain historical records and do not unlock completion.

The Shipper active-delivery UI uploads actual bytes before linking evidence. The account UI offers KYC resubmission after NEEDS_INFO/REJECTED and opens the existing live bank panel for password/email OTP verification. The old demo bank inputs are removed.

## Retention

Delivery evidence and its private file remain until 90 days after order closure. The worker extends earlier, unprocessed seven-day deadlines before deleting objects. This cannot restore files already deleted. Chat remains one month after closure, ticket attachments three months after ticket closure, KYC six months after the review decision, and GPS seven days.

## Processing order

The container scheduler runs Person 1 (payment/email events), Shipper matching (`--seconds=0`), then Person 3 (notifications/earnings/retention) each cycle. It uses each CLI's supported arguments. Existing real IPN validation is unchanged. Manual payouts reserve PROCESSING until an Admin supplies matching transfer evidence; no bank transfer is executed by the scheduler.

## Verification and limits

`tests/shipper_delivery.php` exercises actual service-generated matching/delivery/return events in isolated TEMP tables, file ownership/purpose, evidence replay, notifications, earning, encrypted queued OTP, verified bank, manual payout reconciliation/replay and retention. `tests/shipper_storage_browser.cjs` verifies multipart KYC submission and the live bank-panel entry using HTTP fixtures. `tests/person3_backend.php` covers OTP limits/expiry and payout/refund failure cases.

These fixtures do not prove real SePay settlement, OTP inbox delivery, a bank transfer, or authenticated mobile use on hosting. Those acceptance checks require the team's test accounts and evidence. Never mark PROCESSING as transferred or expose OTPs/keys in logs.
