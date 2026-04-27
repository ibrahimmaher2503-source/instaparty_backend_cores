# InstaParty — Phase 1 DB Schema (LOCKED)

> **Sources:** Tech Decisions §4, all 7 v1 schema decisions, and the v2 additions (booking_snapshots, service_inventory_reservations, event_outbox, booking status split, booking_locks, customer_addresses, notification_preferences, analytics_events).
>
> **Locked decisions:**
> - Search: **Option A** — Meilisearch via Scout only. No `service_search_index` MySQL table.
> - Cart hold TTL: **15 minutes** (`held` state, before submission).
> - Payment hold TTL: **24 hours** (after submission, awaiting payment).
> - Vendor response SLA: **24 hours** (`booking_vendors.response_deadline`).
> - Reviews: BOTH `service_reviews` AND `vendor_reviews`.
> - Loyalty: per-vendor only (no platform-wide loyalty).
> - Customer addresses: YES, included in Phase 1.
> - Excel imports: strict no partial commits.
> - Service inventory: reservation system included.
> - Vendor display name (`business_name`): translatable JSON.

**60 tables, 13 modules.**

---

## Table of contents

0. Conventions
1. Module overview (60-table inventory)
2. Framework + Spatie tables
3. Identity module (8 tables)
4. Geography / Shared (3 tables)
5. Catalog (13 tables)
6. Discovery (4 tables)
7. Booking & Negotiation (10 tables)
8. Payments (5 tables)
9. Settlement (6 tables)
10. Reviews (4 tables)
11. Communication (9 tables)
12. Loyalty (4 tables)
13. Imports (2 tables)
14. Cross-cutting (6 tables)
15. Migration order

---

## 0. Conventions (recap)

| Convention | Rule |
|---|---|
| Internal PK | `id` BIGINT UNSIGNED auto-increment |
| External ID | `public_id` CHAR(26) ULID, UNIQUE, exposed in URLs |
| Timestamps | `created_at`, `updated_at` (and `deleted_at` where soft-deletes apply) |
| User audit FKs | `created_by`, `updated_by`, `deleted_by` BIGINT NULL where useful |
| Money | `{field}_minor` BIGINT UNSIGNED + `{field}_currency` CHAR(3) |
| Translatable | JSON column with shape `{"en": "...", "ar": "..."}` |
| Enums | MySQL ENUM in DDL, backed by PHP backed enum cast |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` enforced per migration |
| Soft deletes | ONLY where listed in `02_Tech_Decisions.md` §4 |
| Append-only (no soft delete, no UPDATE except status) | `wallet_ledger`, `audit_logs`, `payments`, `commissions`, `withdrawals` (status only), `booking_state_transitions`, `event_outbox`, `analytics_events`, `loyalty_ledger`, `booking_snapshots` |

---

## 1. Module Overview

| # | Module | Tables |
|---|---|---|
| 0 | Framework + Spatie | `users`, `password_reset_tokens`, `sessions`, `personal_access_tokens`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `notifications`, `media` (spatie) |
| 1 | Identity (8) | `vendor_profiles`, `vendor_documents`, `vendor_approved_product_types`, `vendor_business_hours`, `vendor_coverage_areas`, `customer_profiles`, `user_devices`, `two_factor_secrets` (+ Spatie tables) |
| 2 | Geography / Shared (3) | `governorates`, `regions`, `cities` |
| 3 | Catalog (13) | `occasions`, `categories`, `occasion_category`, `category_field_schemas`, `service_themes`, `services`, `service_rental_details`, `service_sale_details`, `service_digital_details`, `service_pricing_tiers`, `service_availability_blocks`, `service_excluded_dates`, `service_themes_pivot`, **`service_inventory_reservations`** |
| 4 | Discovery (4) | `wishlists`, `wishlist_items`, `saved_searches`, `search_logs` |
| 5 | Booking & Negotiation (10) | `bookings`, `booking_addresses`, **`booking_snapshots`**, **`booking_locks`**, `booking_vendors`, `booking_items`, `booking_modifications`, `booking_modification_items`, `booking_state_transitions`, `booking_customer_notes` |
| 6 | Payments (5) | `payments`, `payment_attempts`, `refunds`, `idempotency_keys`, `gateway_webhook_logs` |
| 7 | Settlement (6) | `wallets`, `wallet_ledger`, `commissions`, `commission_rates`, `withdrawals`, `settlement_runs` |
| 8 | Reviews (4) | `service_reviews`, `vendor_reviews`, `review_responses`, `review_moderation_log` |
| 9 | Communication (9) | `chat_threads`, `chat_message_log`, `chat_moderation_flags`, `notification_templates`, `notification_dispatches`, **`notification_preferences`**, `campaigns`, `campaign_runs`, `campaign_recipients` |
| 10 | Loyalty (4) | `loyalty_programs`, `loyalty_rules`, `loyalty_ledger`, `loyalty_redemptions` |
| 11 | Imports (2) | `excel_imports`, `excel_import_errors` |
| 12 | Cross-cutting (6) | `audit_logs`, **`event_outbox`**, **`analytics_events`**, `customer_addresses`, `cms_pages`, `app_settings`, `feature_flags` |

**Bold** = added in v2 (after the original 7 decisions).

---

## 2. Framework + Spatie Tables

Standard Laravel + Sanctum + Spatie Permission + Spatie Media Library. Only InstaParty-specific extensions to `users` are detailed below.

### `users`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | ULID |
| name | VARCHAR(160) | NO | Display name |
| email | VARCHAR(190) UNIQUE | YES | Optional for customers |
| email_verified_at | TIMESTAMP | YES | |
| phone_e164 | VARCHAR(20) UNIQUE | NO | E.164 |
| phone_verified_at | TIMESTAMP | YES | |
| password | VARCHAR(255) | YES | NULL allowed for OAuth-only |
| preferred_locale | ENUM('ar','en') | NO | Default `'ar'` |
| timezone | VARCHAR(64) | NO | Default `'Africa/Cairo'` |
| numeral_system | ENUM('western','eastern') | NO | Default `'western'` |
| status | ENUM('active','suspended','banned') | NO | Default `'active'` |
| last_login_at | TIMESTAMP | YES | |
| last_login_ip | VARCHAR(45) | YES | |
| remember_token | VARCHAR(100) | YES | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:** `phone_e164` UNIQUE, `email` UNIQUE, `(status, deleted_at)`.

### Spatie Permission seeded permissions (per-product-type)

- `service.create.{rental|sale|digital}.own`
- `service.update.{rental|sale|digital}.own`
- `service.delete.{rental|sale|digital}.own`
- `service.publish.{rental|sale|digital}.own`
- `booking.respond.own`, `wallet.withdraw.own`
- Admin: `vendor.approve`, `vendor.approve.{type}`, `service.moderate`, `commission.manage`, `withdrawal.approve`, `audit.view`, `report.view`

---

## 3. Identity Module (8 tables)

### `vendor_profiles`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | UNIQUE (1:1) |
| business_name | JSON | NO | Translatable `{en,ar}` |
| slug | VARCHAR(160) UNIQUE | NO | URL-safe |
| bio | JSON | YES | Translatable |
| logo_path | VARCHAR(500) | YES | |
| cover_path | VARCHAR(500) | YES | |
| business_type | ENUM('individual','company') | NO | |
| commercial_register_no | VARCHAR(60) | YES | |
| tax_id | VARCHAR(60) | YES | |
| national_id | VARCHAR(20) | YES | |
| primary_governorate_id | BIGINT UNSIGNED FK | NO | |
| primary_city_id | BIGINT UNSIGNED FK | NO | |
| address_line | JSON | YES | Translatable |
| latitude | DECIMAL(10,7) | YES | |
| longitude | DECIMAL(10,7) | YES | |
| approval_status | ENUM('pending','approved','rejected','suspended') | NO | Default `'pending'` |
| approved_at | TIMESTAMP | YES | |
| approved_by | BIGINT UNSIGNED FK→users | YES | |
| rejection_reason | JSON | YES | Translatable |
| bank_holder_name | VARCHAR(160) | YES | |
| bank_iban | VARCHAR(40) | YES | |
| bank_swift | VARCHAR(20) | YES | |
| bank_name | VARCHAR(120) | YES | |
| rating_avg | DECIMAL(3,2) | NO | Default 0 |
| rating_count | INT UNSIGNED | NO | Default 0 |
| response_time_avg_minutes | INT UNSIGNED | YES | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:** `(approval_status, deleted_at)`, `(primary_city_id)`, `slug` UNIQUE.

### `vendor_documents`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| doc_type | ENUM('cr','tax_card','national_id','iban_proof','other') | NO | |
| file_path | VARCHAR(500) | NO | Private bucket |
| file_name | VARCHAR(255) | NO | |
| status | ENUM('pending','approved','rejected') | NO | Default `'pending'` |
| reviewed_at | TIMESTAMP | YES | |
| reviewed_by | BIGINT UNSIGNED FK→users | YES | |
| review_notes | JSON | YES | Translatable |
| created_at, updated_at | TIMESTAMPS | | |

### `vendor_approved_product_types`

> Per-type approval is independent of overall vendor approval (Tech Decisions §2.4).

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| product_type | ENUM('rental','sale','digital') | NO | |
| approved_at | TIMESTAMP | NO | |
| approved_by | BIGINT UNSIGNED FK→users | NO | |
| revoked_at | TIMESTAMP | YES | |
| revoked_by | BIGINT UNSIGNED FK→users | YES | |
| revoke_reason | JSON | YES | Translatable |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(vendor_profile_id, product_type, revoked_at)`.

