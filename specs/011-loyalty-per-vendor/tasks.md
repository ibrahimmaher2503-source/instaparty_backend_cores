# Tasks: Phase 5.2 — Loyalty (Per-Vendor)

**Input**: Design documents from `specs/011-loyalty-per-vendor/`
**Prerequisites**: [spec.md](./spec.md) ✅ | [plan.md](./plan.md) ✅ | [ADR-0012](../../docs/adr/0012-loyalty-module.md) ✅ **Accepted**
**Phase**: 5.2 — 2 days, Week 6 (`docs/specs/09_Phasing_Plan.md`)
**PRD coverage**: PRD §"Software Description" (loyalty per-vendor locked); spec FR-LOY-001..042
**Spec User Stories**: US1 (P1) vendor configures program · US2 (P1) customer earns on completion · US3 (P1) customer redeems on new booking

**Format**:
```
- [ ] T### [P?] [USx?] Description
  - File: exact/path/to/file.php
  - Source: spec.md FR-LOY-### or plan.md §X or ADR-0012 §X or 11_DB_Schema.md §"Loyalty (4)"
  - [P] = parallel-safe (must not touch the same file as another [P] in the same batch)
```

---

## Phase 1 — Setup & ADR Finalization

- [X] T001 Resolve ADR-0012 §11 open questions (multi-vendor earn granularity; redemption on paused programs; floor-vs-round on partial-refund reversal); flip status `Proposed → Accepted`; update `docs/adr/README.md` index status column
  - File: `docs/adr/0012-loyalty-module.md`, `docs/adr/README.md`
  - Source: plan.md §1; new-module-adr skill output; CLAUDE.md §"ADR before code"

- [X] T002 Create `LoyaltyServiceProvider` skeleton; register in `bootstrap/providers.php`; load migrations from `Database/Migrations`, translations from `Resources/lang` (namespace `loyalty`), routes from `Routes/{vendor,customer,admin}.php`, register listeners on `BookingCompleted`, `BookingCancelled`, `RefundFinalized`, bind 4 contracts in `register()`
  - File: `app/Modules/Loyalty/Providers/LoyaltyServiceProvider.php`, `bootstrap/providers.php`
  - Source: plan.md §3; `.claude/rules/modules.md` §"Module ServiceProvider"

---

## Phase 2 — Foundational

### Migrations (FK dependency order — sequential, blocked on T001)

- [X] T003 Migration: `loyalty_programs` — `id` BIGINT, `public_id` CHAR(26) UNIQUE, `vendor_profile_id` BIGINT FK→`vendor_profiles.id` UNIQUE restrict-on-delete, `name` JSON, `terms` JSON nullable, `currency` CHAR(3) default `EGP`, `status` ENUM(`active`,`paused`,`archived`) default `active`, `expiration_days` SMALLINT UNSIGNED nullable, `created_by`/`updated_by` FK→users.id nullable, timestamps. Indexes: UNIQUE `(vendor_profile_id)`, INDEX `(status)`. utf8mb4. NO softDeletes (use `status='archived'`).
  - File: `app/Modules/Loyalty/Database/Migrations/2026_05_03_120001_create_loyalty_programs_table.php`
  - Source: plan.md §4 row 1; 11_DB_Schema.md §"Loyalty (4)"; FR-LOY-001, FR-LOY-002; `.claude/rules/migrations.md`

- [X] T004 Migration: `loyalty_rules` — `id` BIGINT, `public_id` CHAR(26), `loyalty_program_id` FK→`loyalty_programs.id` cascade-on-delete, `label` JSON, `earn_points_per_minor` INT UNSIGNED, `earn_minor_per_unit` INT UNSIGNED, `redemption_ratio_points` INT UNSIGNED, `redemption_ratio_minor` INT UNSIGNED, `min_points_to_redeem` INT UNSIGNED default 0, `max_redeem_pct_bps` SMALLINT UNSIGNED default 5000, `is_active` BOOLEAN default 1, `effective_from` TIMESTAMP, timestamps. Indexes: `(loyalty_program_id, is_active)`. CHECK `max_redeem_pct_bps <= 5000`. utf8mb4.
  - File: `app/Modules/Loyalty/Database/Migrations/2026_05_03_120002_create_loyalty_rules_table.php`
  - Source: plan.md §4 row 2; 11_DB_Schema.md §"Loyalty (4)"; FR-LOY-003, FR-LOY-004

