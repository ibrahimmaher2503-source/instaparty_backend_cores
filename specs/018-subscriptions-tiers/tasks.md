---
description: "Task list — Vendor Subscription Tiers (Phase 1.7)"
---

# Tasks: Vendor Subscription Tiers (Phase 1.7)

**Input**: Design documents from `/specs/018-subscriptions-tiers/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-vendor.yaml, contracts/api-admin.yaml, contracts/events.md, quickstart.md

**Tests**: Pest tests are REQUIRED (per `CLAUDE.md` "Required coverage for every feature": happy path, auth, authorization, validation, idempotency, locale EN+AR, all three product types where type-aware). Test tasks are interleaved per user story.

**Organization**: Six user stories from spec.md, P1 → P3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete-task dependencies)
- **[Story]**: US1–US6 (matches spec.md user stories)
- All paths absolute from repo root `G:\instaparty\instaparty_backend_cores\`

## Path Conventions

Modular Laravel monolith. New module at `app/Modules/Subscriptions/`. Cross-module edits in Identity, Catalog, Discovery, Settlement, Payments. Tests under `tests/Feature/Modules/Subscriptions/` and `tests/Unit/Modules/Subscriptions/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Scaffold the new module and update planning docs.

- [X] T001 Create module skeleton directory tree under `app/Modules/Subscriptions/` matching the layout in `plan.md` (Domain/, Application/, Infrastructure/, Http/, Filament/, Routes/, Database/Migrations/, Database/Seeders/, Database/Factories/, Resources/lang/{en,ar}/, Providers/)
- [X] T002 [P] Create `app/Modules/Subscriptions/Providers/SubscriptionsServiceProvider.php` (loadMigrationsFrom, loadTranslationsFrom 'subscriptions', register routes vendor.php/admin.php/customer.php with sanctum middleware groups, bind contracts, register listeners)
- [X] T003 [P] Register `SubscriptionsServiceProvider` in `bootstrap/providers.php`
- [X] T004 [P] Create empty route stubs `app/Modules/Subscriptions/Routes/{vendor.php,admin.php,customer.php}` and module locale stubs `app/Modules/Subscriptions/Resources/lang/{en,ar}/subscription.php`
- [X] T005 [P] Author `docs/adr/ADR-0013-subscription-tiers-module.md` from the new-module ADR template (cite FR-001..FR-030, the 8 schema artifacts, the 5th-level commission fallback, the override-layering decision, the recurring-token feature flag)
- [X] T006 [P] Update `docs/specs/09_Phasing_Plan.md` to add Phase 1.7 row (Subscriptions, between Phase 5 and Phase 6) and update the cut-list section
- [X] T007 [P] Update `docs/specs/01_PRD.md` §5.2 / §11 to remove vendor subscription tiers from the Phase-2-only list and add a back-reference to Phase 1.7
- [X] T008 [P] Update `CLAUDE.md` "Phase 1 modules:" line to include `Subscriptions`
- [X] T009 [P] Update `CLAUDE.md` "Architecture Decision Records (ADR)" current ADRs list to add ADR-0013

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enums, models, base contracts. **MUST complete before any user-story phase.**

**⚠️ CRITICAL**: No story work begins until this phase is green.

### Migrations (table T1–T6 + alter T7 from data-model.md)

- [X] T010 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000001_create_subscription_plans_table.php` (utf8mb4, public_id ULID, plan_code UNIQUE, JSON name/description, monthly/yearly *_minor + *_currency, is_default, is_published, display_order, audit cols, softDeletes) — implements T1 in data-model.md, FR-001
- [X] T011 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000002_create_plan_features_table.php` (subscription_plan_id cascadeOnDelete, feature_key, value_type ENUM, value_int/value_bool/value_string, JSON label, UNIQUE(plan_id, feature_key)) — T2, FR-002
- [X] T012 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000003_create_vendor_subscriptions_table.php` (public_id, vendor_profile_id restrict, subscription_plan_id restrict, status, billing_cycle, period cols, grace_period_ends_at, cancel_at_period_end, is_admin_override, override_reason/expires_at, gateway_token_ref, started_at/ended_at/ended_reason, audit cols, indexes per data-model.md §T3) — T3, FR-004/FR-007/FR-020
- [X] T013 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000004_create_subscription_invoices_table.php` (append-only — no updated_at, no deleted_at; UNIQUE(vendor_subscription_id, period_start, period_end); status ENUM with status-only updates; idempotency_key column) — T4, FR-005/FR-022
- [X] T014 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000005_create_subscription_payments_table.php` (append-only insert-only except status transition on latest row; UNIQUE(invoice_id, attempt_no); UNIQUE(gateway, gateway_ref) WHERE NOT NULL; mode ENUM `vendor_initiated`|`recurring_token`) — T5, FR-006/FR-008
- [X] T015 [P] Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000006_create_subscription_audit_table.php` (append-only ledger, no updated_at; event_type VARCHAR; actor_type/actor_id; before/after/metadata JSON; reason TEXT) — T6, FR-022/FR-023
- [X] T016 Create migration `app/Modules/Subscriptions/Database/Migrations/2026_05_03_000007_add_subscription_plan_id_to_commission_rates.php` (add NULL FK + index `(subscription_plan_id, product_type, category_id)`) — T7, FR-018 — NOT [P], must run after T010
- [X] T017 Run `php artisan migrate` and verify all 7 migrations apply cleanly; depends on T010..T016