### `vendor_business_hours`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| day_of_week | TINYINT UNSIGNED | NO | 0=Sun..6=Sat |
| opens_at | TIME | YES | NULL = closed |
| closes_at | TIME | YES | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(vendor_profile_id, day_of_week)`.

### `vendor_coverage_areas`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| city_id | BIGINT UNSIGNED FK→cities | NO | |
| delivery_fee_minor | BIGINT UNSIGNED | NO | Default 0 |
| delivery_fee_currency | CHAR(3) | NO | |
| min_order_minor | BIGINT UNSIGNED | NO | Default 0 |
| min_order_currency | CHAR(3) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(vendor_profile_id, city_id)`.

### `customer_profiles`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | UNIQUE |
| date_of_birth | DATE | YES | |
| gender | ENUM('male','female','prefer_not') | YES | |
| how_heard_about_us | VARCHAR(120) | YES | |
| children | JSON | YES | `[{name, dob, gender}]` |
| accepts_marketing | BOOLEAN | NO | Default `true` |
| created_at, updated_at | TIMESTAMPS | | |

### `user_devices`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| platform | ENUM('ios','android','web') | NO | |
| fcm_token | VARCHAR(255) | NO | |
| device_id | VARCHAR(190) | YES | |
| last_seen_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(user_id, fcm_token)`.

### `two_factor_secrets`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | UNIQUE |
| secret_encrypted | TEXT | NO | TOTP base32 |
| recovery_codes_encrypted | TEXT | NO | JSON array, encrypted |
| confirmed_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

---

## 4. Geography / Shared Module (3 tables)

### `governorates`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| name | JSON | NO | Translatable |
| code | VARCHAR(20) UNIQUE | NO | e.g., `EG-C` |
| country_code | CHAR(2) | NO | ISO-3166-1 alpha-2 |
| is_active | BOOLEAN | NO | Default `true` |
| sort_order | INT UNSIGNED | NO | Default 0 |
| created_at, updated_at | TIMESTAMPS | | |

### `regions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| governorate_id | BIGINT UNSIGNED FK | NO | |
| name | JSON | NO | Translatable |
| is_active | BOOLEAN | NO | Default `true` |
| sort_order | INT UNSIGNED | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `cities`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| region_id | BIGINT UNSIGNED FK | NO | |
| governorate_id | BIGINT UNSIGNED FK | NO | Denormalized for fast filter |
| name | JSON | NO | Translatable |
| latitude | DECIMAL(10,7) | YES | Centroid |
| longitude | DECIMAL(10,7) | YES | |
| is_active | BOOLEAN | NO | |
| sort_order | INT UNSIGNED | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** `(governorate_id, is_active)`, `(region_id, is_active)`.

---

## 5. Catalog Module (13 tables)

### `occasions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| code | VARCHAR(60) UNIQUE | NO | `birthday`, `wedding`, `engagement` |
| name | JSON | NO | Translatable |
| description | JSON | YES | Translatable |
| icon_path | VARCHAR(500) | YES | |
| sort_order | INT UNSIGNED | NO | |
| is_active | BOOLEAN | NO | Default `true` |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

### `categories`

> Hierarchical, 2-level (group → subcategory).

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| parent_id | BIGINT UNSIGNED FK→categories | YES | Self-ref |
| code | VARCHAR(80) UNIQUE | NO | |
| name | JSON | NO | Translatable |
| description | JSON | YES | Translatable |
| icon_path | VARCHAR(500) | YES | |
| allowed_product_types | JSON | NO | e.g., `["rental","sale"]` |
| sort_order | INT UNSIGNED | NO | |
| is_active | BOOLEAN | NO | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:** `(parent_id, is_active)`.

### `occasion_category` (pivot)

| Column | Type | Null | Notes |
|---|---|---|---|
| occasion_id | BIGINT UNSIGNED FK | NO | |
| category_id | BIGINT UNSIGNED FK | NO | |
| sort_order | INT UNSIGNED | NO | |

**PK:** composite `(occasion_id, category_id)`.

### `category_field_schemas`

