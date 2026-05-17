---
description: One-page schema cheat sheet — table inventory, FKs at a glance, common columns. Auto-loads when working on migrations, models, repositories, or anywhere DB schema matters.
globs:
  - "app/Modules/*/Database/Migrations/*.php"
  - "app/Modules/*/Domain/Models/*.php"
  - "app/Modules/*/Infrastructure/Repositories/*.php"
  - "database/migrations/*.php"
  - "database/seeders/*.php"
  - "database/factories/*.php"
---

# InstaParty Schema Cheat Sheet

> Compact reference. Full column lists in `docs/specs/11_DB_Schema.md`.
> 60 tables across 13 modules. Locked — no changes without conversation.

## Locked global decisions (re-state if asked)

- Search: **Meilisearch only** via Scout. NO `service_search_index` MySQL table.
- Reservations: cart hold **15 min**, payment hold **24h**.
- Vendor SLA: **24h** response deadline.
- Reviews: BOTH `service_reviews` + `vendor_reviews`.
- Loyalty: per-vendor only.
- Excel imports: strict no partial commits.
- Money: integer minor units + currency CHAR(3). No floats.
- IDs: internal `id` BIGINT, external `public_id` CHAR(26) ULID.

## Table inventory — what lives where

### Identity (8)
`vendor_profiles`, `vendor_documents`, `vendor_approved_product_types`, `vendor_business_hours`, `vendor_coverage_areas`, `customer_profiles`, `user_devices`, `two_factor_secrets`

### Geography / Shared (3)
`governorates` → `regions` → `cities` (cities denormalize `governorate_id` for fast filter)

### Catalog (13)
**Top-level:** `occasions`, `categories` (self-ref parent_id), `occasion_category` pivot, `category_field_schemas` (per category × type), `service_themes`
**Services:** `services` (polymorphic base, `product_type` discriminator) + 1:1 details `service_rental_details`, `service_sale_details`, `service_digital_details`
**Pricing & availability:** `service_pricing_tiers`, `service_availability_blocks`, `service_excluded_dates`, `service_themes_pivot`
**Critical:** `service_inventory_reservations` (overselling guard)

### Discovery (4)
`wishlists`, `wishlist_items`, `saved_searches`, `search_logs` (append-only)

### Booking (10)
`bookings` (with **3 status columns**: `lifecycle_status`, `payment_status`, `fulfillment_status`)
→ `booking_addresses` (snapshot, NOT FK to customer_addresses)
→ `booking_snapshots` (versioned read model, append-only)
→ `booking_locks` (pessimistic locks with UNIQUE on `(resource_type, resource_id, released_at)`)
→ `booking_vendors` (one per vendor per booking)
→ `booking_items` (with `product_type` denorm, `type_snapshot` JSON, `fulfillment_data` JSON)
→ `booking_modifications` (status ENUM includes `draft`; generated `draft_slot` column + UNIQUE `(booking_vendor_id, draft_slot)` enforces one open draft per booking_vendor) + `booking_modification_items`
→ `booking_state_transitions` (append-only, polymorphic)
→ `booking_customer_notes`

### Payments (5)
`payments` (UNIQUE `(gateway, gateway_ref)`) — ADDED: `correlation_id`, `capture_ledger_group_id`; `payment_attempts`, `refunds` — ADDED: `ledger_group_id`, `idempotency_key`; `idempotency_keys` (per-scope TTL) — ADDED: `scope`, `ttl_seconds`, `payload_hash`; `gateway_webhook_logs`

### Settlement (10 — Phase 4.9 expanded from 6)
`wallets` (UNIQUE `(owner_type, owner_id, currency)`) — ADDED: `last_ledger_entry_id`, `last_projected_at`; balance columns are now projection cache only
`wallet_ledger` (append-only, DB trigger enforced) — ADDED: `direction` ENUM, `running_balance_minor`, `transaction_group_id`, `counter_account_*`, `correlation_id`, `causation_id`, `idempotency_key`, `posted_at`; `amount_minor` is UNSIGNED magnitude
`ledger_transaction_groups` (NEW, append-only) — groups balanced debit+credit entries; CHECK `total_debits_minor = total_credits_minor`
`commissions` — ADDED: `accrual_ledger_entry_id`, `reversal_ledger_entry_id`, `idempotency_key`
`commission_rates` (most-specific match wins), `withdrawals` — ADDED: `idempotency_key`, `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id`
`settlement_runs`
`financial_snapshots` (NEW, append-only) — daily per-wallet balance: `wallet_id`, `snapshot_at`, `as_of_ledger_entry_id` (FK high-water mark), `available_minor`, `pending_minor`, `currency`, `checksum` (SHA-256 of ledger replay sequence). Idempotent per (wallet, calendar day).
`reconciliation_runs` (NEW) — orchestrator runs with status lifecycle (`pending→running→clean|repaired|requires_manual_review|failed`); `wallets_scanned`, `findings_count`, `auto_repaired_count`, `manual_review_count`
`reconciliation_findings` (NEW) — per-wallet findings: `severity` ENUM(`info`,`warning`,`high`), `finding_type`, `resolution` (null=unresolved, `auto_repaired`, `ignored`)

