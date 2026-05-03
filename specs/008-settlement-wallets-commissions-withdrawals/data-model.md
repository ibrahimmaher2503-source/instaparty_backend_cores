# Data Model: Settlement — Wallets, Commissions, Withdrawals

**Feature**: 008-settlement-wallets-commissions-withdrawals | **Phase**: Plan / Phase 1 | **Date**: 2026-05-03

Six entities. Schema verified against `docs/specs/11_DB_Schema.md` lines 925–1015.

---

## Entity Map

```text
Wallet (1)─────────(many) WalletLedgerEntry          [append-only ledger]
   │
   │ (morphTo owner)
   ▼
VendorProfile  ────(many)── Withdrawal               [pending → paid | rejected]
   │
   ▼
Commission ────(belongs to)── BookingItem (cross-module via SettlementBookingReader)
   │
   ▼
CommissionRate (resolved via 4-level fallback)

SettlementRun (standalone — no rows in Phase 4.2)
```

---

## 1. Wallet

**Table**: `wallets` | **Soft-deletes**: ❌ | **Append-only**: ❌ (cached `balance_minor` updated)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `owner_type` | VARCHAR(50) NOT NULL | morph type — typically `'vendor_profile'` |
| `owner_id` | BIGINT UNSIGNED NOT NULL | morph id |
| `currency` | CHAR(3) NOT NULL | ISO-4217; phase-1 only `'EGP'` |
| `balance_minor` | BIGINT NOT NULL DEFAULT 0 | cached sum of ledger; can be negative |
| `pending_withdrawal_minor` | BIGINT NOT NULL DEFAULT 0 | reserved for in-flight pending; ≥ 0 |
| `created_at`, `updated_at` | TIMESTAMP | |

**Indexes**: UNIQUE `(owner_type, owner_id, currency)`; INDEX `(owner_type, owner_id)`
**Relationships**: `morphTo` owner; `hasMany` `WalletLedgerEntry`
**Invariant**: `balance_minor == SUM(wallet_ledger.amount_minor WHERE wallet_id = id)` — Pest test verifies after every operation
**Validation**: `currency` MUST match booking currency at credit time (lazy-create another wallet if mismatch); `balance_minor` may be negative; `pending_withdrawal_minor` MUST be ≥ 0

---

## 2. WalletLedgerEntry

**Table**: `wallet_ledger` | **Soft-deletes**: ❌ | **Append-only**: ✅ (no `updated_at`, no UPDATE, no DELETE)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | no `public_id` (internal table) |
| `wallet_id` | BIGINT UNSIGNED FK → wallets.id (cascade) | parent wallet |
| `entry_type` | ENUM('commission_credit','refund_debit','withdrawal_debit','manual_adjustment') NOT NULL | |
| `amount_minor` | BIGINT NOT NULL | signed (positive=credit, negative=debit) |
| `currency` | CHAR(3) NOT NULL | denorm from wallet |
| `description_key` | VARCHAR(100) NULL | i18n key (e.g., `'settlement.ledger.commission_credit'`) |
| `description_params` | JSON NULL | params for i18n key |
| `related_entity_type` | VARCHAR(50) NULL | morph type — `'commission'`, `'withdrawal'`, `'refund'` |
| `related_entity_id` | BIGINT UNSIGNED NULL | morph id |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | only timestamp |

**Indexes**:
- `(wallet_id, created_at DESC)` — pagination newest-first
- UNIQUE `(related_entity_type, related_entity_id, entry_type)` — refund-reversal idempotency

**Relationships**: `belongsTo` `Wallet`; `morphTo` related entity
**Invariants**: Sum equals `wallets.balance_minor`; once inserted, never updated/deleted

---

## 3. Commission

