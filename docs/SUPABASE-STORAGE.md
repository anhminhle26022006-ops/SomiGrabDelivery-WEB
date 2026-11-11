# Private files on Supabase Storage

SOMI stores KYC files, delivery evidence, chat attachments and ticket attachments in a private Supabase Storage bucket. File metadata, permissions and retention timestamps stay in PostgreSQL. Identity document numbers remain encrypted in PostgreSQL; this change does not rotate encryption keys.

## Environment

```dotenv
SOMI_STORAGE_DRIVER=supabase
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_SECRET_KEY=YOUR_SERVER_SECRET_KEY
SUPABASE_STORAGE_BUCKET=somi-private
SOMI_STORAGE_SIGNING_KEY=YOUR_EXISTING_RANDOM_SECRET_AT_LEAST_32_CHARACTERS
```

Use a server-only `sb_secret_...` key or legacy `service_role` JWT, never a publishable/anon key. Obtain these in the Supabase project's API settings. Do not put the key in browser JavaScript or Git. `.env` and `.env.render` are ignored by Git and Docker; import the values into Render Environment separately. With this backend, no Render persistent disk or `SOMI_STORAGE_ROOT` is needed.

Create the dedicated **private** bucket `somi-private` in Supabase Storage, restrict uploads to 5 MiB and JPEG/PNG/WebP/PDF, or run the explicit initialization command once with server credentials:

```sh
php bin/storage-init.php --create
php bin/storage-init.php
php bin/person3-check.php
```

`--create` requests a private bucket with the size/MIME limits. It does not convert an existing public bucket: public buckets are rejected. The check command reads bucket settings and does not upload data. Do not add public read policies or client write policies for this bucket. SOMI uses PHP sessions, not Supabase Auth, and authorizes every request on its server.

The browser keeps using `/public/api/storage.php`. Download links expire after five minutes and are bound to the user. The API rechecks permission and expiry, retrieves the object on the server and verifies its size and SHA-256 checksum before responding. Neither the server key nor a direct Supabase URL is returned to the browser.

## Retention and existing files

Enable the existing worker (`SOMI_RUN_WORKERS=1` in Docker) to delete expired Storage objects through the Storage API before marking their metadata deleted. Failed provider requests remain retryable. `SOMI_STORAGE_PRUNE_ORPHANS=0` is the safe default; enable only for a dedicated SOMI bucket to prune unreferenced random object names older than 24 hours.

If a deployment already has filesystem files, pause uploads/workers during the copy, keep `SOMI_STORAGE_ROOT` pointing to the old directory and set the Supabase credentials. Inspect a dry run and then copy:

```sh
php bin/storage-migrate.php
php bin/storage-migrate.php --apply
```

The copy verifies source/destination checksums, preserves object keys and source files, and marks each verified record with its Supabase backend and bucket. It can be resumed and does not overwrite mismatching objects. Run migrations before copying. `SOMI_STORAGE_DRIVER` takes precedence over the legacy `SOMI_STORAGE_PROVIDER` alias. Verify downloads before removing the old volume. Existing remote files created by older deployments must have their metadata reconciled after verifying object checksums; do not label local files as remote without verification.

## Validation

- `php tests/supabase_storage.php`: privacy enforcement, API routes, auth headers, input limits, missing objects, failures and listing.
- `python tests/person3_upload_http.py`: real multipart requests using isolated PostgreSQL TEMP tables and a temporary filesystem.
- Supabase transport tests use an in-memory provider; multipart tests always use temporary local storage.
- `php tests/person3_backend.php`: existing ownership, support permissions and signed-link protections; isolated database only.

Provider tests do not prove real project credentials or bucket setup work. Verify `storage-init.php` against the target project, then manually upload/download KYC and evidence on the deployed application.

Sources: [Supabase Storage REST API](https://supabase.com/docs/reference/self-hosting-storage/introduction), [private buckets](https://supabase.com/docs/guides/storage/buckets/creating-buckets), [server API keys](https://supabase.com/docs/guides/api/api-keys).

## Verified on 2026-11-11

The configured project passed the private bucket check and a real server-side PDF upload/download/SHA-256/delete round trip. The test object was removed, no personal data was uploaded, and no application database records were written. `bin/person3-check.php` reported all readiness checks true, including private storage and the existing migrations. These checks were run from local PHP, not from Render; import `.env.render` and verify upload/download again after deployment.
