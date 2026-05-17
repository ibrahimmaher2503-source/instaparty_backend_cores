# ADR-0028 — Financial Ledger Hardening

- **Status:** Accepted
- **Date:** 2026-05-15
- **Decision-makers:** Ibrahim
- **Tags:** settlement, payments, ledger, phase-4.9-financial-hardening
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/adr/0009-settlement-module.md`](0009-settlement-module.md) — Settlement module baseline (this ADR extends it)
  - [`docs/adr/0005-payments-module.md`](0005-payments-module.md) — Payments module affected by idempotency changes
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §7 FR-EXT-101–FR-EXT-130
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §11 (money as integer minor units)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §7 (Settlement tables)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) Phase 4.9
  - [`specs/028-financial-ledger-hardening/research.md`](../../specs/028-financial-ledger-hardening/research.md) — 15 resolved decisions (primary source)

---

## 1. السياق / Context

**EN:** The existing Settlement module stores wallet balances via direct `INCREMENT`/`DECREMENT` operations on `wallets.balance_minor`, with `wallet_ledger` as an optional audit trail rather than the source of truth. This creates a dual source-of-truth problem: balance drift is undetectable at runtime, concurrent mutations can race, and refund accounting bypasses the ledger entirely. Phase 4.9 hardens the financial architecture into a genuine append-only double-entry ledger where `wallet_ledger` is the sole source of truth and `wallets.balance_minor` becomes a projection cache derived from it. It also closes the idempotency surface at both the HTTP and internal Action boundaries, adds layered concurrency locks (Redis + DB row lock), and introduces a scheduled reconciliation engine that detects and repairs drift.

**AR:** النظام الحالي يحدّث رصيد المحفظة (`wallets.balance_minor`) مباشرة عبر `INCREMENT`/`DECREMENT`، مما يجعل `wallet_ledger` سجلاً اختيارياً وليس مصدر الحقيقة. هذا يخلق تضارباً في مصادر البيانات: لا يمكن اكتشاف الانجراف في وقت التشغيل، والعمليات المتزامنة تتسابق، ومحاسبة المبالغ المستردة تتجاوز السجل بالكامل. Phase 4.9 يُصلّب البنية المالية إلى سجل مزدوج القيد ومضاف إليه فقط، مع إضافة قفل Redis + قفل صف قاعدة بيانات، ومحرك تسوية مجدول.

**Phase:** 4.9 (Financial Ledger Hardening)
**Built in:** Week 7–8 (post Phase 4.2 Settlement baseline)

---

## 2. Core Decisions

### 2.1 Ledger as sole source of truth (research.md §1)

`wallet_ledger` is the canonical financial record. `wallets.balance_minor` and `wallets.pending_withdrawal_minor` are **projection caches** — computed by `ProjectWalletBalanceAction` from the ledger and updated atomically inside the same DB transaction as every ledger write. No code path outside `ProjectWalletBalanceAction` may mutate the balance columns; enforced by architecture tests.

**Rejected alternative**: Keep the dual write (direct balance update + ledger row). Rejected because the two can drift, drift is invisible until reconciliation, and it contradicts the constitution's append-only principle.

### 2.2 Ledger entry shape: direction + unsigned magnitude (research.md §1)

`wallet_ledger.amount_minor` becomes `BIGINT UNSIGNED` (magnitude), paired with a non-nullable `direction ENUM('debit','credit')`. The running signed balance lives in `running_balance_minor BIGINT` (computed per entry by the writer). This aligns with Constitution §III and eliminates sign-flip ambiguity in SQL aggregations.

**Backfill**: Existing signed rows are backfilled by the `ledger:backfill` command: `direction = amount_minor > 0 ? 'credit' : 'debit'`, `amount_minor = ABS(amount_minor)`. Backfill bypasses the append-only trigger via `SET @ledger_backfill_in_progress = 1` (session variable, not global).

### 2.3 Double-entry chart of accounts (research.md §2)

Every transaction group is balanced: `SUM(debit entries) = SUM(credit entries)`. Platform-side legs are booked to one of seven **suspense accounts** materialised as `wallets` rows with `owner_type='platform_account'`:

| `SuspenseAccount` case | Purpose |
|---|---|
| `gateway_in_transit` | Funds in flight between customer card and platform |
| `platform_clearing` | Captured funds pending allocation |
| `platform_commission_receivable` | Commission earned but refund-window not closed |
| `platform_commission_realised` | Commission realised after refund window |
| `platform_refund_payable` | Refund liability owed to customer |
| `platform_withdrawal_payable` | Withdrawal liability owed to vendor |
| `platform_adjustments` | Admin manual adjustments |

**Rejected alternatives**: Single "platform" suspense (cannot distinguish liability types); full general-ledger admin UI (Phase 2 scope).

### 2.4 Balanced-entry enforcement: belt-and-suspenders (research.md §3)

1. **Application invariant**: `PostLedgerTransactionAction` validates `Σdebit = Σcredit` before inserting, throwing `UnbalancedTransactionException` if violated.
2. **Database CHECK constraint**: `ledger_transaction_groups` carries `total_debits_minor` and `total_credits_minor` with `CHECK (total_debits_minor = total_credits_minor)`.
3. **AFTER INSERT trigger** on `wallet_ledger` increments the aggregate totals on the group row; an imbalanced final state causes the MySQL constraint to fire.

### 2.5 Idempotency surface extended to internal Actions (research.md §4)

The existing `idempotency_keys` table gains `scope ENUM('http','internal')` and `ttl_seconds INT UNSIGNED`. All money-mutating Actions call `IdempotencyService::remember(scope, key, payload, callable)`. Canonical internal key patterns:
- Webhook: `paymob:{event_type}:{order_id}:{transaction_id}:{success}`
- Capture: `capture:{payment_id}`
- Refund: `refund:{refund_id}`
- Commission accrual: `comm:{booking_item_id}`
- Commission reversal: `comm_rev:{commission_id}:{refund_id}`
- Withdrawal reserve: `wd_reserve:{withdrawal_id}`
- Withdrawal settle: `wd_settle:{withdrawal_id}`
- Withdrawal reject: `wd_reject:{withdrawal_id}`

### 2.6 Layered concurrency locks (research.md §5)

Every money-mutating Action acquires:
1. **Redis lock** first (outer, 30s TTL via `Cache::lock()`) — kills duplicate runners before they touch the DB.
2. **DB `BEGIN`**.
3. **`SELECT … FOR UPDATE`** on the `wallets` row (inner, prevents DB-level races).
4. Business logic + ledger write + projection update.
5. **COMMIT**.
6. Redis lock released in `finally`.

Multi-wallet operations use `WalletLocker::tryAcquireMany()` which acquires locks in ascending `wallet_id` order to prevent deadlock.

### 2.7 Append-only trigger (research.md §6)

A `BEFORE UPDATE` / `BEFORE DELETE` trigger on `wallet_ledger`, `ledger_transaction_groups`, and `financial_snapshots` signals `SQLSTATE '45000'` with message `"wallet_ledger is append-only"` on any mutation attempt. Bypass mechanism: `SET @ledger_backfill_in_progress = 1` (session-scoped, not global — affects only the backfill connection, invisible to concurrent transactions).

### 2.8 Reconciliation scheduling (research.md §7)

- `reconcile:run --scope=recent_touch --window=60min` → every hour, `withoutOverlapping`, `onOneServer`.
- `reconcile:run --scope=all` → daily at 04:00 Cairo, `withoutOverlapping`, `onOneServer`.
- Concurrent triggers with identical scope are serialised by a Redis lock on the scope hash.

### 2.9 Financial snapshots (research.md §8)

`ledger:snapshot --all` → daily at 04:30 Cairo. Each snapshot anchors `balance_minor` + `pending_withdrawal_minor` to a specific `wallet_ledger.id`, enabling O(entries-since-snapshot) balance reconstruction instead of full table scan.

### 2.10 Causal chain propagation (research.md §9)

`correlation_id` (ULID, one per HTTP request / webhook) and `causation_id` (ULID of the triggering event) propagate through Laravel 12 `Context` set by `StartCausalChain` middleware. Both are stamped onto every `wallet_ledger` row, `ledger_transaction_groups` row, and `payments` row.

### 2.11 Three-stage rollout (research.md §11)

1. **Shadow writes** (1 week): both old and new paths run inside the same transaction; `ledger:diff` runs daily to confirm zero drift before cut-over.
2. **Cut-over** (feature flag `financial_ledger_hardening_v2 = true`): old direct-balance paths removed; 24h rollback window.
3. **Cleanup** (30 days after cut-over): deprecated `incrementBalance`/`decrementBalance` methods removed from `EloquentWalletRepository`.

---

## 3. Tables Owned / Modified

| Table | Change | Append-only? |
|---|---|---|
| `wallets` | ADD `last_ledger_entry_id`, `last_projected_at` | No (projection cache updated) |
| `wallet_ledger` | ADD `direction`, `running_balance_minor`, `transaction_group_id`, `counter_account_*`, `correlation_id`, `causation_id`, `idempotency_key`, `posted_at`; CHANGE `amount_minor` to UNSIGNED | Yes |
| `ledger_transaction_groups` | **NEW TABLE** | Yes |
| `withdrawals` | ADD `idempotency_key`, `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id` | Partial (status updates still allowed) |
| `commissions` | ADD `accrual_ledger_entry_id`, `reversal_ledger_entry_id`, `idempotency_key` | Partial |
| `payments` | ADD `correlation_id`, `capture_ledger_group_id` | Partial |
| `refunds` | ADD `ledger_group_id`, `idempotency_key` | Partial |
| `idempotency_keys` | ADD `scope`, `ttl_seconds`, `payload_hash` | No (24h TTL rows expire) |
| `financial_snapshots` | **NEW TABLE** | Yes |
| `reconciliation_runs` | **NEW TABLE** | Partial (status updated) |
| `reconciliation_findings` | **NEW TABLE** | Partial (resolution updated) |

---

## 4. Module Type-Awareness

**Cross-type** — Settlement and Payments are financial infrastructure. The ledger, idempotency, and reconciliation layers operate identically regardless of the product type of the underlying booking. Cross-type behaviour is verified by `RentalSaleDigitalRefundParityTest` (T101), which asserts identical ledger shape for all three product types.

---

## 5. Layer Layout Changes

```
app/Modules/Settlement/
├── Domain/
│   ├── Enums/          # NEW: LedgerDirection, TransactionKind, SuspenseAccount,
│   │                   #      ReconciliationStatus, ReconciliationFindingType,
│   │                   #      ReconciliationFindingSeverity; EXTENDED: LedgerEntryType
│   ├── Contracts/      # NEW: LedgerWriter, WalletProjector, WalletLocker,
│   │                   #      WalletLockHandle, ReconciliationDetector
│   ├── Models/         # NEW: LedgerTransactionGroup, FinancialSnapshot,
│   │                   #      ReconciliationRun, ReconciliationFinding
│   │                   # UPDATED: Wallet, WalletLedgerEntry, Withdrawal, Commission
│   └── Exceptions/     # NEW: 6 domain exceptions
├── Application/
│   ├── Actions/        # NEW: PostLedgerTransactionAction, ProjectWalletBalanceAction,
│   │                   #      ReconcileWalletAction, RunReconciliationAction,
│   │                   #      TakeFinancialSnapshotAction
│   │                   # REFACTORED: CreditWalletAction, DebitWalletAction,
│   │                   #             CalculateCommissionAction, ReverseCommissionAction,
│   │                   #             RequestWithdrawalAction, ApproveAndMarkWithdrawalPaidAction,
│   │                   #             RejectWithdrawalAction, SettlementRunAction
│   ├── DTOs/           # NEW: PostLedgerTransactionInput, LedgerEntryInput,
│   │                   #      LedgerTransactionResult, WalletProjectionResult,
│   │                   #      RunReconciliationInput, ReconciliationRunResult, DetectedFinding
│   └── Listeners/      # NEW: DispatchReconciliationNotificationListener
├── Infrastructure/
│   ├── Repositories/   # NEW: EloquentLedgerRepository, EloquentSnapshotRepository,
│   │                   #      EloquentReconciliationRepository, EloquentReconciliationDetector
│   │                   # REFACTORED: EloquentWalletRepository (balance methods deprecated)
│   └── Locks/          # NEW: RedisWalletLocker
├── Console/Commands/   # NEW: BackfillLedgerColumnsCommand, LedgerDiffCommand,
│                       #      LedgerInventoryCommand, LedgerSnapshotCommand,
│                       #      ReconcileRunCommand
├── Http/
│   ├── Controllers/    # NEW: ReconciliationAdminController
│   ├── Requests/       # NEW: TriggerReconciliationRequest
│   └── Resources/      # NEW: ReconciliationStatusResource, TriggerReconciliationResource
├── Filament/Resources/ # NEW: ReconciliationRunResource, ReconciliationFindingResource
└── Database/
    ├── Migrations/     # 12 new migrations (T008–T019)
    └── Seeders/        # NEW: FinancialSuspenseAccountsSeeder

