# Implementation Plan: Settlement — Wallets, Commissions, Withdrawals (Phase 4.2)

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/008-settlement-wallets-commissions-withdrawals/spec.md`
**ADR**: [ADR-0009 — Settlement Module](../../docs/adr/0009-settlement-module.md) — *to be drafted as task T001 on Day 1*

---

## Summary

Build the Settlement module — a new `app/Modules/Settlement/` that listens to Payments events, calculates per-`booking_item` commissions using a most-specific match against `commission_rates`, credits vendor wallets via an append-only `wallet_ledger`, and exposes a vendor withdrawal-request flow with admin Filament approval (bank-transfer proof upload, no automatic payout). Refunds reverse both the commission row (status flip) and the wallet credit (compensating ledger entry).

Six tables, five vendor API endpoints, three Filament resources, two cross-module listeners, one ADR. Cut-list defers `settlement_runs` auto-reconciliation and auto-approval rules.

Depends on: Payments (Phase 4.0/4.1 — `PaymentCaptured` and `RefundCompleted` events), Booking (Phase 3.x — `booking_items.commission_bps` snapshot), Identity (Phase 1.1 — vendor entity).

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**:
- `brick/money` — `MoneyCast` for `*_minor` ↔ `Brick\Money\Money`
- `spatie/laravel-translatable` — `withdrawals.rejected_reason` JSON
- `spatie/laravel-medialibrary` — bank-transfer proof upload (private bucket)
- `spatie/laravel-permission` — settlement permissions
- `bezhansalleh/filament-shield` — auto-generate per-resource permissions
- `filament/spatie-laravel-translatable-plugin` — EN/AR tabs in Filament

**Storage**: MySQL 8 — 6 new tables. Append-only constraints enforced by schema (no `updated_at` on `wallet_ledger`; `commissions` has `status` updates only per §15 carve-out).
**Testing**: Pest — feature tests for the 5 vendor endpoints + Filament admin actions; unit tests for `CalculateCommissionAction` rate-resolution + `ReverseCommissionAction` proportionality math; architecture tests for append-only / no-cross-import / event-after-commit.
**Target Platform**: Linux (Docker Compose for dev; staging on Hetzner CCX13)
**Project Type**: Modular monolith API + Filament admin
**Performance Goals**:
- Commission calculation listener completes within 5 seconds of `PaymentCaptured` (queued)
- Wallet balance read p95 under 100ms (cached on `wallets.balance_minor`)
- Withdrawal request p95 under 250ms
**Constraints**:
- All amounts BIGINT `_minor` + CHAR(3) `_currency` — no DECIMAL/FLOAT
- `wallet_ledger` append-only (no UPDATE, no DELETE, no soft-delete, no `updated_at`)
- `commissions` status-only updates allowed (per §15 carve-out)
- Domain events fire after `DB::afterCommit()` only
- `Idempotency-Key` middleware on `POST /api/v1/vendor/withdrawals`
- Single pending withdrawal per vendor (DB-level UNIQUE partial index)
- Bank proof private bucket only — no public URLs
**Scale/Scope**: Soft launch: ~50 vendors, ~500 bookings/month; wallet balance per vendor expected to fit in BIGINT (max ~100k EGP); commission resolution executes 4 SELECT queries worst case (one per fallback level); each indexed.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-evaluated post-design at end of plan.*

The project's effective constitution lives in `CLAUDE.md` + `docs/specs/02_Tech_Decisions.md` + `.claude/rules/*.md` (the `.specify/memory/constitution.md` stub is unfilled placeholder content). Gates checked:

| Rule | Status | Notes |
|---|---|---|
| Modular monolith — `app/Modules/Settlement/` | ✅ PASS | New module with full layer layout |
| Thin controllers (3-line action body max) | ✅ PASS | `WalletController`, `WithdrawalController` delegate to Settlement Actions |
| Per-purpose Actions, single `execute()` method | ✅ PASS | 8 Actions, each one method |
| Models hold relationships/casts/scopes only | ✅ PASS | All 6 models lean — business logic in Actions |
| `match($enum)` not if/elseif on type strings | ✅ PASS | `LedgerEntryType` and commission-rate fallback use `match()` |
| No cross-module Eloquent Model imports | ✅ PASS | Reads via contracts: `SettlementBookingReader`, `SettlementPaymentReader` |
| Money as integer minor units | ✅ PASS | All money columns BIGINT `_minor` + CHAR(3) `_currency`; `MoneyCast` returns `Brick\Money\Money` |
| ULID `public_id` on top-level entities | ✅ PASS | `wallets`, `withdrawals`, `commissions`, `commission_rates`, `settlement_runs` all have `public_id` CHAR(26) UNIQUE |
| Translatable JSON columns | ✅ PASS | `withdrawals.rejected_reason` JSON via `spatie/laravel-translatable` |
| `wallet_ledger` append-only (no `updated_at`, no `deleted_at`) | ✅ PASS | `timestamp('created_at')->useCurrent()` only |
| `commissions` append-only except `status` | ✅ PASS | §15 carve-out; status: `calculated` → `partially_reversed` \| `reversed` |
| `withdrawals` status-only updates | ✅ PASS | §15 carve-out; status: `pending` → `paid` \| `rejected` |
| Soft-deletes only on whitelisted tables | ✅ PASS | None of the 6 Settlement tables have `softDeletes()` |
| Domain events fire after `DB::afterCommit()` | ✅ PASS | All 7 events dispatched via `DB::afterCommit()` |
| `Idempotency-Key` middleware on payment-mutating endpoints | ✅ PASS | `POST /api/v1/vendor/withdrawals` only |
| `ApiResponse` envelope on every API response | ✅ PASS | All 5 vendor endpoints |
| Locale conversion at API Resource layer | ✅ PASS | `WithdrawalResource` resolves `rejected_reason` from JSON via `Accept-Language` |
| `filament-shield` permissions per resource | ✅ PASS | 5 permissions; `php artisan shield:generate --all` after each new resource |
| No new packages outside `10_Package_List.md` | ✅ PASS | Re-uses existing packages; no `composer require` needed |
| No Phase 2 features | ✅ PASS | No subscription tiers, dispute engine, auto-payout API — all explicitly cut |

**GATE RESULT: ALL PASS — proceed to Phase 0 research.**

---

## Schema (matches `docs/specs/11_DB_Schema.md` lines 925–1015)

Migrations in FK dependency order:

| # | Migration filename | Tables / Changes |
|---|---|---|
| 1 | `2026_05_03_000001_create_wallets_table.php` | `wallets` — UNIQUE `(owner_type, owner_id, currency)`; cached `balance_minor` |
| 2 | `2026_05_03_000002_create_wallet_ledger_table.php` | append-only ledger; `entry_type` ENUM |
| 3 | `2026_05_03_000003_create_commission_rates_table.php` | configurable rate per `(category_id NULLABLE, product_type NULLABLE)` |
| 4 | `2026_05_03_000004_create_commissions_table.php` | UNIQUE `booking_item_id`; snapshot of `commission_bps`, `commission_minor`, `vendor_share_minor`; status field |
| 5 | `2026_05_03_000005_create_withdrawals_table.php` | per-vendor withdrawal request; bank account snapshot JSON; rejected_reason translatable JSON |
| 6 | `2026_05_03_000006_create_settlement_runs_table.php` | table only; no rows written in Phase 4.2 (cut-list) |

All migrations: `utf8mb4` charset; `bigIncrements('id')` + `char('public_id', 26)->unique()` on top-level entities; money columns BIGINT `_minor` + CHAR(3) `_currency`; FK `restrictOnDelete()` by default (only `wallet_ledger.wallet_id` cascades).

Critical indexes:
- `wallets`: UNIQUE `(owner_type, owner_id, currency)`
- `wallet_ledger`: `(wallet_id, created_at DESC)` for pagination; UNIQUE `(related_entity_type, related_entity_id, entry_type)` for refund-reversal idempotency
- `commission_rates`: NULL-safe UNIQUE on `(category_id, product_type)` (using derived `IFNULL` columns)
- `commissions`: UNIQUE `booking_item_id`; INDEX `(vendor_profile_id, status, created_at)`
- `withdrawals`: UNIQUE PARTIAL `(vendor_profile_id) WHERE status = 'pending'` — single-pending rule at DB level

---

## Per-type Coverage

Settlement is **NOT a per-product-type module** — it operates on `booking_items` regardless of type. However, type-aware behavior exists via:

| Concern | How types are handled |
|---|---|
| Commission rate resolution | `commission_rates` keyed by `(category_id, product_type)`; 4-level fallback |
| Pest tests | Test suite verifies commission flow for all 3 product types (rental + sale + digital) — parameterized Pest tests |
| Refund reversal proportionality | Same logic for all 3 types; no per-type branching |
| Wallet credit | Single code path; type-agnostic |

---

## Locale Coverage

| Field | Locale strategy |
|---|---|
| `withdrawals.rejected_reason` | JSON `{en, ar}` via `spatie/laravel-translatable`; admin Filament EN/AR tabs |
| `wallet_ledger.description_key` + `description_params` | i18n key resolved at API layer via `lang/{en,ar}/settlement.php` |
| API responses | `WalletResource`, `WalletLedgerEntryResource`, `WithdrawalResource` resolve `Accept-Language` at Resource layer |
| Admin Filament | EN/العربية locale switcher; money via `->money('EGP', divideBy: 100)` |
| Validation messages | Bilingual via Laravel's existing system + module-scoped `Resources/lang/{en,ar}/settlement.php` |

---

## Idempotency

| Endpoint | Idempotency-Key required? | Mechanism |
|---|---|---|
| `GET /api/v1/vendor/wallet` | No | Read-only |
| `GET /api/v1/vendor/wallet/ledger` | No | Read-only |
| `GET /api/v1/vendor/withdrawals` | No | Read-only |
| `POST /api/v1/vendor/withdrawals` | **Yes** | `IdempotencyKeyMiddleware` (existing from Payments) — 24h TTL keyed by `(route, key, user_id)` |
| `GET /api/v1/vendor/withdrawals/{public_id}` | No | Read-only |

Listeners idempotent by schema constraint:
- `commissions.booking_item_id` UNIQUE → retried `PaymentCaptured` produces same row (or duplicate-key, caught and ignored)
- `wallet_ledger` UNIQUE on `(related_entity_type, related_entity_id, entry_type)` → duplicate `RefundCompleted` cannot create duplicate debits

---

## Domain Events

All events fire after `DB::afterCommit()`. Listeners run via `database` queue.

### Settlement-emitted events (7)
- `WalletCredited` — fires from `CreditWalletAction`
- `WalletDebited` — fires from `DebitWalletAction`
- `CommissionCalculated` — fires from `CalculateCommissionAction`
- `CommissionReversed` — fires from `ReverseCommissionAction`
- `WithdrawalRequested` — fires from `RequestWithdrawalAction`
- `WithdrawalPaid` — fires from `ApproveAndMarkWithdrawalPaidAction`
- `WithdrawalRejected` — fires from `RejectWithdrawalAction`

### Settlement-consumed events (cross-module)
- `PaymentCaptured` (from Payments) → `CalculateCommissionOnPaymentCapturedListener` (queued)
- `RefundCompleted` (from Payments) → `ReverseCommissionOnRefundCompletedListener` (queued)

### Cross-module access pattern
Settlement does NOT import Booking or Payments models. Instead:
- `Domain/Contracts/SettlementBookingReader` — interface owned by Settlement
- `Domain/Contracts/SettlementPaymentReader` — interface owned by Settlement
- Implementations live in Booking/Payments modules, registered in their ServiceProviders
- Contracts return DTOs (`BookingItemSnapshotDto`, `PaymentSnapshotDto`, `RefundSnapshotDto`), never models

---

## API Documentation Plan

| Method | Path | Auth | Roles | Idempotency | `@bodyParam` | `@response` | Registry | Bruno | Postman |
|---|---|---|---|---|---|---|---|---|---|
| GET | `/api/v1/vendor/wallet` | sanctum | `settlement.view_wallet.own` | — | — | EN+AR | ✅ | ✅ | ✅ |
| GET | `/api/v1/vendor/wallet/ledger` | sanctum | `settlement.view_wallet.own` | — | query | paginated | ✅ | ✅ | ✅ |
| GET | `/api/v1/vendor/withdrawals` | sanctum | `settlement.view_withdrawals.own` | — | query | paginated | ✅ | ✅ | ✅ |
| POST | `/api/v1/vendor/withdrawals` | sanctum | `settlement.request_withdrawal.own` | required | body | 201+422 | ✅ | ✅ | ✅ |
| GET | `/api/v1/vendor/withdrawals/{public_id}` | sanctum | `settlement.view_withdrawals.own` | — | path | EN+AR | ✅ | ✅ | ✅ |

Documentation tasks per endpoint:
1. `@bodyParam` PHPDoc on Form Request fields
2. `@response` PHPDoc on API Resource with realistic EN+AR data
3. Row in `.specify/memory/api-registry.md`
4. Entry in `docs/api/collections/settlement.bru`
5. Entry in `docs/api/collections/settlement.postman_collection.json`
6. `php artisan scribe:generate` after all 5 endpoints documented

Admin operations (Filament resources) — no Scribe entries.

---

## Packages Used (all in `docs/specs/10_Package_List.md`)

| Package | Use | Source |
|---|---|---|
| `brick/money` | `MoneyCast` for all `*_minor` columns | already installed (Phase 0.2) |
| `spatie/laravel-translatable` | `withdrawals.rejected_reason` JSON | already installed (Phase 0.0) |
| `spatie/laravel-medialibrary` | `bank_proof` collection on Withdrawal | already installed (Phase 0.0) |
| `spatie/laravel-permission` | settlement.* permissions | already installed (Phase 0.0) |
| `filament/filament` v3 | Admin UI | already installed (Phase 0.0) |
| `bezhansalleh/filament-shield` | Auto-generate per-resource permissions | already installed |
| `filament/spatie-laravel-translatable-plugin` | EN/AR tabs on rejected_reason | already installed |
| `pestphp/pest` + `pestphp/pest-plugin-arch` | Feature/unit/arch tests | already installed |

**No new packages required.** Phase 4.2 is pure business logic.

---

## Architecture Tests

| Test | Action |
|---|---|
| `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest` | **Extend** — add `wallet_ledger`, `commissions` to assertion list |
| `tests/Architecture/EventsFireAfterCommitTest` | **Extend** — assert all 7 Settlement events fire via `DB::afterCommit()` |
| `tests/Architecture/SettlementModuleNoCrossImportTest` | **New** — Settlement code does NOT import Booking or Payments Eloquent models |
| `tests/Architecture/IdempotencyMiddlewareCoverageTest` | **Extend** — `POST /api/v1/vendor/withdrawals` is wrapped by Idempotency middleware |

---

## Project Structure

### Documentation (this feature)

```text
specs/008-settlement-wallets-commissions-withdrawals/
├── plan.md                ← This file
├── spec.md
├── research.md            ← Phase 0 output (decisions)
├── data-model.md          ← Phase 1 output (entity definitions)
├── quickstart.md          ← Phase 1 output (run/test commands)
├── contracts/             ← Phase 1 output
│   ├── wallet.md                            (vendor wallet endpoints)
│   ├── withdrawals.md                       (vendor withdrawal endpoints)
│   ├── listener-payment-captured.md         (cross-module listener)
│   └── listener-refund-completed.md         (cross-module listener)
├── checklists/requirements.md
└── tasks.md               ← Phase 2 output (/speckit-tasks creates this)
```

### Source Code (repository root)

```text
app/Modules/Settlement/
├── Domain/
│   ├── Models/                       # Wallet, WalletLedgerEntry, Commission, CommissionRate, Withdrawal, SettlementRun
│   ├── Enums/                        # LedgerEntryType, CommissionStatus, WithdrawalStatus
│   ├── Events/                       # 7 events
│   ├── Exceptions/                   # InsufficientWalletBalance, ExistingPendingWithdrawal, WithdrawalBelowMinimum
│   ├── ValueObjects/BankAccountSnapshot.php
│   └── Contracts/                    # SettlementBookingReader, SettlementPaymentReader, CommissionRateResolver
├── Application/
│   ├── Actions/                      # Calculate/Credit/Debit/Reverse Commission, Request/ApproveAndMarkPaid/Reject Withdrawal
│   ├── DTOs/                         # BookingItemSnapshotDto, PaymentSnapshotDto, RefundSnapshotDto, RequestWithdrawalDto
│   ├── Listeners/                    # CalculateCommissionOnPaymentCapturedListener, ReverseCommissionOnRefundCompletedListener
│   └── Services/EloquentCommissionRateResolver.php
├── Infrastructure/Repositories/      # EloquentWallet/Withdrawal/CommissionRepository
├── Http/
│   ├── Controllers/Vendor/           # WalletController, WithdrawalController
│   ├── Requests/RequestWithdrawalRequest.php
│   └── Resources/                    # Wallet, WalletLedgerEntry, Withdrawal Resources
├── Filament/Resources/               # WithdrawalsQueueResource, WalletLedgerViewerResource, CommissionRulesResource
├── Routes/vendor.php
├── Database/{Migrations,Factories,Seeders}/
├── Resources/lang/{en,ar}/settlement.php
└── Providers/SettlementServiceProvider.php

# Cross-module reader implementations
app/Modules/Booking/Infrastructure/Repositories/EloquentSettlementBookingReader.php
app/Modules/Payments/Infrastructure/Repositories/EloquentSettlementPaymentReader.php

# API documentation
docs/api/collections/settlement.bru
docs/api/collections/settlement.postman_collection.json
.specify/memory/api-registry.md   (5 new rows appended)

# ADR
docs/adr/0009-settlement-module.md
```

**Structure Decision**: Modular monolith (per `02_Tech_Decisions.md` §1). New top-level module `app/Modules/Settlement/` with the standard 9-folder layout.

---

## Implementation Order (Day by Day)

### Day 1 — ADR + Schema + Commission Logic
- T001: Draft and accept ADR-0009
- T002–T007: 6 migrations in FK dependency order
- T010–T015: 6 Models with relationships/casts/scopes
- T020–T025: 6 Factories
- T030: Enums (`LedgerEntryType`, `CommissionStatus`, `WithdrawalStatus`)
- T040: `BankAccountSnapshot` value object
- T050: `EloquentCommissionRateResolver` (4-level fallback)
- T051: `CalculateCommissionAction` + `CreditWalletAction`
- T060: `CalculateCommissionOnPaymentCapturedListener` (queued)
- T061: Wire listener in `SettlementServiceProvider`
- T062–T063: `SettlementBookingReader` + `SettlementPaymentReader` contracts + impls in Booking/Payments
- T070: `DefaultCommissionRatesSeeder` (NULL × NULL @ 1500 bps)

### Day 2 — Withdrawal Flow + Filament + Refund Reversal
- T100–T102: `RequestWithdrawalAction` + `DebitWalletAction` + `ApproveAndMarkWithdrawalPaidAction` + `RejectWithdrawalAction`
- T110–T114: Form Request, controllers, routes, API Resources, IdempotencyKeyMiddleware
- T120–T122: 3 Filament resources
- T130: `php artisan shield:generate --all`
- T140–T141: `ReverseCommissionAction` + `ReverseCommissionOnRefundCompletedListener`

### Day 3 — Tests + API Docs
- T200–T204: Pest feature tests for the 5 vendor endpoints
- T210–T216: Pest tests — 4-level rate resolution, all 3 product types, append-only invariant, full withdrawal flow, refund reversal (full + partial), single-pending rule, Idempotency-Key
- T220: Architecture tests (extend 3, add 1)
- T230–T234: API doc backfill (`@bodyParam`, `@response`, registry rows, Bruno, Postman)
- T240: `php artisan scribe:generate`
- T250: `pint` + `phpstan` + `pest --bail`

---

## Cut-list (inherited from `09_Phasing_Plan.md` §Phase 4.2)

| Cut | Phase 4.2 substitute | Tracked under |
|---|---|---|
| Auto-reconciliation jobs for `settlement_runs` | Manual reconciliation; admin reads `wallet_ledger` directly | Phase 1.5 |
| Auto-approval rules for withdrawals | Every withdrawal goes through admin manually | Phase 1.5 |
| Multi-currency vendor wallets in Filament | Schema supports it; UI ships single-currency (EGP) | Phase 1.5 |
| Automatic Paymob payout API integration | Admin transfers via banking portal externally + uploads proof | Phase 1.5 / Phase 2 |

---

## Complexity Tracking

| Decision | Why simpler alternative was rejected |
|---|---|
| Cached `wallets.balance_minor` (vs. computed on every read) | Read p95 must stay under 100ms; computing sum-of-ledger doesn't scale |
| `CommissionRateResolver` as separate service (vs. inline in Action) | 4-level fallback needs unit tests in isolation; extracting it makes math testing clean |
| Combined `ApproveAndMarkWithdrawalPaidAction` (vs. two actions) | Phase 4.2 admin UX collapses approve+paid into one click; split when Phase 1.5 introduces auto-payout intermediate state |
| Negative wallet balance allowed | Refund-after-withdrawal is rare but real; rejecting it would require complex pre-checks and could cause double-charge |
| Bank account snapshot on the withdrawal row (vs. FK to vendor_bank_accounts) | No vendor_bank_accounts table in Phase 1; vendors enter bank account at withdrawal time. Phase 1.5 introduces the table if needed |

---

## Post-Design Constitution Re-Check

After Phase 0 (`research.md`) and Phase 1 (`data-model.md`, `contracts/`, `quickstart.md`):

| Rule | Status | Notes |
|---|---|---|
| All gates from initial check | ✅ PASS | No new violations introduced by design artifacts |
| Contracts technology-agnostic at spec layer | ✅ PASS | Contract files are markdown describing endpoints + listeners (Laravel-agnostic) |
| Listener contracts documented | ✅ PASS | `listener-payment-captured.md` and `listener-refund-completed.md` describe inputs, outputs, idempotency guarantees |
| Architecture tests cover the new module | ✅ PASS | `SettlementModuleNoCrossImportTest` planned |

**RE-CHECK RESULT: ALL PASS — proceed to /speckit-tasks.**
