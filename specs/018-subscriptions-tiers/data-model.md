# Phase 1 Data Model — Subscription Tiers

**Feature**: 018-subscriptions-tiers
**Date**: 2026-05-03

Eight schema artifacts: 6 new tables, 1 alter on `commission_rates`, 1 seeder. All money in `*_minor` BIGINT UNSIGNED + `*_currency` CHAR(3); all charset `utf8mb4` / `utf8mb4_unicode_ci`; all relevant FKs `restrictOnDelete()` unless cascade is domain-correct.

---

## Cross-table convention summary

- Internal `id` BIGINT auto-increment.
- External `public_id` CHAR(26) ULID UNIQUE on every user-facing entity (`subscription_plans`, `vendor_subscriptions`, `subscription_invoices`, `subscription_payments`). Leaf/ledger tables (`plan_features`, `subscription_audit`) intentionally have no `public_id`.
- Translatable JSON columns: `subscription_plans.name`, `subscription_plans.description`, optional `plan_features.label`.
- Append-only tables (no `updated_at`, no `deleted_at`, no UPDATE except status fields where explicitly noted): `subscription_invoices`, `subscription_payments`, `subscription_audit`.
- Soft-delete: only `subscription_plans` (constitution allowance — Tech Decisions §4 list expanded by clarification).
- Audit columns (`created_by`, `updated_by`) on `subscription_plans`, `plan_features`, `vendor_subscriptions`, `subscription_invoices`. Append-only tables omit these per `migrations.md`.

---

## T1 — `subscription_plans` (NEW, soft-deletable)

The four sellable tiers + room to grow.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `plan_code` | VARCHAR(32) UNIQUE | `free`, `silver`, `gold`, `premium` (UNIQUE) |
| `name` | JSON | translatable, EN+AR required before publish |
| `description` | JSON | translatable |
| `monthly_price_minor` | BIGINT UNSIGNED | |
| `monthly_price_currency` | CHAR(3) | `EGP` only at v1 |
| `yearly_price_minor` | BIGINT UNSIGNED | |
| `yearly_price_currency` | CHAR(3) | `EGP` only at v1 |
| `is_default` | BOOLEAN DEFAULT false | exactly one row may be true (DB-level partial UNIQUE on `is_default WHERE is_default = true` — emulated via app validation since MariaDB 11 partial indexes are limited) |
| `is_published` | BOOLEAN DEFAULT false | gates visibility on `GET /vendor/plans` |
| `display_order` | INT UNSIGNED DEFAULT 0 | sort order in vendor UI |
| `created_by`, `updated_by`, `deleted_by` | BIGINT NULL FK→users | |
| `created_at`, `updated_at`, `deleted_at` | timestamps | |

Indexes: `(is_published, display_order)`, soft-delete index implied by Eloquent.

FRs: FR-001, FR-003, FR-024.

---

## T2 — `plan_features` (NEW)

Typed feature catalogue per plan. Adding a new gate = new row, no migration.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `subscription_plan_id` | BIGINT UNSIGNED FK→`subscription_plans.id` `cascadeOnDelete()` | |
| `feature_key` | VARCHAR(64) | e.g. `max_active_services` |
| `value_type` | ENUM(`int`,`bool`,`string`) | |
| `value_int` | BIGINT NULL | populated when `value_type='int'`; `-1` = unlimited |
| `value_bool` | BOOLEAN NULL | populated when `value_type='bool'` |
| `value_string` | VARCHAR(255) NULL | populated when `value_type='string'` |
| `label` | JSON NULL | translatable optional friendly label for plan-comparison UI |
| `created_by`, `updated_by` | BIGINT NULL FK→users | |
| `created_at`, `updated_at` | timestamps | |

UNIQUE: `(subscription_plan_id, feature_key)`.
Indexes: `(feature_key)`.

FRs: FR-002, FR-016.

---

## T3 — `vendor_subscriptions` (NEW)

The one source of truth for "what tier does this vendor have right now?". A vendor can have at most one row per `(vendor_profile_id, status='active')` — enforced via partial UNIQUE.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `public_id` | CHAR(26) UNIQUE | |
| `vendor_profile_id` | BIGINT UNSIGNED FK→`vendor_profiles.id` `restrictOnDelete()` | |
| `subscription_plan_id` | BIGINT UNSIGNED FK→`subscription_plans.id` `restrictOnDelete()` | |
| `status` | VARCHAR(24) | `active` \| `past_due` \| `cancelled` \| `expired` \| `superseded` (spatie/model-states) |
| `billing_cycle` | VARCHAR(16) | `monthly` \| `yearly` \| `none` (Free) |
| `current_period_start` | TIMESTAMP NULL | NULL on Free |
| `current_period_end` | TIMESTAMP NULL | NULL on Free |
| `grace_period_ends_at` | TIMESTAMP NULL | populated on `past_due` transition |
| `cancel_at_period_end` | BOOLEAN DEFAULT false | |
| `is_admin_override` | BOOLEAN DEFAULT false | |
| `override_reason` | TEXT NULL | required when `is_admin_override=true` |
| `override_expires_at` | TIMESTAMP NULL | optional — NULL = until manually ended |
| `gateway_token_ref` | VARCHAR(128) NULL | opaque saved-token id from gateway; NULL when feature flag is off or vendor declined to save |
| `started_at` | TIMESTAMP NOT NULL | |
| `ended_at` | TIMESTAMP NULL | set when status moves to a terminal value |
| `ended_reason` | VARCHAR(64) NULL | `superseded_by_upgrade`, `cancelled_by_vendor`, `expired_by_grace`, `override_ended` |
| `created_by`, `updated_by` | BIGINT NULL FK→users | |
| `created_at`, `updated_at` | timestamps | |