### Enums (T2 from plan.md)

- [X] T018 [P] Create `app/Modules/Subscriptions/Domain/Enums/PlanCode.php` (Free, Silver, Gold, Premium with `from()` and `label()`)
- [X] T019 [P] Create `app/Modules/Subscriptions/Domain/Enums/BillingCycle.php` (Monthly, Yearly, None)
- [X] T020 [P] Create `app/Modules/Subscriptions/Domain/Enums/SubscriptionStatus.php` (Active, PastDue, Cancelled, Expired, Superseded)
- [X] T021 [P] Create `app/Modules/Subscriptions/Domain/Enums/InvoiceStatus.php` (Pending, Paid, Failed, Refunded)
- [X] T022 [P] Create `app/Modules/Subscriptions/Domain/Enums/SubscriptionEventType.php` — exactly 14 cases per `contracts/events.md`

### Models (relationships, casts, scopes ONLY — no business logic per CLAUDE.md §3)

- [X] T023 [P] Create `app/Modules/Subscriptions/Domain/Models/SubscriptionPlan.php` with `$translatable = ['name','description']`, MoneyCast on monthly/yearly, soft deletes, scopes `published()`, `default()`
- [X] T024 [P] Create `app/Modules/Subscriptions/Domain/Models/PlanFeature.php` with relation to plan and value-resolver scope (`forKey($key)`)
- [X] T025 [P] Create `app/Modules/Subscriptions/Domain/Models/VendorSubscription.php` with state machine cast (spatie/laravel-model-states) → see T031 for state classes; scopes `active()`, `dueForRenewal($at)`, `inGrace($at)`
- [X] T026 [P] Create `app/Modules/Subscriptions/Domain/Models/SubscriptionInvoice.php` (append-only, no UPDATED_AT timestamp; MoneyCast on amount; status ENUM cast)
- [X] T027 [P] Create `app/Modules/Subscriptions/Domain/Models/SubscriptionPayment.php` (append-only insert-only)
- [X] T028 [P] Create `app/Modules/Subscriptions/Domain/Models/SubscriptionAuditEntry.php` (insert-only)

### State machine (spatie/laravel-model-states)

- [X] T029 [P] Create `app/Modules/Subscriptions/Domain/States/SubscriptionState.php` abstract base + transition map per data-model.md §T3
- [X] T030 [P] Create concrete state classes `ActiveState.php`, `PastDueState.php`, `CancelledState.php`, `ExpiredState.php`, `SupersededState.php` under `app/Modules/Subscriptions/Domain/States/` (each lives in its own file, [P]-able)
- [X] T031 Wire state classes into `VendorSubscription` (T025); depends on T025 and T030

### Domain events (T1 from contracts/events.md, fired post-commit)

- [X] T032 [P] Create event classes in `app/Modules/Subscriptions/Domain/Events/` — `SubscriptionActivated`, `SubscriptionRenewed`, `SubscriptionPastDue`, `SubscriptionExpired`, `SubscriptionCancelled`, `SubscriptionTierChanged`, `AdminOverrideApplied`, `AdminOverrideEnded` (one file each, [P])

### Cross-module contracts (Domain/Contracts — never importable models)

- [X] T033 [P] Create `app/Modules/Subscriptions/Domain/Contracts/SubscriptionPolicyContract.php` (canCreateService(int $vendorProfileId, ProductType $type), canFeature, canImportExcel, featuredCap, maxActiveServices, commissionDiscountBps, currentPlanCode — returns DTOs only, never models)
- [X] T034 [P] Create `app/Modules/Subscriptions/Domain/Contracts/CommissionTierLookup.php` (resolveTierBps(int $vendorProfileId): ?int) — consumed by Settlement
- [X] T035 [P] Create `app/Modules/Subscriptions/Domain/Contracts/SubscriptionRepository.php` (find, currentForVendor, listForAdmin, persist)

### Repositories + bindings

- [X] T036 [P] Create `app/Modules/Subscriptions/Infrastructure/Repositories/EloquentSubscriptionRepository.php` implementing T035
- [X] T037 [P] Create `app/Modules/Subscriptions/Infrastructure/Repositories/EloquentPlanRepository.php` (lookup by plan_code, list published)
- [X] T038 In `SubscriptionsServiceProvider::register()` bind `SubscriptionPolicyContract` → `SubscriptionPolicy`, `CommissionTierLookup` → `SubscriptionPolicy`, `SubscriptionRepository` → `EloquentSubscriptionRepository`; depends on T036, T037 and T046 (policy class)

### DTOs

