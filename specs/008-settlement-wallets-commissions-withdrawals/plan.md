# Implementation Plan: Settlement — Wallets, Commissions, Withdrawals

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/008-settlement-wallets-commissions-withdrawals/spec.md`

---

## Summary

Phase 4.2 introduces the Settlement module, which owns the full financial distribution cycle after a payment is captured: commission calculation per `booking_item` (with a 4-level most-specific-wins fallback across `commission_rates`), vendor wallet credit/debit via an append-only ledger, withdrawal request + admin approval flow with bank-transfer proof upload, and commission reversal on refund. The module is strictly event-driven — it subscribes to `PaymentCaptured` and `RefundCompleted` events published by the Payments module, and never imports Booking or Payments Eloquent models directly (contracts pattern via `SettlementBookingReader` and `SettlementPaymentReader`).

**PRD coverage**: FR-28 (vendor wallet visibility), FR-29 (admin approves withdrawals), FR-30 (commission deduction + financial tracking).
**ADR**: [ADR-0009 — Settlement Module](../../docs/adr/0009-settlement-module.md) — **Accepted 2026-05-03**.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Brick\Money (integer minor units), spatie/laravel-medialibrary (bank proof), spatie/laravel-translatable (rejection_reason JSON), bezhansalleh/filament-shield (permissions), Laravel Queue (database driver)
**Storage**: MySQL 8 / MariaDB 11 — 6 new tables: `wallets`, `wallet_ledger`, `commissions`, `commission_rates`, `withdrawals`, `settlement_runs`
**Testing**: Pest — 10 Feature + 3 Unit + 1 Architecture test files
**Target Platform**: Laravel modular monolith — `app/Modules/Settlement/`
**Performance Goals**: Wallet balance read <100ms (cached on `wallets.balance_minor`); commission listener completes within 5s of `PaymentCaptured` (queued)
**Constraints**: `wallet_ledger` fully append-only; `commissions` append-only except `status`; no auto-payout in Phase 4.2; EGP-only UI (schema is multi-currency)
**Scale/Scope**: Phase 1 — ~100 vendors, low transaction volume; no Redis queue required

---

## Constitution Check

*GATE: All 11 constitution principles verified. Re-checked post-design — all still pass.*

| # | Principle | Status | How satisfied |
|---|---|---|---|
| I | Modular monolith — new module in `app/Modules/Settlement/` | ✅ PASS | Full layer layout: Domain / Application / Infrastructure / Http / Filament / Routes / Database / Providers |
| II | `match($enum)` not if/elseif on type strings | ✅ PASS | `LedgerEntryType` enum + `ProductType` enum — all branching via `match()` in Actions and rate resolver |
| III | Money as integer minor units (Brick\Money) | ✅ PASS | All money columns BIGINT `_minor` + CHAR(3) `_currency`; `MoneyCast` returns `Brick\Money\Money`; no decimals or floats |
| IV | Bilingual EN+AR mandatory | ✅ PASS | `withdrawals.rejected_reason` is JSON translatable; ledger entry descriptions use i18n keys; API Resources resolve via `Accept-Language`; Filament uses translatable plugin |
| V | Append-only tables respected | ✅ PASS | `wallet_ledger`: no `updated_at`, no UPDATE, no DELETE. `commissions`: only `status` updatable (§15 carve-out). No `softDeletes()` on any of the 6 tables |
| VI | ADR before code | ✅ PASS | ADR-0009 accepted 2026-05-03 before any migration is written |
| VII | Test-first for critical paths (money flows) | ✅ PASS | 13 test files cover commission flows × 3 product types, all 4 rate fallbacks, refund reversal idempotency, withdrawal guard, and wallet balance invariant |
| VIII | Idempotency on state-changing endpoints | ✅ PASS | `POST /api/v1/vendor/withdrawals` wrapped in Idempotency middleware (24h TTL); commission listener idempotent via UNIQUE `commissions.booking_item_id` |
| IX | Domain events fire `DB::afterCommit` | ✅ PASS | `WalletCredited`, `CommissionCalculated`, `WithdrawalRequested`, `WithdrawalPaid`, `WithdrawalRejected`, `CommissionReversed` all dispatched via `DB::afterCommit()` |
| X | Vendor approval two-step gate | ✅ PASS | Settlement actions check `VendorProfile` existence via `SettlementBookingReader`; no direct vendor model import |
| XI | Document storage — typed vs. gallery | ✅ PASS | Bank proof: Spatie Media Library on `s3_private` disk (typed, sensitive). No gallery assets in Settlement |

**GATE RESULT: ALL 11 PRINCIPLES PASS — proceed to implementation.**

---

## Project Structure

### Documentation (this feature)

```text
specs/008-settlement-wallets-commissions-withdrawals/
├── plan.md              ← This file (/speckit.plan output)
├── spec.md              ← Feature specification
├── research.md          ← Phase 0: 13 design decisions resolved
├── data-model.md        ← Phase 1: 6 entities + DTOs + Actions + test plan
├── quickstart.md        ← Phase 1: migrations, seeding, smoke test
├── contracts/
│   ├── wallet.md        ← GET /api/v1/vendor/wallet + /wallet/ledger contracts
│   ├── withdrawals.md   ← POST/GET /api/v1/vendor/withdrawals contracts
│   ├── listener-payment-captured.md  ← PaymentCaptured listener contract
│   └── listener-refund-completed.md  ← RefundCompleted listener contract
└── tasks.md             ← Phase 2 output (/speckit.tasks — not yet generated)
```

### Source Code (repository root)

```text
app/Modules/Settlement/
├── Domain/
│   ├── Models/
│   │   ├── Wallet.php
│   │   ├── WalletLedgerEntry.php
│   │   ├── Commission.php
│   │   ├── CommissionRate.php
│   │   ├── Withdrawal.php
│   │   └── SettlementRun.php
│   ├── Enums/
│   │   ├── LedgerEntryType.php        # commission_credit | refund_debit | withdrawal_debit | manual_adjustment
│   │   ├── CommissionStatus.php       # calculated | partially_reversed | reversed
│   │   ├── WithdrawalStatus.php       # pending | approved | paid | rejected
│   │   └── SettlementRunStatus.php    # pending | reconciled | disputed
│   ├── Events/
│   │   ├── WalletCredited.php
│   │   ├── WalletDebited.php
│   │   ├── CommissionCalculated.php
│   │   ├── CommissionReversed.php
│   │   ├── WithdrawalRequested.php
│   │   ├── WithdrawalPaid.php
│   │   └── WithdrawalRejected.php
│   ├── Exceptions/
│   │   ├── InsufficientWalletBalanceException.php
│   │   ├── ExistingPendingWithdrawalException.php
│   │   └── WithdrawalBelowMinimumException.php
│   ├── ValueObjects/
│   │   └── BankAccountSnapshot.php
│   └── Contracts/
│       ├── SettlementBookingReader.php
│       ├── SettlementPaymentReader.php
│       └── CommissionRateResolver.php
├── Application/
│   ├── Actions/
│   │   ├── CalculateAndCreditCommissionsAction.php
│   │   ├── ReverseCommissionForRefundAction.php
│   │   ├── CreditWalletAction.php
│   │   ├── DebitWalletAction.php
│   │   ├── RequestWithdrawalAction.php
│   │   ├── ApproveAndMarkWithdrawalPaidAction.php
│   │   └── RejectWithdrawalAction.php
│   ├── DTOs/
│   │   ├── BookingItemSnapshotDto.php
│   │   ├── PaymentSnapshotDto.php
│   │   ├── RefundSnapshotDto.php
│   │   └── RequestWithdrawalDto.php
│   ├── Listeners/
│   │   ├── HandlePaymentCapturedListener.php   # queued, after commit
│   │   └── HandleRefundCompletedListener.php   # queued, after commit
│   └── Services/
│       └── CommissionCalculatorService.php
├── Infrastructure/
│   └── Repositories/
│       ├── EloquentWalletRepository.php
│       ├── EloquentWithdrawalRepository.php
│       ├── EloquentCommissionRepository.php
│       └── EloquentCommissionRateResolver.php     # binds CommissionRateResolver contract
├── Http/
│   ├── Controllers/
│   │   └── Vendor/
│   │       ├── WalletController.php
│   │       └── WithdrawalController.php
│   ├── Requests/
│   │   └── RequestWithdrawalRequest.php
│   └── Resources/
│       ├── WalletResource.php
│       ├── WalletLedgerEntryResource.php
│       ├── WithdrawalResource.php
│       └── WithdrawalCollection.php
├── Filament/
│   └── Resources/
│       ├── WithdrawalsQueueResource.php
│       ├── WalletLedgerViewerResource.php
│       └── CommissionRulesResource.php
├── Routes/
│   └── vendor.php
├── Database/
│   ├── Migrations/
│   │   ├── 2026_05_03_000001_create_wallets_table.php
│   │   ├── 2026_05_03_000002_create_wallet_ledger_table.php
│   │   ├── 2026_05_03_000003_create_commission_rates_table.php
│   │   ├── 2026_05_03_000004_create_commissions_table.php
│   │   ├── 2026_05_03_000005_create_withdrawals_table.php
│   │   └── 2026_05_03_000006_create_settlement_runs_table.php
│   ├── Factories/
│   │   ├── WalletFactory.php
│   │   ├── CommissionFactory.php
│   │   ├── CommissionRateFactory.php
│   │   └── WithdrawalFactory.php
│   └── Seeders/
│       └── DefaultCommissionRatesSeeder.php
├── Resources/
│   └── lang/
│       ├── en/settlement.php
│       └── ar/settlement.php
└── Providers/
    └── SettlementServiceProvider.php

