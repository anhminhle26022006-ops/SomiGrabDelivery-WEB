# Customer and Shipper dashboard update

Customer uses the approved second layout: forecast across the top, active/recent orders and monthly totals on the left, tasks and packing guidance on the right. Shipper uses the approved first layout: active delivery first, forecast and preparation checklist alongside it. Existing authenticated order, earnings, notification and availability APIs remain the data sources.

## Weather

`GET public/api/account/weather.php?area=centre|east|south` requires an active CUSTOMER or SHIPPER session and `profile.read_own`. It requests current weather plus four hourly points from Open-Meteo, using the existing `OPEN_METEO_MODE` / `OPEN_METEO_API_KEY` configuration. Responses are cached on the server for five minutes. Missing values remain unavailable; failures expose retry controls without blocking the dashboard.

Only the public area centres in `WorkspaceWeatherService::AREAS` are sent to the provider. This endpoint does not read or send a customer's address or a shipper's GPS. The selected area is a local display preference. It does not change pricing or delivery eligibility. The existing pricing weather checks are unchanged.

The Shipper preparation checklist is local UI state; it does not claim to verify equipment or alter account eligibility.

## Profile photos

`public/api/account/avatar.php` supports:

- GET: `{avatar_url}` metadata for the current account.
- GET `?content=1`: private image bytes for that same account. No arbitrary user ID is accepted.
- POST multipart `avatar`: update the image; requires `profile.update_own` and same-origin validation.
- DELETE: remove the current account's photo, with the same permission and origin checks.

Both profile pages expose choose, preview, save, cancel and remove controls. A selected JPEG/PNG/WebP up to 5 MiB is centre-cropped to a 512px JPEG before uploading, stripping original metadata. The server independently checks raster MIME, byte size (1 MiB) and dimensions (1024px maximum). Header/profile avatars update after save and survive reload. Failed uploads preserve the previously saved photo.

The additive `20261113_account_avatars` release runs through `WorkspaceProfileSchema` in the canonical migration runner. Small images live in the private `account_avatars` table with RLS enabled, one row per user, no public grants and no external storage credential in the browser. Reads use the authenticated PHP API. Versioned URLs refresh the image after updates; responses use private/no-store and nosniff. Updates/removals are audited. The image is shared across the current user's Customer and Shipper workspaces.

## Validation

```
php tests/workspace_enhancements.php
node tests/workspace_enhancements_browser.cjs
node tests/shipper_dashboard_browser.cjs
node tests/customer_orders_browser.cjs
node tests/shipper_storage_browser.cjs
```

Backend fixtures use temporary tables and mocked provider responses. Browser fixtures render the actual PHP views and intercept API responses; they cover layout, forecast selection/error/retry, upload/reload/remove, invalid images, failed upload preservation and unauthenticated requests. Screenshots are under ignored `tests/.runtime/workspace-enhancements/`.