- [X] T039 [P] Create `app/Modules/Subscriptions/Application/DTOs/SubscribeRequestDto.php`, `PolicyDecisionDto.php`, `PlanFeaturesDto.php`

### Domain services + feature resolver (used everywhere)

- [X] T040 [P] Create `app/Modules/Subscriptions/Application/Services/FeatureResolver.php` — request-cached + Redis-cached lookup of `(plan_id → feature_key → typed value)`; explicit invalidation hooks `forgetForPlan(int $planId)` called by Filament plan writes
- [X] T041 [P] Create `app/Modules/Subscriptions/Application/Services/SubscriptionLifecycleService.php` — helpers `enterGrace()`, `expire()`, `pauseExcessServices()` orchestration entry points
- [X] T042 Create `app/Modules/Subscriptions/Application/Services/SubscriptionPolicy.php` implementing both `SubscriptionPolicyContract` and `CommissionTierLookup`; depends on T040, T036
- [X] T043 [P] Unit test `tests/Unit/Modules/Subscriptions/FeatureResolverCacheTest.php` (cache hit/miss, invalidation on plan update)
- [X] T044 [P] Unit test `tests/Unit/Modules/Subscriptions/SubscriptionPolicyTest.php` (gate decisions across 4 tiers; commission discount lookup; admin override layering)
- [X] T045 [P] Unit test `tests/Unit/Modules/Subscriptions/SubscriptionStateTransitionTest.php` (all valid transitions and rejects on invalid ones per data-model.md §T3)

### Seeder + factories

- [X] T046 Create `app/Modules/Subscriptions/Database/Seeders/SubscriptionPlansSeeder.php` — idempotent on `plan_code`, seeds 4 plans with EN+AR per `research.md` §R2, ~9 features each, plus 4 tier-only `commission_rates` rows; depends on T017, T023, T024
- [X] T047 [P] Create `app/Modules/Subscriptions/Database/Factories/SubscriptionPlanFactory.php`
- [X] T048 [P] Create `app/Modules/Subscriptions/Database/Factories/VendorSubscriptionFactory.php` (states: `active()`, `pastDue()`, `cancelled()`, `expired()`, `withOverride()`)
- [X] T049 [P] Create `app/Modules/Subscriptions/Database/Factories/SubscriptionInvoiceFactory.php`
- [X] T050 Run `php artisan db:seed --class=SubscriptionPlansSeeder` and confirm 4 plans + ~36 features + 4 commission_rates rows; depends on T046

### Feature flag + app settings

- [X] T051 [P] Add `subscriptions.recurring_tokens_enabled` row in `feature_flags` (default false) via a one-off seed in T046 or a dedicated seeder
- [X] T052 [P] Add `subscription_grace_period_days` row in `app_settings` (default 7) via the same seed; FR-009/research.md §R1

### Outbox + audit helpers

- [X] T053 [P] Add a small helper `app/Modules/Subscriptions/Application/Services/SubscriptionAuditWriter.php` that writes both `subscription_audit` and an `event_outbox` row inside the same transaction with a stable `dedupe_hash`; used by every Action

**Checkpoint**: Phase 2 complete — schema is live, models bind correctly, contracts are bound to implementations. User story phases can begin.

---

## Phase 3: User Story 1 — New vendor auto-enrolled on Free tier (Priority: P1) 🎯 MVP

**Goal**: Every new vendor gets an active Free-tier subscription within 5 s of approval (FR-004, FR-028, SC-001), and Free-tier limits are enforced on Catalog actions (FR-013).

**Independent Test**: Approve a new vendor profile → verify a `vendor_subscriptions` row exists with `status=active`, `plan_code=free`. Attempt to publish a 6th service → verify rejection naming Silver as the unblocking tier. No payment flow needed.

### Tests (Pest, write FIRST and verify they FAIL before implementation)

- [X] T054 [P] [US1] Feature test `tests/Feature/Modules/Subscriptions/AutoEnrolFreeTierTest.php` — covers: row created within 5s of `VendorRegistered`, idempotent on retried event, audit row written
- [X] T055 [P] [US1] Feature test `tests/Feature/Modules/Subscriptions/ServiceCapEnforcementTest.php` — three groups (`rental`, `sale`, `digital`); each tests Free-tier 5-cap denial and the error message naming Silver; covers EN and AR responses

### Listeners + actions

- [X] T056 [P] [US1] Create `app/Modules/Subscriptions/Application/Actions/AutoEnrolFreeTierAction.php` (single `execute(int $vendorProfileId)`, DB::transaction → create active Free `vendor_subscriptions` row, write audit + outbox via T053, fire `SubscriptionActivated` after commit)
- [X] T057 [US1] Create `app/Modules/Subscriptions/Application/Listeners/OnVendorRegistered.php` invoking T056; register in service provider
- [X] T058 [US1] In `app/Modules/Identity/Domain/Events/VendorRegistered.php` confirm payload includes `vendorProfileId`; if missing, add (read-only widening — does not break consumers)

### Catalog gate integration (FR-013, gating type-aware)

