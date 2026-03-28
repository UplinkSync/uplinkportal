-- Licensing schema for <PLUGIN_NAME_PLACEHOLDER>
-- Replace placeholder slugs/plan references before production rollout.

create extension if not exists pgcrypto;

create type billing_type_enum as enum ('one_time', 'subscription');
create type license_status_enum as enum ('active', 'trialing', 'past_due', 'cancelled', 'expired', 'revoked');

create table products (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique check (slug ~ '^[a-z0-9-]+$'),
  name text not null,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table plans (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null references products(id) on delete cascade,
  slug text not null check (slug ~ '^[a-z0-9-]+$'),
  name text not null,
  billing_type billing_type_enum not null,
  external_plan_ref text,
  max_activations integer not null check (max_activations > 0),
  features jsonb not null default '[]'::jsonb check (jsonb_typeof(features) = 'array'),
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (product_id, slug)
);

create table licenses (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null references products(id) on delete restrict,
  plan_id uuid not null references plans(id) on delete restrict,
  license_key text not null unique,
  email text,
  status license_status_enum not null default 'active',
  source_ref text,
  expires_at timestamptz null,
  cancelled_at timestamptz null,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table activations (
  id uuid primary key default gen_random_uuid(),
  license_id uuid not null references licenses(id) on delete cascade,
  instance_id uuid not null,
  domain text not null check (length(trim(domain)) > 0),
  first_activated_at timestamptz not null default now(),
  last_validated_at timestamptz not null default now(),
  deactivated_at timestamptz null,
  unique (license_id, instance_id, domain)
);

create table audit_log (
  id bigint generated always as identity primary key,
  action text not null,
  actor text not null,
  license_id uuid references licenses(id) on delete set null,
  context jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index plans_product_id_idx on plans (product_id);
create index plans_product_active_idx on plans (product_id, is_active);
create index plans_external_plan_ref_idx on plans (external_plan_ref) where external_plan_ref is not null;

create index licenses_product_status_idx on licenses (product_id, status);
create index licenses_plan_status_idx on licenses (plan_id, status);
create index licenses_expires_at_idx on licenses (expires_at);
create index licenses_source_ref_idx on licenses (source_ref) where source_ref is not null;

create index activations_license_active_idx on activations (license_id, deactivated_at);
create index activations_instance_domain_idx on activations (instance_id, domain);

create index audit_log_license_created_idx on audit_log (license_id, created_at desc);
create index audit_log_action_created_idx on audit_log (action, created_at desc);

create or replace function set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create trigger products_set_updated_at
before update on products
for each row
execute function set_updated_at();

create trigger plans_set_updated_at
before update on plans
for each row
execute function set_updated_at();

create trigger licenses_set_updated_at
before update on licenses
for each row
execute function set_updated_at();

-- Example seed data (edit before applying in production):
-- insert into products (slug, name) values ('<PRODUCT_SLUG_PLACEHOLDER>', '<PLUGIN_NAME_PLACEHOLDER>');
-- insert into plans (product_id, slug, name, billing_type, external_plan_ref, max_activations, features)
-- values (
--   (select id from products where slug = '<PRODUCT_SLUG_PLACEHOLDER>'),
--   'pro-lifetime',
--   'Pro Lifetime',
--   'one_time',
--   'managed-plan-ref',
--   5,
--   '["core", "advanced"]'::jsonb
-- );
