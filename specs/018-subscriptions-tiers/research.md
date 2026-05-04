# Phase 0 Research — Subscription Tiers

**Feature**: 018-subscriptions-tiers
**Date**: 2026-05-03

Two questions were deferred from `/speckit.clarify` to planning. Both are resolved below with explicit defaults and an "alternatives considered" trail. No `[NEEDS CLARIFICATION]` markers remain.

---

## R1 — Grace-period length on failed renewals

**Decision**: **7 days**, configurable at runtime via `app_settings.subscription_grace_period_days` (default 7, valid range 1–30).

**Rationale**:
- Long enough to clear weekend bank declines and a typical SaaS "card on file failed → email vendor → vendor updates card → retry" cycle.
- Short enough to bound revenue leakage and unauthorised exposure of paid-tier features (especially `can_feature` and `can_import_excel`).
- 7 days aligns with industry norms (Stripe default 7-day, Paddle 7-day, Recurly default 3–7) and with FR-009 + SC-003 ("99% of renewals resolve within 24 hours" → 7-day window leaves ample retry headroom).
- Configurable per env via `app_settings` (already in the locked schema) so production can tune without a deploy. Test envs default to 1 day to keep the lifecycle tests fast.

**Alternatives considered**:
- 3 days — too aggressive, risks downgrading legitimate vendors who hit a single weekend decline.
- 14 days — generous but stretches revenue exposure; rejected as overgenerous for a Phase 1.7 introduction where we want to learn the pattern before tuning up.
- Per-plan configurable — over-engineered for Phase 1.7. Single global default acceptable; can graduate to per-plan in Phase 2 if data demands it.

**Retry cadence inside the grace window**: gateway retries at T+0, T+24h, T+72h, T+144h (capped at 4 attempts). After the 4th failure or at end of grace, transition to `expired`. Each attempt is recorded as a `subscription_payments` row.

---

## R2 — Concrete numeric limits per tier

**Decision** (default fixture, seeded by `SubscriptionPlansSeeder`):

| Feature key | Free | Silver | Gold | Premium |
|---|---:|---:|---:|---:|
| `max_active_services` | 5 | 25 | 100 | unlimited (`-1`) |
| `max_gallery_images_per_service` | 5 | 11 (current default) | 11 | 11 |
| `can_feature` | false | true | true | true |
| `featured_cap` | 0 | 2 | 5 | 15 |
| `can_import_excel` | false | false | true | true |
| `can_use_custom_branding` | false | false | true | true |
| `analytics_access_level` | basic | basic | advanced | advanced |
| `commission_discount_bps` | 0 | 50 | 100 | 200 |
| `priority_support` | false | false | false | true |

Pricing (EGP, monthly / yearly, in `_minor`):

| Plan | Monthly | Yearly | Notes |
|---|---:|---:|---|
| Free | 0 / 0 | 0 / 0 | non-billable |
| Silver | 19 900 (199 EGP) | 199 000 (1 990 EGP, ~17% off) | |
| Gold | 49 900 (499 EGP) | 499 000 (4 990 EGP, ~17% off) | |
| Premium | 99 900 (999 EGP) | 999 000 (9 990 EGP, ~17% off) | |

**Rationale**:
- Service caps follow a clear x5 progression (5 / 25 / 100 / ∞) — easy to communicate, gives clear upgrade pressure as catalogue grows.
- Featured cap mirrors paid-tier service expectations (Premium vendors who run 100+ services need 15 simultaneous featured spots; Silver gets a starter taste with 2).
- Excel import is one of the most operationally heavy features — gating it to Gold+ matches the original Phase 6 import-readiness expectations and gives upgrade pressure to vendors with large catalogues.
- Commission discount bps are illustrative and can be tuned in Filament without code change (FR-018 stores the value in `plan_features`, not in code).
- All values are stored as `plan_features` rows (typed `int` / `bool` / `string`), so adding a new gate later requires only a new feature key + seeder update — no migration.

**Alternatives considered**:
- Hard-coded enum-based limits in PHP — rejected because it forces a deploy to retune limits and conflicts with FR-002 ("adding a new gate later does not require a schema change").
- Per-product-type service caps (e.g., separate rental cap vs. sale cap) — over-engineered for Phase 1.7; one cap covers all three types, with the auto-pause job applying oldest-first across types regardless. Can graduate to per-type if vendor feedback demands.