# Cross-module contracts implemented by Booking and Payments modules:
app/Modules/Booking/Infrastructure/Repositories/
└── EloquentSettlementBookingReader.php       # implements Settlement\Domain\Contracts\SettlementBookingReader
app/Modules/Payments/Infrastructure/Repositories/
└── EloquentSettlementPaymentReader.php       # implements Settlement\Domain\Contracts\SettlementPaymentReader

# Tests:
tests/Feature/Modules/Settlement/
├── CommissionCalculationTest.php             # US1 — all 3 product types
├── CommissionFallbackTest.php               # US1 — all 4 fallback levels
├── WalletViewTest.php                       # US2 — balance, auth, locale
├── WalletLedgerTest.php                     # US2 — pagination, ordering
├── RequestWithdrawalTest.php                # US3 — happy + guards
├── ListWithdrawalsTest.php                  # US3 — pagination, isolation
├── AdminApproveWithdrawalTest.php           # US4 — approve+pay, proof upload
├── AdminRejectWithdrawalTest.php            # US4 — reject with bilingual reason
├── RefundReversalTest.php                   # US5 — full + partial + idempotency
└── CommissionRulesAdminTest.php             # US6 — commission CRUD

tests/Unit/Modules/Settlement/
├── EloquentCommissionRateResolverTest.php    # 4-level fallback isolated
├── ProportionalReversalMathTest.php         # Brick\Money HALF_EVEN rounding
└── WalletBalanceInvariantTest.php           # balance == SUM(ledger) invariant

