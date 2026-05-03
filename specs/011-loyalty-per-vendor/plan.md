---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Implementation Plan: Loyalty (Per-Vendor) — Phase 5.2

**Branch**: `011-loyalty-per-vendor` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**ADR**: ⚠️ **MISSING — run `/new-module-adr` first** (target: `docs/adr/0012-loyalty-module.md`). No code in this plan may be written until ADR-0012 is in `Accepted` state. The ADR must lock the six decisions enumerated in §1 below.

---

## Summary

Build the Loyalty module — a new `app/Modules/Loyalty/` that lets each vendor configure exactly one loyalty program (`loyalty_programs`) with versioned rules (`loyalty_rules`), credits per-vendor points to a customer's balance after every completed `booking_item` via the appended-only `loyalty_ledger`, and lets customers spend those points (per-vendor scoped) on a later draft booking through a `loyalty_redemptions` lifecycle (`pending → applied | voided | reversed`).

Four migrations, one Filament Resource (`LoyaltyProgramResource`, vendor-scoped) plus one read-only platform-admin View, four Actions (`ConfigureLoyaltyProgramAction`, `CalculateLoyaltyPointsAction`, `ApplyRedemptionToBookingAction`, `FinalizeRedemptionAction`), one queued listener on `BookingCompleted`, three new domain events, and a Pest suite that exercises per-vendor isolation, append-only invariants, idempotent earning, and refund-driven reversal across all three product-type completion paths.

**Cross-module reads via Contracts only:**

- `Loyalty\Domain\Contracts\BookingItemNetAmountReader` (implemented in **Settlement**) — returns the net paid minor-unit amount for a `booking_item_id` after refunds and excluding commission. Source of truth for earn calculations (FR-LOY-014).
- `Loyalty\Domain\Contracts\BookingDraftReader` (implemented in **Booking**) — returns `(vendor_profile_id, subtotal_minor, currency, customer_id)` for a draft `booking_id`. Used by `ApplyRedemptionToBookingAction` (FR-LOY-021, FR-LOY-027, FR-LOY-028).
- `Loyalty\Domain\Contracts\BookingDiscountWriter` (implemented in **Booking**) — applies a redemption-derived discount line to a draft booking and returns the updated total. The Loyalty module never mutates `bookings.*` directly.
- `Loyalty\Domain\Contracts\VendorLookup` (implemented in **Identity**) — resolves `vendor_profile_id` from a `booking_vendor_id` and confirms the vendor is approved.

**Depends on:** Booking (Phase 3.x — `bookings`, `booking_items.item_status='completed'`, `BookingCompleted` event, draft discount slot), Settlement (Phase 4.x — net-paid resolver), Identity (Phase 1.x — `vendor_profiles`, `users`, `customer_profiles.preferred_locale`), Communication (Phase 5.0 — notification template registry).

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12

**Primary Dependencies** (all from `docs/specs/10_Package_List.md` — **no new packages**):

- `brick/money` — already in use; loyalty discount is computed as `Money::ofMinor(points * ratio_numer / ratio_denom, 'EGP')`
- `spatie/laravel-translatable` — JSON columns on `loyalty_programs.name`, `loyalty_programs.terms`, `loyalty_rules.label`
- `spatie/laravel-permission` — vendor permissions `loyalty.program.manage.own`, `loyalty.rule.manage.own`; admin permissions `loyalty.view_any`, `loyalty.view_ledger`
- `spatie/laravel-model-states` — `RedemptionState` (`Pending`, `Applied`, `Voided`, `Reversed`)
- `bezhansalleh/filament-shield` — Resource + Page-level permissions; run `shield:generate --all` after Day 1
- `filament/filament` — `LoyaltyProgramResource` (vendor scope), `LoyaltyOverviewPage` (admin read-only)
- `filament/spatie-laravel-translatable-plugin` — EN/AR tabs on program name and terms
- `pestphp/pest` + `pestphp/pest-plugin-laravel` — feature, unit, architecture tests

**Storage**: MySQL 8 — 4 new tables per `docs/specs/11_DB_Schema.md` §"Loyalty (4) — per vendor":

- `loyalty_programs` — soft-deletable (whitelisted? **NO** — Loyalty module gets no soft delete; if a vendor wants to retire a program, set `status='archived'`. This matches Constitution V: only the curated list in CLAUDE.md §15 may use `softDeletes()`.)
- `loyalty_rules` — standard timestamps; row-versioned by inserting a new active row and marking the old one inactive (no UPDATE on rule values themselves)
- `loyalty_ledger` — **append-only** (`created_at` only, no `updated_at`, no `deleted_at`, no `softDeletes()`)
- `loyalty_redemptions` — standard timestamps; only `status` and `applied_at` / `voided_at` / `reversed_at` columns may be UPDATEd

