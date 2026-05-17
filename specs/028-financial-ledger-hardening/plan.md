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
- FR traceability: cite specific FR numbers from 01_PRD.md or define FR-EXT-NNN locals.
- Schema traceability: cite existing tables from 11_DB_Schema.md or flag NEW TABLE / NEW COLUMN.
- Phase alignment: cite Phase ID from 09_Phasing_Plan.md or propose a Phase 1.X extension.
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack
---

# Implementation Plan: Financial Ledger Hardening

**Branch**: `028-financial-ledger-hardening` | **Date**: 2026-05-15 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/028-financial-ledger-hardening/spec.md`

## Summary

Refactor the marketplace's six financial modules — Payments, Settlement (Wallets + Commissions + Withdrawals + Settlement Runs), and Refunds — so that every money movement is recorded as a balanced, append-only ledger transaction. Stored wallet balances become a verifiable projection cache of the ledger, never a parallel source of truth. Every money-mutating Action (Capture, Refund, Credit, Debit, Withdrawal Request/Approve/Reject, Commission Calculate/Reverse, Settlement Run) is reshaped to: (a) acquire a wallet-scoped lock, (b) write one balanced `ledger_transaction_group` with its child `wallet_ledger` rows, (c) project the balance cache inside the same DB transaction, (d) honour an explicit idempotency key, and (e) thread correlation/causation IDs from the originating trigger. A reconciliation command and reconciliation runs/findings tables provide an explicit "prove it" pass on schedule and on demand. Architecture tests, a MySQL trigger that rejects UPDATE/DELETE on `wallet_ledger`, and CI guardrails make regressions impossible.

The approach is **additive at the DB layer** (new tables + new columns; no column drops in Phase 1.X) so the rollout can ship behind a feature flag, back-populate historical data, and cut over per-Action once parity is proven.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12 (per `02_Tech_Decisions.md` + Constitution §"Locked Tech Stack")
**Primary Dependencies**: `brick/money`, `spatie/laravel-model-states`, `spatie/laravel-permission`, `predis/predis` (for Redis locks), `pxlrbt/filament-excel` (existing reconciliation export). No new packages required — all primitives exist in `docs/specs/10_Package_List.md`.
**Storage**: MySQL 8 / MariaDB 11 (`utf8mb4_unicode_ci`), Redis 7 for distributed locks and queues.
**Testing**: Pest (`./vendor/bin/pest`), with `parallel` and `--group` markers for `concurrency`, `idempotency`, `reconciliation`. Pest stress harness via `pestphp/pest-plugin-stressless` is **not** required and **not** in the package list — we use a hand-rolled parallel-process test helper that fork-spawns artisan tinker workers, which is sufficient and adds no dependency.
**Target Platform**: Linux server (Laravel queue workers + scheduler); Filament admin panel for ops.
**Project Type**: Modular monolith — backend only (no frontend in this feature).
**Performance Goals**:
- Reconciliation of all wallets in <5 min at current data volume (initial target; design tolerates 10× growth via `financial_snapshots`).
- Balance read p99 < 50 ms (served from projected cache, not ledger replay).
- Concurrent withdrawal decision contention bounded to <500 ms wait under 50 concurrent attempts on the same wallet (Redis lock + DB row lock).
**Constraints**:
- Zero financial data loss during rollout (additive-only DDL; legacy code paths kept until shadow-write parity is validated).
- No service downtime — migrations are non-blocking on MySQL 8 (`ALGORITHM=INSTANT` where possible, `INPLACE` otherwise).
- All append-only invariants enforced at both ORM layer and DB trigger (Constitution §V).
- All money operations EN+AR aware where they surface to admins (Filament reconciliation views).
**Scale/Scope**:
- ~10 financial tables (existing) + 4 new tables.
- ~12 Actions touched across Payments + Settlement.
- ~30 functional requirements (FR-EXT-101..130) from `spec.md`.
- Expected ledger volume at launch: <100k rows; design scales to >100M via snapshots and partitioning hint baked into table definitions.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | How this plan satisfies it |
|---|---|
| **I. Modular Monolith** | All work stays under `app/Modules/Payments/` and `app/Modules/Settlement/`. Cross-module reads use existing contracts. No new top-level folders. New tables live in their owning module's `Database/Migrations/`. |
| **II. Three Product Types** | Not type-aware. Refunds, commissions, and wallet movement already operate cross-type and use `match($enum)` where they branch on `product_type`. No new `if/elseif` on type strings; existing per-type refund policy resolver (`RefundPolicyService`) is preserved. |
| **III. Money Discipline** | Every new column storing money uses `BIGINT UNSIGNED` `_minor` + `CHAR(3)` `_currency`. `direction` ENUM('debit','credit') replaces the current signed `amount_minor`. `brick/money` continues to be the only arithmetic path. Running balance projection uses `Money::ofMinor()` exclusively. No floats anywhere. |
| **IV. Bilingual EN+AR Mandatory** | Reconciliation findings carry a translatable `description_key` + `description_params` (same pattern as `wallet_ledger.description_key`). Filament reconciliation views render in EN/AR via the translatable plugin. Admin notifications produced by reconciliation use translatable templates. |
| **V. Append-only Tables** | `wallet_ledger` already append-only — this plan **hardens** the rule via a MySQL trigger (`tr_wallet_ledger_no_update_delete`) plus an architecture test. New tables (`ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings`) are also append-only. The existing append-only inventory in Constitution §V is **extended** by this work, not reduced. |
| **VI. ADR-before-code** | This feature is bound to a **new ADR-0028 Financial Ledger Hardening** that MUST be authored and set to `Accepted` before any migration is written. The ADR records the double-entry decision, the projection-cache decision, the idempotency surface, and the reconciliation contract. |
| **VII. Test-First (money)** | Mandatory: all 12 refactored Actions get Pest tests in the same commit. Concurrency tests use forked workers. Coverage on Actions must remain ≥80% (Constitution §VII). New architecture test: `tests/Architecture/NoDirectWalletBalanceWritesTest.php`. |
| **VIII. Idempotency** | This plan **extends** idempotency from HTTP endpoints to **every** internal money-mutating Action. `idempotency_keys` (existing 24h-TTL table) gains a `scope` column to distinguish HTTP vs internal use. Webhook handler derives a deterministic key from `(gateway, gateway_ref, event_type)`. |
| **IX. Domain events `DB::afterCommit`** | Existing pattern preserved. Each ledger write is wrapped in `DB::transaction`; emitted events (`WalletCredited`, `WalletDebited`, `LedgerTransactionPosted`, `ReconciliationFindingRaised`) fire only inside `DB::afterCommit()`. |
| **X. Vendor Approval gate** | Not impacted. Vendor wallets are created lazily on first credit but only for vendors whose profile is already `approved` — existing guard preserved. |
| **XI. Document storage** | Not impacted. No file uploads in this feature. |

**Gate verdict: PASS.** No principle requires a justification entry in *Complexity Tracking*.

## Project Structure

### Documentation (this feature)

```text
specs/028-financial-ledger-hardening/
├── plan.md              # This file
├── spec.md              # Feature spec (existing)
├── research.md          # Phase 0 output — decisions resolved
├── data-model.md        # Phase 1 — entities + columns + invariants
├── quickstart.md        # Phase 1 — local validation walkthrough
├── contracts/           # Phase 1 — internal & admin contracts
│   ├── ledger-writer.md
│   ├── reconciliation.md
│   ├── admin-endpoints.md
│   └── concurrency-locks.md
├── checklists/
│   └── requirements.md  # Existing — quality checklist
└── tasks.md             # Phase 2 — produced by /speckit.tasks (NOT here)
```

### Source Code (repository root)

```text
app/Modules/Settlement/
├── Database/Migrations/
│   ├── 2026_05_15_000010_alter_wallet_ledger_add_ledger_columns.php           # NEW
│   ├── 2026_05_15_000011_create_ledger_transaction_groups_table.php           # NEW TABLE
│   ├── 2026_05_15_000012_alter_wallets_add_projection_cache_columns.php       # NEW COLUMNS
│   ├── 2026_05_15_000013_alter_withdrawals_add_ledger_links.php               # NEW COLUMNS
│   ├── 2026_05_15_000014_alter_commissions_add_ledger_links.php               # NEW COLUMNS
│   ├── 2026_05_15_000015_create_financial_snapshots_table.php                 # NEW TABLE
│   ├── 2026_05_15_000016_create_reconciliation_runs_table.php                 # NEW TABLE
│   ├── 2026_05_15_000017_create_reconciliation_findings_table.php             # NEW TABLE
│   └── 2026_05_15_000018_install_wallet_ledger_immutability_trigger.php       # NEW
├── Domain/
│   ├── Contracts/
│   │   ├── LedgerWriter.php                  # NEW — write API
│   │   ├── WalletProjector.php               # NEW — cache projection
│   │   ├── WalletLocker.php                  # NEW — Redis+DB lock primitives
│   │   └── ReconciliationDetector.php        # NEW — pure-function detectors
│   ├── Enums/
│   │   ├── LedgerDirection.php               # NEW — debit/credit
│   │   ├── TransactionKind.php               # NEW — capture/refund/commission/etc.
│   │   ├── SuspenseAccount.php               # NEW — platform clearing / receivable / etc.
│   │   ├── ReconciliationStatus.php          # NEW
│   │   └── ReconciliationFindingType.php     # NEW
│   ├── Events/
│   │   ├── LedgerTransactionPosted.php       # NEW
│   │   ├── ReconciliationFindingRaised.php   # NEW
│   │   └── (existing: WalletCredited, WalletDebited)
│   └── Models/
│       ├── LedgerTransactionGroup.php        # NEW
│       ├── FinancialSnapshot.php             # NEW
│       ├── ReconciliationRun.php             # NEW
│       └── ReconciliationFinding.php         # NEW
├── Application/
│   ├── Actions/
│   │   ├── PostLedgerTransactionAction.php   # NEW — single canonical writer
│   │   ├── ProjectWalletBalanceAction.php    # NEW — cache projection
│   │   ├── ReconcileWalletAction.php         # NEW — per-wallet reconciliation
│   │   ├── RunReconciliationAction.php       # NEW — fleet-wide orchestrator
│   │   ├── CreateFinancialSnapshotAction.php # NEW
│   │   ├── CreditWalletAction.php            # REFACTOR — delegates to PostLedgerTransactionAction
│   │   ├── DebitWalletAction.php             # REFACTOR
│   │   ├── RequestWithdrawalAction.php       # REFACTOR — reserve via ledger
│   │   ├── ApproveAndMarkWithdrawalPaidAction.php  # REFACTOR — settle via ledger
│   │   ├── RejectWithdrawalAction.php        # REFACTOR — unreserve via ledger
│   │   ├── CalculateCommissionAction.php     # REFACTOR — accrue via ledger
│   │   └── ReverseCommissionAction.php       # REFACTOR — reverse via ledger
│   └── Services/
│       └── (no new services; logic lives in Actions per Constitution §I)
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentLedgerRepository.php      # NEW — append-only reads/writes
│   │   ├── EloquentSnapshotRepository.php    # NEW
│   │   ├── EloquentReconciliationRepository.php # NEW
│   │   └── EloquentWalletRepository.php      # REFACTOR — remove incrementBalance/decrementBalance
│   └── Locks/
│       └── RedisWalletLocker.php             # NEW — implements WalletLocker
├── Console/
│   └── Commands/
│       ├── ReconcileFinancialsCommand.php    # NEW — artisan command
│       ├── BackfillLedgerColumnsCommand.php  # NEW — one-shot for rollout
│       └── SnapshotWalletsCommand.php        # NEW — scheduled
├── Filament/
│   ├── Pages/
│   │   └── ReconciliationDashboard.php       # NEW — read-only admin
│   └── Resources/
│       ├── ReconciliationRunResource.php     # NEW
│       └── ReconciliationFindingResource.php # NEW
├── Http/
│   ├── Controllers/
│   │   └── Admin/ReconciliationController.php # NEW
│   └── Routes/admin.php                       # +2 routes
└── Providers/
    └── SettlementServiceProvider.php          # REFACTOR — bind new contracts