tests/Architecture/
└── SettlementModuleNoCrossImportTest.php     # Settlement never imports Booking/Payments models
```

---

## API Endpoints

| Method | Path | Auth | Roles | Idempotency-Key | Purpose |
|---|---|---|---|---|---|
| `GET` | `/api/v1/vendor/wallet` | Sanctum | `vendor` (own) | — | Current balance + summary |
| `GET` | `/api/v1/vendor/wallet/ledger` | Sanctum | `vendor` (own) | — | Paginated ledger entries |
| `GET` | `/api/v1/vendor/withdrawals` | Sanctum | `vendor` (own) | — | Withdrawal history |
| `POST` | `/api/v1/vendor/withdrawals` | Sanctum | `vendor` | required | Request withdrawal |
| `GET` | `/api/v1/vendor/withdrawals/{public_id}` | Sanctum | `vendor` (own) | — | Single withdrawal detail |

**Admin operations**: Filament resources only — `WithdrawalsQueueResource`, `WalletLedgerViewerResource`, `CommissionRulesResource`.

---

## Migration Dependency Order

All 6 migrations live in `app/Modules/Settlement/Database/Migrations/` and must run in this order (no circular FKs):

1. `create_wallets_table` — needs `vendor_profiles` (Phase 0.2)
2. `create_wallet_ledger_table` — needs `wallets` (#1)
3. `create_commission_rates_table` — needs `categories` (Phase 2.0)
4. `create_commissions_table` — needs `booking_items` (Phase 3.1) + `payments` (Phase 4.0) + `vendor_profiles` + `categories`
5. `create_withdrawals_table` — needs `vendor_profiles` + `users` + `media` (Spatie, Phase 0.0)
6. `create_settlement_runs_table` — standalone (no FK dependencies)

---

## Domain Events & Listeners

### Events consumed (from Payments module)

| Event | Source | Handler | Queue |
|---|---|---|---|
| `Payments\Domain\Events\PaymentCaptured` | Payments | `HandlePaymentCapturedListener` → `CalculateAndCreditCommissionsAction` | database queue, 3 retries |
| `Payments\Domain\Events\RefundCompleted` | Payments | `HandleRefundCompletedListener` → `ReverseCommissionForRefundAction` | database queue, 3 retries |

### Events published (by Settlement)

| Event | Fired after | `DB::afterCommit` |
|---|---|---|
| `CommissionCalculated` | commission row created | ✅ |
| `WalletCredited` | ledger credit entry inserted | ✅ |
| `WalletDebited` | ledger debit entry inserted | ✅ |
| `CommissionReversed` | commission reversed/partially reversed | ✅ |
| `WithdrawalRequested` | withdrawal row created | ✅ |
| `WithdrawalPaid` | admin marks paid | ✅ |
| `WithdrawalRejected` | admin rejects | ✅ |

---

## Cross-Module Contracts

Settlement defines these contracts in `Domain/Contracts/`. Implementing modules bind them in their own `ServiceProvider::register()`:

```php
// SettlementServiceProvider::register()
$this->app->bind(
    \App\Modules\Settlement\Domain\Contracts\CommissionRateResolver::class,
    \App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRateResolver::class,
);