Partial UNIQUE: `(vendor_profile_id) WHERE status='active' AND is_admin_override=false` — enforced in app + a deferrable check (MariaDB lacks partial UNIQUE; emulated via Eloquent listener and a regular composite UNIQUE on `(vendor_profile_id, status, is_admin_override, ended_at)` where `ended_at IS NULL` rows collide).

Effective tier resolution (used by `SubscriptionPolicy`):
1. Active row with `is_admin_override = true` and `override_expires_at IS NULL OR > now`.
2. Otherwise the active row with `is_admin_override = false`.
3. Otherwise (only at the moment of vendor creation, briefly) — Free auto-enrolment fires.

Indexes:
- `(vendor_profile_id, status)`
- `(status, current_period_end)` — renewal job poll
- `(status, grace_period_ends_at)` — expiration sweep
- `(is_admin_override, override_expires_at)` — admin override sweep
- `(subscription_plan_id, status)` — admin reporting

State machine (spatie/laravel-model-states):

```
[Active]
  ├── on cancel(at_period_end)        → [Active] cancel_at_period_end=true
  ├── on cancel(immediate, refund)    → [Cancelled]
  ├── on renewal_failed               → [PastDue]
  └── on superseded                   → [Superseded]

[PastDue]
  ├── on renewal_succeeded            → [Active]
  ├── on grace_window_elapsed         → [Expired]
  └── on cancelled                    → [Cancelled]

[Expired] (terminal)
[Cancelled] (terminal)
[Superseded] (terminal)
```

FRs: FR-004, FR-005, FR-007, FR-008, FR-009, FR-010, FR-011, FR-012, FR-020.

---

## T4 — `subscription_invoices` (NEW, append-only — status-only updates)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `public_id` | CHAR(26) UNIQUE | |
| `vendor_subscription_id` | BIGINT UNSIGNED FK→`vendor_subscriptions.id` `restrictOnDelete()` | |
| `vendor_profile_id` | BIGINT UNSIGNED FK→`vendor_profiles.id` `restrictOnDelete()` | denorm for reporting |
| `subscription_plan_id` | BIGINT UNSIGNED FK→`subscription_plans.id` `restrictOnDelete()` | denorm; tier at billing time |
| `billing_cycle` | VARCHAR(16) | snapshot |
| `period_start` | TIMESTAMP NOT NULL | |
| `period_end` | TIMESTAMP NOT NULL | |
| `amount_minor` | BIGINT UNSIGNED | snapshot of plan price at issuance |
| `amount_currency` | CHAR(3) | |
| `status` | ENUM(`pending`,`paid`,`failed`,`refunded`) | only column updatable post-insert |
| `due_at` | TIMESTAMP NOT NULL | |
| `paid_at` | TIMESTAMP NULL | |
| `idempotency_key` | VARCHAR(64) NULL | echoes the inbound key for traceability (NOT the table-level guard — that is `idempotency_keys`) |
| `created_at` | TIMESTAMP USE_CURRENT | no `updated_at`, no soft delete |

UNIQUE: `(vendor_subscription_id, period_start, period_end)` — prevents duplicate invoice for the same period.
Indexes: `(status, due_at)`, `(vendor_profile_id, created_at desc)`.

FRs: FR-005, FR-007, FR-022, FR-023.

---

## T5 — `subscription_payments` (NEW, append-only)

Insert-only. Each renewal/upgrade attempt is a row.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `public_id` | CHAR(26) UNIQUE | |
| `subscription_invoice_id` | BIGINT UNSIGNED FK→`subscription_invoices.id` `restrictOnDelete()` | |
| `gateway` | VARCHAR(32) NOT NULL | `paymob` |
| `gateway_ref` | VARCHAR(128) NULL | populated as soon as the gateway returns a ref |
| `amount_minor` | BIGINT UNSIGNED | |
| `amount_currency` | CHAR(3) | |
| `attempt_no` | INT UNSIGNED NOT NULL | 1-based per-invoice sequence |
| `mode` | ENUM(`vendor_initiated`,`recurring_token`) | which branch of FR-008 fired |
| `status` | ENUM(`pending`,`succeeded`,`failed`) | only column updatable post-insert (status-only update permitted on the *latest* attempt as it transitions `pending → terminal`) |
| `failure_code` | VARCHAR(64) NULL | gateway code on failure |
| `failure_reason` | TEXT NULL | |
| `responded_at` | TIMESTAMP NULL | |
| `created_at` | TIMESTAMP USE_CURRENT | |