### Reviews (4)
`service_reviews` (UNIQUE per `booking_item_id`), `vendor_reviews` (UNIQUE per `booking_vendor_id`), `review_responses`, `review_moderation_log`

### Communication (9)
`chat_threads` (Firestore audit mirror), `chat_message_log` (append-only), `chat_moderation_flags`
`notification_templates` (UNIQUE `(event_key, channel, audience)`), `notification_dispatches`, `notification_preferences` (UNIQUE `(user_id, channel, event_category)` — `system` cannot be disabled)
`campaigns`, `campaign_runs`, `campaign_recipients`

### Loyalty (4) — per vendor
`loyalty_programs` (UNIQUE per `vendor_profile_id`), `loyalty_rules`, `loyalty_ledger` (append-only), `loyalty_redemptions`

### Imports (2)
`excel_imports` (3 product type templates), `excel_import_errors`

### Cross-cutting (6)
`audit_logs` (append-only, polymorphic), `event_outbox` (transactional outbox, append-only), `analytics_events` (append-only, partition once >10M rows)
`customer_addresses` (snapshot to `booking_addresses` on booking creation)
`cms_pages`, `app_settings`, `feature_flags`

## Append-only tables (NEVER use softDeletes, NEVER UPDATE except status fields)

`wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `audit_logs`, `payments` (status-only updates), `commissions`, `booking_state_transitions`, `event_outbox`, `analytics_events`, `loyalty_ledger`, `booking_snapshots`, `chat_message_log`, `search_logs`, `payment_attempts`

Note: `reconciliation_runs` and `reconciliation_findings` allow status/resolution updates but no deletes.

## Soft-deleted tables (have `deleted_at`)

`users`, `vendor_profiles`, `services`, `bookings`, `service_reviews`, `vendor_reviews`, `categories`, `occasions`, `customer_addresses`

## Per-product-type pattern reminder

Three columns/tables/lifecycles where it matters:
- `services.product_type` ENUM('rental','sale','digital') — discriminator
- 3 detail tables (1:1 with `service_id` PK)
- `booking_items.product_type` (denormalized for fast filter)
- `booking_items.item_status` runs **3 different state machines** (one per type)
- `category_field_schemas` keyed by `(category_id, product_type)`
- `commission_rates` keyed by `(category_id, product_type)` — most-specific wins
- `vendor_approved_product_types` — vendor approved per type independently
- `service_inventory_reservations.product_type` denormalized for query speed

## Money columns — exact convention

```php
$table->unsignedBigInteger('total_minor');
$table->char('total_currency', 3);
```

Always paired. Eloquent: cast via custom `MoneyCast` returning `Brick\Money\Money`. Never store as `DECIMAL` or `FLOAT`.

## Translatable JSON columns — common ones

`name`, `description`, `short_description`, `long_description`, `bio`, `business_name`, `address_line`, `subject`, `body`, `theme`, `customer_notes`, `vendor_notes`, `rejection_reason`, `vendor_explanation`, `review_notes`, `failure_message`, `reason`, `field_label`, `label`, `meta_description`

Shape: `{"en": "...", "ar": "..."}`

## Foreign key audit columns

These appear on user-auditable tables (NOT on append-only ledger tables):
- `created_by` BIGINT NULL FK→users
- `updated_by` BIGINT NULL FK→users
- `deleted_by` BIGINT NULL FK→users (only on soft-delete tables)

## Critical indexes to remember

| Table | Index | Why |
|---|---|---|
| `services` | `(vendor_profile_id, slug)` UNIQUE | Composite (per locked decision) |
| `services` | `(category_id, product_type, status)` | Catalog browse |
| `booking_vendors` | `(vendor_profile_id, sub_status, response_deadline)` | Vendor queue |
| `payments` | `(gateway, gateway_ref)` UNIQUE | Idempotency |
| `service_inventory_reservations` | `(service_id, reserved_starts_at, reserved_ends_at, status)` | Rental overlap query |
| `service_inventory_reservations` | `(status, expires_at)` | Cleanup job |
| `event_outbox` | `(status, next_retry_at)` | Worker poll |
| `booking_snapshots` | `(booking_id, version)` UNIQUE | Latest snapshot lookup |
| `booking_locks` | `(resource_type, resource_id, released_at)` UNIQUE | One active lock per resource |

## What forbids a schema change

If asked to add/modify a table, push back if:
- Adding `softDeletes()` to an append-only table
- Storing money as DECIMAL/FLOAT (use `_minor` + `_currency`)
- Adding `service_search_index` table (search is Meilisearch-only)
- Single Table Inheritance for services (use polymorphic base + 3 detail tables)
- Three independent top-level service tables (use polymorphic base)
- `Schema::create` without `utf8mb4` charset
- Top-level user-facing entity without `public_id` ULID
- Translatable field as separate `name_en` / `name_ar` columns (use JSON)