- [X] T005 Migration: `loyalty_redemptions` — `id` BIGINT, `public_id` CHAR(26), `customer_id` FK→`users.id` restrict, `vendor_profile_id` FK→`vendor_profiles.id` restrict, `loyalty_program_id` FK, `loyalty_rule_id` FK→`loyalty_rules.id` restrict (rule snapshot), `booking_id` FK→`bookings.id` restrict, `points_held` INT UNSIGNED, `discount_minor` BIGINT UNSIGNED, `discount_currency` CHAR(3), `status` VARCHAR (managed by `RedemptionState`), `applied_at`/`voided_at`/`reversed_at` TIMESTAMP nullable, timestamps. Indexes: UNIQUE `(booking_id, status)` (partial via generated column trick) for `pending`/`applied`; `(customer_id, vendor_profile_id, status)`. utf8mb4.
  - File: `app/Modules/Loyalty/Database/Migrations/2026_05_03_120003_create_loyalty_redemptions_table.php`
  - Source: plan.md §4 row 4; 11_DB_Schema.md §"Loyalty (4)"; FR-LOY-022, FR-LOY-023

- [X] T006 Migration: `loyalty_ledger` — APPEND-ONLY: `created_at` only (NO `updated_at`, NO `deleted_at`, NO `softDeletes()`). `id` BIGINT, `public_id` CHAR(26), `customer_id` FK restrict, `vendor_profile_id` FK restrict, `loyalty_program_id` FK restrict, `entry_type` ENUM(`earn`,`redeem`,`reversal`,`void_release`), `points` INT (signed), `booking_id` FK nullable, `booking_item_id` FK nullable, `redemption_id` FK→`loyalty_redemptions.id` nullable restrict, `reversed_from_ledger_id` BIGINT nullable self-FK restrict, `product_type` ENUM(`rental`,`sale`,`digital`) nullable (denorm), `reason` JSON. Indexes: UNIQUE `(booking_item_id)` partial via generated column where `entry_type='earn'`; `(customer_id, vendor_profile_id, created_at)`; `(vendor_profile_id, entry_type, created_at)`. utf8mb4.
  - File: `app/Modules/Loyalty/Database/Migrations/2026_05_03_120004_create_loyalty_ledger_table.php`
  - Source: plan.md §4 row 3; 11_DB_Schema.md §"Loyalty (4)" + "Append-only tables"; FR-LOY-012, FR-LOY-013, FR-LOY-030; CLAUDE.md §15

- [X] T007 Run `php artisan migrate` to apply T003–T006
  - File: (CLI)
  - Source: migrate-module skill §"After confirmation"

### Enums (parallel-safe — distinct files)

- [X] T008 [P] `ProgramStatus` backed enum: `active`, `paused`, `archived`
  - File: `app/Modules/Loyalty/Domain/Enums/ProgramStatus.php`
  - Source: plan.md §3 Enums; FR-LOY-002

- [X] T009 [P] `LedgerEntryType` backed enum: `earn`, `redeem`, `reversal`, `void_release`
  - File: `app/Modules/Loyalty/Domain/Enums/LedgerEntryType.php`
  - Source: plan.md §3 Enums; FR-LOY-012, FR-LOY-024..026

### Models (parallel-safe — distinct files; relationships, casts, scopes ONLY)

- [X] T0XX [P] `LoyaltyProgram` model — `HasUlids` for `public_id`, `$translatable = ['name','terms']`, `status` cast to `ProgramStatus`, `belongsTo` VendorProfile via `vendor_profile_id`, `hasMany` LoyaltyRule, `hasOne` activeRule scope (`is_active=true`)
  - File: `app/Modules/Loyalty/Domain/Models/LoyaltyProgram.php`
  - Source: ADR-0012 §6; plan.md §3; CLAUDE.md §"Models hold relationships/casts/scopes ONLY"

- [X] T0XX [P] `LoyaltyRule` model — `HasUlids`, `$translatable = ['label']`, `is_active` bool cast, `effective_from` datetime cast, `belongsTo` LoyaltyProgram, scope `active()`
  - File: `app/Modules/Loyalty/Domain/Models/LoyaltyRule.php`
  - Source: ADR-0012 §6; plan.md §3; FR-LOY-003

- [X] T0XX [P] `LoyaltyRedemption` model — `HasUlids`, `status` cast via `spatie/laravel-model-states` to `RedemptionState`, money cast on `discount_minor`+`discount_currency` via `MoneyCast`, `belongsTo` Customer (User), VendorProfile, LoyaltyProgram, LoyaltyRule, Booking, `hasMany` ledgerEntries (where `redemption_id`)
  - File: `app/Modules/Loyalty/Domain/Models/LoyaltyRedemption.php`
  - Source: ADR-0012 §6; plan.md §3; FR-LOY-022

- [X] T0XX `LoyaltyLedgerEntry` model — `HasUlids`, `public $timestamps = false;`, manual `created_at` set in `creating` hook, `entry_type` cast to `LedgerEntryType`, `product_type` cast to `ProductType`, `$translatable = ['reason']`, `belongsTo` Customer (User), VendorProfile, LoyaltyProgram, LoyaltyRedemption, `belongsTo` reversedFrom (self-FK). **`booted()` MUST register `updating` and `deleting` listeners that throw `RuntimeException`.** NO `SoftDeletes`.
  - File: `app/Modules/Loyalty/Domain/Models/LoyaltyLedgerEntry.php`
  - Source: ADR-0012 §6.1; plan.md §10 test 2; FR-LOY-030; CLAUDE.md §15