- [X] T059 [US1] In `app/Modules/Catalog/Application/Actions/CreateRentalServiceAction.php` add a `SubscriptionPolicyContract::canCreateService($vendorId, ProductType::Rental)` check at top of `execute()`; throw `SubscriptionLimitReachedException` (new class in `app/Modules/Subscriptions/Domain/Exceptions/`) on denial
- [X] T060 [US1] Same gate in `app/Modules/Catalog/Application/Actions/CreateSaleServiceAction.php`
- [X] T061 [US1] Same gate in `app/Modules/Catalog/Application/Actions/CreateDigitalServiceAction.php`
- [ ] T062 [US1] Same gate in `app/Modules/Catalog/Application/Actions/PublishServiceAction.php` (covers transition from draft → published when count is now over the cap)
- [X] T063 [P] [US1] Create `app/Modules/Subscriptions/Domain/Exceptions/SubscriptionLimitReachedException.php` carrying `featureKey`, `currentCount`, `limit`, `unblockingPlanCode` fields; render as 422 with localised message via existing `Handler`

### Vendor read endpoint (so the limit message has somewhere to point)

- [X] T064 [P] [US1] `app/Modules/Subscriptions/Http/Controllers/Vendor/ShowSubscriptionController.php` (3-line body invoking `SubscriptionRepository::currentForVendor`)
- [X] T065 [P] [US1] `app/Modules/Subscriptions/Http/Resources/SubscriptionResource.php` and `PlanResource.php` with Scribe `@response` PHPDoc carrying EN+AR examples (per `contracts/api-vendor.yaml`)
- [ ] T066 [US1] Wire `GET /vendor/subscription` in `app/Modules/Subscriptions/Routes/vendor.php` under `auth:sanctum` + `vendor` ability middleware
- [ ] T067 [P] [US1] Add the endpoint to `.specify/memory/api-registry.md` and create the Bruno collection entry `docs/api/collections/subscriptions/show-subscription.bru`

**Checkpoint**: A new vendor is auto-enrolled, can fetch their current subscription, and is blocked at the 6th service. End-to-end demoable. ← MVP scope.

---

## Phase 4: User Story 2 — Vendor self-serves an upgrade and pays (Priority: P1)

**Goal**: Vendor browses plans, subscribes via the existing `PaymentGateway`, and on payment capture moves to the new tier with full audit + idempotency (FR-003, FR-005, FR-006, FR-007, FR-026, SC-002, SC-004).

**Independent Test**: Free-tier vendor → `GET /vendor/plans` (4 results, EN/AR) → `POST /vendor/subscribe` with idempotency key (returns 202 + checkout URL) → simulate `PaymentCaptured` webhook → verify (a) new active subscription, (b) invoice paid, (c) old subscription `superseded`, (d) audit + outbox rows written. Replay subscribe with same key → identical response, no duplicate invoice.

### Tests

- [ ] T068 [P] [US2] Feature test `tests/Feature/Modules/Subscriptions/SubscribeFlowTest.php` — happy path + idempotency replay (SC-004) + EN+AR response shape (SC-010)
- [ ] T069 [P] [US2] Feature test `tests/Feature/Modules/Subscriptions/UpgradeDowngradeTest.php` — upgrade Silver→Premium ends Silver as `superseded`; downgrade defers to `current_period_end`
- [ ] T070 [P] [US2] Feature test `tests/Feature/Modules/Subscriptions/SubscribeAuthAndValidationTest.php` — 401 anon, 403 non-vendor, 422 unknown plan_code, 422 missing billing_cycle, 422 missing Idempotency-Key

### Actions

- [ ] T071 [P] [US2] Create `app/Modules/Subscriptions/Application/Actions/SubscribeToPlanAction.php` (DB::transaction → check idempotency_keys table → create pending invoice → call `PaymentGateway::initiate` → save token if `feature_flags.subscriptions.recurring_tokens_enabled` and `save_payment_token=true` → write audit + outbox; returns DTO with checkout_url + invoice public_id)
- [ ] T072 [P] [US2] Create `app/Modules/Subscriptions/Application/Actions/HandleSubscriptionPaymentCapturedAction.php` (DB::transaction → mark invoice paid (status-only update), close prior active subscription as `superseded`, activate new one with `current_period_start = now`, `current_period_end = +1 month|+1 year`, fire `SubscriptionActivated`/`SubscriptionTierChanged` post-commit, queue notification dispatch)
- [ ] T073 [P] [US2] Create `app/Modules/Subscriptions/Application/Actions/UpgradeSubscriptionAction.php` (delegates to SubscribeToPlanAction with prior-subscription closure on capture)
- [ ] T074 [P] [US2] Create `app/Modules/Subscriptions/Application/Actions/DowngradeSubscriptionAction.php` (sets `cancel_at_period_end=true` on current; the renewal job at period end attaches the new lower-tier subscription)

### Listener

