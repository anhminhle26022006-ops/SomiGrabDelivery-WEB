# Scheduled pickups

The appointment is the time the shipper should collect the parcel, in Vietnam
time (`Asia/Ho_Chi_Minh`, UTC+7). It is a target, subject to a shipper accepting
the order. It is not a promised recipient delivery time.

## Customer flow

1. Choose immediate delivery or a pickup appointment, 30 minutes to 7 days ahead.
2. Request a quote. The server validates an explicit-offset timestamp and records
   the normalized UTC appointment and dispatch time in `snapshot.schedule`.
3. Confirm the quote and pay. The order preserves that snapshot and price.
4. After verified payment, the order waits until 15 minutes before the appointment.
   Customer screens show the pickup time and matching start time in UTC+7.
5. The matching worker prioritizes eligible scheduled orders by earliest appointment,
   ahead of immediate orders. Shipper offers and active deliveries show the appointment.
6. A shipper can accept during the dispatch window, but cannot confirm pickup before
   the appointment. Existing ownership checks and the one-active-order constraint remain.

Appointment data is stored in the existing `orders.pricing_snapshot` and quote
snapshot. Existing orders without schedule metadata keep immediate-delivery behavior.
No schema migration or changes to payment methods, fees, or refund settlement are needed.
Only the quote API accepts schedule input; order creation uses the owned quote and
its existing idempotency key. Clients cannot supply a dispatch time or priority.

## Dispatch and failure behavior

All dispatch paths use the same eligibility gate: event consumer, periodic sweep,
shipper polling fallback, pending-offer listing and offer acceptance. Future events
remain unacknowledged until eligible, so a restart does not lose the appointment.
Paid orders can also be recovered by the sweep without a browser being open.

The normal five-minute timeout does not count the time spent waiting for a future
appointment. Scheduled searches continue until 15 minutes after pickup time, or
at least five minutes after a late payment initiated the current search,
whichever is later. Open invitations remain available within that search window; eligible newly online shippers can also join. Failure
uses the existing `NO_SHIPPER_FOUND` event and cancellation/refund processing;
this feature does not introduce another settlement path.

Cancellation before assignment uses the existing customer cancellation API.
Cancelled, unpaid and already assigned orders cannot be dispatched as new jobs.
The server rejects stale quote appointments on creation and rejects forged early
offer acceptance and early pickup confirmation.

## Running workers

Production Docker deployments must enable the existing scheduler with
`SOMI_RUN_WORKERS=1`. It already invokes `bin/shipper-worker.php` each cycle.
Without a running worker, dispatch depends on an available shipper polling;
scheduled operation requires the worker.

For a local PHP web server, run the existing helper, tied to its process lifetime:

```powershell
powershell -NoProfile -File scripts/run-local-matching.ps1 -ServerPid <web-server-pid>
```

The helper keeps invoking the matching worker and exits when the web server stops.
The normal scheduler cadence can add up to one cycle of delay; it is not a hard
real-time dispatch guarantee.

## Verification

```text
php tests/order_schedule.php
php tests/scheduled_pickups.php
php tests/customer_orders.php
php tests/shipper_live_offers.php
node tests/customer_orders_browser.cjs
```

Backend fixtures use connection-local temporary tables and do not modify real
orders, payments or notifications. Browser fixtures include a browser timezone
different from Vietnam, schedule selection, UTC submission, draft restoration,
switching to immediate delivery and the waiting-for-appointment screen.