// BookingServiceProvider::register()
$this->app->bind(
    \App\Modules\Settlement\Domain\Contracts\SettlementBookingReader::class,
    \App\Modules\Booking\Infrastructure\Repositories\EloquentSettlementBookingReader::class,
);

// PaymentsServiceProvider::register()
$this->app->bind(
    \App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader::class,
    \App\Modules\Payments\Infrastructure\Repositories\EloquentSettlementPaymentReader::class,
);
```

---

## Key Design Decisions (from research.md)

| ID | Decision |
|---|---|
| R1 | Wallet balance **cached** on `wallets.balance_minor` (updated in same transaction as ledger insert); invariant tested by Pest |
| R2 | Commission rate fallback via **4 sequential SQL queries**, short-circuiting on first match (indexed, <5ms each) |
| R3 | Single-pending withdrawal rule enforced at **DB level** via UNIQUE PARTIAL index on `(vendor_profile_id) WHERE status = 'pending'` |
| R4 | Refund reversal proportional: `ratio = refund/payment`; Brick\Money `HALF_EVEN` rounding |
| R5 | Bank proof on `s3_private`/`minio_private` via Spatie Media Library; signed URLs (1h TTL) |
| R6 | Phase 4.2 ships **combined "Approve & Mark Paid"** action (no intermediate `approved` state in Filament) |
| R7 | **Negative balance allowed** on refund-after-withdrawal; audit-logged; blocks new withdrawals until ≥ 0 |
| R8 | Cross-module reads via `SettlementBookingReader` / `SettlementPaymentReader` contracts; never direct Eloquent imports |
| R9 | UNIQUE on `commissions.booking_item_id` — one commission per item for lifetime; modifications create new booking_items |
| R10 | Schema is multi-currency; Phase 4.2 UI/API surfaces EGP only |
| R11 | Listeners use `database` queue; 3 retries with exponential backoff; hard failures → `failed_jobs` + `audit_logs` |

---

## Filament Resources

| Resource | Navigation Group | Permissions | Purpose |
|---|---|---|---|
| `WithdrawalsQueueResource` | Settlement | `settlement.approve_withdrawal`, `settlement.reject_withdrawal` | Admin approves / rejects pending withdrawals, uploads bank proof |
| `WalletLedgerViewerResource` | Settlement | `settlement.view_wallet` (admin) | Read-only ledger viewer for any vendor wallet |
| `CommissionRulesResource` | Settlement | `settlement.manage_commission_rates` | CRUD on `commission_rates` (category × type) |

After creating resources: `php artisan shield:generate --all`

---

## Locale Coverage

| Layer | EN | AR |
|---|---|---|
| Validation error messages | ✅ | ✅ |
| Ledger entry descriptions | ✅ (`lang/en/settlement.php`) | ✅ (`lang/ar/settlement.php`) |
| Rejection reason (`withdrawals.rejected_reason`) | ✅ JSON key `en` | ✅ JSON key `ar` |
| Filament (admin panel) | ✅ | ✅ (with language switcher plugin) |
| API Resources | ✅ (via `Accept-Language: en`) | ✅ (via `Accept-Language: ar`) |
| Pest locale assertions | Both locales tested in `WalletViewTest` + `AdminRejectWithdrawalTest` | ✅ |

---

## Three Product Types Coverage

| Flow | Rental | Sale | Digital |
|---|---|---|---|
| Commission calculation | ✅ `product_type = rental` rate resolution | ✅ `product_type = sale` | ✅ `product_type = digital` |
| Rate fallback all 4 levels | ✅ tested in `CommissionFallbackTest` | ✅ | ✅ |
| Refund reversal | ✅ | ✅ | ✅ |
| `CommissionCalculationTest` covers | ✅ explicit `->group('rental')` | ✅ `->group('sale')` | ✅ `->group('digital')` |

---

## Idempotency

| Endpoint / Listener | Mechanism | TTL |
|---|---|---|
| `POST /api/v1/vendor/withdrawals` | `Idempotency-Key` middleware + `idempotency_keys` table | 24h |
| `HandlePaymentCapturedListener` | UNIQUE `commissions.booking_item_id` at DB layer | permanent |
| `HandleRefundCompletedListener` | UNIQUE `(related_entity_type, related_entity_id, entry_type)` on `wallet_ledger` | permanent |

---

## Architecture Tests Required

| Test file | Assertion |
|---|---|
| `tests/Architecture/SettlementModuleNoCrossImportTest.php` | Settlement module files do not import `App\Modules\Booking\Domain\Models\*` or `App\Modules\Payments\Domain\Models\*` |

Existing architecture tests that must remain green:
- `tests/Architecture/NoFloatForMoneyTest.php`
- `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php`
- `tests/Architecture/NoIfElseOnProductTypeStringTest.php`

---

## Cut-list (inherited from Phase 4.2)

- **`settlement_runs` auto-reconciliation deferred** — table created (schema stable), no scheduled job or Filament resource in Phase 4.2
- **Auto-approval rules deferred** — every withdrawal requires manual admin approval in Phase 4.2
- **PDF bank transfer receipt generation deferred** — Phase 2
- **Per-currency wallet UI deferred** — schema is multi-currency; Phase 4.2 surfaces EGP only
- **Intermediate `approved` state for withdrawals deferred** — Phase 1.5 when automated payout (Paymob payout API) is added

---

## Exit Criteria (from `docs/specs/09_Phasing_Plan.md` §Phase 4.2)

- [ ] Commission per `(category × type)` working with all 4 fallback levels
- [ ] Wallet credit on payment, debit on refund
- [ ] Admin approves withdrawal with proof
- [ ] Pest covers all 3 product types' commission flows

---

## Complexity Tracking

*No constitution violations — no complexity justifications required.*

---

## References

- **Spec**: `docs/specs/01_PRD.md` §7.7 (FR-28, FR-29, FR-30)
- **Schema**: `docs/specs/11_DB_Schema.md` §9 (Settlement — 6 tables, lines 923–1015)
- **Tech Decisions**: `docs/specs/02_Tech_Decisions.md` §11 (money as minor units) + §1 (modular monolith)
- **Three Product Types**: `docs/specs/03_Three_Product_Types.md` §Commission rates (most-specific match)
- **Phasing Plan**: `docs/specs/09_Phasing_Plan.md` §Phase 4.2
- **ADR**: `docs/adr/0009-settlement-module.md` (Accepted 2026-05-03)
- **Module rules**: `.claude/rules/modules.md`, `.claude/rules/actions.md`, `.claude/rules/migrations.md`