- [ ] T075 [US2] Create `app/Modules/Subscriptions/Application/Listeners/OnPaymentCaptured.php` — detects `payable_type = subscription_invoice` on `Payments\Domain\Events\PaymentCaptured` and invokes T072; register in service provider; FR-026
- [ ] T076 [US2] Confirm Payments emits `PaymentCaptured` with a discriminator. If Payments currently doesn't carry the `payable_type`, extend the event payload (additive) in `app/Modules/Payments/Domain/Events/PaymentCaptured.php`

### HTTP

- [ ] T077 [P] [US2] `app/Modules/Subscriptions/Http/Requests/SubscribeRequest.php` (`@bodyParam plan_code string required`, `@bodyParam billing_cycle string required`, `@bodyParam save_payment_token boolean optional`); validates plan_code is published + non-Free
- [ ] T078 [P] [US2] `app/Modules/Subscriptions/Http/Controllers/Vendor/SubscribeController.php` (3-line body) — enforces `Idempotency-Key` middleware
- [ ] T079 [P] [US2] `app/Modules/Subscriptions/Http/Controllers/Vendor/ListPlansController.php`
- [ ] T080 [P] [US2] `app/Modules/Subscriptions/Http/Resources/PlanCollection.php` and `InvoiceResource.php` with EN+AR `@response` examples per `contracts/api-vendor.yaml`
- [ ] T081 [US2] Wire routes `GET /vendor/plans` and `POST /vendor/subscribe` in `Routes/vendor.php` (sanctum + vendor ability + `IdempotencyKey` middleware on POST)
- [ ] T082 [P] [US2] Add both endpoints to `.specify/memory/api-registry.md` and Bruno collection

### Notification templates

- [ ] T083 [P] [US2] Add `notification_templates` rows for `subscription.activated` and `subscription.upgraded` (EN+AR; channels email + in_app + push) via a small companion seeder; FR-025/research.md §R8

**Checkpoint**: Free vendor can pay and arrive on Silver/Gold/Premium with audit trail; idempotency proven. Phase 4 + Phase 3 = monetisation MVP.

---

## Phase 5: User Story 3 — Auto-renewal, grace, expiry, downgrade (Priority: P1)

**Goal**: Reliable cycle (FR-008, FR-009, FR-010, FR-011, FR-027) with auto-pause of excess services on downgrade (oldest first, never deleted; SC-007).

**Independent Test**: paid sub with `current_period_end` in the past → renewal job runs → simulate gateway fail → status `past_due`, grace = +7 days → advance time → expiration job → status `expired`, fresh Free attached, services > 5 paused oldest-first.

### Tests

- [ ] T084 [P] [US3] Feature test `tests/Feature/Modules/Subscriptions/CancelSubscriptionTest.php` — cancel sets `cancel_at_period_end`, no immediate refund, idempotent
- [ ] T085 [P] [US3] Feature test `tests/Feature/Modules/Subscriptions/RenewalSuccessTest.php` — recurring_token mode + vendor_initiated mode (feature flag toggle) both work and produce a renewed period
- [ ] T086 [P] [US3] Feature test `tests/Feature/Modules/Subscriptions/RenewalFailureGraceExpiryTest.php` — full lifecycle Active → PastDue → Expired → Free; verifies `subscription_audit` rows for every transition (SC-008) and notification_dispatches rows at 24h/72h/144h grace warnings
- [ ] T087 [P] [US3] Feature test `tests/Feature/Modules/Subscriptions/AutoPauseExcessServicesTest.php` — three product-type groups; oldest-first pause; never deletes; reversible via republish

### Actions + jobs

- [ ] T088 [P] [US3] Create `app/Modules/Subscriptions/Application/Actions/RenewSubscriptionAction.php` — branches on `feature_flags.subscriptions.recurring_tokens_enabled`: (a) `chargeWithToken` → on success activate new period + `SubscriptionRenewed`; on fail → `SubscriptionPastDue` + grace = now + `subscription_grace_period_days`. (b) `vendor_initiated`: create pending invoice + notify; if invoice unpaid by period_end+grace, expire.
- [ ] T089 [P] [US3] Create `app/Modules/Subscriptions/Application/Actions/ProcessExpirationsAction.php` — sweeps `vendor_subscriptions WHERE status='past_due' AND grace_period_ends_at <= now`, transitions to `expired`, calls `AutoEnrolFreeTierAction`, fires `SubscriptionExpired`
- [ ] T090 [P] [US3] Create `app/Modules/Subscriptions/Application/Actions/CancelSubscriptionAction.php` — sets `cancel_at_period_end=true`, audit + outbox; FR-011
- [ ] T091 [P] [US3] Create `app/Modules/Subscriptions/Application/Actions/PauseExcessServicesAction.php` — uses Catalog's existing service unpublish/pause flow via a contract method exposed from Catalog (`PausableServicesContract`); oldest-first by `created_at ASC`
- [ ] T092 [US3] In Catalog, expose `app/Modules/Catalog/Domain/Contracts/PausableServicesContract.php` (interface) + `app/Modules/Catalog/Infrastructure/Services/EloquentPausableServicesAdapter.php` (impl); bind in `CatalogServiceProvider`. Subscriptions consumes the contract.
- [ ] T093 [US3] Create `app/Modules/Subscriptions/Application/Listeners/OnSubscriptionExpired.php` invoking T091; register in service provider; FR-027