app/Modules/Payments/
├── Domain/Models/      # UPDATED: Payment (correlation_id, capture_ledger_group_id),
│                       #          Refund (ledger_group_id, idempotency_key)
├── Application/Actions/ # REFACTORED: CapturePaymentAction, ProcessPaymobWebhookAction,
│                        #             ReplayWebhookAction, InitiateRefundAction, ProcessRefundAction
└── Database/Migrations/ # 3 new migrations (T013–T015)

app/Modules/Shared/
├── Application/
│   ├── Concerns/       # NEW: ThreadsCausalChain trait
│   └── Services/       # REFACTORED: IdempotencyService (scope + ttl_seconds + payload_hash)
└── Http/Middleware/    # NEW: StartCausalChain
```

---

## 6. Events Published

| Event | When | Payload |
|---|---|---|
| `Settlement\LedgerTransactionPosted` | After every ledger group commit | `groupId`, `groupPublicId`, `kind`, `correlationId`, `affectedWalletIds` |
| `Settlement\ReconciliationFindingRaised` | When `severity=high` finding inserted | `findingId`, `findingType`, `resourceType`, `resourceId`, `delta` |
| `Settlement\ReconciliationRunCompleted` | After each reconciliation run | `runId`, `status`, `findingsCount` |

---

## 7. Admin Endpoints

| Method | Path | Auth | Idempotency |
|---|---|---|---|
| `GET` | `/admin/reconciliation/status` | admin role + `audit.view` | none |
| `POST` | `/admin/reconciliation/trigger` | admin role + `audit.view` | required |

---

## 8. Success Criteria

SC-001 through SC-012 as defined in `specs/028-financial-ledger-hardening/spec.md` §"Success Criteria". Abbreviated:
- SC-001: No direct balance writes outside the projection layer (architecture test)
- SC-002: `ledger:diff` reports zero drift (live check)
- SC-003: 100 duplicate webhooks → exactly 1 capture group
- SC-004: 50 concurrent withdrawal requests on a wallet with 1× balance → exactly 1 succeeds
- SC-005: Partial refund sum cannot exceed captured total
- SC-006: Reconciliation of full DB completes in under 5 minutes
- SC-007: Drift is detected and auto-repaired within one reconciliation cycle
- SC-008: All money-mutating Actions are idempotent
- SC-009: Every ledger entry carries `correlation_id` + `causation_id`
- SC-010: Settlement run can resume after a crash with no double-booking
- SC-011: Admin can trace any wallet entry to its originating HTTP request in 3 clicks
- SC-012: CI pipeline fails if a direct balance write is introduced

---

## 9. Testing Strategy

**Pest groups:** `ledger`, `reconciliation`, `concurrency`, `idempotency`, `settlement`, `payments`

**Key test files:**
```
tests/
├── Feature/Modules/Settlement/
│   ├── LedgerWriter/              # BalancedTransaction, Unbalanced, CurrencyMismatch, Idempotency, ConcurrentCredit
│   ├── CreditWallet/              # DelegatesToLedgerWriter
│   ├── DebitWallet/               # DelegatesToLedgerWriter
│   ├── Commission/                # AccrualUsesLedger, ReversalUsesLedger
│   ├── Withdrawals/               # ConcurrentBlocksOverdraw, PartialReserve, ApprovalSettles, RejectionUnreserves
│   └── Reconciliation/            # RunDetectsDrift, AutoRepair, HighSeverityNotifies, ConcurrentRunSerialised
├── Feature/Modules/Payments/
│   ├── Webhook/                   # DuplicateProducesOneLedgerGroup, ConcurrentDuplicates, LateDuplicate, ConflictReturns409
│   └── Refunds/                   # PartialExhaustedCapture, OverRefundRejected, CommissionReversal, DuplicateCallback, RentalSaleDigitalParity
├── Unit/Modules/Settlement/
│   ├── LedgerProjectorTest.php
│   ├── SuspenseAccountBalancesTest.php
│   ├── RedisWalletLockerTest.php
│   └── CausalChainTraversalTest.php
└── Architecture/
    ├── NoDirectWalletBalanceWritesTest.php
    ├── LedgerEntryHasRequiredColumnsTest.php
    └── AppendOnlyTablesHaveNoSoftDeletesTest.php