app/Modules/Payments/Application/Actions/
├── CapturePaymentAction.php                   # REFACTOR — emits payment_capture ledger group
├── InitiateRefundAction.php                   # REFACTOR
├── ProcessRefundAction.php                    # REFACTOR — emits refund ledger group
├── ProcessPaymobWebhookAction.php             # REFACTOR — derives deterministic idempotency key
└── ReplayWebhookAction.php                    # REFACTOR — uses same idempotency surface

app/Modules/Shared/Application/Concerns/
└── ThreadsCausalChain.php                     # NEW trait — correlation_id / causation_id helpers

tests/
├── Architecture/
│   ├── NoDirectWalletBalanceWritesTest.php           # NEW
│   ├── AppendOnlyTablesHaveNoSoftDeletesTest.php     # EXTEND — add new tables
│   └── LedgerEntryHasRequiredColumnsTest.php         # NEW
├── Feature/Modules/Settlement/
│   ├── LedgerWriter/
│   │   ├── BalancedTransactionTest.php
│   │   ├── IdempotencyTest.php
│   │   ├── ConcurrentCreditTest.php
│   │   └── CauseCorrelationTest.php
│   ├── Reconciliation/
│   │   ├── DetectsCacheDriftTest.php
│   │   ├── DetectsOrphanedRefundTest.php
│   │   ├── ManualRunCommandTest.php
│   │   └── ConcurrentRunIsSerialisedTest.php
│   ├── Withdrawals/
│   │   ├── ConcurrentRequestsBlockOverdrawTest.php
│   │   ├── ApprovalPaymentSettlesViaLedgerTest.php
│   │   └── RejectionUnreservesViaLedgerTest.php
│   ├── Snapshots/
│   │   ├── SnapshotReproducesBalanceTest.php
│   │   └── HistoricalAsOfReadTest.php
│   └── Migration/
│       └── BackfillLedgerColumnsTest.php
├── Feature/Modules/Payments/
│   ├── Webhook/
│   │   ├── DuplicateWebhookProducesOneLedgerGroupTest.php
│   │   ├── ConcurrentDuplicateWebhooksTest.php
│   │   └── LateDuplicateWebhookIsNoOpTest.php
│   └── Refunds/
│       ├── PartialRefundsExhaustCapturedTest.php
│       ├── OverRefundIsRejectedTest.php
│       ├── PartialRefundReversesCommissionExactlyTest.php
│       └── DuplicateRefundCallbackTest.php
└── Unit/Modules/Settlement/
    ├── LedgerProjectorTest.php
    ├── SuspenseAccountBalancesTest.php
    └── CausalChainTraversalTest.php