> Defines dynamic required/optional fields per `(category × product_type)`. Drives the dynamic vendor service form (FR-19, FR-20).

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| category_id | BIGINT UNSIGNED FK | NO | |
| product_type | ENUM('rental','sale','digital') | NO | |
| field_key | VARCHAR(80) | NO | e.g., `requires_electricity` |
| field_label | JSON | NO | Translatable |
| field_type | ENUM('text','number','boolean','select','multiselect','date') | NO | |
| options | JSON | YES | For select types |
| is_required | BOOLEAN | NO | |
| is_filterable | BOOLEAN | NO | Exposed as Meilisearch facet |
| validation_rules | JSON | YES | Laravel rule strings |
| sort_order | INT UNSIGNED | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(category_id, product_type, field_key)`.

### `service_themes`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| code | VARCHAR(80) UNIQUE | NO | |
| name | JSON | NO | Translatable |
| icon_path | VARCHAR(500) | YES | |
| is_active | BOOLEAN | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `services` (polymorphic base)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| category_id | BIGINT UNSIGNED FK | NO | |
| product_type | ENUM('rental','sale','digital') | NO | **Discriminator** |
| vendor_sku | VARCHAR(80) | YES | |
| name | JSON | NO | Translatable |
| slug | VARCHAR(190) | NO | |
| short_description | JSON | YES | Translatable |
| long_description | JSON | YES | Translatable |
| base_price_minor | BIGINT UNSIGNED | NO | |
| base_price_currency | CHAR(3) | NO | |
| status | ENUM('draft','pending_review','published','rejected','archived') | NO | Default `'draft'` |
| moderation_notes | JSON | YES | Translatable |
| moderated_at | TIMESTAMP | YES | |
| moderated_by | BIGINT UNSIGNED FK→users | YES | |
| video_url | VARCHAR(500) | YES | |
| external_url | VARCHAR(500) | YES | |
| rating_avg | DECIMAL(3,2) | NO | Default 0 |
| rating_count | INT UNSIGNED | NO | Default 0 |
| views_count | INT UNSIGNED | NO | Default 0 |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:**
- `(vendor_profile_id, status)`
- `(category_id, product_type, status)`
- UNIQUE `(vendor_profile_id, slug)` ← composite per locked decision
- `public_id` UNIQUE

### `service_rental_details` (1:1)

| Column | Type | Null | Notes |
|---|---|---|---|
| service_id | BIGINT UNSIGNED PK FK→services | NO | 1:1 PK |
| size_dimensions | JSON | YES | `{length, width, height, unit}` |
| weight_kg | DECIMAL(8,2) | YES | |
| suggested_age_min | TINYINT UNSIGNED | YES | |
| suggested_age_max | TINYINT UNSIGNED | YES | |
| requires_electricity | BOOLEAN | NO | |
| requires_outdoor_space | BOOLEAN | NO | |
| min_space_required | JSON | YES | `{length, width, unit}` |
| default_rental_duration_hours | SMALLINT UNSIGNED | NO | |
| min_rental_duration_hours | SMALLINT UNSIGNED | NO | |
| max_rental_duration_hours | SMALLINT UNSIGNED | YES | NULL = unlimited |
| setup_time_minutes | SMALLINT UNSIGNED | NO | Default 0 |
| teardown_time_minutes | SMALLINT UNSIGNED | NO | Default 0 |
| security_deposit_minor | BIGINT UNSIGNED | NO | Default 0 |
| security_deposit_currency | CHAR(3) | NO | |
| theme_id | BIGINT UNSIGNED FK→service_themes | YES | |
| custom_attributes | JSON | YES | Per-category-schema values |
| created_at, updated_at | TIMESTAMPS | | |

### `service_sale_details` (1:1)

| Column | Type | Null | Notes |
|---|---|---|---|
| service_id | BIGINT UNSIGNED PK FK→services | NO | |
| size_dimensions | JSON | YES | |
| weight_kg | DECIMAL(8,2) | YES | |
| suggested_age_min | TINYINT UNSIGNED | YES | |
| suggested_age_max | TINYINT UNSIGNED | YES | |
| is_perishable | BOOLEAN | NO | Default `false` |
| is_made_to_order | BOOLEAN | NO | Default `false` |
| lead_time_hours | SMALLINT UNSIGNED | YES | Required if `is_made_to_order` |
| stock_quantity | INT UNSIGNED | YES | NULL = unlimited |
| allows_customization | BOOLEAN | NO | Default `false` |
| customization_fields | JSON | YES | |
| min_order_quantity | INT UNSIGNED | NO | Default 1 |
| max_order_quantity | INT UNSIGNED | YES | |
| theme_id | BIGINT UNSIGNED FK | YES | |
| custom_attributes | JSON | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `service_digital_details` (1:1)

| Column | Type | Null | Notes |
|---|---|---|---|
| service_id | BIGINT UNSIGNED PK FK→services | NO | |
| delivery_method | ENUM('email','sms','in_app','redirect_url') | NO | |
| has_expiry | BOOLEAN | NO | Default `false` |
| expiry_days_after_purchase | SMALLINT UNSIGNED | YES | |
| is_refundable_after_delivery | BOOLEAN | NO | Default `false` |
| redemption_url_template | VARCHAR(500) | YES | |
| code_pool_id | BIGINT UNSIGNED | YES | Phase 1.5 ext |
| asset_path | VARCHAR(500) | YES | |
| customization_fields | JSON | YES | |
| custom_attributes | JSON | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `service_pricing_tiers`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| service_id | BIGINT UNSIGNED FK | NO | |
| tier_kind | ENUM('quantity','duration_hours','duration_days') | NO | |
| min_units | INT UNSIGNED | NO | |
| max_units | INT UNSIGNED | YES | |
| price_minor | BIGINT UNSIGNED | NO | |
| price_currency | CHAR(3) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `service_availability_blocks`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| service_id | BIGINT UNSIGNED FK | NO | |
| starts_at | TIMESTAMP | NO | UTC |
| ends_at | TIMESTAMP | NO | UTC |
| reason | JSON | YES | Translatable |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** `(service_id, starts_at, ends_at)`.

### `service_excluded_dates`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| service_id | BIGINT UNSIGNED FK | YES | NULL = applies to all vendor services |
| date | DATE | NO | |
| created_at | TIMESTAMP | NO | |

**Indexes:** UNIQUE `(vendor_profile_id, service_id, date)`.

### `service_themes_pivot`

| Column | Type | Null | Notes |
|---|---|---|---|
| service_id | BIGINT UNSIGNED FK | NO | |
| theme_id | BIGINT UNSIGNED FK | NO | |

**PK:** composite.

### `service_inventory_reservations` (CRITICAL — overselling guard)

> Cart hold = **15 min**, payment hold = **24h** per locked decision.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| service_id | BIGINT UNSIGNED FK→services | NO | |
| product_type | ENUM('rental','sale','digital') | NO | Denormalized |
| booking_item_id | BIGINT UNSIGNED FK→booking_items | YES | NULL while in `held` (cart) |
| holder_user_id | BIGINT UNSIGNED FK→users | NO | |
| quantity | INT UNSIGNED | NO | Default 1 |
| reserved_starts_at | TIMESTAMP | YES | Rental only |
| reserved_ends_at | TIMESTAMP | YES | Rental only |
| status | ENUM('held','confirmed','released','expired') | NO | Default `'held'` |
| held_at | TIMESTAMP | NO | |
| expires_at | TIMESTAMP | NO | TTL: 15 min cart, 24h after submission |
| confirmed_at | TIMESTAMP | YES | |
| released_at | TIMESTAMP | YES | |
| release_reason | ENUM('payment_failed','customer_cancelled','vendor_rejected','expired','admin_release') | YES | |
| idempotency_key | VARCHAR(120) | YES | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:**
- `(service_id, status)`
- `(service_id, reserved_starts_at, reserved_ends_at, status)` — rental overlap query
- `(status, expires_at)` — cleanup job
- `(booking_item_id)`

**Concurrency:** writes use `SELECT … FOR UPDATE` on parent `services` row inside `DB::transaction`. Cleanup job (`ReleaseExpiredReservations`) runs every minute, transitions `held → expired` past `expires_at`.

> **Media:** Use `spatie/laravel-medialibrary`'s `media` table — no custom table. Service galleries attach via `Media::registerCollection('gallery')`.

---

## 6. Discovery Module (4 tables)

### `wishlists`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK | NO | |
| name | VARCHAR(120) | NO | Default `'Default'` |
| created_at, updated_at | TIMESTAMPS | | |

### `wishlist_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| wishlist_id | BIGINT UNSIGNED FK | NO | |
| service_id | BIGINT UNSIGNED FK | NO | |
| created_at | TIMESTAMP | NO | |