### States (sequential — same `States/Redemption/` folder)

- [X] T0XX `RedemptionState` abstract + 4 concrete states (`Pending`, `Applied`, `Voided`, `Reversed`) using `spatie/laravel-model-states`. Allowed transitions: `Pending → Applied`, `Pending → Voided`, `Applied → Reversed`. Reject all others.
  - File: `app/Modules/Loyalty/Domain/States/Redemption/RedemptionState.php`, `Pending.php`, `Applied.php`, `Voided.php`, `Reversed.php`
  - Source: plan.md §3 States; FR-LOY-022

### Domain Events (parallel-safe — distinct files)

- [X] T0XX [P] Create 6 domain event classes (readonly): `LoyaltyProgramConfigured`, `LoyaltyPointsEarned`, `LoyaltyRedemptionApplied`, `LoyaltyRedemptionVoided`, `LoyaltyRedemptionReversed`, `LoyaltyLedgerEntryAppended`
  - File: `app/Modules/Loyalty/Domain/Events/{LoyaltyProgramConfigured,LoyaltyPointsEarned,LoyaltyRedemptionApplied,LoyaltyRedemptionVoided,LoyaltyRedemptionReversed,LoyaltyLedgerEntryAppended}.php`
  - Source: plan.md §8; CLAUDE.md §7

### Cross-module Contracts (parallel-safe — distinct files)

- [X] T0XX [P] Define 4 published+consumed contract interfaces: `BookingItemNetAmountReader`, `BookingDraftReader`, `BookingDiscountWriter`, `VendorLookup` (consumed by Loyalty); `PointsBalanceReader` (published by Loyalty for Communication)
  - File: `app/Modules/Loyalty/Domain/Contracts/{BookingItemNetAmountReader,BookingDraftReader,BookingDiscountWriter,VendorLookup,PointsBalanceReader}.php`
  - Source: plan.md §Summary cross-module; ADR-0012 §7; `.claude/rules/modules.md`

### Repositories (parallel-safe — distinct files)

- [X] T0XX [P] `EloquentLoyaltyProgramRepository` — `findByVendor`, `create`, `updateStatus`. Returns models or DTOs only; no business logic
  - File: `app/Modules/Loyalty/Infrastructure/Repositories/EloquentLoyaltyProgramRepository.php`
  - Source: plan.md §3 Infrastructure

- [X] T0XX [P] `EloquentLoyaltyRuleRepository` — `activeRuleFor(programId)`, `replaceActive(programId, RuleDraft)` (deactivate prior + insert new in single tx)
  - File: `app/Modules/Loyalty/Infrastructure/Repositories/EloquentLoyaltyRuleRepository.php`
  - Source: plan.md §1 decision §4 forward-only

- [X] T0XX [P] `EloquentLoyaltyLedgerRepository` — `append(LedgerEntryType, ...)` ONLY. NO `update()`, NO `delete()`. Returns inserted model. Fires `LoyaltyLedgerEntryAppended` via `DB::afterCommit`.
  - File: `app/Modules/Loyalty/Infrastructure/Repositories/EloquentLoyaltyLedgerRepository.php`
  - Source: plan.md §3; FR-LOY-030; plan.md §10 test 2

- [X] T0XX [P] `EloquentLoyaltyRedemptionRepository` — `create`, `transitionTo(state)`, `findActiveForBooking`, `findHeldFor(customer,vendor)`
  - File: `app/Modules/Loyalty/Infrastructure/Repositories/EloquentLoyaltyRedemptionRepository.php`
  - Source: plan.md §3; FR-LOY-022, FR-LOY-023

### Domain Service

- [X] T0XX `BalanceCalculator` — `availableFor(customerId, vendorProfileId)` returns int = SUM(`loyalty_ledger.points`) MINUS SUM of `points_held` for `pending` redemptions for that pair. Indexed by composite `(customer_id, vendor_profile_id, created_at)`.
  - File: `app/Modules/Loyalty/Domain/Services/BalanceCalculator.php`
  - Source: plan.md §3 Services; FR-LOY-020, SC-LOY-004

### DTOs (parallel-safe — distinct files)

- [X] T0XX [P] `ProgramDraft`, `RuleDraft`, `RedemptionRequest` readonly DTOs
  - File: `app/Modules/Loyalty/Application/DTOs/{ProgramDraft,RuleDraft,RedemptionRequest}.php`
  - Source: plan.md §3 DTOs

### Lang files (parallel-safe — distinct files)

