# ADR-0009 — Settlement Module

- **Status:** Accepted
- **Date:** 2026-05-03
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-4-settlement
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/adr/0005-payments-module.md`](0005-payments-module.md) — Payments publishes `PaymentCaptured` + `RefundCompleted` events consumed here
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) FR-30 (settlement / wallet baseline)
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §11 (money as integer minor units)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §7 (Settlement — 6 tables)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) Phase 4.2

---

## 1. Context

The platform collects payments from customers and must distribute the vendor's share (gross minus platform commission) to each vendor after a successful booking payment. Phase 4.2 introduces the Settlement module, which owns the wallet, ledger, commission accrual, withdrawal request, and settlement reconciliation lifecycle. It is triggered exclusively via domain events (`PaymentCaptured`, `RefundCompleted`) published by the Payments module — no direct coupling between Settlement and Payments/Booking Eloquent models.

**Phase:** 4.2 (Wallets + Commissions + Withdrawals)
**Built in:** Week 5, Days 25–27

---

## 2. Module Ownership

### This module owns:

- `wallets` — polymorphic balance store (vendor wallets, platform wallet)
- `wallet_ledger` — append-only double-entry credit/debit records
- `commissions` — per-booking-item commission record (append-only, status-only updates)
- `commission_rates` — tiered rate lookup (category × product_type, most-specific-wins)
- `withdrawals` — vendor withdrawal requests (pending → approved → paid | rejected)
- `settlement_runs` — periodic reconciliation snapshots

### This module does NOT own:

- `bookings`, `booking_items`, `booking_vendors` — owned by Booking; accessed via `SettlementBookingReader` contract
- `payments`, `refunds` — owned by Payments; accessed via `SettlementPaymentReader` contract
- `vendor_profiles`, `users` — owned by Identity; referenced via FK only
- `categories` — owned by Catalog; `CommissionRate.category()` relation is an acceptable direct reference (categories are reference data, see §6.3)
- Bank transfer execution — manual in Phase 4.2; admin uploads proof via Media Library

---

## 3. Append-Only Carve-Outs

| Table | Rule |
|---|---|
| `wallet_ledger` | Fully immutable after insert. No `updated_at`. No soft-delete. Query-only. Balance is always derived by summing ledger rows. |
| `commissions` | Only `status` field may be updated (e.g., `calculated → partially_reversed → reversed`). No `updated_at`. No soft-delete. Amount fields are immutable post-insert. |

Any attempt to UPDATE `amount_minor`, `commission_minor`, `vendor_share_minor`, or any money column on `commissions` is an architecture violation. Reversals are handled by creating a new `wallet_ledger` debit row and updating the `commissions.status` field only.

---

## 4. No Auto-Payout in Phase 4.2

**Decision:** Phase 4.2 does NOT auto-transfer funds to vendor bank accounts. The flow is:

1. Vendor requests a withdrawal via the API.
2. Admin reviews and approves/rejects via Filament.
3. Admin performs the bank transfer manually.
4. Admin uploads proof (PDF/image) via the `bank_proof` media collection on the `Withdrawal` model.
5. Admin marks the withdrawal as `paid`.

Automatic payout via bank APIs (Vodafone Cash, bank direct debit) is deferred to Phase 2. The `Withdrawal` model and `WithdrawalStatus` enum are designed to accommodate automation in Phase 2 without schema changes.

---

## 5. Single-Pending-Per-Vendor DB Rule

MySQL 8 does not support partial unique indexes (WHERE clause on unique index). The workaround is an application-level `pending_lock` column:

```sql
-- withdrawals table
pending_lock BIGINT UNSIGNED NULL UNIQUE
```

Rules:
- When creating a new `pending` withdrawal, set `pending_lock = vendor_profile_id`.
- When resolving (approving, paying, or rejecting), set `pending_lock = NULL`.
- The `UNIQUE` constraint on `pending_lock` prevents two pending withdrawals for the same vendor simultaneously.
- `RequestWithdrawalAction` checks `EloquentWithdrawalRepository::hasPending()` before insert and throws `ExistingPendingWithdrawalException` if true (defense in depth).

---

## 6. Cross-Module Contracts

Settlement must never import Eloquent models from Booking or Payments. All cross-module reads go through contracts:

| Contract | Interface Owned By | Implemented By | Bound In |
|---|---|---|---|
| `SettlementBookingReader` | Settlement | `Booking\Infrastructure\Repositories\EloquentSettlementBookingReader` | `BookingServiceProvider::register()` |
| `SettlementPaymentReader` | Settlement | `Payments\Infrastructure\Repositories\EloquentSettlementPaymentReader` | `PaymentsServiceProvider::register()` |
| `CommissionRateResolver` | Settlement | `Settlement\Infrastructure\Repositories\EloquentCommissionRateResolver` | `SettlementServiceProvider::register()` |

### 6.1 SettlementBookingReader

```php
interface SettlementBookingReader {
    public function findItemById(int $id): ?BookingItemSnapshotDto;
    public function itemsForPayment(int $bookingId): array; // BookingItemSnapshotDto[]
}
```

Returns `BookingItemSnapshotDto` — a Settlement-owned DTO. Never returns Booking models.

### 6.2 SettlementPaymentReader

```php
interface SettlementPaymentReader {
    public function findById(int $id): ?PaymentSnapshotDto;
    public function findRefundById(int $id): ?RefundSnapshotDto;
}
```

### 6.3 Direct Category reference — allowed exception

`CommissionRate` has a `category()` relation that imports `App\Modules\Catalog\Domain\Models\Category`. This is an intentional exception:

- `categories` is reference/classification data, not transactional domain data.
- The FK is nullable — rates can exist without a category (global fallback).
- Commission rate resolution is Settlement-internal; no Catalog models are returned across the boundary.
- If categories ever become volatile or multi-shape, introduce a `SettlementCategoryReader` contract at that time.

---

## 7. Commission Rate Resolution — Most-Specific-Wins

Resolution order (descending specificity):

1. `(category_id = X, product_type = 'rental')` — most specific
2. `(category_id = X, product_type = NULL)` — any type within this category
3. `(category_id = NULL, product_type = 'rental')` — any category for this type
4. `(category_id = NULL, product_type = NULL)` — global default (always seeded at 15%)

The resolved `commission_bps` is snapshotted onto `booking_items.commission_bps` at booking time (Booking module responsibility). Settlement reads the snapshotted value, not the live rate.

---

## 8. Events

### Events we consume:

| Event | Source | Action |
|---|---|---|
| `Payments\Domain\Events\PaymentCaptured` | Payments | `HandlePaymentCapturedListener` → `CalculateAndCreditCommissionsAction` |
| `Payments\Domain\Events\RefundCompleted` | Payments | `HandleRefundCompletedListener` → `ReverseCommissionForRefundAction` |

### Events we publish:

| Event | When |
|---|---|
| `WalletCredited` | After ledger entry inserted, after commit |
| `WalletDebited` | After ledger entry inserted, after commit |
| `CommissionCalculated` | After commission row created, after commit |
| `CommissionReversed` | After commission reversed, after commit |
| `WithdrawalRequested` | After withdrawal row created, after commit |
| `WithdrawalPaid` | After admin marks withdrawal paid |
| `WithdrawalRejected` | After admin rejects withdrawal |

All events fire **after** `DB::transaction` commit using `DB::afterCommit()`.

---

## 9. Layer Layout

```
app/Modules/Settlement/
├── Domain/
│   ├── Models/          Wallet, WalletLedgerEntry, Commission, CommissionRate, Withdrawal, SettlementRun
│   ├── Enums/           LedgerEntryType, CommissionStatus, WithdrawalStatus, SettlementRunStatus
│   ├── Events/          WalletCredited, WalletDebited, CommissionCalculated, CommissionReversed,
│   │                    WithdrawalRequested, WithdrawalPaid, WithdrawalRejected
│   ├── Exceptions/      InsufficientWalletBalanceException, ExistingPendingWithdrawalException,
│   │                    WithdrawalBelowMinimumException
│   ├── ValueObjects/    BankAccountSnapshot
│   └── Contracts/       SettlementBookingReader, SettlementPaymentReader, CommissionRateResolver
├── Application/
│   ├── Actions/         CalculateAndCreditCommissionsAction, ReverseCommissionForRefundAction,
│   │                    RequestWithdrawalAction, ApproveWithdrawalAction, RejectWithdrawalAction,
│   │                    MarkWithdrawalPaidAction, CreditWalletAction, DebitWalletAction
│   ├── DTOs/            BookingItemSnapshotDto, PaymentSnapshotDto, RefundSnapshotDto,
│   │                    RequestWithdrawalDto
│   ├── Listeners/       HandlePaymentCapturedListener, HandleRefundCompletedListener
│   └── Services/        CommissionCalculatorService
├── Infrastructure/
│   └── Repositories/    EloquentWalletRepository, EloquentWithdrawalRepository,
│                        EloquentCommissionRepository, EloquentCommissionRateResolver
├── Http/
│   ├── Controllers/Vendor/  WithdrawalController
│   ├── Requests/            RequestWithdrawalRequest
│   └── Resources/           WalletResource, WalletLedgerEntryResource, WithdrawalResource
├── Filament/
│   └── Resources/       WithdrawalResource, CommissionResource, WalletResource
├── Routes/
│   └── vendor.php
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/         DefaultCommissionRatesSeeder
├── Resources/
│   └── lang/{en,ar}/    settlement.php
└── Providers/
    └── SettlementServiceProvider.php
```

---

## 10. Testing Strategy

**Pest groups:** `settlement`, `wallet`, `commission`, `withdrawal`

Required coverage:
- Happy path: `PaymentCaptured` → commission calculated → vendor wallet credited
- Happy path: `RefundCompleted` → commission reversed → vendor wallet debited
- `hasPending()` guard: second withdrawal request while pending → 422
- `pending_lock` uniqueness: concurrent inserts blocked at DB level
- Insufficient balance: withdrawal > balance → `InsufficientWalletBalanceException`
- Commission rate resolution: all 4 specificity levels
- All three product types on commission calculations

---

## 11. Cut-List (if behind schedule)

- Filament `SettlementRun` resource → defer to Phase 6 (Reporting)
- `SettlementRunStatus::Disputed` flow → Phase 2
- PDF bank transfer receipt generation → Phase 2
- Per-currency wallet support (multi-currency) → Phase 2 (EGP-only in Phase 1)
