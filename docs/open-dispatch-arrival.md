# Open dispatch and delivery arrival

Deploy the PHP, JavaScript and CSS changes together with the database migration:

```powershell
php database/migrate-customer-orders.php --apply
```

The migration `20261114_open_dispatch_arrival` removes the expiry requirement for invitations and adds the delivery arrival milestone. It does not reopen cancelled orders or reverse refunds. Existing exhausted orders are left unchanged.

- Paid immediate orders have a five-minute matching window. No shipper acceptance within that window triggers automatic cancellation with an explicit reason and a full refund request. Individual invitations have no countdown.
- Scheduled orders enter matching 30 minutes before their pickup appointment and have a five-minute matching window starting at that time (or verified payment if later).
- All eligible available shippers within the matching radius can receive the same order. Shippers joining later can also receive it. Rejecting an invitation only hides it for that shipper.
- The order row lock and unique active-assignment indexes enforce one winner, including concurrent accept/cancel requests.
- While `IN_DELIVERY`, a GPS fix within 200 metres of the delivery point, no older than two minutes and with accuracy at most 200 metres records `arrived_at`. Arrival does not complete delivery.
- Delivery proof and completion both require arrival; completion additionally requires a valid private delivery photo. The customer sees arrival separately from delivery completion.

Tests use temporary tables, except the explicit concurrent-process test below. That test creates a randomly named `shipper_race_*` schema and removes it in `finally`; it does not modify application tables.

```powershell
php -d extension=pdo_pgsql tests/shipper_matching.php
php -d extension=pdo_pgsql tests/shipper_live_offers.php
php -d extension=pdo_pgsql tests/shipper_delivery.php
php -d extension=pdo_pgsql tests/open_dispatch_race.php --isolated
node tests/shipper_availability_browser.cjs
node tests/shipper_arrival_browser.cjs
```
