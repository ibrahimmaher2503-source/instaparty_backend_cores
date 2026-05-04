# Implementation Plan: Vendor Subscription Tiers (Phase 1.7)

**Branch**: `018-subscriptions-tiers` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/018-subscriptions-tiers/spec.md`

## Summary

Introduce a new `Subscriptions` bounded module that owns the four-tier (Free / Silver / Gold / Premium) vendor plan catalog, vendor subscriptions, billing invoices, payment attempts, and an append-only lifecycle audit. Wire it into Identity (auto-enrol on vendor approval), Catalog (gate service create / publish / Excel import), Discovery (gate featured placement), Settlement (5th-level commission fallback by `subscription_plan_id`), and Payments (reuse `PaymentGateway` with a feature-flagged saved-token recurring path and a vendor-initiated fallback).

Approach: a single `SubscriptionPolicy` query object backed by a per-request-cached `FeatureResolver`, three queued cron Actions (renew, expire, cleanup), and standard InstaParty patterns — Domain-Application-Infrastructure split, integer minor units for money, `Brick\Money` cast, EN+AR translatable JSON columns, append-only ledger discipline, idempotency on every payment-mutating endpoint, audit row on every state transition. Filament admin gets two Resources (`SubscriptionPlansResource` for CRUD, `VendorSubscriptionsResource` for monitoring + override action). No new third-party packages.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies** (all already in `docs/specs/10_Package_List.md`):
- `spatie/laravel-translatable` — JSON name/description on `subscription_plans` and `plan_features`
- `spatie/laravel-permission` — Shield permissions (`view_subscription_plan`, `update_subscription_plan`, `override_vendor_subscription`)
- `spatie/laravel-model-states` — `VendorSubscription` lifecycle (`Active → PastDue → Expired | Cancelled | Superseded`)
- `brick/money` — invoice and plan amount casts
- `filament/filament` v3 + `filament/spatie-laravel-translatable-plugin` + `bezhansalleh/filament-shield`
- existing `App\Modules\Payments\Domain\Contracts\PaymentGateway` (Paymob) — extended only behaviourally via a feature flag, not a new package
- existing `App\Modules\Communication\Application\Actions\DispatchNotificationAction`
- existing `audit_logs`, `feature_flags`, `app_settings`, `idempotency_keys`, `event_outbox` cross-cutting tables (no new ones)
**Storage**: MySQL 8 / MariaDB 11, `utf8mb4` / `utf8mb4_unicode_ci`. Redis for cache and queues.
**Testing**: Pest. Required coverage: tier auto-enrolment, upgrade flow with idempotency replay, renewal success / failure / grace / expiry, downgrade auto-pause, commission 5th-level fallback (×4 tiers × 3 product types × 2 category overrides), gating denials, admin override layering. Tests grouped `subscriptions`, plus `rental`/`sale`/`digital` where commission and service-cap behaviour intersects with product type.
**Target Platform**: Laravel API + Filament admin under existing modular monolith; queue worker + scheduler for cron jobs.
**Project Type**: Modular Laravel monolith (`app/Modules/Subscriptions/...`).
**Performance Goals**: SC-005 — gated denials < 200 ms p95. `FeatureResolver` cache TTL 60 s with explicit invalidation on plan or subscription writes. Renewal job batch size 200 with per-vendor rate limit to respect gateway TPS limits.
**Constraints**:
- All money in `*_minor` (BIGINT UNSIGNED) + `*_currency` CHAR(3); `Brick\Money` cast.
- Append-only on `subscription_invoices`, `subscription_payments`, `subscription_audit` (no UPDATE except status fields, no soft delete).
- EN + AR required for every translatable plan field before `published`.
- All admin and vendor mutations land in `audit_logs` (cross-module) **and** `subscription_audit` (module-local lifecycle ledger).
- `Idempotency-Key` header mandatory on `POST /vendor/subscribe`, `POST /vendor/subscription/cancel`, `POST /admin/subscriptions/{id}/override`.
- Domain events fire **after** transaction commit via `DB::afterCommit()`.
**Scale/Scope**: ~10k vendors at steady state, ~1k upgrades/cancels per month, renewal job runs hourly and processes whatever subscriptions reached `current_period_end` in the last hour. Audit table partition is not required at this scale.

**Phase placement**: Phase 1.7 (confirmed in clarification). Update `docs/specs/09_Phasing_Plan.md` to slot Phase 1.7 between the existing Phase 5 (Communications + Reviews + Loyalty) and Phase 6 (Reporting + Imports + CMS). Mark vendor subscription tiers as **moved out** of Phase 2 §5.2 / §11.

## Constitution Check

Running each constitution gate from `CLAUDE.md` and `.claude/rules/*` against this design.

| Gate | Status | Notes |
|---|---|---|
| Modular monolith — feature lives in its own `app/Modules/{Name}/` | ✅ PASS | `app/Modules/Subscriptions/` is a new bounded module per `modules.md` layout. |
| No cross-module model imports | ✅ PASS | All cross-module reach is via Contracts (`SubscriptionPolicyContract` published in `Subscriptions/Domain/Contracts/`, consumed by Catalog / Discovery / Settlement). Listeners react to existing events (`PaymentCaptured`, `VendorRegistered`). |
| Thin controllers + fat single-purpose Actions | ✅ PASS | All controllers ≤ 3 lines body. Each Action has one `execute()`. |
| Models hold no business logic | ✅ PASS | Lifecycle in `Domain/States/`, policy in `Application/Services/SubscriptionPolicy.php`. |
| Three product types respected | ✅ PASS | Subscriptions are vendor-scoped, not service-scoped — feature is **cross-type** per `product-types.md`. Where gates touch services (max active services), the gate uses `ProductType` enum and `match($enum)` if needed. Tests cover all three types via the commission matrix. |
| Money — `_minor` + `_currency`, `Brick\Money` cast | ✅ PASS | All amounts on `subscription_plans`, `subscription_invoices`, `subscription_payments`. |
| Translatable fields = JSON via Spatie Translatable | ✅ PASS | `subscription_plans.name`, `subscription_plans.description`. |
| IDs — internal `id` BIGINT, external `public_id` ULID | ✅ PASS | All four user-facing tables (`subscription_plans`, `vendor_subscriptions`, `subscription_invoices`, `subscription_payments`) carry `public_id`. `plan_features` and `subscription_audit` are leaf/ledger tables — no `public_id`. |
| Append-only tables — no soft-delete, no UPDATE except status | ✅ PASS | `subscription_invoices` (status-only updates), `subscription_payments` (insert-only), `subscription_audit` (insert-only). |
| Soft delete only where allowed by Tech Decisions §4 | ✅ PASS | `subscription_plans` is the only soft-deletable table here, and the constitution allowance covers it. Other tables have no `deleted_at`. |
| Domain events fire after commit | ✅ PASS | `DB::afterCommit()` in every Action. |
| Idempotency on payment-mutating endpoints | ✅ PASS | `Idempotency-Key` enforced on subscribe / cancel / override / renewal job. |
| Standard `ApiResponse` envelope | ✅ PASS | Reuses existing global response macro. |
| FK with `restrictOnDelete()` default | ✅ PASS | Cascade only on `plan_features.plan_id` (rows are detail of the plan). All other FKs restrict. |
| UTC server time, conversion at Resource layer | ✅ PASS | All timestamps stored UTC; API resources convert per request locale. |
| Audit log on every state transition | ✅ PASS | Both global `audit_logs` and module-local `subscription_audit`. |
| EN + AR required everywhere | ✅ PASS | Plan translatable fields enforced both locales before `published`. Tests cover EN and AR responses. |
| Per-product-type Filament Resources | ✅ N/A | Subscription resources are not type-aware. |
| No new packages outside `10_Package_List.md` | ✅ PASS | Zero new packages. |
| No Phase 2 features | ✅ PASS | Subscriptions explicitly approved as **Phase 1.7** (clarification 2026-05-03). `09_Phasing_Plan.md` will be updated. |
| Contradicts `02_Tech_Decisions.md` locked stack | ✅ PASS | None. |
| API docs — `@bodyParam`, `@response`, `api-registry.md`, Bruno collection | ✅ PASS | Required for every endpoint in §Phase 1 contracts. |

**Result**: PASS — no Constitution violations. Complexity Tracking section below intentionally empty.

## Project Structure

### Documentation (this feature)

```text
specs/018-subscriptions-tiers/
├── plan.md                                # This file
├── research.md                            # Phase 0 — decisions on grace, limits, recurring tokens
├── data-model.md                          # Phase 1 — 8 tables, FK map, state machine
├── quickstart.md                          # Phase 1 — local dev runbook
├── contracts/
│   ├── api-vendor.yaml                    # OpenAPI 3.1 — vendor endpoints
│   ├── api-admin.yaml                     # OpenAPI 3.1 — admin endpoints
│   └── events.md                          # Domain events + outbox payload contracts
└── checklists/
    └── requirements.md                    # already created by /speckit.specify
```

### Source Code (modular monolith — adds one new module + edits in five existing modules)

```text
app/Modules/Subscriptions/                                        # NEW
├── Domain/
│   ├── Models/
│   │   ├── SubscriptionPlan.php
│   │   ├── PlanFeature.php
│   │   ├── VendorSubscription.php
│   │   ├── SubscriptionInvoice.php
│   │   ├── SubscriptionPayment.php
│   │   └── SubscriptionAuditEntry.php
│   ├── Enums/
│   │   ├── PlanCode.php                                          # Free | Silver | Gold | Premium
│   │   ├── BillingCycle.php                                      # Monthly | Yearly
│   │   ├── SubscriptionStatus.php                                # Active | PastDue | Cancelled | Expired | Superseded
│   │   ├── InvoiceStatus.php                                     # Pending | Paid | Failed | Refunded
│   │   └── SubscriptionEventType.php                             # 14 event types — see contracts/events.md
│   ├── States/
│   │   ├── SubscriptionState.php                                 # spatie/laravel-model-states base
│   │   ├── ActiveState.php
│   │   ├── PastDueState.php
│   │   ├── CancelledState.php
│   │   ├── ExpiredState.php
│   │   └── SupersededState.php
│   ├── Events/
│   │   ├── SubscriptionActivated.php
│   │   ├── SubscriptionRenewed.php
│   │   ├── SubscriptionPastDue.php
│   │   ├── SubscriptionExpired.php
│   │   ├── SubscriptionCancelled.php
│   │   ├── SubscriptionTierChanged.php
│   │   └── AdminOverrideApplied.php
│   └── Contracts/
│       ├── SubscriptionPolicyContract.php                        # consumed by Catalog/Discovery
│       ├── CommissionTierLookup.php                              # consumed by Settlement
│       └── SubscriptionRepository.php
├── Application/
│   ├── Services/
│   │   ├── SubscriptionPolicy.php                                # canCreateService / canFeature / canImportExcel / featuredCap / maxActiveServices / commissionDiscountBps
│   │   ├── FeatureResolver.php                                   # request-cached + Redis-cached
│   │   └── SubscriptionLifecycleService.php                      # grace/expire/auto-pause helpers
│   ├── Actions/
│   │   ├── SubscribeToPlanAction.php
│   │   ├── UpgradeSubscriptionAction.php
│   │   ├── DowngradeSubscriptionAction.php                       # (defer effective date to current_period_end)
│   │   ├── CancelSubscriptionAction.php
│   │   ├── RenewSubscriptionAction.php                           # called by queued cron
│   │   ├── ProcessExpirationsAction.php                          # called by queued cron
│   │   ├── PauseExcessServicesAction.php                         # invoked from listener
│   │   ├── ApplyAdminOverrideAction.php
│   │   ├── EndAdminOverrideAction.php                            # cron sweep for expired overrides
│   │   ├── AutoEnrolFreeTierAction.php                           # called from VendorRegistered listener
│   │   └── HandleSubscriptionPaymentCapturedAction.php           # called from PaymentCaptured listener
│   ├── DTOs/
│   │   ├── SubscribeRequestDto.php
│   │   ├── PolicyDecisionDto.php
│   │   └── PlanFeaturesDto.php
│   └── Listeners/
│       ├── OnVendorRegistered.php
│       ├── OnPaymentCaptured.php
│       └── OnSubscriptionExpired.php                             # → PauseExcessServicesAction
├── Infrastructure/
│   └── Repositories/
│       ├── EloquentSubscriptionRepository.php
│       └── EloquentPlanRepository.php
├── Http/
│   ├── Controllers/
│   │   ├── Vendor/
│   │   │   ├── ListPlansController.php
│   │   │   ├── ShowSubscriptionController.php
│   │   │   ├── SubscribeController.php
│   │   │   └── CancelSubscriptionController.php
│   │   └── Admin/
│   │       ├── ListSubscriptionsController.php
│   │       └── OverrideSubscriptionController.php
│   ├── Requests/
│   │   ├── SubscribeRequest.php
│   │   ├── CancelSubscriptionRequest.php
│   │   └── OverrideSubscriptionRequest.php
│   └── Resources/
│       ├── PlanResource.php
│       ├── PlanCollection.php
│       ├── SubscriptionResource.php
│       └── InvoiceResource.php
├── Filament/
│   └── Resources/
│       ├── SubscriptionPlanResource.php                          # CRUD on plans (translatable)
│       ├── SubscriptionPlanResource/Pages/...
│       ├── VendorSubscriptionResource.php                        # read-only + admin actions
│       └── VendorSubscriptionResource/Pages/...
├── Routes/
│   ├── vendor.php
│   ├── admin.php
│   └── customer.php                                              # empty stub for module convention
├── Database/
│   ├── Migrations/
│   │   ├── 2026_05_03_000001_create_subscription_plans_table.php
│   │   ├── 2026_05_03_000002_create_plan_features_table.php
│   │   ├── 2026_05_03_000003_create_vendor_subscriptions_table.php
│   │   ├── 2026_05_03_000004_create_subscription_invoices_table.php
│   │   ├── 2026_05_03_000005_create_subscription_payments_table.php
│   │   ├── 2026_05_03_000006_create_subscription_audit_table.php
│   │   └── 2026_05_03_000007_add_subscription_plan_id_to_commission_rates.php   # Settlement alter
│   ├── Seeders/
│   │   └── SubscriptionPlansSeeder.php                           # idempotent on plan_code
│   └── Factories/
│       ├── SubscriptionPlanFactory.php
│       ├── VendorSubscriptionFactory.php
│       └── SubscriptionInvoiceFactory.php
├── Resources/
│   └── lang/
│       ├── en/subscription.php
│       └── ar/subscription.php
└── Providers/
    └── SubscriptionsServiceProvider.php                          # binds contracts, loads routes/migrations/translations, registers listeners

# Cross-module edits (NO model imports — all via Contracts)
app/Modules/Identity/Application/Listeners/AutoEnrolNewVendor.php # NEW listener — calls AutoEnrolFreeTierAction via contract
app/Modules/Catalog/Application/Actions/CreateRentalServiceAction.php   # EDIT — gate via SubscriptionPolicyContract
app/Modules/Catalog/Application/Actions/CreateSaleServiceAction.php     # EDIT
app/Modules/Catalog/Application/Actions/CreateDigitalServiceAction.php  # EDIT
app/Modules/Catalog/Application/Actions/PublishServiceAction.php        # EDIT — gate
app/Modules/Catalog/Application/Actions/ImportRental*FromExcelAction.php (×3) # EDIT — gate canImportExcel
app/Modules/Discovery/Application/Actions/FeatureServiceAction.php       # EDIT — gate canFeature
app/Modules/Discovery/Infrastructure/Repositories/EloquentDiscoveryReader.php # EDIT — exclude expired-subscription vendors from featured list
app/Modules/Settlement/Application/Services/CommissionRateResolver.php   # EDIT — add 5th-level fallback via CommissionTierLookup contract
app/Modules/Payments/Application/Listeners/{...}                         # EDIT — fire SubscriptionInvoicePaid event when invoice is a subscription invoice
docs/specs/09_Phasing_Plan.md                                            # EDIT — add Phase 1.7 row
.specify/memory/api-registry.md                                          # EDIT — register 6 new endpoints

# Tests
tests/Feature/Modules/Subscriptions/
├── AutoEnrolFreeTierTest.php
├── SubscribeFlowTest.php                          # idempotency replay, EN+AR responses
├── UpgradeDowngradeTest.php
├── CancelSubscriptionTest.php
├── RenewalSuccessTest.php
├── RenewalFailureGraceExpiryTest.php              # walks the full lifecycle
├── AdminOverrideTest.php
├── ServiceCapEnforcementTest.php                  # × rental, × sale, × digital
├── ExcelImportGateTest.php                        # × rental, × sale, × digital
├── FeatureGateTest.php                            # Discovery
└── CommissionTierFallbackTest.php                 # 4 tiers × 3 types × 2 category overrides matrix
tests/Unit/Modules/Subscriptions/
├── SubscriptionPolicyTest.php
├── FeatureResolverCacheTest.php
└── SubscriptionStateTransitionTest.php
```

**Structure Decision**: Modular monolith with one new module (`Subscriptions`) and contract-based touchpoints in five existing modules. Filament resources live under the module's `Filament/Resources/` (auto-discovered by the panel provider). Migrations stay under the module's `Database/Migrations/` directory and are loaded by `SubscriptionsServiceProvider`. The Settlement alter migration also lives under Subscriptions because the *reason* for the FK is owned by this feature; Settlement gains a column it queries through, but does not own the schema-change motivation. (This matches the convention used in `001-phase-0-foundation` for cross-module foundation alters.)

## Complexity Tracking

> No Constitution violations. Section intentionally left empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| _(none)_  | _(n/a)_    | _(n/a)_                              |