**Indexes:** UNIQUE `(wishlist_id, service_id)`.

### `saved_searches`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK | NO | |
| label | VARCHAR(120) | NO | |
| filters | JSON | NO | Filter snapshot |
| created_at, updated_at | TIMESTAMPS | | |

### `search_logs` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK | YES | NULL for anon |
| query | VARCHAR(255) | NO | |
| locale | ENUM('ar','en') | NO | |
| filters | JSON | YES | |
| results_count | INT UNSIGNED | NO | |
| clicked_service_id | BIGINT UNSIGNED FK | YES | |
| created_at | TIMESTAMP | NO | |

> **NO `service_search_index` table.** Search is Meilisearch-only via Laravel Scout (locked Option A).

---

## 7. Booking & Negotiation Module (10 tables)

### `bookings` — **3 orthogonal status columns**

> The single `status` column was split per locked v2 decision.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| reference_no | VARCHAR(20) UNIQUE | NO | e.g., `IP-2026-000123` |
| customer_id | BIGINT UNSIGNED FK→users | NO | |
| occasion_id | BIGINT UNSIGNED FK | NO | |
| **lifecycle_status** | ENUM('draft','submitted','vendor_review','customer_review','confirmed','active','completed','cancelled') | NO | Default `'draft'` |
| **payment_status** | ENUM('unpaid','partial','paid','refund_pending','partially_refunded','refunded') | NO | Default `'unpaid'` |
| **fulfillment_status** | ENUM('not_started','in_progress','partially_completed','completed','failed') | NO | Default `'not_started'` |
| event_starts_at | TIMESTAMP | NO | UTC |
| event_ends_at | TIMESTAMP | NO | UTC |
| event_address_id | BIGINT UNSIGNED FK→booking_addresses | YES | |
| guest_count | SMALLINT UNSIGNED | YES | |
| theme | JSON | YES | Translatable, free-text |
| celebrant_name | VARCHAR(120) | YES | |
| celebrant_dob | DATE | YES | |
| celebrant_gender | ENUM('male','female','prefer_not') | YES | |
| customer_notes | JSON | YES | Translatable, free-text |
| subtotal_minor | BIGINT UNSIGNED | NO | |
| subtotal_currency | CHAR(3) | NO | |
| delivery_total_minor | BIGINT UNSIGNED | NO | Default 0 |
| delivery_total_currency | CHAR(3) | NO | |
| discount_total_minor | BIGINT UNSIGNED | NO | Default 0 |
| discount_total_currency | CHAR(3) | NO | |
| loyalty_redeemed_points | INT UNSIGNED | NO | Default 0 |
| loyalty_redeemed_minor | BIGINT UNSIGNED | NO | Default 0 |
| total_minor | BIGINT UNSIGNED | NO | |
| total_currency | CHAR(3) | NO | |
| amount_paid_minor | BIGINT UNSIGNED | NO | Default 0 |
| tax_invoice_required | BOOLEAN | NO | Default `false` |
| tax_invoice_name | VARCHAR(160) | YES | |
| tax_invoice_id | VARCHAR(60) | YES | |
| submitted_at | TIMESTAMP | YES | |
| confirmed_at | TIMESTAMP | YES | |
| cancelled_at | TIMESTAMP | YES | |
| cancelled_by | BIGINT UNSIGNED FK→users | YES | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:**
- `(customer_id, lifecycle_status, created_at)`
- `(lifecycle_status, event_starts_at)`
- `(payment_status, created_at)`
- `(fulfillment_status, event_starts_at)`
- `public_id` UNIQUE, `reference_no` UNIQUE

**State derivation:**
- `lifecycle_status` driven by booking events (state machine)
- `payment_status` computed from `amount_paid_minor` vs `total_minor` and `refunds` rows; updated by listener on `PaymentCaptured` / `RefundCompleted`
- `fulfillment_status` aggregated from all `booking_items.item_status`; updated by listener on `BookingItemStateChanged`

### `booking_addresses`

> Snapshot, not FK to `customer_addresses`. Booking history stays immutable when customer edits saved address.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| city_id | BIGINT UNSIGNED FK | NO | |
| address_line | VARCHAR(255) | NO | |
| building | VARCHAR(60) | YES | |
| floor | VARCHAR(20) | YES | |
| apartment | VARCHAR(20) | YES | |
| landmark | VARCHAR(255) | YES | |
| latitude | DECIMAL(10,7) | YES | |
| longitude | DECIMAL(10,7) | YES | |
| recipient_name | VARCHAR(120) | NO | |
| recipient_phone_e164 | VARCHAR(20) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `booking_snapshots` (append-only versioned read model)

> Versioned read-model snapshots. UI reads the latest snapshot instead of joining 6 tables.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_id | BIGINT UNSIGNED FK→bookings | NO | |
| version | INT UNSIGNED | NO | Monotonic per booking, starts at 1 |
| snapshot | JSON | NO | Full resolved state |
| trigger_kind | ENUM('booking_created','vendor_responded','customer_decided','modification_proposed','payment_captured','refund_issued','state_changed','admin_action') | NO | |
| trigger_reference_type | VARCHAR(120) | YES | |
| trigger_reference_id | BIGINT UNSIGNED | YES | |
| triggered_by | BIGINT UNSIGNED FK→users | YES | NULL = system |
| created_at | TIMESTAMP | NO | No `updated_at` — append-only |

**Indexes:** UNIQUE `(booking_id, version)`, `(booking_id, created_at DESC)`.

### `booking_locks`