**Testing**: Pest — feature tests for vendor configuration, earn after completion, redemption math (positive + cap rejection), per-vendor isolation, idempotent earn replay, refund-driven reversal across rental + sale + digital completion paths; architecture tests for no-cross-module-import, append-only enforcement on `loyalty_ledger`, no-if-elseif-on-product-type-strings, no direct `bookings.*` writes from Loyalty.

**Target Platform**: Linux (Docker Compose dev, Hetzner CCX13 staging)

**Project Type**: Modular monolith API + Filament admin

**Performance Goals**:

- Earn listener completes within 5 s of `BookingCompleted` commit (SC-LOY-002), well within Reverb queue depth headroom
- Apply-redemption endpoint p95 under 250 ms (single SUM over `loyalty_ledger` filtered by composite index `(customer_id, vendor_profile_id, created_at)` + one INSERT into `loyalty_redemptions`)
- Customer-facing balance read p95 under 100 ms (covered by composite index `(customer_id, vendor_profile_id)` and a 60 s redis cache invalidated on every ledger insert via `LedgerEntryAppended` event)

**Constraints**:

- All cross-module reads go through Contracts — no `use App\Modules\Booking\Domain\Models\Booking` (or BookingItem, Vendor, etc.) anywhere under `app/Modules/Loyalty/`
- `loyalty_ledger` append-only: enforced by `LoyaltyLedgerEntry::booted()` rejecting `updating`/`deleting`, by an architecture test, and by an `ON UPDATE NO ACTION` consideration (advisory only; MySQL won't enforce app-layer immutability)
- Earning idempotent per `booking_item_id` — guaranteed by UNIQUE `(booking_item_id, entry_type='earn')` partial index on `loyalty_ledger`
- Domain events fire `DB::afterCommit` only (architecture test asserts)
- Loyalty NEVER mutates `bookings.*` directly — discount lines applied via `BookingDiscountWriter` contract
- Earn calc input is **net paid amount** from Settlement, never raw `booking_items.subtotal_minor`

**Scale/Scope**: Year-1 estimate ~10k active customers × ~50 vendors with programs × ~10 completed bookings/customer/year ≈ 5M ledger rows over Year 1. Index strategy below sizes for 50M rows safely; partition is **NOT** introduced in Phase 5.2 (deferred to Phase 7+ per `analytics_events`-style policy).

---

## 1. ADR Reference

**Status**: ⚠️ **MISSING** — must be created before any migration is generated.

**Action required:** run `/new-module-adr loyalty` to scaffold `docs/adr/0012-loyalty-module.md` from the template. The ADR must lock the following six decisions; this plan assumes all six are decided "as below":

1. **§Module ownership** — Loyalty is its own module under `app/Modules/Loyalty/`. It does not live inside Catalog, Booking, or Settlement, because (a) its lifecycle is independent of any one of them and (b) its tables are self-contained.
2. **§Cross-type posture** — Loyalty is **cross-type**. Earn and redemption rules apply uniformly across `rental`, `sale`, `digital`. The product type enters Loyalty only as informational metadata on the credit row (denormalized from `booking_items.product_type`); no `match($enum)` or if/elseif appears in Loyalty Actions.
3. **§Per-vendor scoping (locked)** — Balances are scoped to `(customer_id, vendor_profile_id)`. Cross-vendor redemption is rejected at the Action layer (FR-LOY-027). Reaffirms PRD locked decision.
4. **§Forward-only rule changes** — Rule edits create a new row in `loyalty_rules` and deactivate the prior one. Prior credits are **not revalued**; redemption ratio is read at redemption time from the currently-active rule row. Documented in spec Assumptions and SC-LOY-006.
5. **§Append-only ledger** — `loyalty_ledger` is append-only. Refund-driven reversal is a new offsetting credit row referencing the original debit (`reversed_from_ledger_id`), never a row mutation. Aligns Constitution V.
6. **§Net-paid as earn basis** — Earn calculation reads `BookingItemNetAmountReader` from Settlement (not `booking_items.subtotal_minor`). Refund flows therefore correctly reduce eligible earnings without Loyalty needing to know about the refund pipeline.

If any of these six get rejected during ADR review, this plan must be revised before `/speckit.tasks`.

---

## 2. Constitution Check

| # | Principle | Status | Notes |
|---|---|---|---|
| **I** | Modular monolith — module boundaries, no cross-module model imports | ✅ PASS (gated on ADR-0012) | New `app/Modules/Loyalty/` with full layer layout per `.claude/rules/modules.md`. Four cross-module Contracts (`BookingItemNetAmountReader`, `BookingDraftReader`, `BookingDiscountWriter`, `VendorLookup`) — no Eloquent imports. Architecture test `tests/Architecture/LoyaltyModuleNoCrossImportTest.php` enforces (added §10 below). |
| **II** | Three product types — `match($enum)`, no if/elseif | ✅ PASS | Loyalty is cross-type. No `match($productType)` and no if/elseif chains in Loyalty Actions. The `product_type` column on `loyalty_ledger` is denormalized informational metadata only (used for analytics filtering, not for branching). All three types covered in Pest by completing a rental, a sale, and a digital booking and asserting credit rows appear identically (`->group('rental')`, `->group('sale')`, `->group('digital')`). |
| **III** | Money discipline — integer minor units, Brick\Money | ✅ PASS | Discount value column on `loyalty_redemptions.discount_minor` (BIGINT UNSIGNED) + `discount_currency` (CHAR(3)). All discount math uses `Brick\Money\Money`. Redemption ratio stored as integer pair `(ratio_points, ratio_minor)` to avoid float — e.g. `(100, 1000)` means "100 points = 1000 piastres = 10 EGP". Architecture test asserts no `decimal`/`float` casts on Loyalty money columns. |
| **IV** | Bilingual EN+AR mandatory | ✅ PASS | `loyalty_programs.name` (JSON, translatable), `loyalty_programs.terms` (JSON, translatable), `loyalty_rules.label` (JSON, translatable), `loyalty_ledger.reason` (JSON, translatable for system-generated reasons; vendor-supplied reasons mirrored EN+AR via the configured program). Notification templates `loyalty.points_earned` and `loyalty.points_redeemed` registered with both locales. Filament uses translatable plugin's EN/العربية tabs. Pest covers EN and AR rendering paths. |
| **V** | Append-only tables — no softDeletes, no UPDATE | ✅ PASS | `loyalty_ledger` is append-only: `created_at` only, no `updated_at`, no `deleted_at`, no `softDeletes()`. Reversal is a new offsetting row. `loyalty_programs`/`loyalty_rules`/`loyalty_redemptions` use standard timestamps (status-only mutations are allowed under Principle V). Architecture test `tests/Architecture/LoyaltyLedgerAppendOnlyTest.php` rejects `updating`/`deleting` via the model's `booted` hook. |
| **VI** | Spec-driven — ADR before code | ⚠️ GATED | ADR-0012 **does not yet exist**. This plan is produced for review; no migration may be merged until ADR-0012 is `Accepted`. Tracked in §1. |
| **VII** | Test-first for critical paths | ✅ PASS | Loyalty touches money — falls under "money flows" critical-path bucket (Constitution VII). Pest target: 80%+ coverage on Actions. Tests written same day as code per Constitution VII. Coverage matrix: configure (vendor RBAC, uniqueness 409), earn (3 product types, idempotent replay, refund offset), redeem (cap rejection, min threshold, cross-vendor rejection, concurrent attempt rejection), reversal, locale (EN+AR), audit_logs entries. |
| **VIII** | Idempotency for state-changing endpoints | ✅ PASS | Required `Idempotency-Key` headers per Constitution §VIII for all mutating endpoints listed in §6 below: `POST /vendor/loyalty/program`, `PUT /vendor/loyalty/program`, `POST /vendor/loyalty/rules`, `POST /customer/bookings/{public_id}/redemptions`, `DELETE /customer/bookings/{public_id}/redemptions/{redemption_id}` (void). Earning is event-driven (no HTTP) and idempotent via DB UNIQUE on `(booking_item_id, entry_type='earn')`. 24h TTL middleware reused. |
| **IX** | Domain events fire `DB::afterCommit` | ✅ PASS | Six events: `LoyaltyProgramConfigured`, `LoyaltyPointsEarned`, `LoyaltyRedemptionApplied`, `LoyaltyRedemptionVoided`, `LoyaltyRedemptionReversed`, `LoyaltyLedgerEntryAppended` — all dispatched via `DB::afterCommit(fn () => event(...))`. Earn listener implements `ShouldQueue`. Architecture test asserts no raw `event()` outside `DB::afterCommit` in Loyalty Actions. |
| **X** | Vendor approval — two-step gate (per ADR-0003 §6.2) | ✅ N/A (with guard) | A vendor must already be approved (gated by Identity) to access the Loyalty Filament Resource — enforced by Shield permission `loyalty.program.manage.own` granted only to vendors in `vendor_profiles.approval_status='approved'`. No new approval surface introduced. |
| **XI** | Document storage — Direct S3 for typed, MediaLibrary for galleries | ✅ N/A | Loyalty has no document or media uploads. |

**Gate result:** Plan **PASSES** Constitution Check **subject to** ADR-0012 reaching `Accepted` status. Re-check after ADR review.

---

## 3. Project Structure

### Documentation (this feature)

```text
specs/011-loyalty-per-vendor/
├── plan.md              # This file
├── research.md          # Phase 0 — earn/redemption math, refund-reversal worked examples
├── data-model.md        # Phase 1 — entity diagram, indexes, state machine for redemptions
├── quickstart.md        # Phase 1 — vendor + customer happy path through Filament + API
├── contracts/           # Phase 1 — OpenAPI snippets for the 5 endpoints in §6 + 4 cross-module Contract interfaces
└── tasks.md             # Phase 2 — emitted by /speckit.tasks (NOT this command)
```

### Source code (repository root)

```text
app/Modules/Loyalty/
├── Domain/
│   ├── Models/
│   │   ├── LoyaltyProgram.php
│   │   ├── LoyaltyRule.php
│   │   ├── LoyaltyLedgerEntry.php           # booted(): rejects updating + deleting
│   │   └── LoyaltyRedemption.php
│   ├── Enums/
│   │   ├── LedgerEntryType.php              # earn | redeem | reversal | void_release
│   │   └── ProgramStatus.php                # active | paused | archived
│   ├── Events/
│   │   ├── LoyaltyProgramConfigured.php
│   │   ├── LoyaltyPointsEarned.php
│   │   ├── LoyaltyRedemptionApplied.php
│   │   ├── LoyaltyRedemptionVoided.php
│   │   ├── LoyaltyRedemptionReversed.php
│   │   └── LoyaltyLedgerEntryAppended.php
│   ├── States/
│   │   └── Redemption/
│   │       ├── RedemptionState.php          # abstract
│   │       ├── Pending.php  Applied.php  Voided.php  Reversed.php
│   ├── Contracts/                           # consumed by OTHER modules from this one
│   │   ├── PointsBalanceReader.php          # used by Communication for "you have X points" notifications
│   │   └── (none others — Loyalty owns everything else internally)
│   └── Services/
│       └── BalanceCalculator.php            # signed sum + held-redemption deduction
├── Application/
│   ├── Actions/
│   │   ├── ConfigureLoyaltyProgramAction.php
│   │   ├── CalculateLoyaltyPointsAction.php       # called by listener after BookingCompleted
│   │   ├── ApplyRedemptionToBookingAction.php
│   │   └── FinalizeRedemptionAction.php           # pending → applied | voided | reversed
│   ├── Listeners/
│   │   ├── CreditPointsOnBookingCompleted.php     # ShouldQueue
│   │   ├── VoidRedemptionOnBookingCancelled.php   # ShouldQueue
│   │   └── ReverseRedemptionOnBookingRefunded.php # ShouldQueue
│   └── DTOs/
│       ├── ProgramDraft.php  RuleDraft.php  RedemptionRequest.php
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentLoyaltyProgramRepository.php
│   │   ├── EloquentLoyaltyRuleRepository.php
│   │   ├── EloquentLoyaltyLedgerRepository.php   # append() only — no update(), no delete()
│   │   └── EloquentLoyaltyRedemptionRepository.php
│   └── Adapters/
│       └── (Loyalty consumes Booking/Settlement/Identity Contracts — adapters live in those modules, not here)
├── Http/
│   ├── Controllers/
│   │   ├── Vendor/LoyaltyProgramController.php
│   │   ├── Vendor/LoyaltyRuleController.php
│   │   ├── Customer/LoyaltyBalanceController.php
│   │   └── Customer/LoyaltyRedemptionController.php
│   ├── Requests/
│   │   ├── Vendor/StoreLoyaltyProgramRequest.php
│   │   ├── Vendor/UpdateLoyaltyProgramRequest.php
│   │   ├── Vendor/StoreLoyaltyRuleRequest.php
│   │   └── Customer/ApplyRedemptionRequest.php
│   └── Resources/
│       ├── LoyaltyProgramResource.php
│       ├── LoyaltyBalanceResource.php
│       └── LoyaltyRedemptionResource.php
├── Filament/
│   └── Resources/
│       ├── LoyaltyProgramResource.php             # vendor-scoped (own program only)
│       └── (and Pages/ for LoyaltyOverviewPage admin read-only)
├── Routes/
│   ├── vendor.php
│   ├── customer.php
│   └── admin.php
├── Database/
│   └── Migrations/
│       ├── YYYY_MM_DD_HHMMSS_create_loyalty_programs_table.php
│       ├── YYYY_MM_DD_HHMMSS_create_loyalty_rules_table.php
│       ├── YYYY_MM_DD_HHMMSS_create_loyalty_ledger_table.php
│       └── YYYY_MM_DD_HHMMSS_create_loyalty_redemptions_table.php
├── Resources/
│   └── lang/
│       ├── en/loyalty.php
│       └── ar/loyalty.php
└── Providers/
    └── LoyaltyServiceProvider.php
```

Architecture tests live in repo-level:

```text
tests/Architecture/
├── LoyaltyModuleNoCrossImportTest.php
├── LoyaltyLedgerAppendOnlyTest.php
├── LoyaltyNoIfElseifOnProductTypeTest.php
└── LoyaltyNoDirectBookingMutationTest.php
```

**Structure Decision**: The Loyalty module follows the standard modular layout in `.claude/rules/modules.md`. No deviations.

---

## 4. Tables to Create / Modify

All four are **new**. Match exactly the locked entries in `docs/specs/11_DB_Schema.md` §"Loyalty (4)". No existing table is modified by this plan.

| Table | Append-only | Soft delete | Key columns (excerpt — full DDL in `data-model.md`) | Critical indexes |
|---|---|---|---|---|
| `loyalty_programs` | No | **No** (use `status='archived'`) | `id` BIGINT PK, `public_id` CHAR(26) UNIQUE, `vendor_profile_id` BIGINT FK→`vendor_profiles.id` UNIQUE, `name` JSON (translatable), `terms` JSON (translatable, nullable), `currency` CHAR(3) default `EGP`, `status` ENUM(`active`,`paused`,`archived`) default `active`, `expiration_days` SMALLINT UNSIGNED NULL, `created_by`/`updated_by`, timestamps | UNIQUE `(vendor_profile_id)`, INDEX `(status)` |
| `loyalty_rules` | No (status-only updates allowed on `is_active`) | No | `id` BIGINT PK, `public_id` ULID, `loyalty_program_id` BIGINT FK→`loyalty_programs.id` cascade-on-delete, `label` JSON (translatable), `earn_points_per_minor` INT UNSIGNED, `earn_minor_per_unit` INT UNSIGNED (e.g. 100 = "1 point per 1 EGP"), `redemption_ratio_points` INT UNSIGNED, `redemption_ratio_minor` INT UNSIGNED, `min_points_to_redeem` INT UNSIGNED default 0, `max_redeem_pct_bps` SMALLINT UNSIGNED default 5000 (≤5000 = ≤50%), `is_active` BOOLEAN default 1, `effective_from` TIMESTAMP, timestamps | INDEX `(loyalty_program_id, is_active)`, CHECK `max_redeem_pct_bps <= 5000` |
| `loyalty_ledger` | **Yes** (append-only, `created_at` only) | No | `id` BIGINT PK, `public_id` ULID, `customer_id` BIGINT FK→`users.id` restrict-on-delete, `vendor_profile_id` BIGINT FK→`vendor_profiles.id` restrict-on-delete, `loyalty_program_id` BIGINT FK→`loyalty_programs.id` restrict-on-delete, `entry_type` ENUM(`earn`,`redeem`,`reversal`,`void_release`), `points` INT (signed), `booking_id` BIGINT NULL FK→`bookings.id`, `booking_item_id` BIGINT NULL FK→`booking_items.id`, `redemption_id` BIGINT NULL FK→`loyalty_redemptions.id`, `reversed_from_ledger_id` BIGINT NULL self-FK, `product_type` ENUM(`rental`,`sale`,`digital`) NULL (denormalized for analytics), `reason` JSON (translatable), `created_at` only | UNIQUE `(booking_item_id, entry_type)` WHERE `entry_type='earn'` (partial via generated column trick or app-layer enforcement); INDEX `(customer_id, vendor_profile_id, created_at)`; INDEX `(vendor_profile_id, entry_type, created_at)` |
| `loyalty_redemptions` | No (status-only updates) | No | `id` BIGINT PK, `public_id` ULID, `customer_id` BIGINT FK→`users.id`, `vendor_profile_id` BIGINT FK→`vendor_profiles.id`, `loyalty_program_id` BIGINT FK, `loyalty_rule_id` BIGINT FK→`loyalty_rules.id` (snapshots the active rule), `booking_id` BIGINT FK→`bookings.id`, `points_held` INT UNSIGNED, `discount_minor` BIGINT UNSIGNED, `discount_currency` CHAR(3), `status` (managed by `RedemptionState`), `applied_at`/`voided_at`/`reversed_at` TIMESTAMP NULL, timestamps | UNIQUE `(booking_id, status)` WHERE `status IN ('pending','applied')` (one active redemption per booking); INDEX `(customer_id, vendor_profile_id, status)` |

**Schema match check** vs `docs/specs/11_DB_Schema.md`:

- `schema-cheatsheet.md` confirms 4 loyalty tables: ✅ matches.
- `loyalty_ledger` listed under "append-only tables": ✅ matches.
- `loyalty_programs` UNIQUE per `vendor_profile_id`: ✅ matches.
- `loyalty_rules` keyed by program: ✅ matches.
- No new soft-delete additions — Loyalty is **not** in the soft-delete whitelist (CLAUDE.md §15): ✅ matches.

---

## 5. Per-type Coverage

Loyalty is **cross-type**. There are no per-type Form Requests, Actions, or Resources. However, type-aware verification is still required by Constitution II:

- **Pest test set** under `tests/Feature/Modules/Loyalty/EarnAfterCompletionTest.php` runs three scenarios with `->group('rental')`, `->group('sale')`, `->group('digital')`:
  - Complete a rental booking_item (state machine `Reserved → Setup → Live → Completed`) → assert credit row appears with `product_type='rental'`.
  - Complete a sale booking_item (state machine `InPreparation → ReadyForHandover → Completed`) → assert credit row appears with `product_type='sale'`.
  - Complete a digital booking_item (state machine `Pending → Delivered → Completed`) → assert credit row appears with `product_type='digital'`.
- **Refund-reversal Pest test** runs the same three product types, partially refunds the booking item, and asserts the offsetting reversal row's `points` equals `-floor(original_credit * refund_share)`.
- **Architecture test** `LoyaltyNoIfElseifOnProductTypeTest.php` rejects any `if ($x === 'rental')` / `elseif` / `match($productType)` patterns in `app/Modules/Loyalty/`. The `product_type` value is allowed in WHERE clauses and analytics queries (read-only), forbidden in branching logic.

---

## 6. Locale Coverage (EN + AR)

| Surface | EN+AR mechanism | Tested in Pest |
|---|---|---|
| `loyalty_programs.name` | JSON translatable column via `spatie/laravel-translatable` | `LoyaltyProgramLocaleTest.php` — assert AR locale returns AR string, EN locale returns EN string |
| `loyalty_programs.terms` | JSON translatable column | same test, optional field path |
| `loyalty_rules.label` | JSON translatable column | yes |
| `loyalty_ledger.reason` | JSON translatable column; system-generated reasons hydrated from `loyalty.php` lang files (`loyalty.reason.earn`, `loyalty.reason.redeem`, `loyalty.reason.reversal`, `loyalty.reason.void_release`) | yes — assert AR rendering of reversal reason on a refund flow |
| Filament admin | `Translatable` trait + EN/العربية tabs (per `.claude/rules/filament.md` §"Translatable fields") | Visual smoke + locale switcher Pest test |
| Validation errors (insufficient points, cap exceeded, cross-vendor, etc.) | `Resources/lang/{en,ar}/loyalty.php` keys: `errors.insufficient_balance`, `errors.below_min_threshold`, `errors.exceeds_max_pct`, `errors.cross_vendor_forbidden`, `errors.no_active_program` | `LoyaltyValidationLocaleTest.php` — AR header → AR error body |
| API Resource layer | Locale conversion happens in `LoyaltyProgramResource` / `LoyaltyBalanceResource` / `LoyaltyRedemptionResource` per `.claude/rules/modules.md` (never in business logic) | Resource snapshot tests in EN + AR |
| Notification templates | `loyalty.points_earned` and `loyalty.points_redeemed` registered in Communication module's `notification_templates` with EN+AR rows (audience: `customer`) | Communication module's existing template-resolution test extended with two new keys |

**Inviolable check:** `audit:translations:check` job (existing in repo) must pass — every key referenced from PHP code under `app/Modules/Loyalty/` and every JSON column row must have both EN and AR entries.

---

## 7. Idempotency Keys (per Constitution §VIII)

Constitution §VIII requires `Idempotency-Key` on **state-changing** endpoints. Loyalty's mutating endpoints and their idempotency posture:

| Endpoint | Method | Idempotency-Key | Rationale |
|---|---|---|---|
| `/vendor/loyalty/program` | POST | **Required** | Creates program; replay must not double-create (DB UNIQUE on `vendor_profile_id` is secondary safeguard) |
| `/vendor/loyalty/program` | PUT | **Required** | Mutates program status / terms; replay must produce same final state |
| `/vendor/loyalty/rules` | POST | **Required** | Creates new rule + deactivates prior — multi-row mutation, must be replay-safe |
| `/customer/bookings/{public_id}/redemptions` | POST | **Required** | Holds points + writes `loyalty_redemptions` row + writes booking discount via Contract — most critical replay surface |
| `/customer/bookings/{public_id}/redemptions/{rid}` | DELETE | **Required** | Voids a pending redemption — replay must be a no-op on already-voided |
| `/customer/loyalty/balance` | GET | N/A | Read-only |
| `/customer/loyalty/programs/{vendor_public_id}/history` | GET | N/A | Read-only |

All POST/PUT/DELETE endpoints share the project's existing `Idempotency-Key` middleware which writes to `idempotency_keys` (24h TTL) and returns the cached envelope on replay.

**Event-driven mutations are NOT covered by `Idempotency-Key`** (Constitution §VIII applies to HTTP). Their idempotency is enforced at the data layer:

- `CreditPointsOnBookingCompleted` listener — UNIQUE `(booking_item_id, entry_type='earn')` on `loyalty_ledger` rejects double-credit on replay.
- `VoidRedemptionOnBookingCancelled` listener — checks `status` before transitioning; no-op if already `voided`.
- `ReverseRedemptionOnBookingRefunded` listener — checks for existing `reversal` ledger row referencing the same redemption before appending.

---

## 8. Domain Events

Six events; **all** dispatched via `DB::afterCommit(fn () => event(...))`.

| Event | Fired by | Listeners | After commit? |
|---|---|---|---|
| `LoyaltyProgramConfigured` | `ConfigureLoyaltyProgramAction` | (none in Phase 5.2 — Communication module may subscribe later for vendor onboarding emails) | ✅ |
| `LoyaltyPointsEarned` | `CalculateLoyaltyPointsAction` | `Communication\…\SendNotification` (template `loyalty.points_earned`, customer audience, in-app + push respecting `notification_preferences`) | ✅ |
| `LoyaltyRedemptionApplied` | `FinalizeRedemptionAction` (on `pending → applied` transition triggered by booking payment captured) | `Communication\…\SendNotification` (template `loyalty.points_redeemed`) | ✅ |
| `LoyaltyRedemptionVoided` | `FinalizeRedemptionAction` (`pending → voided`) | (none) | ✅ |
| `LoyaltyRedemptionReversed` | `FinalizeRedemptionAction` (`applied → reversed`) | `Communication\…\SendNotification` (template `loyalty.points_restored`) | ✅ |
| `LoyaltyLedgerEntryAppended` | `EloquentLoyaltyLedgerRepository::append()` after the row exists | `Loyalty\Application\Listeners\InvalidateBalanceCache` (sync, in same listener queue but cache-only — no DB writes) | ✅ |

**Listeners consumed FROM other modules' events:**

| Source event | Source module | Listener in Loyalty | Queue |
|---|---|---|---|
| `BookingCompleted` (per `booking_item_id`) | Booking | `CreditPointsOnBookingCompleted` | `default` (low contention; listener calls `CalculateLoyaltyPointsAction`) |
| `BookingCancelled` (with active redemption) | Booking | `VoidRedemptionOnBookingCancelled` | `default` |
| `RefundFinalized` (per `booking_item_id`) | Payments | `ReverseRedemptionOnBookingRefunded` | `default` (calls `FinalizeRedemptionAction` for `applied → reversed` and adjusts earn) |

**Architecture test** `tests/Architecture/LoyaltyEventsAfterCommitTest.php` asserts that every `event(` invocation in `app/Modules/Loyalty/` is wrapped in `DB::afterCommit(`.

---

## 9. Packages Used

All packages already in `docs/specs/10_Package_List.md` — **no new `composer require` requests in this plan**.

| Package | Purpose in Loyalty | Listed in §10_Package_List.md |
|---|---|---|
| `brick/money` | Discount math, currency handling | ✅ §1 (locked) |
| `spatie/laravel-translatable` | JSON translatable columns | ✅ §1 |
| `spatie/laravel-permission` | RBAC (`loyalty.program.manage.own`, etc.) | ✅ §1 |
| `spatie/laravel-model-states` | `RedemptionState` lifecycle | ✅ §1 |
| `bezhansalleh/filament-shield` | Filament Resource permissions | ✅ §3 |
| `filament/filament` | Vendor + admin admin UI | ✅ §3 |
| `filament/spatie-laravel-translatable-plugin` | EN/AR tabs in program form | ✅ §3 |
| `pestphp/pest` + `pest-plugin-laravel` | Test runner | ✅ §1 |

**Confirm before merge:** `composer.json` diff shows zero new `require`/`require-dev` entries. CI guard (existing) fails the PR otherwise.

---

## 10. Architecture Tests Added

Four new Pest architecture tests under `tests/Architecture/`:

1. **`LoyaltyModuleNoCrossImportTest.php`** — fails if any file under `app/Modules/Loyalty/` imports any class from `App\Modules\Booking\Domain\Models\*`, `App\Modules\Catalog\Domain\Models\*`, `App\Modules\Settlement\Domain\Models\*`, `App\Modules\Identity\Domain\Models\*`, or `App\Modules\Payments\Domain\Models\*`. Mirror of existing `tests/Architecture/GeographyModuleNoCrossImportTest.php`.

2. **`LoyaltyLedgerAppendOnlyTest.php`** — asserts:
   - Migration for `loyalty_ledger` does **not** call `softDeletes()` and does **not** add `updated_at`.
   - `LoyaltyLedgerEntry` model's `booted()` registers handlers that throw on `updating` and `deleting` events.
   - No file in `app/Modules/Loyalty/` calls `LoyaltyLedgerEntry::*->update(`, `->save(` (after creation), `->delete(`, `::query()->update(`, or `::query()->delete(`.

3. **`LoyaltyNoIfElseifOnProductTypeTest.php`** — fails if any file under `app/Modules/Loyalty/` matches the regex patterns for if/elseif chains on `'rental'|'sale'|'digital'` string literals or `match` expressions over `ProductType::*` cases. Permitted: `where('product_type', $value)` query expressions.

4. **`LoyaltyNoDirectBookingMutationTest.php`** — fails if any file under `app/Modules/Loyalty/` calls write methods on the `bookings` or `booking_items` tables (DB::table writes, model saves, model updates). All booking mutations must route through `BookingDiscountWriter`.

A fifth event-discipline test extends an existing repo-wide guard rather than being net-new:

5. **`LoyaltyEventsAfterCommitTest.php`** (or extension of existing `EventsAfterCommitTest`) — asserts every `event(` call inside `app/Modules/Loyalty/Application/Actions/*` is preceded on the same call chain by `DB::afterCommit(`.

---

## 11. Cut-list (inherited from Phase 5.2)

Per the Phase 5.2 cut-list (and reaffirmed in spec §"Out of scope"):

| Item | Deferred to | Reason |
|---|---|---|
| Referral rules (refer-a-friend point credits) | Phase 1.5 | Not in Phase 1 scope; needs a separate referral identity/anti-abuse model |
| Background expiration cleanup job | Phase 7.0 | Schema captures `loyalty_programs.expiration_days`; cleanup worker comes with hardening phase |
| Tier mechanics (silver/gold/platinum) | Out of Phase 1 entirely | Forbidden Phase 2 feature per PRD §5.2 / Constitution §"Phase 1 Forbidden Features" |
| Cross-vendor / platform-wide loyalty | Out of Phase 1 entirely | Locked decision: per-vendor only |
| Loyalty as a marketing campaign channel | Phase 1.5+ | Communication module needs campaign tooling first |

If Ibrahim asks to add any of these during Phase 5.2, push back and reference this section + the spec's "Out of scope" block.

---

## 12. Phase 0 / Phase 1 Outputs (to be generated by `/speckit.plan` follow-up)

This `/speckit.plan` invocation produces only `plan.md`. The following files are generated next as part of the standard speckit flow:

- **`research.md`** (Phase 0) — worked numerical examples for: earn calc with non-trivial ratios, redemption cap rejection at 50.01%, refund proration math, concurrent-redemption race scenario.
- **`data-model.md`** (Phase 1) — full Mermaid ERD of the four tables, complete column lists, all CHECK/UNIQUE constraints with rationale, redemption state diagram.
- **`quickstart.md`** (Phase 1) — step-by-step: vendor onboards → configures program in Filament → customer completes booking → customer sees balance → customer redeems on next booking.
- **`contracts/`** (Phase 1) — OpenAPI 3.1 fragments for all 5 endpoints in §7, plus PHP interface stubs for `BookingItemNetAmountReader`, `BookingDraftReader`, `BookingDiscountWriter`, `VendorLookup`.

`tasks.md` is **not** produced by this command — that is `/speckit.tasks`'s job.

---

## Complexity Tracking

No constitution violations to justify. No "4th project" or unusual abstractions introduced. The only deviation flagged is the **missing ADR-0012**, tracked in §1 — must be resolved before tasks/implementation.
