---
description: Rules Claude must follow when writing Laravel migrations
globs:
  - "app/Modules/*/Database/Migrations/*.php"
  - "database/migrations/*.php"
---

# Migration Rules

## Always

- Top of file: `<?php` then `declare(strict_types=1);`
- Use anonymous-class migrations (Laravel 11+ default).
- Inside `Schema::create(...)`:
  - `$table->charset = 'utf8mb4';`
  - `$table->collation = 'utf8mb4_unicode_ci';`
- Internal PK: `$table->bigIncrements('id');`
- External ID: `$table->char('public_id', 26)->unique();` (ULID, exposed in API URLs).
- Money columns: `$table->unsignedBigInteger('{field}_minor');` paired with `$table->char('{field}_currency', 3);`
- Translatable text fields: `$table->json('name');` (then declare `$translatable` array on the model).
- Timestamps: `$table->timestamps();` always — except on append-only ledger tables, which use `$table->timestamp('created_at')->useCurrent();` only.
- Foreign keys: `$table->foreignId('xxx_id')->constrained()->restrictOnDelete();` is the default. Use `cascadeOnDelete()` only when domain-correct (e.g., child detail rows of a parent service).
- Declare indexes for every (FK, status) and (FK, created_at) combination that will be queried.
- Declare composite UNIQUE indexes explicitly (e.g., `(vendor_profile_id, slug)` on services).

## Soft deletes

- Soft-delete only the tables listed in CLAUDE.md §15.
- For every other table, **never** add `softDeletes()` — even if it seems convenient.
- Append-only ledger tables (`wallet_ledger`, `audit_logs`, `payments`, `commissions`, `booking_state_transitions`, `event_outbox`, `analytics_events`, `loyalty_ledger`) must not have `updated_at` either.

## Detail tables (rental/sale/digital)

- PK is `service_id` (BIGINT, FK to `services.id`, `cascadeOnDelete()`).
- Use `$table->primary('service_id');` — no auto-increment id on detail tables.
- Detail tables hold ONLY type-specific columns. Shared columns belong in `services`.

## What forbids a migration

- Schema::create without utf8mb4 charset — **fail**.
- Money column stored as DECIMAL or FLOAT — **fail**.
- A new table for a top-level entity without `public_id` — flag and ask whether to make it a pivot/ledger or add the ULID.
- Adding `softDeletes()` to an append-only table — **fail**.

## Order matters

When asked for a module's migrations, output them in dependency order. Identity needs Geography. Catalog needs Identity. Booking needs Catalog. Payments need Booking. And so on.

## After generating migrations

- Run `php artisan migrate` to apply.
- If a relationship needs a model factory, generate the factory in the same step.
- If the model holds translatable fields, also produce the model with `protected $translatable` array.