> Application-level pessimistic locks for cross-request coordination.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| resource_type | VARCHAR(80) | NO | `booking`, `booking_vendor`, `service`, `vendor`, `withdrawal` |
| resource_id | BIGINT UNSIGNED | NO | |
| lock_token | CHAR(36) | NO | UUID |
| locked_by_user_id | BIGINT UNSIGNED FK→users | YES | NULL = system |
| lock_purpose | ENUM('modification','payment','vendor_response','admin_action','settlement') | NO | |
| acquired_at | TIMESTAMP | NO | |
| expires_at | TIMESTAMP | NO | |
| released_at | TIMESTAMP | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:** UNIQUE `(resource_type, resource_id, released_at)` — only one active lock per resource.

### `booking_vendors`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| sub_status | ENUM('pending','accepted','modified','rejected','cancelled','in_progress','completed') | NO | Default `'pending'` |
| response_deadline | TIMESTAMP | NO | 24h SLA |
| responded_at | TIMESTAMP | YES | |
| rejection_reason | JSON | YES | Translatable |
| vendor_notes | JSON | YES | Translatable |
| subtotal_minor | BIGINT UNSIGNED | NO | |
| subtotal_currency | CHAR(3) | NO | |
| delivery_fee_minor | BIGINT UNSIGNED | NO | Default 0 |
| delivery_fee_currency | CHAR(3) | NO | |
| commission_minor | BIGINT UNSIGNED | NO | Default 0 |
| commission_currency | CHAR(3) | NO | |
| vendor_payout_minor | BIGINT UNSIGNED | NO | Default 0 |
| vendor_payout_currency | CHAR(3) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:**
- `(vendor_profile_id, sub_status, response_deadline)` — vendor queue
- UNIQUE `(booking_id, vendor_profile_id)`

### `booking_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_vendor_id | BIGINT UNSIGNED FK | NO | |
| service_id | BIGINT UNSIGNED FK | NO | |
| product_type | ENUM('rental','sale','digital') | NO | Denormalized |
| name_snapshot | JSON | NO | Translatable, frozen |
| unit_price_minor | BIGINT UNSIGNED | NO | |
| unit_price_currency | CHAR(3) | NO | |
| quantity | INT UNSIGNED | NO | Default 1 |
| line_total_minor | BIGINT UNSIGNED | NO | |
| line_total_currency | CHAR(3) | NO | |
| effective_starts_at | TIMESTAMP | NO | Defaults to event_starts_at |
| effective_ends_at | TIMESTAMP | NO | |
| has_item_slot_override | BOOLEAN | NO | FR-7, FR-8 |
| customization_data | JSON | YES | |
| type_snapshot | JSON | NO | Frozen rules at booking time |
| fulfillment_data | JSON | YES | Runtime state |
| item_status | VARCHAR(40) | NO | Per-type state machine value |
| commission_bps | INT UNSIGNED | NO | Locked rate at booking time |
| commission_minor | BIGINT UNSIGNED | NO | |
| commission_currency | CHAR(3) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** `(booking_vendor_id)`, `(service_id)`, `(product_type, item_status)`.

### `booking_modifications`

> Vendor-proposed change customer must re-approve (FR-11..FR-14).

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_vendor_id | BIGINT UNSIGNED FK | NO | |
| proposed_by | BIGINT UNSIGNED FK→users | NO | |
| proposal_kind | ENUM('add_item','remove_item','change_quantity','change_price','change_slot','add_surcharge','add_note') | NO | |
| status | ENUM('pending','customer_accepted','customer_rejected','withdrawn','expired') | NO | Default `'pending'` |
| customer_decision_at | TIMESTAMP | YES | |
| expires_at | TIMESTAMP | YES | |
| vendor_explanation | JSON | YES | Translatable |
| diff_snapshot | JSON | NO | Before/after for highlighted UI |
| created_at, updated_at | TIMESTAMPS | | |

### `booking_modification_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| booking_modification_id | BIGINT UNSIGNED FK | NO | |
| target_booking_item_id | BIGINT UNSIGNED FK→booking_items | YES | NULL for new items |
| change_kind | ENUM('add','remove','update') | NO | |
| payload | JSON | NO | New values |
| created_at | TIMESTAMP | NO | |

### `booking_state_transitions` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| transitionable_type | VARCHAR(120) | NO | `Booking`, `BookingVendor`, `BookingItem` |
| transitionable_id | BIGINT UNSIGNED | NO | |
| from_state | VARCHAR(40) | YES | NULL on create |
| to_state | VARCHAR(40) | NO | |
| triggered_by | BIGINT UNSIGNED FK→users | YES | NULL = system |
| trigger_kind | ENUM('user','system','admin','webhook','timeout') | NO | |
| context | JSON | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(transitionable_type, transitionable_id, created_at)`.

### `booking_customer_notes`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| body | TEXT | NO | Stored as-typed |
| detected_locale | ENUM('ar','en','mixed') | NO | |
| created_at | TIMESTAMP | NO | |

---

## 8. Payments Module (5 tables)

### `payments` (append-only, status-only updates)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | Payer |
| gateway | VARCHAR(40) | NO | `paymob`, etc. |
| gateway_ref | VARCHAR(190) | NO | |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| method | ENUM('card','wallet','installment','cash_on_delivery','transfer') | NO | |
| status | ENUM('pending','authorized','captured','failed','refunded','partially_refunded','voided') | NO | |
| captured_at | TIMESTAMP | YES | |
| failure_code | VARCHAR(80) | YES | |
| failure_message | JSON | YES | Translatable |
| metadata | JSON | YES | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(gateway, gateway_ref)` — idempotency, `(booking_id, status)`.

### `payment_attempts`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| payment_id | BIGINT UNSIGNED FK | NO | |
| attempt_no | INT UNSIGNED | NO | |
| request_payload | JSON | NO | Sanitized |
| response_payload | JSON | YES | |
| http_status | SMALLINT UNSIGNED | YES | |
| created_at | TIMESTAMP | NO | |

### `refunds`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| payment_id | BIGINT UNSIGNED FK | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| reason_code | VARCHAR(80) | NO | |
| reason_notes | JSON | YES | Translatable |
| gateway_ref | VARCHAR(190) | YES | |
| status | ENUM('pending','processing','completed','failed') | NO | |
| initiated_by | BIGINT UNSIGNED FK→users | NO | |
| processed_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `idempotency_keys`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| key | VARCHAR(190) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | YES | |
| route | VARCHAR(190) | NO | |
| request_hash | CHAR(64) | NO | SHA-256 of body |
| response_status | SMALLINT UNSIGNED | YES | |
| response_body | JSON | YES | |
| expires_at | TIMESTAMP | NO | 24h TTL |
| created_at | TIMESTAMP | NO | |

**Indexes:** `key` UNIQUE, `(expires_at)`.

### `gateway_webhook_logs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| gateway | VARCHAR(40) | NO | |
| event_type | VARCHAR(80) | NO | |
| signature_valid | BOOLEAN | NO | |
| payload | JSON | NO | |
| processed_at | TIMESTAMP | YES | |
| processing_error | TEXT | YES | |
| created_at | TIMESTAMP | NO | |

---