UNIQUE: `(subscription_invoice_id, attempt_no)`. UNIQUE: `(gateway, gateway_ref) WHERE gateway_ref IS NOT NULL`.
Indexes: `(status, created_at)`.

FRs: FR-006, FR-008.

---

## T6 — `subscription_audit` (NEW, append-only ledger)

Module-local lifecycle ledger. Cross-cutting `audit_logs` is **also** written for human-readable audit; this table is the canonical machine-readable lifecycle stream.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK auto-inc | |
| `vendor_subscription_id` | BIGINT UNSIGNED FK→`vendor_subscriptions.id` `restrictOnDelete()` | |
| `event_type` | VARCHAR(48) | one of `SubscriptionEventType` enum (14 values — see contracts/events.md) |
| `actor_type` | VARCHAR(32) NOT NULL | `vendor` \| `admin` \| `system` |
| `actor_id` | BIGINT NULL FK→users | NULL when `actor_type='system'` |
| `before` | JSON NULL | snapshot of mutable fields before the event |
| `after` | JSON NULL | snapshot after |
| `reason` | TEXT NULL | always populated for admin overrides; optional otherwise |
| `metadata` | JSON NULL | gateway response excerpts, retry counts, etc. |
| `created_at` | TIMESTAMP USE_CURRENT | no `updated_at` |

Indexes: `(vendor_subscription_id, created_at desc)`, `(event_type, created_at desc)`.

FRs: FR-022, FR-023.

---

## T7 — alter `commission_rates` (Settlement)

Add the 5th-level fallback key.

| Change | Notes |
|---|---|
| Add `subscription_plan_id` BIGINT UNSIGNED NULL FK→`subscription_plans.id` `restrictOnDelete()` | NULL = not tier-specific |
| Add index `(subscription_plan_id, product_type, category_id)` | resolver lookup |

Resolution precedence (most specific wins) — extends the existing 4-level rule:
1. `(category_id, product_type)` — already specced
2. `(category_id, NULL)`
3. `(NULL, product_type)`
4. `(NULL, NULL)` — global default
5. **NEW**: `(NULL, NULL, subscription_plan_id)` — tier-only discount

Tier-only rows are seeded by `SubscriptionPlansSeeder` to honour the `commission_discount_bps` value of each plan. Admins can override per category × type × plan by inserting more specific rows (e.g., `(category_id, product_type, subscription_plan_id)` — that combination becomes priority 1 with extra specificity).

The snapshot rule from `product-types.md` is unchanged: the resolved bps lands on `booking_items.commission_bps` at booking creation time and is never recomputed.

FRs: FR-018, FR-019.

---

## Seeder — `SubscriptionPlansSeeder`

Idempotent on `plan_code`. Seeds:
- 4 plans with EN+AR `name` and `description`, prices per R2 in `research.md`, `is_default=true` on `free`, `is_published=true` on all.
- ~9 `plan_features` rows per plan (matrix in `research.md` §R2).
- 4 commission_rates rows (NULL category × NULL type × plan_id) carrying the per-tier discount bps from `commission_discount_bps`.

ADR placeholder: `docs/adr/ADR-0013-subscription-tiers-module.md` to be authored alongside Phase 1 delivery (records the module introduction, ownership, and the 5th-level commission fallback).

---

## Cross-module impact summary

| Module | Touches | Reason |
|---|---|---|
| Identity | `vendor_profiles` (read only); listener on `VendorRegistered` | Free auto-enrolment (FR-004, FR-028) |
| Catalog | `services` (read-only count + write `paused`); `excel_imports` flow | Service cap + Excel gate (FR-013, FR-014) |
| Discovery | `services.is_featured`-equivalent flag | Featured cap + tier filter (FR-015) |
| Settlement | `commission_rates` (alter), `CommissionRateResolver` | 5th-level fallback (FR-018) |
| Payments | `payments` (untouched), `PaymentGateway` (reused), webhook listener | `OnPaymentCaptured` for subscription invoices (FR-026) |
| Communication | `notification_templates`, `notification_dispatches` | 9 lifecycle template keys (research.md §R8) |

No direct Eloquent model imports across module boundaries — every interaction goes through the Subscriptions module's contracts.

---

## Validation rules summary

- Plan: name + description must contain both EN and AR before `is_published=true`.
- Subscribe: `plan_code` must reference a published plan; `billing_cycle` ∈ {monthly, yearly}; vendor must have an active vendor profile with `approval_status='approved'`.
- Cancel: only an `active` or `past_due` subscription is cancellable.
- Override: requires `reason`, optional `expires_at` (must be future), admin Shield permission `override_vendor_subscription`. Cannot stack two active overrides on the same vendor.
- Invoice: insert-only beyond status updates; `period_start < period_end`; amount > 0 except for Free (Free plan never produces invoices).
- Payment: `attempt_no` strictly increasing per invoice; only `pending` status is mutable into a terminal value.