docs/adr/
└── 0028-financial-ledger-hardening.md         # NEW ADR (Accepted before any migration)

docs/api/collections/
└── admin/reconciliation.bru                   # NEW Bruno collection entries (2 endpoints)

.specify/memory/
└── api-registry.md                            # EXTEND — 2 new admin endpoints
```

**Structure Decision**: Modular monolith (Constitution §I). Two modules carry the change set — `Settlement` (majority) and `Payments` (minority); the `Shared` module gets one tiny trait for causal-chain plumbing. No new top-level module. All cross-module reads use existing repository contracts; no Eloquent model leaks across boundaries (architecture test `NoCrossModuleModelImportsTest` continues to pass).

## Per-type coverage (rental / sale / digital)

This feature is **not** type-aware at the ledger layer. Wallet money movement is identical across product types. The per-type touchpoints in scope:

- **Refunds**: refund policy resolution remains type-aware (`RefundPolicyService::policyFor(ProductType)`) — preserved unchanged. Tests cover refunds against rental, sale, and digital captured payments to confirm the ledger writes are identical regardless of source type (FR-EXT-124 implies behavioural parity).
- **Commissions**: commission rate resolution (`(category × product_type)`-keyed, most-specific match wins) is preserved unchanged. Tests assert that accrual ledger entries match the snapshot rate regardless of product type.

No Form Request, API Resource, or Filament Resource is split per type in this feature.

## Locale coverage (EN+AR)

| Surface | EN | AR | Mechanism |
|---|---|---|---|
| Reconciliation finding messages | ✅ | ✅ | `description_key` + `description_params` via existing translatable pattern (mirrors `wallet_ledger.description_key`) |
| Filament `ReconciliationRunResource` | ✅ | ✅ | Filament translatable plugin + locale switcher |
| Filament `ReconciliationFindingResource` | ✅ | ✅ | Same |
| `ReconciliationDashboard` page | ✅ | ✅ | Same |
| Admin endpoints (JSON responses) | ✅ | ✅ | Resource layer locale conversion (`App::getLocale()`) |
| Artisan command output | en only | en only | Operator-facing tooling — bilingual not required per Constitution §IV (command output is for ops, not end-users) |

## Idempotency keys (which endpoints + which Actions)

| Surface | Key source | Window | Notes |
|---|---|---|---|
| `POST /admin/reconciliation/trigger` | Header `Idempotency-Key` (required) | 24h | New admin endpoint |
| `GET /admin/reconciliation/status` | n/a | — | Read-only |
| `ProcessPaymobWebhookAction` | Deterministic key `paymob:{event_type}:{order_id}:{transaction_id}` | 30 days | Stops duplicate webhooks definitively |
| `CapturePaymentAction` | `capture:{payment_id}` | 30 days | Idempotent regardless of caller |
| `ProcessRefundAction` | `refund:{refund_id}` | 30 days | |
| `CreditWalletAction` | Caller-supplied (required) | 24h–30d depending on caller | Internal callers MUST pass a key |
| `DebitWalletAction` | Caller-supplied (required) | Same | |
| `RequestWithdrawalAction` | `wd_req:{vendor_id}:{amount}:{nonce}` (nonce from request body) | 24h | Vendor-supplied nonce |
| `ApproveAndMarkWithdrawalPaidAction` | `wd_settle:{withdrawal_id}` | 30d | |
| `RejectWithdrawalAction` | `wd_reject:{withdrawal_id}` | 30d | |
| `CalculateCommissionAction` | `comm:{booking_item_id}` | 30d | |
| `ReverseCommissionAction` | `comm_rev:{commission_id}:{refund_id}` | 30d | |
| `SettlementRunAction` (orchestrator) | `settle_run:{run_id}:{withdrawal_id}` per withdrawal | 30d | Per-withdrawal idempotency, not per-run |

The existing `idempotency_keys` table (24h TTL) gains a `scope` column (`http` / `internal`) and a configurable TTL per scope (`config/idempotency.php`).

## Domain events fired (with DB::afterCommit confirmation)

| Event | Module | Fired by | Listener side-effects |
|---|---|---|---|
| `LedgerTransactionPosted` | Settlement | `PostLedgerTransactionAction` | Audit log write; metrics; optional outbox event |
| `WalletCredited` | Settlement | `CreditWalletAction` (preserved) | Existing notification listeners (preserved) |
| `WalletDebited` | Settlement | `DebitWalletAction` (preserved) | Existing |
| `ReconciliationRunStarted` | Settlement | `RunReconciliationAction` | Slack-ish admin notification (Communication module) |
| `ReconciliationRunCompleted` | Settlement | Same | Admin notification with summary |
| `ReconciliationFindingRaised` | Settlement | `ReconcileWalletAction` | Audit log + admin notification when severity ≥ high |
| `WithdrawalReserved` | Settlement | `RequestWithdrawalAction` (preserved) | Existing vendor notification |
| `WithdrawalSettled` | Settlement | `ApproveAndMarkWithdrawalPaidAction` (preserved) | Existing |
| `WithdrawalReleased` | Settlement | `RejectWithdrawalAction` (preserved) | Existing |
| `PaymentCaptured` | Payments | `CapturePaymentAction` (preserved) | Existing |
| `RefundProcessed` | Payments | `ProcessRefundAction` (preserved) | Existing |

**All listeners that perform external I/O (notifications, gateway calls, outbox writes) are queued.** Confirmed by `ShouldQueue` interface + `Bus::dispatch` in listeners. Constitution §IX enforced.

## Architecture tests added/updated

| Test | Asserts |
|---|---|
| `tests/Architecture/NoDirectWalletBalanceWritesTest.php` (NEW) | No file under `app/Modules/Settlement/` outside `ProjectWalletBalanceAction` mutates `wallets.balance_minor` or `wallets.pending_withdrawal_minor` (regex grep + AST node check on Eloquent `update`/`increment`/`decrement` against `Wallet`). |
| `tests/Architecture/LedgerEntryHasRequiredColumnsTest.php` (NEW) | Asserts `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings` have all required columns and no `softDeletes`, no `updated_at`. |
| `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` (EXTEND) | Add the 4 new tables to the inventory. |
| `tests/Architecture/NoFloatForMoneyTest.php` (EXTEND) | Ensure no `float`/`double` type hints on the new Action signatures. |
| `tests/Architecture/NoIfElseOnProductTypeStringTest.php` (EXIST) | Continues to pass — no new product-type branches introduced. |

## Cut-list (inherited from Phase 1.X discipline)

If the feature runs long, cut in this order (reversible later, in strict reverse order, without compromising data integrity):

1. **`financial_snapshots` scheduled job and historical "as-of" reads.** Reconciliation falls back to full-ledger replay until launch volume justifies snapshots.
2. **Filament `ReconciliationDashboard` page.** Admins use `ReconciliationRunResource` list + detail view; the dedicated dashboard page becomes a Phase 1.5 nice-to-have.
3. **The two admin HTTP endpoints (`/admin/reconciliation/status`, `/admin/reconciliation/trigger`).** Operators trigger reconciliation via the artisan command and read findings via Filament until the HTTP surface is added.
4. **Static-analysis CI rule for direct balance writes** (covered by `tests/Architecture/NoDirectWalletBalanceWritesTest.php` — the architecture test alone is non-negotiable; the additional Pint/PHPStan static rule is the optional layer).

**NOT cuttable under any circumstances:**
- The ledger writer Action
- The 4 new tables
- The wallet projection cache contract
- The 12 Action refactors
- All P1 user stories' tests
- The MySQL append-only trigger
- The backfill command

## Complexity Tracking

> Fill only if Constitution Check has violations that must be justified.

No violations. This feature **strengthens** the constitution's Append-only Tables principle (V) and the Idempotency principle (VIII). All other principles are preserved unchanged.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| *(none)* | — | — |
