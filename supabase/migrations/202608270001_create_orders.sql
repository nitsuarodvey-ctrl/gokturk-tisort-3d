create extension if not exists pgcrypto;

create table if not exists public.orders (
  id uuid primary key default gen_random_uuid(),
  name text not null check (char_length(name) between 2 and 120),
  phone text not null check (char_length(phone) between 7 and 30),
  size text not null check (size in ('S', 'M', 'L', 'XL')),
  quantity integer not null check (quantity between 1 and 20),
  delivery_type text not null check (
    delivery_type in ('Genel Merkezden Teslim', 'İzmir Elden Teslim', 'Adrese Kargo')
  ),
  city text,
  district text,
  address text,
  unit_price integer not null default 499 check (unit_price = 499),
  total integer not null check (total = unit_price * quantity),
  payment_status text not null default 'waiting' check (
    payment_status in ('waiting', 'paid', 'rejected')
  ),
  order_status text not null default 'preorder' check (
    order_status in ('preorder', 'confirmed', 'cancelled')
  ),
  production_status text not null default 'waiting' check (
    production_status in ('waiting', 'queued', 'in_production', 'ready')
  ),
  delivery_status text not null default 'waiting' check (
    delivery_status in ('waiting', 'ready_for_pickup', 'shipped', 'delivered')
  ),
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint shipping_address_required check (
    delivery_type <> 'Adrese Kargo'
    or (
      nullif(trim(city), '') is not null
      and nullif(trim(district), '') is not null
      and nullif(trim(address), '') is not null
    )
  )
);

create index if not exists orders_created_at_idx on public.orders (created_at desc);
create index if not exists orders_status_idx on public.orders (
  payment_status,
  production_status,
  delivery_status
);

create or replace function public.set_orders_updated_at()
returns trigger
language plpgsql
security invoker
set search_path = ''
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists orders_set_updated_at on public.orders;
create trigger orders_set_updated_at
before update on public.orders
for each row execute function public.set_orders_updated_at();

alter table public.orders enable row level security;
alter table public.orders force row level security;

revoke all on table public.orders from anon, authenticated;
grant insert on table public.orders to anon;
grant select, insert, update, delete on table public.orders to authenticated;

drop policy if exists "public_can_create_preorders" on public.orders;
create policy "public_can_create_preorders"
on public.orders
for insert
to anon, authenticated
with check (
  unit_price = 499
  and total = 499 * quantity
  and payment_status = 'waiting'
  and order_status = 'preorder'
  and production_status = 'waiting'
  and delivery_status = 'waiting'
  and notes is null
);

drop policy if exists "admins_can_read_orders" on public.orders;
create policy "admins_can_read_orders"
on public.orders
for select
to authenticated
using ((select auth.jwt() -> 'app_metadata' ->> 'role') = 'admin');

drop policy if exists "admins_can_create_orders" on public.orders;
create policy "admins_can_create_orders"
on public.orders
for insert
to authenticated
with check ((select auth.jwt() -> 'app_metadata' ->> 'role') = 'admin');

drop policy if exists "admins_can_update_orders" on public.orders;
create policy "admins_can_update_orders"
on public.orders
for update
to authenticated
using ((select auth.jwt() -> 'app_metadata' ->> 'role') = 'admin')
with check ((select auth.jwt() -> 'app_metadata' ->> 'role') = 'admin');

drop policy if exists "admins_can_delete_orders" on public.orders;
create policy "admins_can_delete_orders"
on public.orders
for delete
to authenticated
using ((select auth.jwt() -> 'app_metadata' ->> 'role') = 'admin');

alter table public.orders replica identity full;

do $$
begin
  alter publication supabase_realtime add table public.orders;
exception
  when duplicate_object then null;
end;
$$;