**Table**: `commissions` | **Soft-deletes**: ❌ | **Append-only**: ✅ except `status` (per §15 carve-out)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `booking_item_id` | BIGINT UNSIGNED FK → booking_items.id (restrict) | source |
| `payment_id` | BIGINT UNSIGNED FK → payments.id (restrict) | originating payment |
| `vendor_profile_id` | BIGINT UNSIGNED FK → vendor_profiles.id (restrict) | denorm |
| `category_id` | BIGINT UNSIGNED FK → categories.id (restrict) NULL | snapshot |
| `product_type` | ENUM('rental','sale','digital') NOT NULL | snapshot |
| `gross_amount_minor` | BIGINT NOT NULL | total of the booking item |
| `commission_bps` | INT NOT NULL | snapshotted basis points (1500 = 15%) |
| `commission_minor` | BIGINT NOT NULL | platform's share |
| `vendor_share_minor` | BIGINT NOT NULL | gross − commission |
| `currency` | CHAR(3) NOT NULL | denorm |
| `reversed_amount_minor` | BIGINT NOT NULL DEFAULT 0 | for partial reversals |
| `status` | ENUM('calculated','partially_reversed','reversed') NOT NULL DEFAULT 'calculated' | only updatable column |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | (no `updated_at`) |

**Indexes**:
- UNIQUE `booking_item_id` — one commission per booking item (idempotent against retries)
- INDEX `(vendor_profile_id, status, created_at)` — vendor commission report
- INDEX `(payment_id)` — refund reversal lookup

**Relationships** (all cross-module via contracts — no Eloquent imports):
- `BookingItem` via `SettlementBookingReader`
- `Payment` via `SettlementPaymentReader`
- `VendorProfile` via `SettlementBookingReader.bookingItem.vendorProfile`

**State transitions**:
- `calculated` → `partially_reversed` (first partial refund)
- `calculated` → `reversed` (full refund)
- `partially_reversed` → `reversed` (subsequent refund completes reversal)
- No transition out of `reversed` (terminal)

---

## 4. CommissionRate

**Table**: `commission_rates` | **Soft-deletes**: ❌ | **Append-only**: ❌ (admin edits)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `category_id` | BIGINT UNSIGNED FK → categories.id (restrict) NULL | NULL = wildcard |
| `product_type` | ENUM('rental','sale','digital') NULL | NULL = wildcard |
| `commission_bps` | INT NOT NULL | basis points |
| `effective_from` | DATE NOT NULL DEFAULT CURRENT_DATE | future-dating support (Phase 1.5) |
| `created_at`, `updated_at` | TIMESTAMP | |

