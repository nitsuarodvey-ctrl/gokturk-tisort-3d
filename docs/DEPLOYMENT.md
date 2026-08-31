# Production deployment runbook

## Platform requirements

- PHP 8.1 or newer with `curl`, `dom`, `iconv`, `mbstring` and `pdo_mysql`.
- MySQL 8.x, InnoDB and `utf8mb4`.
- Apache with `mod_rewrite` and `mod_headers`, plus a valid HTTPS certificate.

## Layout and permissions

Copy the application HTML/PHP files and the `admin`, `api`, `assets` and
`includes` directories into `public_html`. Keep the real `.env` one directory
above `public_html`; use permission `600` where supported. Use `755` for
directories and `644` for application files. Do not upload `tests`, `release`,
`.git`, logs, dumps or development caches.

## Database

For a new installation, import `api/database.sql`. For an existing installation
based on commit `6d707f1`, back up the database and then run, in this order:

1. `api/migrations/20260830_security_hardening.sql`
2. `api/migrations/20260830_kuveyt_turk_payments.sql`

Both migrations are safe to run again. Verify that all tables use InnoDB and
that `payment_attempts.merchant_order_id` remains unique.

## Backup, deploy and rollback

Before deployment, create and verify a full database dump and an archive of the
current `public_html`. Deploy application files without overwriting the external
`.env`, run the migrations, verify permissions, and clear OPcache if enabled.

If a migration or smoke test fails, stop accepting orders, restore the previous
`public_html` archive, then restore the matching database dump if the schema was
changed. Keep any `unknown` payment attempt for manual reconciliation; never
re-run a bank provision call manually.

## Production checks

Set `APP_ENV=production`, `SESSION_SECURE=true`, an HTTPS `APP_URL`, and
`KUVEYT_TURK_MODE=production`. The callback and both bank return URLs are
`APP_URL + /api/payment-callback.php`. Confirm that `.env`, SQL, `includes`,
`tests`, `release` and `docs` return HTTP 403 before enabling payment.