## 9. Settlement Module (6 tables)

### `wallets`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| owner_type | VARCHAR(120) | NO | `VendorProfile` or `Platform` |
| owner_id | BIGINT UNSIGNED | NO | |
| currency | CHAR(3) | NO | One wallet per (owner, currency) |
| balance_available_minor | BIGINT UNSIGNED | NO | Default 0 |
| balance_pending_minor | BIGINT UNSIGNED | NO | Default 0 |
| balance_reserved_minor | BIGINT UNSIGNED | NO | Default 0 — pending withdrawals |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(owner_type, owner_id, currency)`.

### `wallet_ledger` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| wallet_id | BIGINT UNSIGNED FK | NO | |
| direction | ENUM('credit','debit') | NO | |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| balance_after_minor | BIGINT UNSIGNED | NO | Snapshot |
| reference_type | VARCHAR(120) | NO | `Booking`, `Withdrawal`, `Refund`, `LoyaltyRedemption` |
| reference_id | BIGINT UNSIGNED | NO | |
| description | JSON | YES | Translatable |
| created_at | TIMESTAMP | NO | No `updated_at` |

**Indexes:** `(wallet_id, created_at)`, `(reference_type, reference_id)`.

### `commissions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| booking_vendor_id | BIGINT UNSIGNED FK | NO | |
| booking_item_id | BIGINT UNSIGNED FK | YES | NULL for vendor-level (delivery) |
| basis_minor | BIGINT UNSIGNED | NO | |
| basis_currency | CHAR(3) | NO | |
| rate_bps | INT UNSIGNED | NO | Snapshot |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| status | ENUM('pending','settled','reversed') | NO | |
| settled_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `commission_rates`

> Most-specific match wins: `(category × type) → (category × NULL) → (NULL × type) → (NULL × NULL)`.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| category_id | BIGINT UNSIGNED FK | YES | NULL = any category |
| product_type | ENUM('rental','sale','digital') | YES | NULL = any type |
| rate_bps | INT UNSIGNED | NO | 1500 = 15% |
| currency | CHAR(3) | YES | NULL = any |
| effective_from | TIMESTAMP | NO | |
| effective_to | TIMESTAMP | YES | NULL = open-ended |
| created_by | BIGINT UNSIGNED FK→users | NO | |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** `(category_id, product_type, effective_from)`.

### `withdrawals`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| wallet_id | BIGINT UNSIGNED FK | NO | |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| status | ENUM('requested','approved','processing','paid','rejected','cancelled') | NO | |
| bank_holder_name_snapshot | VARCHAR(160) | NO | |
| bank_iban_snapshot | VARCHAR(40) | NO | |
| bank_name_snapshot | VARCHAR(120) | NO | |
| approved_by | BIGINT UNSIGNED FK→users | YES | |
| approved_at | TIMESTAMP | YES | |
| paid_at | TIMESTAMP | YES | |
| transfer_proof_path | VARCHAR(500) | YES | Private bucket |
| rejection_reason | JSON | YES | Translatable |
| created_at, updated_at | TIMESTAMPS | | |

### `settlement_runs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| period_start | TIMESTAMP | NO | |
| period_end | TIMESTAMP | NO | |
| status | ENUM('pending','running','completed','failed') | NO | |
| commissions_count | INT UNSIGNED | NO | |
| total_settled_minor | BIGINT UNSIGNED | NO | |
| total_settled_currency | CHAR(3) | NO | |
| started_by | BIGINT UNSIGNED FK→users | YES | |
| started_at | TIMESTAMP | YES | |
| completed_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

---

## 10. Reviews Module (4 tables)

### `service_reviews`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| service_id | BIGINT UNSIGNED FK | NO | |
| booking_item_id | BIGINT UNSIGNED FK UNIQUE | NO | One review per item |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| rating | TINYINT UNSIGNED | NO | 1–5 |
| body | TEXT | YES | |
| locale | ENUM('ar','en','mixed') | NO | |
| moderation_status | ENUM('pending','approved','rejected','hidden') | NO | Default `'pending'` |
| moderated_by | BIGINT UNSIGNED FK→users | YES | |
| moderated_at | TIMESTAMP | YES | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:** `(service_id, moderation_status)`, UNIQUE `booking_item_id`.

### `vendor_reviews`

> Same shape as `service_reviews` but tied to `booking_vendor_id`.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| booking_vendor_id | BIGINT UNSIGNED FK UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| rating | TINYINT UNSIGNED | NO | |
| body | TEXT | YES | |
| locale | ENUM('ar','en','mixed') | NO | |
| moderation_status | ENUM(...) | NO | Same as service_reviews |
| moderated_by | BIGINT UNSIGNED FK→users | YES | |
| moderated_at | TIMESTAMP | YES | |
| created_at, updated_at, deleted_at | TIMESTAMPS | | |

### `review_responses`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| review_type | ENUM('service','vendor') | NO | |
| review_id | BIGINT UNSIGNED | NO | Polymorphic |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| body | TEXT | NO | |
| locale | ENUM('ar','en','mixed') | NO | |
| moderation_status | ENUM(...) | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `review_moderation_log`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| review_type | VARCHAR(80) | NO | |
| review_id | BIGINT UNSIGNED | NO | |
| from_status | VARCHAR(40) | YES | |
| to_status | VARCHAR(40) | NO | |
| moderator_id | BIGINT UNSIGNED FK→users | NO | |
| reason | JSON | YES | Translatable |
| created_at | TIMESTAMP | NO | |

---

## 11. Communication Module (9 tables)

### `chat_threads`

> Audit metadata only. Actual messages live in Firestore.

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| firestore_thread_id | VARCHAR(120) UNIQUE | NO | |
| customer_id | BIGINT UNSIGNED FK→users | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| booking_id | BIGINT UNSIGNED FK | YES | |
| status | ENUM('open','locked','closed') | NO | Locked when admin freezes |
| locked_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `chat_message_log` (append-only mirror)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| chat_thread_id | BIGINT UNSIGNED FK | NO | |
| firestore_message_id | VARCHAR(120) | NO | |
| sender_id | BIGINT UNSIGNED FK→users | NO | |
| message_kind | ENUM('text','image','voice','system') | NO | |
| detected_locale | ENUM('ar','en','mixed') | YES | |
| flagged | BOOLEAN | NO | Default `false` |
| flag_reason | VARCHAR(80) | YES | `phone_pattern`, `email_pattern`, `manual` |
| redacted | BOOLEAN | NO | Default `false` |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(chat_thread_id, created_at)`, `(flagged)`.

### `chat_moderation_flags`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| chat_message_log_id | BIGINT UNSIGNED FK | NO | |
| flag_type | ENUM('phone','email','profanity','external_link','other') | NO | |
| matched_pattern | VARCHAR(255) | YES | |
| action_taken | ENUM('redact','warn','block','none') | NO | |
| reviewed_by | BIGINT UNSIGNED FK→users | YES | |
| reviewed_at | TIMESTAMP | YES | |
| created_at | TIMESTAMP | NO | |

### `notification_templates`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| event_key | VARCHAR(120) | NO | e.g., `booking.modified` |
| channel | ENUM('push','sms','whatsapp','email','in_app') | NO | |
| audience | ENUM('customer','vendor','admin') | NO | |
| subject | JSON | YES | Translatable |
| body | JSON | NO | Translatable |
| variables | JSON | YES | Documented placeholders |
| is_active | BOOLEAN | NO | Default `true` |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(event_key, channel, audience)`.