- [X] T0XX [P] EN+AR loyalty translations: `reason.earn`, `reason.redeem`, `reason.reversal`, `reason.void_release`, `errors.insufficient_balance`, `errors.below_min_threshold`, `errors.exceeds_max_pct`, `errors.cross_vendor_forbidden`, `errors.no_active_program`
  - File: `app/Modules/Loyalty/Resources/lang/en/loyalty.php`, `app/Modules/Loyalty/Resources/lang/ar/loyalty.php`
  - Source: plan.md §6; FR-LOY-005, FR-LOY-033

---

## Phase 3 — User Story 1 (P1): Vendor configures a loyalty program

**Independent test**: Create vendor → POST `/vendor/loyalty/program` with default rules → assert `loyalty_programs` row exists, status `active`, UNIQUE on `vendor_profile_id` enforced; rule edits create a new `loyalty_rules` row + deactivate prior.

### Form Requests

- [X] T0XX [US1] `StoreLoyaltyProgramRequest` — `name.en`, `name.ar` required, `terms.en`/`terms.ar` nullable, `expiration_days` nullable int 1..3650. `@bodyParam` Scribe PHPDoc on every field.
  - File: `app/Modules/Loyalty/Http/Requests/Vendor/StoreLoyaltyProgramRequest.php`
  - Source: FR-LOY-001, FR-LOY-005; spec frontmatter API DOC CONSTRAINT

- [X] T0XX [P] [US1] `UpdateLoyaltyProgramRequest` — same fields all optional + `status` in (`active`,`paused`,`archived`)
  - File: `app/Modules/Loyalty/Http/Requests/Vendor/UpdateLoyaltyProgramRequest.php`
  - Source: FR-LOY-002

- [X] T0XX [P] [US1] `StoreLoyaltyRuleRequest` — `label.en`/`ar` required, `earn_points_per_minor`/`earn_minor_per_unit` int >=1, `redemption_ratio_points`/`redemption_ratio_minor` int >=1 (with platform bounds: 1..100), `min_points_to_redeem` int >=0, `max_redeem_pct_bps` int 0..5000
  - File: `app/Modules/Loyalty/Http/Requests/Vendor/StoreLoyaltyRuleRequest.php`
  - Source: FR-LOY-003; spec Assumption "1..100 bound, 5000 bps cap"

### Action

- [X] T0XX [US1] `ConfigureLoyaltyProgramAction::execute(ProgramDraft|RuleDraft, VendorProfileId)` — `DB::transaction`: upsert program (uniqueness on vendor), if rule provided then deactivate active rule + insert new. Fires `LoyaltyProgramConfigured` via `DB::afterCommit`. Writes `audit_logs` row.
  - File: `app/Modules/Loyalty/Application/Actions/ConfigureLoyaltyProgramAction.php`
  - Source: plan.md §3 Actions; FR-LOY-001, FR-LOY-002, FR-LOY-032; CLAUDE.md §7

### Controller + Routes

- [X] T0XX [US1] `Vendor\LoyaltyProgramController` (3-line bodies) — `store`, `update`, `show`. Delegates to `ConfigureLoyaltyProgramAction`.
  - File: `app/Modules/Loyalty/Http/Controllers/Vendor/LoyaltyProgramController.php`
  - Source: CLAUDE.md §"Thin controllers"; FR-LOY-040

- [X] T0XX [US1] `Vendor\LoyaltyRuleController` — `store` (creates new active rule, deactivates prior). Delegates to action.
  - File: `app/Modules/Loyalty/Http/Controllers/Vendor/LoyaltyRuleController.php`
  - Source: FR-LOY-003

- [X] T0XX [US1] Vendor routes: `POST /vendor/loyalty/program` (Idempotency-Key required), `PUT /vendor/loyalty/program` (idem), `GET /vendor/loyalty/program`, `POST /vendor/loyalty/rules` (idem). Middleware: `auth:sanctum`, `permission:loyalty.program.manage.own`, `idempotency`.
  - File: `app/Modules/Loyalty/Routes/vendor.php`
  - Source: plan.md §7 endpoints; FR-LOY-031; Constitution §VIII

### API Resource (locale conversion at this layer)

- [X] T0XX [P] [US1] `LoyaltyProgramResource` — outputs `public_id`, `name` (locale-resolved), `terms` (locale-resolved), `status`, `currency`, `expiration_days`, `active_rule` nested. Wrap in `ApiResponse` envelope. `@response` PHPDoc with EN+AR examples.
  - File: `app/Modules/Loyalty/Http/Resources/LoyaltyProgramResource.php`
  - Source: FR-LOY-033; CLAUDE.md §12; spec frontmatter API DOC CONSTRAINT

### Filament Resource (vendor scope)

- [X] T0XX [US1] `Filament\Resources\LoyaltyProgramResource` — vendor-scoped query (`->where('vendor_profile_id', auth()->user()->vendor_profile_id)`), uses `Translatable` trait with EN/العربية tabs for `name`+`terms`, money columns via `->money('EGP', divideBy: 100)`, navigation group `Vendors`. Action delegates to `ConfigureLoyaltyProgramAction`. Run `php artisan shield:generate --all` after.
  - File: `app/Modules/Loyalty/Filament/Resources/LoyaltyProgramResource.php`
  - Source: ADR-0012 §8; FR-LOY-040, FR-LOY-042; `.claude/rules/filament.md`