**Sentinel value for "unlimited"**: `-1`. The `SubscriptionPolicy::canCreateService` gate special-cases `-1` to bypass the count check.

---

## R3 — Recurring-token availability (re-confirmation of Q3 from clarify)

**Decision**: implement both branches behind `feature_flags.subscriptions.recurring_tokens_enabled` (default `false` until Paymob token capability is verified in staging). The `Paymob` gateway adapter exposes a `tokenize` and `chargeWithToken` method already (per Phase 4 work in `007-payments-paymob-refunds`) but the recurring path was not exercised against subscriptions; re-verification in staging is part of the implementation acceptance test.

**Rationale**:
- Captured in `/speckit.clarify` Q3=C. Restated here to anchor the implementation: `RenewSubscriptionAction` reads the flag at the top, branches into `chargeWithToken` or `createPendingInvoiceAndNotify`, and the rest of the lifecycle (grace, expiry) is identical regardless of branch.
- Avoids a code fork; flag is a one-line config change in `feature_flags`.

**Alternatives considered**: none — already settled in clarification.

---

## R4 — Idempotency-key scoping

**Decision**: scope `(route, key, user_id)` exactly as specified by `CLAUDE.md` rule #11. TTL 24 h via the existing `idempotency_keys` table; no new table.

**Rationale**: matches Payments module convention — single source of truth, single TTL sweep job already running.

---

## R5 — Subscription-vs-booking payments separation

**Decision**: keep a separate `subscription_payments` table (append-only) rather than reusing the existing `payments` table. Cross-link is via `gateway_ref` + a denormalised `payable_type = subscription_invoice` discriminator only on the gateway side.

**Rationale**:
- `payments` (booking) and `subscription_payments` (recurring billing) have substantially different lifecycle (refund vs. failed-renewal-grace), reporting (commission vs. ARR), and audit needs.
- Keeping them separate prevents "what is this row?" ambiguity in Filament and Reporting; both still flow through the same `PaymentGateway` interface so the integration code is shared.
- Aligns with `11_DB_Schema.md` LOCKED — the locked schema names `payments` as booking-payment-specific (UNIQUE on `(gateway, gateway_ref)` is per-booking-context). Adding subscription rows there would muddle that uniqueness.

**Alternatives considered**: reuse `payments` with a polymorphic owner — rejected; pollutes a locked table.

---

## R6 — Auto-pause ordering on downgrade

**Decision**: pause services exceeding the new tier's `max_active_services` cap, ordered `created_at ASC` (oldest first). Pause is reversible by re-publishing the service after upgrade. Auto-pause **never** deletes.

**Rationale**: oldest-first preserves the vendor's most recent (and likely most relevant) catalogue. Confirmed in spec assumption; restated here so the `PauseExcessServicesAction` has unambiguous ordering.

---

## R7 — Test data matrix for commission tier fallback

**Decision**: `CommissionTierFallbackTest` covers the full grid:

- 4 tiers × 3 product types × 2 category-override scenarios (with/without category-specific rule) = 24 cases.
- Each case asserts the resolved rate and the snapshot value on `booking_items.commission_bps`.

**Rationale**: SC-006 demands "zero discrepancies" — the matrix is the simplest exhaustive proof.

---

## R8 — Notification template keys (handed to Communication module)

**Decision**: register the following template keys in `notification_templates` during the seeder, EN+AR, channels `email` + `in_app` + `push`:

- `subscription.activated`
- `subscription.upgraded`
- `subscription.cancelled_pending`
- `subscription.renewal_succeeded`
- `subscription.renewal_failed_first`
- `subscription.renewal_failed_grace_warning` (sent at 24 h, 72 h, 144 h within grace)
- `subscription.expired_downgraded`
- `subscription.admin_override_applied`
- `subscription.admin_override_ended`

**Rationale**: matches FR-025 + Communication module conventions (`event_key, channel, audience` UNIQUE).

---

## Open items handed to Phase 2 (`/speckit.tasks`)

None. All Phase 0 unknowns resolved.
