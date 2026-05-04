# ADR-0013 — Subscription Tiers Module (Phase 1.7)

**Date:** 2026-05-03
**Status:** Accepted
**Author:** Ibrahim (approved as Phase 1.7 scope extension)
**Branch:** 018-subscriptions-tiers

---

## Context

InstaParty vendors need tiered access controls that gate the number of active services they can publish, whether they can use "featured" promotional slots, whether they can use Excel bulk-import, and the commission rate they pay on bookings. The PRD §5.2 originally deferred this to Phase 2 but has been explicitly approved for Phase 1.7 (between Phase 5 and Phase 6) because: (a) commission rate fallback must be in Settlement before Phase 4.2 reports are built, and (b) Catalog enforcement blocks are simpler to wire now while Catalog Actions are still being actively developed.

---

## Decision

Introduce a new **Subscriptions** module (`app/Modules/Subscriptions/`) as a first-class bounded context. The module:

1. Owns all 6 new tables: `subscription_plans`, `plan_features`, `vendor_subscriptions`, `subscription_invoices`, `subscription_payments`, `subscription_audit`.
2. Adds a `subscription_plan_id` FK column to `commission_rates` (owned by Settlement; altered via a Subscriptions migration).
3. Exposes contracts to other modules — never Eloquent model imports across boundaries.
4. Reuses the existing `PaymentGateway` interface (no new gateway code).
5. Reuses the existing `event_outbox` and `idempotency_keys` tables.

---

## Schema Artifacts (8 total)

| # | Table | Type | FR coverage |
|---|---|---|---|
| T1 | `subscription_plans` | Normal | FR-001, FR-003 |
| T2 | `plan_features` | Normal | FR-002, FR-013–FR-017 |
| T3 | `vendor_subscriptions` | Normal (state machine) | FR-004, FR-007, FR-020 |
| T4 | `subscription_invoices` | Append-only (status-only UPDATE) | FR-005, FR-022 |
| T5 | `subscription_payments` | Append-only (insert-only) | FR-006, FR-008 |
| T6 | `subscription_audit` | Append-only ledger | FR-022, FR-023 |
| T7 | `commission_rates` (alter) | FK addition | FR-018, FR-019 |

---

## Key Design Decisions

### Admin Override Layering (FR-020, Clarification Q2 = B)

Admin overrides are **layered on top** of the vendor's underlying paid subscription, not replacing it. A second `vendor_subscriptions` row is inserted with `is_admin_override=true`. Effective-tier resolution picks the override row first; the underlying subscription keeps renewing normally. When the override ends (manual or via `override_expires_at` sweep), the underlying subscription is revealed unchanged.

**Why:** Preserves billing integrity — the vendor's paid period continues uninterrupted while an admin grants temporary access. No refund or billing adjustment is needed.

### Recurring Token Strategy (FR-008, Clarification Q3 = C)

Two renewal branches coexist behind `feature_flags.subscriptions.recurring_tokens_enabled`:
- **Disabled (default):** Vendor receives a renewal invoice email; payment is vendor-initiated via checkout URL.
- **Enabled:** `RenewSubscriptionAction` charges the saved Paymob token unattended; falls back to vendor-initiated invoice on token failure.

**Why:** Recurring token capability depends on Paymob merchant account configuration. The flag allows enabling it per-environment without a deployment.

### 5th-Level Commission Fallback (FR-018, FR-019)

Settlement's `CommissionRateResolver` adds a 5th-level lookup (after category×type, category×NULL, NULL×type, NULL×NULL) by `subscription_plan_id`. Settlement consumes the `CommissionTierLookup` contract; it never imports any Subscriptions model.

**Why:** Tiered commission discounts must be composable with category overrides. The most-specific rule still wins; tier discount only applies when no other match is found.

### Feature Limits from DB, Not PHP Constants (FR-002)

Tier limits (max active services, featured cap, Excel access) are stored in `plan_features` rows. `FeatureResolver` caches per-plan values with a per-request + Redis layer. Filament plan edits call `FeatureResolver::forgetForPlan()`.

**Why:** Allows limit adjustments without deployments; enables A/B testing and admin override of individual features.

### Grace Period from App Settings (FR-009)

Default 7 days stored in `app_settings.subscription_grace_period_days`. `ProcessExpirationsAction` reads this at runtime.

---

## Consequences

- Settlement's `CommissionRateResolver` gains a constructor-injected `CommissionTierLookup` parameter — minor but intentional dependency.
- Catalog `Create*ServiceAction` classes each get a `SubscriptionPolicyContract::canCreateService()` call at top of `execute()`.
- Discovery's featured-list query gains a join to `vendor_subscriptions` to exclude expired vendors.
- Three new cron jobs registered in `app/Console/Kernel.php`: `subscriptions:renew` (hourly), `subscriptions:expire-grace` (every 30 min), `subscriptions:end-overrides` (daily).
- Module follows all CLAUDE.md conventions: thin controllers, fat Actions, DB::transaction wrapping all mutations, domain events after commit, append-only discipline on T4/T5/T6.

---

## Rejected Alternatives

| Alternative | Rejected because |
|---|---|
| Add tier column directly to `vendor_profiles` | Cannot represent billing lifecycle (invoices, payments, grace) |
| Reuse `payments` table for subscription invoices | Booking vs. recurring billing must stay accounting-separate |
| Store feature limits in PHP config | Prevents runtime adjustment; requires deploy for limit changes |
| Single `vendor_subscriptions` row with override columns | Cannot represent concurrent base + override; makes reporting harder |