### Listener

- [X] T0XX [P] [US1] (No external listener for US1 — `LoyaltyProgramConfigured` published; no Phase 5.2 consumers per plan.md §8 row 1)
  - File: (n/a, marker only)
  - Source: plan.md §8

### Pest tests

- [X] T0XX [P] [US1] Configure: vendor creates program (happy path), vendor cannot create second program (UNIQUE 409), unauthenticated 401, wrong-role 403, locale (EN response, AR response), audit_logs row written
  - File: `tests/Feature/Modules/Loyalty/ConfigureProgramTest.php`
  - Source: spec US1 acceptance scenarios; FR-LOY-001, FR-LOY-005, FR-LOY-032

- [X] T0XX [P] [US1] Rule versioning: creating new rule deactivates prior; prior credit values are NOT recomputed (forward-only)
  - File: `tests/Feature/Modules/Loyalty/RuleForwardOnlyTest.php`
  - Source: spec edge case "Program ratio change between earning and redemption"; ADR-0012 §6 decision §4

---

## Phase 4 — User Story 2 (P1): Customer earns points on completed booking

**Independent test**: Seed active program for vendor V → complete a 500 EGP booking_item with V → assert one `loyalty_ledger` `+500` earn row for `(customer, V)` after commit; replay event → no second row.

### Action

- [X] T0XX [US2] `CalculateLoyaltyPointsAction::execute(bookingItemId)` — reads `BookingItemNetAmountReader` (Settlement contract) for net minor amount, resolves vendor via `VendorLookup`, finds active program+rule, computes points = `floor(net_minor * earn_points_per_minor / earn_minor_per_unit)`, calls `LedgerRepository::append(earn, ...)` with `booking_item_id` denormalized `product_type`. Idempotent via DB UNIQUE `(booking_item_id) WHERE entry_type='earn'`. Fires `LoyaltyPointsEarned` via `DB::afterCommit`.
  - File: `app/Modules/Loyalty/Application/Actions/CalculateLoyaltyPointsAction.php`
  - Source: plan.md §3; FR-LOY-010..014; SC-LOY-002, SC-LOY-005

### Listener

- [X] T0XX [US2] `CreditPointsOnBookingCompleted` (`ShouldQueue`, queue `default`) — listens for `Booking\Domain\Events\BookingCompleted`, calls `CalculateLoyaltyPointsAction` per `booking_item_id`. No-op if vendor has no active program.
  - File: `app/Modules/Loyalty/Application/Listeners/CreditPointsOnBookingCompleted.php`
  - Source: plan.md §8; FR-LOY-011

- [X] T0XX [US2] `ReverseRedemptionOnBookingRefunded` (`ShouldQueue`) — listens for `Payments\Domain\Events\RefundFinalized`. For each `booking_item_id` refunded: append offsetting reversal row (negative points proportional to refund share). Idempotent: skip if reversal row already exists referencing the original earn entry.
  - File: `app/Modules/Loyalty/Application/Listeners/ReverseRedemptionOnBookingRefunded.php`
  - Source: plan.md §8; FR-LOY-012 (acceptance scenario 3); SC-LOY-007

### API + Resource

- [X] T0XX [P] [US2] `Customer\LoyaltyBalanceController` (3-line bodies) — `index` (balances per vendor for current user), `show(vendorPublicId)` (balance + paginated history)
  - File: `app/Modules/Loyalty/Http/Controllers/Customer/LoyaltyBalanceController.php`
  - Source: FR-LOY-020

- [X] T0XX [P] [US2] `LoyaltyBalanceResource` — outputs `vendor` (public_id+name locale-resolved), `available_points`, `held_points`, recent ledger entries. EN+AR examples in `@response`.
  - File: `app/Modules/Loyalty/Http/Resources/LoyaltyBalanceResource.php`
  - Source: FR-LOY-020, FR-LOY-033

- [X] T0XX [US2] Customer routes (read-only segment): `GET /customer/loyalty/balance`, `GET /customer/loyalty/programs/{vendor_public_id}/history`. Middleware: `auth:sanctum`.
  - File: `app/Modules/Loyalty/Routes/customer.php` (initial creation; redemption endpoints added in T049)
  - Source: plan.md §7

### Pest tests (per-type coverage)

- [X] T0XX [P] [US2] Earn after completion — three scenarios `->group('rental')`, `->group('sale')`, `->group('digital')`: complete a booking_item of each product type, assert ledger row exists with matching `product_type` and correct points. Plan §5.
  - File: `tests/Feature/Modules/Loyalty/EarnAfterCompletionTest.php`
  - Source: plan.md §5; FR-LOY-010, FR-LOY-014; CLAUDE.md §"All three product types"

