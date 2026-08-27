# MySQL setup

This application uses PlanetScale Vitess (MySQL-compatible) through its HTTPS driver because the Sites runtime does not allow direct outbound MySQL TCP connections.

1. Create a PlanetScale Vitess database and a production branch.
2. Run `mysql/migrations/001_initial.sql` in the PlanetScale console.
3. Create a dedicated read/write application password for that branch. Do not use an organization owner or schema-admin credential at runtime.
4. Copy `.env.example` to `.env.local` and fill in `DATABASE_HOST`, `DATABASE_USERNAME`, and `DATABASE_PASSWORD`.
5. Set `ADMIN_EMAIL` and a unique 14+ character `ADMIN_PASSWORD`, then run `npm run admin:create` once. Remove those two bootstrap values afterward.
6. Configure only the three `DATABASE_*` values as encrypted hosting secrets.

Database credentials never use a `VITE_` or `NEXT_PUBLIC_` prefix and must never be committed. The browser talks only to same-origin API routes.

For production, rotate the database password periodically and immediately after suspected exposure. Expired admin sessions can be cleaned with:

```sql
DELETE FROM admin_sessions WHERE expires_at <= CURRENT_TIMESTAMP(3);
DELETE FROM rate_limits WHERE updated_at < CURRENT_TIMESTAMP(3) - INTERVAL 1 DAY;
```