### `notification_dispatches`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| template_id | BIGINT UNSIGNED FK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| channel | ENUM(...) | NO | |
| locale | ENUM('ar','en') | NO | |
| status | ENUM('queued','sent','delivered','failed','bounced') | NO | |
| provider | VARCHAR(60) | YES | `fcm`, `twilio`, `mailchimp` |
| provider_ref | VARCHAR(190) | YES | |
| sent_at | TIMESTAMP | YES | |
| delivered_at | TIMESTAMP | YES | |
| error_message | TEXT | YES | |
| context | JSON | YES | Resolved variables |
| reference_type | VARCHAR(120) | YES | Polymorphic |
| reference_id | BIGINT UNSIGNED | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(user_id, channel, created_at)`, `(reference_type, reference_id)`.

### `notification_preferences`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK | NO | |
| channel | ENUM('push','sms','whatsapp','email','in_app') | NO | |
| event_category | ENUM('booking','marketing','system','chat','payment','review') | NO | |
| is_enabled | BOOLEAN | NO | Default `true` |
| quiet_hours_start | TIME | YES | User's tz |
| quiet_hours_end | TIME | YES | |
| timezone | VARCHAR(64) | YES | Falls back to `users.timezone` |
| created_at, updated_at | TIMESTAMPS | | |

**Indexes:** UNIQUE `(user_id, channel, event_category)`.

**Default behavior:** if no row exists, treat as `is_enabled = true`. **`system` category cannot be disabled.**

### `campaigns`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| name | VARCHAR(160) | NO | Internal admin label |
| channel | ENUM('push','sms','whatsapp','email') | NO | |
| target_locale | ENUM('ar','en','both') | NO | |
| segment_filters | JSON | NO | |
| product_type_segment | JSON | YES | Optional `["rental"]` filter |
| subject | JSON | YES | Translatable |
| body | JSON | NO | Translatable |
| scheduled_at | TIMESTAMP | YES | |
| status | ENUM('draft','scheduled','running','completed','failed','cancelled') | NO | |
| created_by | BIGINT UNSIGNED FK→users | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `campaign_runs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| campaign_id | BIGINT UNSIGNED FK | NO | |
| started_at | TIMESTAMP | YES | |
| completed_at | TIMESTAMP | YES | |
| recipients_total | INT UNSIGNED | NO | |
| recipients_sent | INT UNSIGNED | NO | |
| recipients_failed | INT UNSIGNED | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `campaign_recipients`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| campaign_run_id | BIGINT UNSIGNED FK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| dispatch_id | BIGINT UNSIGNED FK→notification_dispatches | YES | |
| status | ENUM('queued','sent','failed','skipped') | NO | |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(campaign_run_id, status)`.

---

## 12. Loyalty Module (4 tables) — per-vendor only

### `loyalty_programs`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | UNIQUE |
| is_active | BOOLEAN | NO | Default `false` (vendor opts in) |
| points_per_currency_unit | DECIMAL(10,4) | NO | e.g., 1 point per EGP |
| min_points_to_redeem | INT UNSIGNED | NO | Default 100 |
| max_redeem_pct | TINYINT UNSIGNED | NO | 0–100 |
| points_value_minor | BIGINT UNSIGNED | NO | What 1 point is worth |
| points_value_currency | CHAR(3) | NO | |
| points_expire_after_days | SMALLINT UNSIGNED | YES | NULL = never |
| created_at, updated_at | TIMESTAMPS | | |

### `loyalty_rules`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| loyalty_program_id | BIGINT UNSIGNED FK | NO | |
| rule_kind | ENUM('first_booking','category_bonus','threshold_bonus','referral') | NO | |
| label | JSON | NO | Translatable |
| multiplier | DECIMAL(4,2) | NO | Default 1.00 |
| conditions | JSON | YES | |
| starts_at | TIMESTAMP | YES | |
| ends_at | TIMESTAMP | YES | |
| is_active | BOOLEAN | NO | |
| created_at, updated_at | TIMESTAMPS | | |

### `loyalty_ledger` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | Per-vendor |
| direction | ENUM('earn','redeem','expire','adjust') | NO | |
| points | INT UNSIGNED | NO | |
| balance_after | INT UNSIGNED | NO | |
| reference_type | VARCHAR(120) | YES | |
| reference_id | BIGINT UNSIGNED | YES | |
| expires_at | TIMESTAMP | YES | For `earn` rows |
| description | JSON | YES | Translatable |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(user_id, vendor_profile_id, created_at)`, `(expires_at)`.

### `loyalty_redemptions`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| vendor_profile_id | BIGINT UNSIGNED FK | NO | |
| booking_id | BIGINT UNSIGNED FK | NO | |
| points_redeemed | INT UNSIGNED | NO | |
| amount_minor | BIGINT UNSIGNED | NO | |
| amount_currency | CHAR(3) | NO | |
| created_at | TIMESTAMP | NO | |

---

## 13. Imports Module (2 tables)

### `excel_imports`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | Vendor or admin |
| product_type | ENUM('rental','sale','digital') | NO | One of three templates |
| original_filename | VARCHAR(255) | NO | |
| stored_path | VARCHAR(500) | NO | Private bucket, retained 30 days |
| total_rows | INT UNSIGNED | NO | |
| valid_rows | INT UNSIGNED | NO | |
| invalid_rows | INT UNSIGNED | NO | |
| status | ENUM('queued','validating','validated','committing','completed','failed','cancelled') | NO | |
| started_at | TIMESTAMP | YES | |
| completed_at | TIMESTAMP | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `excel_import_errors`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| excel_import_id | BIGINT UNSIGNED FK | NO | |
| row_number | INT UNSIGNED | NO | |
| column_name | VARCHAR(120) | YES | |
| error_code | VARCHAR(80) | NO | |
| error_message | JSON | NO | Translatable |
| row_data | JSON | YES | The offending row |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(excel_import_id, row_number)`.

---

## 14. Cross-cutting Tables (6 tables)

### `audit_logs` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| auditable_type | VARCHAR(120) | NO | |
| auditable_id | BIGINT UNSIGNED | NO | |
| user_id | BIGINT UNSIGNED FK→users | YES | NULL = system |
| action | VARCHAR(80) | NO | `created`, `updated`, `approved`, `state_changed` |
| changes | JSON | YES | `{before, after}` |
| ip_address | VARCHAR(45) | YES | |
| user_agent | VARCHAR(255) | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(auditable_type, auditable_id, created_at)`, `(user_id, created_at)`.