- [X] T0XX [P] [US2] Idempotent earn replay — fire `BookingCompleted` twice for same `booking_item_id`, assert exactly one `earn` row
  - File: `tests/Feature/Modules/Loyalty/EarnIdempotencyTest.php`
  - Source: FR-LOY-013; SC-LOY-005

- [X] T0XX [P] [US2] Per-vendor isolation — customer completes bookings with vendor A AND vendor B (both with programs); assert two separate balances, never aggregated
  - File: `tests/Feature/Modules/Loyalty/PerVendorIsolationTest.php`
  - Source: FR-LOY-027; spec US2 acceptance 2; SC-LOY-008

- [X] T0XX [P] [US2] Refund-driven reversal across all 3 product types — partial refund produces proportional reversal row, never an UPDATE
  - File: `tests/Feature/Modules/Loyalty/EarnReversalOnRefundTest.php`
  - Source: spec US2 acceptance 3; SC-LOY-007; ADR-0012 §11 (resolved by T001)

---

## Phase 5 — User Story 3 (P1): Customer redeems points on a new booking

**Independent test**: Pre-credit 1000 points for customer C with vendor V → draft booking 200 EGP with V → POST `/customer/bookings/{public_id}/redemptions` with 400 points → assert booking total drops by 40 EGP, redemption `pending`; payment captured → debit row appended, redemption `applied`.

### Form Requests

- [X] T0XX [US3] `ApplyRedemptionRequest` — `points` int >=1; `@bodyParam` PHPDoc
  - File: `app/Modules/Loyalty/Http/Requests/Customer/ApplyRedemptionRequest.php`
  - Source: FR-LOY-021

### Actions

- [X] T0XX [US3] `ApplyRedemptionToBookingAction::execute(RedemptionRequest)` — `DB::transaction` + pessimistic lock on `(customer_id, vendor_profile_id)`: read draft via `BookingDraftReader` → enforce same-vendor (FR-LOY-027) → enforce `min_points_to_redeem`, `max_redeem_pct_bps`, available balance via `BalanceCalculator`, subtotal floor (FR-LOY-028) → compute `discount_minor` from rule ratio via `Brick\Money` → insert `loyalty_redemptions` `pending` (snapshot rule_id) → call `BookingDiscountWriter` to apply discount line → fire no event yet (Pending only). Returns `LoyaltyRedemption`.
  - File: `app/Modules/Loyalty/Application/Actions/ApplyRedemptionToBookingAction.php`
  - Source: plan.md §3; FR-LOY-021, FR-LOY-023, FR-LOY-027, FR-LOY-028; spec edge case "Concurrent redemption attempts"

- [X] T0XX [US3] `FinalizeRedemptionAction::execute(redemptionId, RedemptionState)` — transitions Pending→Applied (append `redeem` debit row + fire `LoyaltyRedemptionApplied` after commit), Pending→Voided (no debit, fire `LoyaltyRedemptionVoided`), Applied→Reversed (append `reversal` credit row referencing original debit, fire `LoyaltyRedemptionReversed`). Idempotent: no-op if already in target state.
  - File: `app/Modules/Loyalty/Application/Actions/FinalizeRedemptionAction.php`
  - Source: plan.md §3; FR-LOY-022, FR-LOY-024, FR-LOY-025, FR-LOY-026

### Controller + Routes + Resource

- [X] T0XX [US3] `Customer\LoyaltyRedemptionController` (3-line bodies) — `store(bookingPublicId)`, `destroy(bookingPublicId, redemptionId)` (void). Delegates to actions.
  - File: `app/Modules/Loyalty/Http/Controllers/Customer/LoyaltyRedemptionController.php`
  - Source: FR-LOY-021, FR-LOY-025

- [X] T0XX [US3] Add to customer routes: `POST /customer/bookings/{public_id}/redemptions` (Idempotency-Key required), `DELETE /customer/bookings/{public_id}/redemptions/{redemption_public_id}` (Idempotency-Key required). Middleware: `auth:sanctum`, `permission:loyalty.redeem.own`, `idempotency`.
  - File: `app/Modules/Loyalty/Routes/customer.php` (extend T041)
  - Source: plan.md §7; FR-LOY-031; Constitution §VIII

- [X] T0XX [P] [US3] `LoyaltyRedemptionResource` — outputs `public_id`, `points_held`, `discount` (minor+formatted EGP), `status`, transition timestamps, `vendor` summary. EN+AR `@response` examples.
  - File: `app/Modules/Loyalty/Http/Resources/LoyaltyRedemptionResource.php`
  - Source: FR-LOY-022, FR-LOY-033

### Listeners (lifecycle wiring from Booking + Payments)