### Cron registration

- [ ] T094 [US3] Register schedule entries in `app/Console/Kernel.php`: hourly `subscriptions:renew` (T088 wrapper command), every 30 min `subscriptions:expire-grace` (T089 wrapper), daily `subscriptions:end-overrides` (T108 wrapper, see US6)
- [ ] T095 [P] [US3] Create artisan commands `app/Console/Commands/SubscriptionsRenewCommand.php` and `SubscriptionsExpireGraceCommand.php` (queue dispatch; carry `--simulate-failure`/`--simulate-clock-advance` flags for dev use only)

### HTTP

- [ ] T096 [P] [US3] `app/Modules/Subscriptions/Http/Controllers/Vendor/CancelSubscriptionController.php` (3-line body)
- [ ] T097 [P] [US3] `app/Modules/Subscriptions/Http/Requests/CancelSubscriptionRequest.php` (optional `@bodyParam reason string`); idempotency middleware
- [ ] T098 [US3] Wire `POST /vendor/subscription/cancel` in `Routes/vendor.php`
- [ ] T099 [P] [US3] Update `.specify/memory/api-registry.md` and Bruno collection

### Notification templates

- [ ] T100 [P] [US3] Add `notification_templates` rows for `subscription.cancelled_pending`, `subscription.renewal_succeeded`, `subscription.renewal_failed_first`, `subscription.renewal_failed_grace_warning`, `subscription.expired_downgraded` (EN+AR; per research.md §R8)

**Checkpoint**: The full subscription lifecycle runs reliably. SC-003, SC-007, SC-008 demonstrably met.

---

## Phase 6: User Story 4 — Commission tier fallback in Settlement (Priority: P2)

**Goal**: Settlement's commission resolver gains a 5th-level fallback by `subscription_plan_id`; snapshot at booking time per `booking_items.commission_bps` is unchanged retroactively (FR-018, FR-019, SC-006).

**Independent Test**: 24-case matrix (4 tiers × 3 product types × 2 category-override scenarios) — every case picks the right rate, snapshot frozen on booking.

### Tests

- [ ] T101 [P] [US4] Feature test `tests/Feature/Modules/Subscriptions/CommissionTierFallbackTest.php` — full 24-case matrix asserting both resolver output and `booking_items.commission_bps` snapshot

### Resolver wiring

- [ ] T102 [US4] Edit `app/Modules/Settlement/Application/Services/CommissionRateResolver.php` to accept an optional `CommissionTierLookup` (constructor injection) and consult it as the 5th-level fallback (lowest priority, only when levels 1–4 produce no match)
- [ ] T103 [US4] In `SettlementServiceProvider::register()` inject the contract resolved from the container (already bound in T038); Settlement does **not** import any Subscriptions model
- [ ] T104 [US4] Confirm the snapshot path in `app/Modules/Booking/Application/Actions/CreateBookingAction.php` (or wherever booking_items.commission_bps is set) calls the resolver and copies the bps onto the row at booking time — no code change needed if it already does, otherwise minimal edit

**Checkpoint**: Premium vendors pay the discounted commission on every new booking; legacy bookings untouched.

---

## Phase 7: User Story 5 — Featured + tier-gated capabilities in Discovery (Priority: P2)

**Goal**: `canFeature` + `featuredCap` enforced; expired-tier vendors drop from featured listings (FR-015, FR-016).

**Independent Test**: Free vendor cannot feature; Silver vendor capped at 2; Gold vendor capped at 5; expired Gold vendor's featured services drop from Discovery query.

### Tests

- [ ] T105 [P] [US5] Feature test `tests/Feature/Modules/Subscriptions/FeatureGateTest.php` — Free denied; Silver allowed up to cap then denied; Gold allowed up to higher cap; expired-vendor exclusion in Discovery query
- [ ] T106 [P] [US5] Feature test `tests/Feature/Modules/Subscriptions/ExcelImportGateTest.php` — three product-type groups; Free + Silver denied, Gold + Premium allowed

### Wiring

- [ ] T107 [US5] Edit `app/Modules/Discovery/Application/Actions/FeatureServiceAction.php` to call `SubscriptionPolicyContract::canFeature` + cap check at top of execute; throw localised `SubscriptionLimitReachedException`
- [ ] T108 [US5] Edit `app/Modules/Discovery/Infrastructure/Repositories/EloquentDiscoveryReader.php` featured-list query to LEFT JOIN active vendor_subscriptions and filter out vendors whose `status` is not `active` or `past_due` (no direct model import — use a contract `ActiveVendorTierLookup` exposed from Subscriptions if needed; otherwise a raw join on the table is acceptable since Settlement also reads `commission_rates` cross-table)
- [ ] T109 [US5] Edit Catalog's three `Import{Type}ServicesFromExcelAction` (rental/sale/digital) to call `SubscriptionPolicyContract::canImportExcel` at top; FR-014

