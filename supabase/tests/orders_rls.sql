begin;

select plan(8);

set local role anon;

select throws_ok(
  $$ select * from public.orders $$,
  '42501',
  'permission denied for table orders',
  'anon cannot read orders'
);

select throws_ok(
  $$ update public.orders set payment_status = 'paid' $$,
  '42501',
  'permission denied for table orders',
  'anon cannot update orders'
);

select throws_ok(
  $$ delete from public.orders $$,
  '42501',
  'permission denied for table orders',
  'anon cannot delete orders'
);

select lives_ok(
  $$
    insert into public.orders (
      name, phone, size, quantity, delivery_type, unit_price, total
    ) values (
      'Anon Test', '05000000000', 'M', 1, 'Genel Merkezden Teslim', 499, 499
    )
  $$,
  'anon can create a valid preorder'
);

select throws_ok(
  $$
    insert into public.orders (
      name, phone, size, quantity, delivery_type, unit_price, total, payment_status
    ) values (
      'Invalid Test', '05000000000', 'M', 1, 'Genel Merkezden Teslim', 499, 499, 'paid'
    )
  $$,
  '42501',
  'new row violates row-level security policy for table "orders"',
  'anon cannot pre-mark an order as paid'
);

reset role;
set local role authenticated;
select set_config('request.jwt.claims', '{"app_metadata":{"role":"viewer"}}', true);

select is(
  (select count(*)::integer from public.orders),
  0,
  'non-admin authenticated users cannot read orders'
);

select set_config('request.jwt.claims', '{"app_metadata":{"role":"admin"}}', true);

select ok(
  (select count(*) from public.orders) >= 1,
  'admin can read orders'
);

select lives_ok(
  $$ update public.orders set production_status = 'in_production' $$,
  'admin can update orders'
);

select * from finish();
rollback;