- [X] T0XX [US3] `VoidRedemptionOnBookingCancelled` (`ShouldQueue`) — listens `Booking\Domain\Events\BookingCancelled`; for any `pending` redemption referencing that booking, calls `FinalizeRedemptionAction(... → Voided)`. Idempotent.
  - File: `app/Modules/Loyalty/Application/Listeners/VoidRedemptionOnBookingCancelled.php`
  - Source: plan.md §8; FR-LOY-022, FR-LOY-025

- [X] T0XX [US3] Extend `CreditPointsOnBookingCompleted` listener (T037) OR wire `Payments\Domain\Events\PaymentCaptured` listener to flip `pending → applied` redemptions for the booking. Decision lives in ADR-0012 §11 resolution (T001).
  - File: `app/Modules/Loyalty/Application/Listeners/ApplyRedemptionOnPaymentCaptured.php`
  - Source: FR-LOY-022, FR-LOY-024

### Pest tests

- [X] T0XX [P] [US3] Redemption math — accept at boundary (exactly `max_redeem_pct_bps`), reject above cap with localized error EN+AR, reject below `min_points_to_redeem`, reject when discount would exceed subtotal
  - File: `tests/Feature/Modules/Loyalty/RedemptionMathTest.php`
  - Source: spec US3 acceptance 1, 2; FR-LOY-028; SC-LOY-006

- [X] T0XX [P] [US3] Cross-vendor rejection — points earned with vendor A cannot be redeemed on a booking with vendor B; localized error
  - File: `tests/Feature/Modules/Loyalty/CrossVendorRejectionTest.php`
  - Source: FR-LOY-027; spec US3 acceptance 5; SC-LOY-008

- [X] T0XX [P] [US3] Redemption lifecycle — Pending → Voided on booking cancel (no debit appended), Pending → Applied on payment captured (debit appended), Applied → Reversed on full refund (credit appended referencing original debit)
  - File: `tests/Feature/Modules/Loyalty/RedemptionLifecycleTest.php`
  - Source: FR-LOY-022..026; spec US3 acceptance 3, 4

- [X] T0XX [P] [US3] Concurrent redemption — two simultaneous applies for same `(customer, vendor)` with insufficient combined balance; only one succeeds; the other 422 with localized error
  - File: `tests/Feature/Modules/Loyalty/ConcurrentRedemptionTest.php`
  - Source: spec edge case "Concurrent redemption attempts"; FR-LOY-023

- [X] T0XX [P] [US3] Idempotency — replay POST `/customer/bookings/{public_id}/redemptions` with same `Idempotency-Key` returns cached envelope; no second redemption row
  - File: `tests/Feature/Modules/Loyalty/RedemptionIdempotencyTest.php`
  - Source: FR-LOY-031; Constitution §VIII

---

## Phase 6 — Admin read-only surface

- [X] T0XX [P] `Filament\Pages\LoyaltyOverviewPage` — admin read-only listing of all vendor programs, rules, ledger summary, redemption status; filterable by vendor + status + entry_type. Permission `loyalty.view_any`.
  - File: `app/Modules/Loyalty/Filament/Pages/LoyaltyOverviewPage.php`
  - Source: ADR-0012 §8; FR-LOY-041

- [X] T0XX Admin routes (read-only): `GET /admin/loyalty/programs`, `GET /admin/loyalty/ledger?vendor=&customer=`. Middleware `auth:sanctum`, `permission:loyalty.view_any`.
  - File: `app/Modules/Loyalty/Routes/admin.php`
  - Source: FR-LOY-041

- [X] T0XX Run `php artisan shield:generate --all` to register all loyalty permissions
  - File: (CLI)
  - Source: CLAUDE.md §"After every new Filament resource"; `.claude/rules/filament.md`

---

## Phase 7 — Architecture tests + polish

- [X] T0XX [P] `LoyaltyModuleNoCrossImportTest` — fails on any import of Booking/Catalog/Settlement/Identity/Payments models inside `app/Modules/Loyalty/`. Mirror of `tests/Architecture/GeographyModuleNoCrossImportTest.php`.
  - File: `tests/Architecture/LoyaltyModuleNoCrossImportTest.php`
  - Source: plan.md §10 test 1; CLAUDE.md §"Module Ownership Rules"

- [X] T0XX [P] `LoyaltyLedgerAppendOnlyTest` — asserts migration has no `softDeletes()`/`updated_at`; model rejects `updating`+`deleting`; no `update(`/`delete(` calls anywhere in module against `LoyaltyLedgerEntry`
  - File: `tests/Architecture/LoyaltyLedgerAppendOnlyTest.php`
  - Source: plan.md §10 test 2; FR-LOY-030

- [X] T0XX [P] `LoyaltyNoIfElseifOnProductTypeTest` — fails on any `if/elseif` chain on `'rental'|'sale'|'digital'` literals or `match` over `ProductType::*` cases inside `app/Modules/Loyalty/`. Allowed: `where('product_type', $value)` query filters.
  - File: `tests/Architecture/LoyaltyNoIfElseifOnProductTypeTest.php`
  - Source: plan.md §10 test 3; CLAUDE.md §"Three Product Types"