**Checkpoint**: Tier-based promotional levers and Excel imports work end-to-end.

---

## Phase 8: User Story 6 — Admin override (Priority: P3)

**Goal**: Admin layers a tier override on top of any vendor's subscription; underlying paid subscription keeps renewing; full audit (FR-020, SC-009).

**Independent Test**: Admin overrides Free vendor → Premium for 30 days. Effective tier flips immediately. Audit row written. After 30 days, override sweep returns vendor to underlying state. No invoice/payment created.

### Tests

- [ ] T110 [P] [US6] Feature test `tests/Feature/Modules/Subscriptions/AdminOverrideTest.php` — apply, layer-on-top semantics (paid sub keeps renewing concurrently), expiry sweep, audit completeness, replace-existing-override flow

### Actions

- [ ] T111 [P] [US6] Create `app/Modules/Subscriptions/Application/Actions/ApplyAdminOverrideAction.php` — closes any prior active override, inserts new active row with `is_admin_override=true`, audit + outbox; fires `AdminOverrideApplied` + `SubscriptionTierChanged`
- [ ] T112 [P] [US6] Create `app/Modules/Subscriptions/Application/Actions/EndAdminOverrideAction.php` — closes active override row, fires `AdminOverrideEnded` + `SubscriptionTierChanged` (back to underlying tier)
- [ ] T113 [P] [US6] Create artisan command `app/Console/Commands/SubscriptionsEndOverridesCommand.php` (sweeps overrides where `override_expires_at <= now`); registered in T094

### HTTP

- [ ] T114 [P] [US6] `app/Modules/Subscriptions/Http/Controllers/Admin/ListSubscriptionsController.php` and `OverrideSubscriptionController.php` (3-line bodies)
- [ ] T115 [P] [US6] `app/Modules/Subscriptions/Http/Requests/OverrideSubscriptionRequest.php` (`@bodyParam plan_code string required`, `@bodyParam reason string required min:4`, `@bodyParam expires_at date optional`)
- [ ] T116 [US6] Wire `GET /admin/subscriptions` and `POST /admin/subscriptions/{id}/override` under `auth:sanctum` + Shield permission middleware
- [ ] T117 [P] [US6] Update `.specify/memory/api-registry.md` and Bruno collection

### Filament

- [ ] T118 [P] [US6] Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionPlanResource.php` (CRUD; translatable plugin; locale tabs; money columns `->money('EGP', divideBy: 100)`; uses `bezhansalleh/filament-shield` for `view_subscription_plan`/`update_subscription_plan`/etc.) — matches Filament rules in `.claude/rules/filament*.md`
- [ ] T119 [P] [US6] Create `SubscriptionPlanResource/Pages/{ListSubscriptionPlans,CreateSubscriptionPlan,EditSubscriptionPlan}.php`
- [ ] T120 [P] [US6] Create `app/Modules/Subscriptions/Filament/Resources/VendorSubscriptionResource.php` (read-only list filtered by plan/status/renews_before; row action "Override tier" delegating to `ApplyAdminOverrideAction`; another action "End override" delegating to `EndAdminOverrideAction`; tier badge column with per-tier color map)
- [ ] T121 [P] [US6] Create `VendorSubscriptionResource/Pages/{ListVendorSubscriptions,ViewVendorSubscription}.php`
- [ ] T122 [US6] Run `php artisan shield:generate --all` and confirm permissions `view_subscription_plan`, `view_any_subscription_plan`, `create_subscription_plan`, `update_subscription_plan`, `delete_subscription_plan`, `view_any_vendor_subscription`, `view_vendor_subscription`, `override_vendor_subscription` exist

### Notification templates

- [ ] T123 [P] [US6] Add `notification_templates` rows for `subscription.admin_override_applied` and `subscription.admin_override_ended` (EN+AR)

**Checkpoint**: Admin override end-to-end with audit; SC-009 met.

---

## Phase 9: Polish & Cross-Cutting

- [ ] T124 [P] Run `./vendor/bin/pint` across `app/Modules/Subscriptions/`, `tests/Feature/Modules/Subscriptions/`, `tests/Unit/Modules/Subscriptions/`, and the touched files in Identity/Catalog/Discovery/Settlement/Payments
- [ ] T125 [P] Run `./vendor/bin/phpstan analyse` and resolve any new findings introduced by this feature
- [ ] T126 [P] Run `php artisan scribe:generate` and verify all 6 endpoints render with EN+AR `@response` examples
- [ ] T127 [P] Run the full suite `./vendor/bin/pest --group=subscriptions` and the cross-cutting groups `./vendor/bin/pest --group=rental --group=sale --group=digital` — all green
- [ ] T128 Walk through `specs/018-subscriptions-tiers/quickstart.md` end-to-end on a clean dev DB; fix any gaps in this tasks list and resolve
- [ ] T129 [P] Verify `docs/specs/09_Phasing_Plan.md`, `01_PRD.md`, `CLAUDE.md`, ADR-0013, and `.specify/memory/api-registry.md` are all updated and committed
- [ ] T130 Self-review against `superpowers:requesting-code-review` checklist, then open PR back to base branch `002-identity-vendor-onboarding`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: no dependencies; everything in P1 can run [P] except T001 which must seed the directory tree first.
- **Phase 2 (Foundational)**: depends on Phase 1. **BLOCKS all user-story phases.** Within Phase 2, migrations T010–T015 are [P] but T016 alters `commission_rates` and must wait for them; T017 (run migrate) is the gate for T046 (seeder) and T050.
- **Phase 3 (US1)**: depends on Phase 2 fully complete. MVP scope.
- **Phase 4 (US2)**: depends on Phase 2 complete + US1 complete (auto-enrol must work before upgrades have a "from" state).
- **Phase 5 (US3)**: depends on Phase 4 complete (renewals act on subscriptions created in Phase 4).
- **Phase 6 (US4)**: depends on Phase 2; can run in parallel with Phases 4–5 once contracts are bound (T038).
- **Phase 7 (US5)**: depends on Phase 2; can run in parallel with Phases 4–6 once contracts are bound.
- **Phase 8 (US6)**: depends on Phases 2 + 4 (override needs concrete plans + an upgrade path to test layering).
- **Phase 9 (Polish)**: depends on whatever stories are being shipped this slice.

### Within Each User Story

- Pest tests are written FIRST for each story and must FAIL before implementation tasks land.
- Models → contracts/services → actions → listeners → controllers/requests/resources → routes → docs.
- No story crosses into another story's files; the only shared mutation point is `Routes/vendor.php` and `Routes/admin.php` (kept small enough to merge cleanly).

### Parallel Opportunities

- All Phase 1 tasks except T001 are [P].
- Phase 2: migrations T010–T015 [P]; enums T018–T022 [P]; models T023–T028 [P]; states T029–T030 [P]; events T032 [P]; contracts T033–T035 [P]; repositories T036–T037 [P]; DTOs T039 [P]; services T040–T041 [P]; unit tests T043–T045 [P]; factories T047–T049 [P].
- Within each story: tests, model edits, and HTTP layer files are typically [P] amongst themselves.
- Phases 6 and 7 can be staffed by separate developers in parallel with Phases 4–5 once Phase 2 is done.

---

## Parallel Example: Phase 2 Models + Migrations

```bash
# Migrations (after directory scaffold)
Task: "T010 Create migration ...create_subscription_plans_table.php"
Task: "T011 Create migration ...create_plan_features_table.php"
Task: "T012 Create migration ...create_vendor_subscriptions_table.php"
Task: "T013 Create migration ...create_subscription_invoices_table.php"
Task: "T014 Create migration ...create_subscription_payments_table.php"
Task: "T015 Create migration ...create_subscription_audit_table.php"