### `event_outbox` (append-only, transactional outbox pattern)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| aggregate_type | VARCHAR(120) | NO | `Booking`, `Payment` |
| aggregate_id | BIGINT UNSIGNED | NO | |
| event_type | VARCHAR(120) | NO | `BookingConfirmed`, `VendorAcceptedItems` |
| event_version | SMALLINT UNSIGNED | NO | Default 1 |
| payload | JSON | NO | |
| metadata | JSON | YES | `{user_id, ip, request_id}` |
| status | ENUM('pending','processing','sent','failed','dead') | NO | Default `'pending'` |
| attempts | TINYINT UNSIGNED | NO | Default 0 |
| max_attempts | TINYINT UNSIGNED | NO | Default 5 |
| last_attempted_at | TIMESTAMP | YES | |
| last_error | TEXT | YES | |
| next_retry_at | TIMESTAMP | YES | Exponential backoff |
| sent_at | TIMESTAMP | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:**
- `(status, next_retry_at)` — worker poll
- `(aggregate_type, aggregate_id)` — debugging
- `(created_at)` — retention sweep

**Operational:** `DispatchOutboxEvents` command runs every 5s, claims rows with `SELECT … FOR UPDATE SKIP LOCKED`, fires Laravel events, marks `sent`. Failed rows get exponential retry; after `max_attempts` go to `dead`. Old `sent` rows pruned after 30 days.

### `analytics_events` (append-only)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | YES | NULL = anonymous |
| session_id | VARCHAR(64) | YES | |
| event_type | VARCHAR(80) | NO | `service_viewed`, `booking_started`, etc. |
| event_category | ENUM('navigation','catalog','booking','payment','vendor','search','marketing') | NO | |
| entity_type | VARCHAR(120) | YES | |
| entity_id | BIGINT UNSIGNED | YES | |
| metadata | JSON | YES | |
| ip_address | VARCHAR(45) | YES | |
| user_agent | VARCHAR(255) | YES | |
| locale | ENUM('ar','en') | YES | |
| platform | ENUM('ios','android','web') | YES | |
| referrer | VARCHAR(500) | YES | |
| utm_source | VARCHAR(120) | YES | |
| utm_medium | VARCHAR(120) | YES | |
| utm_campaign | VARCHAR(120) | YES | |
| created_at | TIMESTAMP | NO | |

**Indexes:** `(event_type, created_at)`, `(user_id, created_at)`, `(entity_type, entity_id, created_at)`, `(session_id)`.

**Volume warning:** Plan for monthly partitioning by `created_at` once over ~10M rows. Retention: 24 months online, then archive.

### `customer_addresses`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| user_id | BIGINT UNSIGNED FK→users | NO | |
| label | VARCHAR(60) | NO | "Home", "Office" |
| city_id | BIGINT UNSIGNED FK→cities | NO | |
| address_line | VARCHAR(255) | NO | |
| building | VARCHAR(60) | YES | |
| floor | VARCHAR(20) | YES | |
| apartment | VARCHAR(20) | YES | |
| landmark | VARCHAR(255) | YES | |
| latitude | DECIMAL(10,7) | YES | |
| longitude | DECIMAL(10,7) | YES | |
| recipient_name | VARCHAR(120) | NO | Defaults to user name |
| recipient_phone_e164 | VARCHAR(20) | NO | Defaults to user phone |
| is_default | BOOLEAN | NO | Default `false` |
| created_at, updated_at, deleted_at | TIMESTAMPS | | Soft delete |

**Indexes:** `(user_id, deleted_at)`. Enforce one default via app-level constraint inside `DB::transaction`.

**Booking integration:** snapshot to `booking_addresses` always — never reference `customer_addresses` directly from `bookings` (keeps booking history immutable when customer edits saved address).

### `cms_pages`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| public_id | CHAR(26) UNIQUE | NO | |
| slug | VARCHAR(120) UNIQUE | NO | `terms`, `privacy`, `about`, `contact` |
| title | JSON | NO | Translatable |
| body | JSON | NO | Translatable HTML/Markdown |
| meta_description | JSON | YES | Translatable |
| is_published | BOOLEAN | NO | |
| published_at | TIMESTAMP | YES | |
| updated_by | BIGINT UNSIGNED FK→users | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `app_settings`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| key | VARCHAR(120) UNIQUE | NO | |
| value | JSON | NO | |
| description | VARCHAR(255) | YES | |
| updated_by | BIGINT UNSIGNED FK→users | YES | |
| created_at, updated_at | TIMESTAMPS | | |

### `feature_flags`

| Column | Type | Null | Notes |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | NO | |
| key | VARCHAR(120) UNIQUE | NO | |
| is_enabled | BOOLEAN | NO | |
| rollout_pct | TINYINT UNSIGNED | NO | 0–100 |
| description | VARCHAR(255) | YES | |
| created_at, updated_at | TIMESTAMPS | | |

---

## 15. Migration Order

Dependency-correct order. New v2 tables marked **bold**.

1. Framework + Spatie permissions (`users`, sessions, jobs, cache, media, roles/permissions)
2. Geography: `governorates` → `regions` → `cities`
3. Identity: `vendor_profiles`, `vendor_documents`, `vendor_approved_product_types`, `vendor_business_hours`, `vendor_coverage_areas`, `customer_profiles`, `user_devices`, `two_factor_secrets`, **`customer_addresses`**
4. Catalog foundation: `occasions`, `categories`, `occasion_category`, `category_field_schemas`, `service_themes`
5. Catalog services: `services` → `service_rental_details`, `service_sale_details`, `service_digital_details`, `service_pricing_tiers`, `service_availability_blocks`, `service_excluded_dates`, `service_themes_pivot`, **`service_inventory_reservations`**
6. Discovery: `wishlists`, `wishlist_items`, `saved_searches`, `search_logs`
7. Booking: `bookings` → `booking_addresses` → **`booking_snapshots`** → **`booking_locks`** → `booking_vendors` → `booking_items` → `booking_modifications` → `booking_modification_items`, `booking_state_transitions`, `booking_customer_notes`
8. Payments: `payments` → `payment_attempts`, `refunds`, `idempotency_keys`, `gateway_webhook_logs`
9. Settlement: `wallets` → `wallet_ledger`, `commission_rates`, `commissions`, `withdrawals`, `settlement_runs`
10. Reviews: `service_reviews`, `vendor_reviews`, `review_responses`, `review_moderation_log`
11. Communication: `chat_threads`, `chat_message_log`, `chat_moderation_flags`, `notification_templates`, `notification_dispatches`, **`notification_preferences`**, `campaigns`, `campaign_runs`, `campaign_recipients`
12. Loyalty: `loyalty_programs`, `loyalty_rules`, `loyalty_ledger`, `loyalty_redemptions`
13. Imports: `excel_imports`, `excel_import_errors`
14. Cross-cutting: `audit_logs`, **`event_outbox`**, **`analytics_events`**, `cms_pages`, `app_settings`, `feature_flags`

Each module's migrations live in `app/Modules/{Name}/Database/Migrations/` (Tech Decisions §1).