- [X] T0XX [P] `LoyaltyNoDirectBookingMutationTest` — fails on any write against `bookings`/`booking_items` tables (DB::table writes, model saves, model updates) inside `app/Modules/Loyalty/`. All mutations must route through `BookingDiscountWriter`.
  - File: `tests/Architecture/LoyaltyNoDirectBookingMutationTest.php`
  - Source: plan.md §10 test 4

- [X] T0XX [P] `LoyaltyEventsAfterCommitTest` — every `event(` invocation in `app/Modules/Loyalty/Application/Actions/*` and `app/Modules/Loyalty/Infrastructure/Repositories/*` is wrapped by `DB::afterCommit(`.
  - File: `tests/Architecture/LoyaltyEventsAfterCommitTest.php`
  - Source: plan.md §10 test 5; CLAUDE.md §7; Constitution §IX

- [X] T0XX Register two notification templates in Communication: `loyalty.points_earned`, `loyalty.points_redeemed` (and `loyalty.points_restored`) — EN+AR rows in `notification_templates` (audience: `customer`)
  - File: `database/seeders/NotificationTemplatesSeeder.php` (extend) or `app/Modules/Loyalty/Database/Seeders/LoyaltyNotificationTemplatesSeeder.php`
  - Source: plan.md §6 last row, §8 listeners; spec Assumptions

- [X] T0XX Add 5 endpoints to `.specify/memory/api-registry.md` (POST/PUT program, POST rule, GET balance, GET history, POST/DELETE redemption) with @bodyParam + @response references
  - File: `.specify/memory/api-registry.md`
  - Source: spec frontmatter API DOC CONSTRAINT

- [X] T0XX Add Bruno/Postman collection entries for the 5 endpoints
  - File: `docs/api/collections/loyalty.bru` (or `.json`)
  - Source: spec frontmatter API DOC CONSTRAINT

- [X] T0XX Run full Loyalty test suite + architecture tests: `./vendor/bin/pest --filter=Loyalty --bail` and `./vendor/bin/pest tests/Architecture --filter=Loyalty`
  - File: (CLI)
  - Source: migrate-module skill §"After all migrations are written"

---

## Dependencies

- **T001** must complete (ADR Accepted) before T003 (first migration).
- **T003 → T004 → T005 → T006 → T007** strict FK order. T006 depends on T005 because `loyalty_ledger.redemption_id` FKs `loyalty_redemptions`.
- **T007** (`migrate`) blocks T010–T013 (models reference real tables) and all later phases.
- **T008–T009 (enums)** can run before T010–T013 (models cast to them) — schedule first within Phase 2.
- **T013 (LoyaltyLedgerEntry)** blocks T019 (LedgerRepository) and T036 (CalculateLoyaltyPointsAction).
- **T014 (RedemptionState)** blocks T012, T020, T048.
- **T016 (Contracts)** blocks T036, T047, T048 (consumers) and T037, T038, T052 (listeners use contracts).
- **Phase 3 (US1)** is independent of Phase 4 + 5 (configure works without earn/redeem).
- **Phase 4 (US2)** depends on T037 listener + Booking module emitting `BookingCompleted` (already wired Phase 3).
- **Phase 5 (US3)** depends on `BookingDiscountWriter` contract being implemented in Booking module + `PaymentCaptured` event from Payments module. Coordinate with those owners before T053.
- **Phase 7 (architecture tests)** can be authored in parallel with implementation but only pass after all referenced files exist.

## Parallel execution batches (examples)

- **Batch A** (after T007): `[T008, T009]` enums, `[T010, T011, T012]` models (T013 sequential due to booted-hook discipline), `[T015]` events, `[T016]` contracts, `[T017, T018, T019, T020]` repositories, `[T022]` DTOs, `[T023]` lang files.
- **Batch B** (US1 implementation): `[T025, T026]` requests after T024, `[T031]` resource, `[T034, T035]` tests after T032 + T029.
- **Batch C** (US2): `[T039, T040]` controller + resource, `[T042, T043, T044, T045]` tests.
- **Batch D** (US3): `[T051]` resource, `[T054, T055, T056, T057, T058]` tests.
- **Batch E** (Phase 7): `[T062, T063, T064, T065, T066]` architecture tests.

## MVP scope suggestion

**MVP = Phase 1 + Phase 2 + Phase 3 (US1) + Phase 4 (US2)**: vendors can configure programs, customers earn points after completion. Redemption (US3, Phase 5) ships in the second day. All three are P1 in the spec, so true MVP is the full Phase 5.2 deliverable; if behind schedule the cut-list (plan.md §11) is the lever — defer referrals/expiration job/manual grant, never defer P1 stories.
