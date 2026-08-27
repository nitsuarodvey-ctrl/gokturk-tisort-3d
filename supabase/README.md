# Supabase setup

1. Create a Supabase project.
2. Run `migrations/202608270001_create_orders.sql` in the Supabase SQL editor.
3. Create the admin user in **Authentication → Users** with email/password.
4. Assign the non-user-editable admin claim in the SQL editor:

```sql
update auth.users
set raw_app_meta_data = coalesce(raw_app_meta_data, '{}'::jsonb)
  || '{"role":"admin"}'::jsonb
where email = 'ADMIN_EMAIL_HERE';
```

5. Sign out and back in after changing the claim so the JWT refreshes.
6. Set `VITE_SUPABASE_URL` and `VITE_SUPABASE_ANON_KEY` locally and in the host.

The anon key is intentionally used by the browser. Never add the service-role key
to this project or any `VITE_` variable.