```

---

## 10. Cut-List (if behind schedule)

- [ ] `ledger:snapshot` daily job → defer to Phase 5 (reconciliation still works from raw ledger, slower)
- [ ] `ReconciliationFindingResource` Filament detail view → defer; `ReconciliationRunResource` is enough for Phase 4.9
- [ ] `CausalChainTraversalTest` unit test → defer (tracing still works, just untested at unit level)
- [ ] Arabic translations for reconciliation screens → defer 48h (EN must be complete; AR is polish)
- [ ] `SettlementRunAction` crash-resume (US7) → defer to Phase 5 if US1–US4 are the MVP

---

## 11. Open Questions

None. All 15 research decisions are resolved in `specs/028-financial-ledger-hardening/research.md`.

---

## 12. Implementation Checklist

- [ ] 12 migrations in `Settlement/Database/Migrations/` + 3 in `Payments/Database/Migrations/` (T008–T019)
- [ ] Enums, contracts, DTOs, exceptions (T020–T034)
- [ ] Domain models (T035–T044)
- [ ] Repositories + Redis locker (T045–T049)
- [ ] Shared trait + middleware (T050)
- [ ] Suspense seeder (T051)
- [ ] Service provider bindings (T052)
- [ ] Artisan commands: backfill, diff, inventory (T053–T055)
- [ ] Architecture tests (T056–T058)
- [ ] US1–US8 implementation + tests (T059–T163)
- [ ] `php artisan shield:generate --all` after Filament resources
- [ ] Translations in `Resources/lang/en/` and `Resources/lang/ar/`
- [ ] `php artisan ledger:diff` reports zero drift on fresh DB
- [ ] All 12 success criteria verified locally (quickstart.md)