**Indexes**:
- UNIQUE on `(category_id, product_type)` (NULL-safe via derived `IFNULL` columns or MariaDB's `UNIQUE INDEX ... USING HASH`)
- INDEX `(category_id, product_type, effective_from)` — fallback resolution

**Relationships**: `belongsTo` `Category` (Catalog reference table — direct model access acceptable per ADR-0009 since Categories are reference data)
**Validation**: `commission_bps` between 0 and 10000 (0%–100%); both `category_id` and `product_type` MAY be NULL
**Seed data** (`DefaultCommissionRatesSeeder`): one row `(category_id=NULL, product_type=NULL, commission_bps=1500)` — platform default 15%

**Fallback resolution order** (most-specific first):
1. `(category_id × product_type)` — exact match
2. `(category_id × NULL)` — any type within category
3. `(NULL × product_type)` — any category for type
4. `(NULL × NULL)` — global default
5. None matched → use 0 bps + log warning

---

## 5. Withdrawal

**Table**: `withdrawals` | **Soft-deletes**: ❌ | **Append-only**: ❌ (`status` column updates per §15 carve-out)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `vendor_profile_id` | BIGINT UNSIGNED FK → vendor_profiles.id (restrict) | requesting vendor |
| `requested_amount_minor` | BIGINT NOT NULL | always positive |
| `paid_amount_minor` | BIGINT NULL | populated on `paid` |
| `currency` | CHAR(3) NOT NULL | denorm |
| `bank_account_snapshot` | JSON NOT NULL | `{account_holder, iban, bank_name, swift_bic}` |
| `status` | ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending' | only updatable besides timestamps |
| `rejected_reason` | JSON NULL | translatable `{en, ar}` |
| `requested_by_user_id` | BIGINT UNSIGNED FK → users.id (restrict) | which vendor user clicked |
| `processed_by_user_id` | BIGINT UNSIGNED FK → users.id (restrict) NULL | admin who approved/rejected |
| `bank_proof_media_id` | BIGINT UNSIGNED FK → media.id (restrict) NULL | Spatie Media Library |
| `requested_at` | TIMESTAMP | |
| `processed_at` | TIMESTAMP NULL | when status moved out of `pending` |
| `paid_at` | TIMESTAMP NULL | populated on `paid` |
| `created_at`, `updated_at` | TIMESTAMP | |

**Indexes**:
- UNIQUE PARTIAL `(vendor_profile_id) WHERE status = 'pending'` — single-pending rule (DB-level)
- INDEX `(status, created_at)` — admin queue
- INDEX `(vendor_profile_id, status, created_at DESC)` — vendor history
- INDEX `(processed_by_user_id, processed_at)` — admin activity

**Relationships**: `belongsTo` `VendorProfile`, `User` (×2); `morphMany` Spatie Media (`bank_proof` collection)

**State transitions** (audit-logged):
- `pending` → `paid` (Phase 4.2 default — combined approve+pay action)
- `pending` → `rejected` (admin rejects with reason)
- `approved` → `paid` (Phase 1.5 if intermediate state introduced)
- `approved` → `rejected` (Phase 1.5)

**Validation** (Form Request):
- `requested_amount_minor` ≥ minimum (default 100 EGP from `app_settings`)
- `requested_amount_minor` ≤ vendor's `balance_minor − pending_withdrawal_minor`
- Single-pending enforced at DB layer; 422 surfaces as `existing_pending_withdrawal`
- `bank_account_snapshot` must include all 4 keys; `iban` validated via ISO 13616 mod-97 checksum

---

## 6. SettlementRun

**Table**: `settlement_runs` | **Soft-deletes**: ❌ | **Append-only**: ❌
**Phase 4.2 scope**: **table created, no rows written** (cut-list — auto-reconciliation deferred)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `period_start` | DATE NOT NULL | run period begin |
| `period_end` | DATE NOT NULL | run period end |
| `total_gross_minor` | BIGINT NOT NULL DEFAULT 0 | sum of payments captured in period |
| `total_commission_minor` | BIGINT NOT NULL DEFAULT 0 | sum of platform commissions |
| `total_vendor_share_minor` | BIGINT NOT NULL DEFAULT 0 | sum of vendor shares |
| `currency` | CHAR(3) NOT NULL | |
| `status` | ENUM('pending','reconciled','disputed') NOT NULL DEFAULT 'pending' | |
| `created_at`, `updated_at` | TIMESTAMP | |

**Indexes**: `(period_start, period_end)`; `(status, created_at)`
**Note**: No Action, no Listener, no API, no Filament resource in Phase 4.2.

---

## Cross-module DTOs (in `Application/DTOs/`)

These DTOs are returned by the Booking/Payments reader contracts so Settlement never touches Eloquent models from other modules.

### `BookingItemSnapshotDto`
```php
final readonly class BookingItemSnapshotDto {
    public function __construct(
        public int $id,
        public string $public_id,
        public int $booking_id,
        public int $vendor_profile_id,
        public int $category_id,
        public ProductType $product_type,
        public int $total_minor,
        public string $total_currency,
        public ?int $commission_bps,  // snapshot from booking confirmation; null for legacy
    ) {}
}
```

### `PaymentSnapshotDto`
```php
final readonly class PaymentSnapshotDto {
    public function __construct(
        public int $id,
        public string $public_id,
        public int $booking_id,
        public int $amount_minor,
        public string $currency,
        public PaymentStatus $status,
        public Carbon $captured_at,
    ) {}
}
```

### `RefundSnapshotDto`
```php
final readonly class RefundSnapshotDto {
    public function __construct(
        public int $id,
        public string $public_id,
        public int $payment_id,
        public ?int $booking_item_id,  // null = full-payment; non-null = single-item
        public int $amount_minor,
        public string $currency,
        public Carbon $completed_at,
    ) {}
}
```

---

## Actions (in `Application/Actions/`)

### CalculateCommissionAction
Inputs: `BookingItemSnapshotDto`, `PaymentSnapshotDto` → resolves rate via snapshot or resolver → creates `Commission` row + calls `CreditWalletAction` → fires `CommissionCalculated` after commit.

### CreditWalletAction
Inputs: vendor_profile_id, amount_minor, currency, entry_type, related entity → lazy-creates `Wallet` if needed → inserts `WalletLedgerEntry` → updates cached `wallets.balance_minor` in same transaction → fires `WalletCredited` after commit.

### DebitWalletAction
Mirror of `CreditWalletAction` for debits.

### ReverseCommissionAction
Inputs: `Commission`, `RefundSnapshotDto` → computes proportional reversal → updates `commissions.status` + `reversed_amount_minor` → calls `DebitWalletAction` for the proportional vendor share → fires `CommissionReversed` after commit.

### RequestWithdrawalAction
Inputs: `RequestWithdrawalDto` → validates balance + minimum + single-pending → inserts `Withdrawal` row with `status=pending` → updates `wallets.pending_withdrawal_minor` → fires `WithdrawalRequested` after commit.

### ApproveAndMarkWithdrawalPaidAction (admin)
Inputs: `Withdrawal`, bank-proof file, admin user → uploads file via Spatie Media Library → updates `withdrawals.status=paid`, `paid_at`, `bank_proof_media_id` → calls `DebitWalletAction` for `withdrawal_debit` → updates `wallets.pending_withdrawal_minor` (subtracts the amount) → fires `WithdrawalPaid` after commit.

### RejectWithdrawalAction (admin)
Inputs: `Withdrawal`, rejection_reason JSON, admin user → updates `withdrawals.status=rejected`, `rejected_reason`, `processed_at`, `processed_by_user_id` → updates `wallets.pending_withdrawal_minor` (releases the reservation) → fires `WithdrawalRejected` after commit.

---

## Test Plan Summary

| Test file | Coverage |
|---|---|
| `tests/Feature/Modules/Settlement/WalletViewTest.php` | US2 — happy + auth + authz + locale (EN+AR) |
| `tests/Feature/Modules/Settlement/WalletLedgerTest.php` | US2 — pagination, ordering, locale-resolved descriptions |
| `tests/Feature/Modules/Settlement/RequestWithdrawalTest.php` | US3 — happy + insufficient balance + below minimum + existing pending + Idempotency-Key |
| `tests/Feature/Modules/Settlement/ListWithdrawalsTest.php` | US3 — pagination, status filter, vendor isolation |
| `tests/Feature/Modules/Settlement/AdminApproveWithdrawalTest.php` | US4 — approve+mark paid via Filament action; bank proof upload; ledger debit appears |
| `tests/Feature/Modules/Settlement/AdminRejectWithdrawalTest.php` | US4 — reject with EN+AR reason; pending reservation released |
| `tests/Feature/Modules/Settlement/CommissionCalculationTest.php` | US1 — commission credit on PaymentCaptured; all 3 product types |
| `tests/Feature/Modules/Settlement/CommissionFallbackTest.php` | US1 — all 4 fallback levels resolve correctly |
| `tests/Feature/Modules/Settlement/RefundReversalTest.php` | US5 — full + partial reversal; idempotency on duplicate event |
| `tests/Feature/Modules/Settlement/CommissionRulesAdminTest.php` | US6 — admin CRUD on commission_rates via Filament |
| `tests/Unit/Modules/Settlement/EloquentCommissionRateResolverTest.php` | Unit — 4-level fallback isolated |
| `tests/Unit/Modules/Settlement/ProportionalReversalMathTest.php` | Unit — Brick\Money HALF_EVEN rounding correctness |
| `tests/Unit/Modules/Settlement/WalletBalanceInvariantTest.php` | Unit — balance equals sum of ledger after every operation |
| `tests/Architecture/SettlementModuleNoCrossImportTest.php` | New arch test — Settlement does not import Booking/Payments models |

Total: 13 test files (10 Feature + 3 Unit + 1 Arch).
