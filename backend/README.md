# GUB Merch Laravel API

This private API sits between the Vinext storefront and MySQL. Browsers never receive database credentials or the internal API key.

## Local WAMP setup

1. Start WAMP MySQL 8 on `127.0.0.1:3306`.
2. Run `php scripts/setup-wamp.php` with WAMP PHP. The script creates the database, migrations, a random local database password, a least-privilege runtime user, and matching ignored environment files.
3. Create an admin with `php artisan app:create-admin admin@example.com`. The command asks for the password without echoing it.
4. Start the API with `php artisan serve --host=127.0.0.1 --port=8000`.

## Production requirements

- Deploy this directory to a PHP 8.3+ host with HTTPS and MySQL 8.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`, and an independent 32-byte-or-longer `INTERNAL_API_KEY`.
- Give the runtime database user only `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on this database. Run migrations with a separate deployment credential.
- Configure the Site's server-only `LARAVEL_API_URL` (ending in `/api/internal/v1`) and `LARAVEL_API_KEY` secrets. Never use `VITE_` or `NEXT_PUBLIC_` prefixes for either value.
- Restrict the Laravel API at the network layer when the hosting provider supports IP allowlists or private networking.

Admin passwords use Laravel's adaptive password hashing. Admin tokens are random, stored only as SHA-256 hashes, expire after eight hours, and are transported to the storefront only in `HttpOnly`, `Secure`, `SameSite=Strict` cookies.