# Then in another wave:
Task: "T018..T022 enums [P]"
Task: "T023..T028 models [P]"
Task: "T032 events [P]"
```

---

## Implementation Strategy

### MVP slice — ship Phases 1 + 2 + 3 (US1 only)

Free auto-enrolment plus Catalog gate is the smallest deployable increment that still:

- Doesn't regress any existing flow (no vendor sees a behaviour change unless they've been on the platform for less than the deploy window AND already at a service count > 5).
- Lets the team validate the policy gate is correctly wired before exposing payment.
- Provides a `GET /vendor/subscription` for the vendor app to start displaying tier info.

### Increment 2 — add monetisation (Phases 4 + 5)

After MVP, ship US2 + US3 together — they are the revenue path. Validate end-to-end including renewals, grace, and downgrade auto-pause before opening the door to admin tooling.

### Increment 3 — discounts + featured + admin

Phases 6 (US4 commission), 7 (US5 featured + Excel), and 8 (US6 admin override) can land in any order or in parallel.

### Final — polish + sign-off

Phase 9 (lint, phpstan, scribe, full test groups, docs, PR).

---

## Notes

- [P] tasks = different files, no incomplete-task dependency.
- Every Pest feature test in this list runs the EN+AR locale matrix where the response is user-facing.
- Where a task touches an existing module (Catalog/Discovery/Settlement/Payments/Identity), it adds a SINGLE-LINE policy check or a contract-binding edit — never imports a Subscriptions model.
- All payment-mutating endpoints use the existing `idempotency_keys` table with the `(route, key, user_id)` scoping rule per `CLAUDE.md` §11.
- All money columns are `*_minor` BIGINT UNSIGNED + `*_currency` CHAR(3); never `DECIMAL` / `FLOAT` per `migrations.md`.
- All translatable plan content is JSON via `spatie/laravel-translatable`, EN+AR required before `is_published=true` (FR-024).
- All append-only tables (`subscription_invoices`, `subscription_payments`, `subscription_audit`, `event_outbox`) MUST never receive a soft-delete column or a non-status UPDATE per CLAUDE.md §15.
- Domain events MUST fire after `DB::transaction` commit using `DB::afterCommit()` per CLAUDE.md §7.
- Stop and re-validate at the end of each Phase. Don't bundle phases into one mega-PR.
