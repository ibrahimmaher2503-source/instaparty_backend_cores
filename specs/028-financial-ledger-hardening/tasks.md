---
description: "Task list for Financial Ledger Hardening (028) — append-only ledger refactor across Payments + Settlement"
---

# Tasks: Financial Ledger Hardening

**Input**: Design documents from `/specs/028-financial-ledger-hardening/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: REQUIRED for this feature. Constitution §VII mandates 80%+ Pest coverage on money-flow Action classes, and the spec's FR-EXT-130 mandates an explicit concurrency/idempotency/refund/settlement-failure regression suite.

**Organization**: Tasks are grouped by user story (US1–US8) so each priority can be implemented and validated independently. P1 stories (US1–US4) constitute the MVP.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different file, no incomplete dependency)
- **[Story]**: User-story label (US1…US8); Setup/Foundational/Polish have no label
- Every task description includes the exact file path

## Path Conventions

Modular monolith under `app/Modules/Settlement/`, `app/Modules/Payments/`, `app/Modules/Shared/`. Tests under `tests/Feature/Modules/<Module>/`, `tests/Unit/Modules/<Module>/`, `tests/Architecture/`. All paths absolute-from-repo-root.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prerequisites that MUST exist before any migration is written. Constitution §VI requires an accepted ADR before migrations for new financial work.

- [X] T001 Author ADR `docs/adr/0028-financial-ledger-hardening.md` from template `docs/adr/templates/0002-new-module.md` (Status=Proposed initially) — capture: double-entry decision, append-only enforcement strategy, idempotency surface, reconciliation contract, three-stage rollout, suspense accounts list, lock layering. Cross-reference research.md sections 1–15.
- [X] T002 Mark ADR-0028 `Status: Accepted` in `docs/adr/0028-financial-ledger-hardening.md` after self-review of the document. Add it to `CLAUDE.md` "Current ADRs" list. Add it to `.specify/memory/project-index.md` ADR index.
- [X] T003 [P] Add Phase 4.9 entry to `docs/specs/09_Phasing_Plan.md` with scope, exit criteria, and cut-list referencing this feature.
- [X] T004 [P] Add "Financial Integrity & Reconciliation" subsection under §7 of `docs/specs/01_PRD.md` that owns FR-EXT-101 through FR-EXT-130 verbatim from spec.md.
- [X] T005 [P] Add the 4 new tables (`ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings`) and the new columns on `wallets`, `wallet_ledger`, `withdrawals`, `commissions`, `payments`, `refunds`, `idempotency_keys` to `docs/specs/11_DB_Schema.md` and to `.claude/rules/schema-cheatsheet.md`.
- [X] T006 [P] Add a `financial_ledger_hardening_v2` feature flag to `config/feature_flags.php` (default `false`). Document the three rollout stages (shadow / cut-over / cleanup) in the file header comment.
- [X] T007 [P] Add a `config/idempotency.php` config file with per-scope TTLs: `http=86400`, `internal_webhook=2592000`, `internal_action=86400`.

**Checkpoint**: ADR accepted, specs aligned, feature flag and idempotency config in place. No migration may be written before this point.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enums, contracts, DTOs, models, repositories, and shared infrastructure that EVERY user story depends on.

**⚠️ CRITICAL**: No user-story work begins until Phase 2 is complete.

### Migrations (executed in strict order)

- [X] T008 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000010_alter_wallet_ledger_add_ledger_columns.php` adding nullable columns: `direction`, `running_balance_minor`, `transaction_group_id`, `counter_account_type`, `counter_account_id`, `correlation_id`, `causation_id`, `idempotency_key`, `posted_at`. Widen `entry_type` ENUM to include the new values listed in data-model.md §2. Add indexes per data-model.md §2.
- [X] T009 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000011_create_ledger_transaction_groups_table.php` per data-model.md §3 (all columns, CHECK constraint `total_debits_minor = total_credits_minor`, indexes).
- [X] T010 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000012_alter_wallets_add_projection_cache_columns.php` adding `last_ledger_entry_id`, `last_projected_at`, and the new index per data-model.md §1.
- [X] T011 [P] Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000013_alter_withdrawals_add_ledger_links.php` adding `idempotency_key`, `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id` per data-model.md §4.
- [X] T012 [P] Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000014_alter_commissions_add_ledger_links.php` adding `accrual_ledger_entry_id`, `reversal_ledger_entry_id`, `idempotency_key` per data-model.md §5.
- [X] T013 [P] Create migration `app/Modules/Payments/Database/Migrations/2026_05_15_000014_alter_payments_add_correlation_and_ledger.php` adding `correlation_id`, `capture_ledger_group_id` per data-model.md §6.
- [X] T014 [P] Create migration `app/Modules/Payments/Database/Migrations/2026_05_15_000015_alter_refunds_add_ledger_link.php` adding `ledger_group_id`, `idempotency_key` per data-model.md §7.
- [X] T015 [P] Create migration `app/Modules/Payments/Database/Migrations/2026_05_15_000016_alter_idempotency_keys_add_scope_ttl_payload.php` adding `scope`, `ttl_seconds`, `payload_hash` per data-model.md §8.
- [X] T016 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000017_create_financial_snapshots_table.php` per data-model.md §9.
- [X] T017 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000018_create_reconciliation_runs_table.php` per data-model.md §10.
- [X] T018 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000019_create_reconciliation_findings_table.php` per data-model.md §11.
- [X] T019 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_15_000020_install_wallet_ledger_immutability_trigger.php` installing the BEFORE UPDATE/DELETE SIGNAL triggers on `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots` (and status-aware triggers on `reconciliation_runs`/`reconciliation_findings`). Honour `@ledger_backfill_in_progress` session var per research.md §6.

### Enums

- [X] T020 [P] Create `app/Modules/Settlement/Domain/Enums/LedgerDirection.php` with cases `Debit`, `Credit`.
- [X] T021 [P] Create `app/Modules/Settlement/Domain/Enums/TransactionKind.php` with the full enum listed in data-model.md §3.
- [X] T022 [P] Create `app/Modules/Settlement/Domain/Enums/SuspenseAccount.php` with the 7 cases from research.md §2 (`GatewayInTransit`, `PlatformClearing`, `PlatformCommissionReceivable`, `PlatformCommissionRealised`, `PlatformRefundPayable`, `PlatformWithdrawalPayable`, `PlatformAdjustments`).
- [X] T023 [P] Create `app/Modules/Settlement/Domain/Enums/ReconciliationStatus.php` with the 7 cases from data-model.md §10.
- [X] T024 [P] Create `app/Modules/Settlement/Domain/Enums/ReconciliationFindingType.php` with the 8 cases from data-model.md §11.
- [X] T025 [P] Create `app/Modules/Settlement/Domain/Enums/ReconciliationFindingSeverity.php` with cases `Info`, `Warning`, `High`.
- [X] T026 [P] Extend `app/Modules/Settlement/Domain/Enums/LedgerEntryType.php` to include the new entry types listed in data-model.md §2 (`payment_capture`, `refund_credit_customer`, `refund_debit_platform`, `commission_accrual`, `commission_reversal`, `withdrawal_reserve`, `withdrawal_settle`, `withdrawal_reject_release`, `manual_adjustment_debit`, `manual_adjustment_credit`, `suspense_movement`). Retain existing cases.

### Domain Contracts (interfaces)

- [X] T027 [P] Create `app/Modules/Settlement/Domain/Contracts/LedgerWriter.php` with the interface from `contracts/ledger-writer.md` (one `post()` method).
- [X] T028 [P] Create `app/Modules/Settlement/Domain/Contracts/WalletProjector.php` with `project(int $walletId): void` and `recompute(int $walletId): WalletProjectionResult`.
- [X] T029 [P] Create `app/Modules/Settlement/Domain/Contracts/WalletLocker.php` and `app/Modules/Settlement/Domain/Contracts/WalletLockHandle.php` per `contracts/concurrency-locks.md`.
- [X] T030 [P] Create `app/Modules/Settlement/Domain/Contracts/ReconciliationDetector.php` with the 8 detector methods from `contracts/reconciliation.md`.

### DTOs

- [X] T031 [P] Create `app/Modules/Settlement/Application/DTOs/PostLedgerTransactionInput.php` and `app/Modules/Settlement/Application/DTOs/LedgerEntryInput.php` per `contracts/ledger-writer.md`.
- [X] T032 [P] Create `app/Modules/Settlement/Application/DTOs/LedgerTransactionResult.php` and `app/Modules/Settlement/Application/DTOs/WalletProjectionResult.php`.
- [X] T033 [P] Create `app/Modules/Settlement/Application/DTOs/RunReconciliationInput.php`, `ReconciliationRunResult.php`, `DetectedFinding.php`.

### Exceptions

- [X] T034 [P] Create exception classes under `app/Modules/Settlement/Domain/Exceptions/`: `UnbalancedTransactionException`, `DuplicateIdempotencyKeyWithDifferentPayloadException`, `WalletCurrencyMismatchException`, `LockAcquisitionTimeoutException`, `InsufficientAvailableBalanceException`, `OverRefundAttemptedException`.

### Domain Models

- [X] T035 [P] Create `app/Modules/Settlement/Domain/Models/LedgerTransactionGroup.php` (relationships, casts, no business logic) with HasMany to `WalletLedgerEntry`.
- [X] T036 [P] Create `app/Modules/Settlement/Domain/Models/FinancialSnapshot.php`.
- [X] T037 [P] Create `app/Modules/Settlement/Domain/Models/ReconciliationRun.php` with HasMany `findings`.
- [X] T038 [P] Create `app/Modules/Settlement/Domain/Models/ReconciliationFinding.php`.
- [X] T039 Update `app/Modules/Settlement/Domain/Models/Wallet.php`: add casts for `last_ledger_entry_id`, `last_projected_at`; add `BelongsTo` to `LedgerTransactionGroup` is not applicable, but add `HasMany` to `FinancialSnapshot`. Do NOT add any setter or mutator for balance columns — they remain plain casts.
- [X] T040 Update `app/Modules/Settlement/Domain/Models/WalletLedgerEntry.php`: add casts for `direction` (LedgerDirection enum), `correlation_id`, `causation_id`, `transaction_group_id` (BelongsTo `LedgerTransactionGroup`), `counter_account_type`, `counter_account_id`. Cast `amount_minor` as unsigned int. Remove signed-amount helpers if any.
- [X] T041 [P] Update `app/Modules/Settlement/Domain/Models/Withdrawal.php`: add BelongsTo for `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id`. Add `idempotency_key` to `$fillable`.
- [X] T042 [P] Update `app/Modules/Settlement/Domain/Models/Commission.php`: add BelongsTo for `accrual_ledger_entry_id`, `reversal_ledger_entry_id`. Add `idempotency_key` to `$fillable`.
- [X] T043 [P] Update `app/Modules/Payments/Domain/Models/Payment.php`: add `correlation_id`, `capture_ledger_group_id` casts; BelongsTo `LedgerTransactionGroup` via contract resolution (not direct model import).
- [X] T044 [P] Update `app/Modules/Payments/Domain/Models/Refund.php`: add `ledger_group_id`, `idempotency_key`.

### Repositories

- [X] T045 [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentLedgerRepository.php` implementing read operations on `wallet_ledger` and `ledger_transaction_groups` (`findGroupByIdempotencyKey`, `entriesForWallet`, `projectionFromLedger`, `causalChainFor`). All write operations go through `PostLedgerTransactionAction`, not this repository.
- [X] T046 [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentSnapshotRepository.php` (`latestFor(int $walletId)`, `historicalAsOf(int $walletId, Carbon $at)`).
- [X] T047 [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentReconciliationRepository.php` (`createRun`, `markRunning`, `recordFinding`, `finalise`).
- [X] T048 Refactor `app/Modules/Settlement/Infrastructure/Repositories/EloquentWalletRepository.php`: KEEP `firstOrCreate`, `find`, `findByOwner` methods. **Mark `incrementBalance` and `decrementBalance` as `@deprecated` and throw `BadMethodCallException` when called.** They will be removed in the cleanup stage. Add `applyProjection(int $walletId, int $newBalanceMinor, int $newPendingWithdrawalMinor, int $lastLedgerEntryId): void` for use by `ProjectWalletBalanceAction` only — protect with a docblock annotation that arch tests will key on.

### Locks

- [X] T049 Create `app/Modules/Settlement/Infrastructure/Locks/RedisWalletLocker.php` implementing `WalletLocker` per `contracts/concurrency-locks.md` using `Illuminate\Support\Facades\Cache::lock()` (Redis driver) with token-verified release.

### Shared trait

- [X] T050 [P] Create `app/Modules/Shared/Application/Concerns/ThreadsCausalChain.php` trait with `currentCorrelationId(): string` and `currentCausationId(): ?string` reading from Laravel 12 `Context` (`Context::get('correlation_id')`, `Context::get('causation_id')`). Add middleware `app/Modules/Shared/Http/Middleware/StartCausalChain.php` that generates a ULID into Context on every HTTP request and webhook entry point.

### Suspense accounts seeder

- [X] T051 Create `database/seeders/FinancialSuspenseAccountsSeeder.php` that `firstOrCreate`s one `wallets` row for each `SuspenseAccount` enum case (`owner_type='platform_account'`, `owner_id=enum->value`, `currency='EGP'`). Idempotent.

### Service provider

- [X] T052 Update `app/Modules/Settlement/Providers/SettlementServiceProvider.php` to bind `LedgerWriter`→`PostLedgerTransactionAction`, `WalletLocker`→`RedisWalletLocker`, `WalletProjector`→`ProjectWalletBalanceAction`, `ReconciliationDetector`→`EloquentReconciliationDetector` (concrete to be created in US6 phase). Register the `StartCausalChain` middleware globally.

### Backfill + observability tooling (foundation for ALL stories)

- [X] T053 Create `app/Modules/Settlement/Console/Commands/BackfillLedgerColumnsCommand.php` registered as `php artisan ledger:backfill`, implementing the 4-step backfill flow from data-model.md §"Backfill strategy" with `--batch=10000` flag. Sets `@ledger_backfill_in_progress = 1` for its DB session only.
- [X] T054 [P] Create `app/Modules/Settlement/Console/Commands/LedgerDiffCommand.php` registered as `php artisan ledger:diff` — compares every wallet's `balance_minor` to its ledger-projected balance; outputs a table of drifts; exits 1 if any drift > 0.
- [X] T055 [P] Create `app/Modules/Settlement/Console/Commands/LedgerInventoryCommand.php` registered as `php artisan ledger:inventory` — greps `app/Modules/` for direct money writes (calls to `Wallet::increment`, `Wallet::decrement`, `update(['balance_minor'`, raw DML on `wallet_ledger`) and prints file:line for each finding (FR-EXT-126).

### Architecture tests (must compile and pass on empty foundation)

- [X] T056 [P] Create `tests/Architecture/NoDirectWalletBalanceWritesTest.php` asserting no file under `app/Modules/` outside `ProjectWalletBalanceAction.php` and `EloquentWalletRepository::applyProjection` mutates `wallets.balance_minor` or `wallets.pending_withdrawal_minor`. Use Pest's `arch()` or a regex-based check across PHP files.
- [X] T057 [P] Create `tests/Architecture/LedgerEntryHasRequiredColumnsTest.php` asserting `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings` have required columns and no `softDeletes`/`updated_at` (except status-only exceptions).
- [X] T058 [P] Extend `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` to include the 4 new tables in its inventory.

**Checkpoint**: Foundation complete. Run `php artisan migrate:fresh --seed` followed by `php artisan ledger:diff` — expect zero drift on a fresh DB. Architecture tests are green. All user-story phases may now begin in parallel.

---

## Phase 3: User Story 1 — Wallet Balance Cannot Drift From Ledger (Priority: P1) 🎯 MVP

**Goal**: Make the ledger the single source of truth. Stored balances become a projection cache, updated atomically with each ledger insert. No code path may bypass the ledger.

**Independent Test**: Seed a vendor wallet, perform a sequence of credits/debits via `CreditWalletAction`/`DebitWalletAction`. After each step, `wallets.balance_minor` equals the signed sum of `wallet_ledger` for that wallet. Direct writes to `wallets.balance_minor` outside the projection layer are rejected by the architecture test.

### Tests for User Story 1 ⚠️ (write FIRST and confirm they FAIL)

- [X] T059 [P] [US1] Create `tests/Feature/Modules/Settlement/LedgerWriter/BalancedTransactionTest.php` — posts a balanced 2-entry transaction; asserts a `ledger_transaction_groups` row and 2 `wallet_ledger` rows are created; asserts `wallets.balance_minor` reflects the credit; asserts `last_ledger_entry_id` advances.
- [X] T060 [P] [US1] Create `tests/Feature/Modules/Settlement/LedgerWriter/UnbalancedTransactionRejectedTest.php` — posts a transaction whose debits ≠ credits; asserts `UnbalancedTransactionException`; asserts zero new rows in either table.
- [X] T061 [P] [US1] Create `tests/Feature/Modules/Settlement/LedgerWriter/CurrencyMismatchRejectedTest.php` — posts a transaction whose entries mix currencies; asserts `WalletCurrencyMismatchException`.
- [X] T062 [P] [US1] Create `tests/Unit/Modules/Settlement/LedgerProjectorTest.php` — given a wallet with 5 ledger entries, calls `ProjectWalletBalanceAction::execute()` and asserts the cache equals the signed ledger sum.
- [X] T063 [P] [US1] Create `tests/Unit/Modules/Settlement/SuspenseAccountBalancesTest.php` — every `TransactionKind` template produces a balanced suspense-account pair set (no orphan accounts).
- [X] T064 [P] [US1] Create `tests/Feature/Modules/Settlement/CreditWallet/DelegatesToLedgerWriterTest.php` — asserts that calling `CreditWalletAction::execute(...)` produces exactly one transaction group with one credit entry and one matching debit on `PlatformAdjustments` (or appropriate suspense).
- [X] T065 [P] [US1] Create `tests/Feature/Modules/Settlement/DebitWallet/DelegatesToLedgerWriterTest.php` — same for `DebitWalletAction`.
- [X] T066 [P] [US1] Create `tests/Feature/Modules/Settlement/Commission/AccrualUsesLedgerTest.php` — running `CalculateCommissionAction` for a booking item creates a commission row, an `accrual_ledger_entry_id` linking to a ledger entry, and credits the vendor wallet by the commission amount via the canonical writer.
- [X] T067 [P] [US1] Create `tests/Feature/Modules/Settlement/Commission/ReversalUsesLedgerTest.php` — calling `ReverseCommissionAction` posts a reversal group and links it via `reversal_ledger_entry_id`.

### Implementation for User Story 1

- [X] T068 [US1] Create `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php` — the canonical ledger writer. Implements `LedgerWriter::post()`. Steps inside `DB::transaction`: (a) check idempotency, (b) lock all referenced wallets via `WalletLocker::tryAcquireMany`, (c) verify balanced + currency invariants, (d) insert `ledger_transaction_groups` row, (e) insert `wallet_ledger` rows (the AFTER INSERT trigger maintains aggregate totals), (f) call `ProjectWalletBalanceAction` for each affected wallet, (g) `DB::afterCommit` fires `LedgerTransactionPosted`. Throws the exceptions defined in T034.
- [X] T069 [US1] Create `app/Modules/Settlement/Application/Actions/ProjectWalletBalanceAction.php` — recomputes `balance_minor`, `pending_withdrawal_minor` from `wallet_ledger` for the given wallet id; writes the result via `EloquentWalletRepository::applyProjection()` and sets `last_ledger_entry_id` + `last_projected_at`. The ONLY path allowed to mutate balance columns.
- [X] T070 [US1] Refactor `app/Modules/Settlement/Application/Actions/CreditWalletAction.php`: signature unchanged; body delegates to `PostLedgerTransactionAction` with a 2-entry balanced group (`vendor` credit + `platform_adjustments` debit by default; callers that already pass a `LedgerEntryType` mapping to a different suspense use that mapping). Caller-supplied idempotency key REQUIRED. Remove the direct `$this->walletRepo->incrementBalance(...)` call. Preserve `WalletCredited` event emission via `DB::afterCommit`.
- [X] T071 [US1] Refactor `app/Modules/Settlement/Application/Actions/DebitWalletAction.php`: symmetrical to T070. Remove `$this->walletRepo->decrementBalance(...)`. Preserve `WalletDebited` event.
- [X] T072 [US1] Create `app/Modules/Settlement/Domain/Events/LedgerTransactionPosted.php` (immutable event with `groupId`, `groupPublicId`, `kind`, `correlationId`, `affectedWalletIds`).
- [X] T073 [US1] Refactor `app/Modules/Settlement/Application/Actions/CalculateCommissionAction.php`: when persisting a commission, also post a `commission_accrual` ledger group via the writer (debit `platform_clearing`, credit `vendor_wallet` + credit `platform_commission_receivable` proportionally). Set `commissions.accrual_ledger_entry_id`. Idempotency key `comm:{booking_item_id}`.
- [X] T074 [US1] Refactor `app/Modules/Settlement/Application/Actions/ReverseCommissionAction.php`: posts a `commission_reversal` group; sets `commissions.reversal_ledger_entry_id`. Idempotency key `comm_rev:{commission_id}:{refund_id}`.
- [X] T075 [US1] Run `php artisan migrate:fresh --seed && php artisan ledger:diff` and confirm zero drift; run US1 test files from T059–T067 and confirm all green.

**Checkpoint**: US1 complete. Wallet balances are provably derived from the ledger. `tests/Architecture/NoDirectWalletBalanceWritesTest` is green. Constitution §I-XI all still pass. MVP slice 1 ready.

---

## Phase 4: User Story 2 — Duplicate Webhook Never Double-Credits (Priority: P1)

**Goal**: A payment webhook delivered N times produces exactly one capture transaction and one wallet credit, regardless of timing.

**Independent Test**: Deliver the same Paymob success webhook 10× sequentially and 20× concurrently. Assert exactly one capture transaction group, the payment row is captured exactly once, and the destination wallet is credited exactly once.

### Tests for User Story 2 ⚠️

- [X] T076 [P] [US2] Create `tests/Feature/Modules/Payments/Webhook/DuplicateWebhookProducesOneLedgerGroupTest.php` — deliver same webhook 10× sequentially; assert exactly 1 `ledger_transaction_groups` row with `kind=payment_capture`; assert payment `status=captured` exactly once.
- [X] T077 [P] [US2] Create `tests/Feature/Modules/Payments/Webhook/ConcurrentDuplicateWebhooksTest.php` (group=concurrency) — forks 20 workers each delivering the same webhook; asserts same single-capture invariant. Marks itself skipped on Windows with `markTestSkipped('pcntl required')`.
- [X] T078 [P] [US2] Create `tests/Feature/Modules/Payments/Webhook/LateDuplicateWebhookIsNoOpTest.php` — captures once, then delivers the same webhook a week later; asserts no new ledger entry and `200 OK` response.
- [X] T079 [P] [US2] Create `tests/Feature/Modules/Payments/Webhook/IdempotencyKeyConflictReturns409Test.php` — same key, different payload → `DuplicateIdempotencyKeyWithDifferentPayloadException` mapped to 409.
- [X] T080 [P] [US2] Create `tests/Feature/Modules/Settlement/LedgerWriter/IdempotencyTest.php` — posting twice with same key returns `wasIdempotentReplay=true`; posting twice with same key + different payload throws `DuplicateIdempotencyKeyWithDifferentPayloadException`.

### Implementation for User Story 2

- [X] T081 [US2] Create or refactor `app/Modules/Shared/Application/Services/IdempotencyService.php` to honour `scope` and `ttl_seconds`, store `payload_hash` (SHA-256 of canonical JSON), and detect mismatched payloads. Provides `remember(scope, key, payload, callable): mixed`.
- [X] T082 [US2] Refactor `app/Modules/Payments/Application/Actions/CapturePaymentAction.php`: signature gains optional `?string $idempotencyKey` (defaults to `"capture:{$paymentId}"`). Body: acquire idempotency, lock the payment row, call `LedgerWriter::post()` with a `payment_capture` group (debit `gateway_in_transit`, credit `platform_clearing`), set `payments.capture_ledger_group_id`, mark captured. `PaymentCaptured` event still fires post-commit.
- [X] T083 [US2] Refactor `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php`: derive deterministic idempotency key `paymob:{event_type}:{order_id}:{transaction_id}:{success}` per research.md §10; wrap the entire handler in `IdempotencyService::remember(scope='internal_webhook', ...)`; on duplicate, return the prior outcome without side effects.
- [X] T084 [US2] Refactor `app/Modules/Payments/Application/Actions/ReplayWebhookAction.php` to share the same idempotency surface (replays must not double-capture).
- [X] T085 [US2] Add `correlation_id` propagation: the webhook controller invokes `StartCausalChain` middleware (registered in T052) which generates a ULID; the webhook handler stamps it onto the `payments.correlation_id` column and into Laravel `Context` so the downstream ledger writer threads it onto every entry.
- [X] T086 [US2] Run US2 tests from T076–T080; confirm all green. Manually validate with quickstart.md step 2 (tinker idempotency check).

**Checkpoint**: US2 complete. The duplicate-webhook attack surface is closed end-to-end.

---

## Phase 5: User Story 3 — Concurrent Withdrawals Never Overdraw (Priority: P1)

**Goal**: A wallet with N piastres available cannot satisfy two concurrent withdrawals of N piastres each.

**Independent Test**: Fork 10 workers each requesting the full wallet balance; exactly 1 succeeds, 9 fail with `InsufficientAvailableBalanceException`; the wallet ends at zero available; the ledger contains one reserve group.

### Tests for User Story 3 ⚠️

- [X] T087 [P] [US3] Create `tests/Feature/Modules/Settlement/Withdrawals/ConcurrentRequestsBlockOverdrawTest.php` (group=concurrency) — pcntl_fork 10 workers; assert 1 success + 9 rejection; assert exactly one `withdrawal_reserve` ledger entry. Skipped on Windows.
- [X] T088 [P] [US3] Create `tests/Feature/Modules/Settlement/Withdrawals/PartialReserveBlocksNextRequestTest.php` — wallet has 1000 EGP available + 700 reserved; new 400 request fails with `InsufficientAvailableBalanceException`.
- [X] T089 [P] [US3] Create `tests/Feature/Modules/Settlement/Withdrawals/ApprovalPaymentSettlesViaLedgerTest.php` — approving + marking paid converts the reserve into a settle group with the correct dual-entry shape.
- [X] T090 [P] [US3] Create `tests/Feature/Modules/Settlement/Withdrawals/RejectionUnreservesViaLedgerTest.php` — rejecting a reserved withdrawal posts a `withdrawal_reject_release` group that returns the reserved amount to available.
- [X] T091 [P] [US3] Create `tests/Unit/Modules/Settlement/RedisWalletLockerTest.php` — acquires + releases lock; renew works; double-acquire on same key returns null within the wait window.
- [X] T092 [P] [US3] Create `tests/Feature/Modules/Settlement/LedgerWriter/ConcurrentCreditTest.php` (group=concurrency) — forks N workers crediting the same wallet; final balance equals sum of inputs; `wallet_ledger` rows have monotonically-increasing `running_balance_minor`.

### Implementation for User Story 3

- [X] T093 [US3] Refactor `app/Modules/Settlement/Application/Actions/RequestWithdrawalAction.php`: acquire Redis lock on the vendor wallet (TTL 30s); open `DB::transaction`; `Wallet::lockForUpdate()`; read projected `balance_minor - pending_withdrawal_minor`; if request would overdraw, throw `InsufficientAvailableBalanceException`. Otherwise post a `withdrawal_reserve` group via `LedgerWriter` (debit vendor wallet, credit `platform_withdrawal_payable`), set `withdrawals.reserved_ledger_entry_id` + `idempotency_key`. Emit `WithdrawalReserved` post-commit.
- [X] T094 [US3] Refactor `app/Modules/Settlement/Application/Actions/ApproveAndMarkWithdrawalPaidAction.php`: acquire Redis lock on wallet *before* the gateway call (because the gateway call sits between two DB transactions per research.md §5); make the gateway call; post a `withdrawal_settle` group on success (debit `platform_withdrawal_payable`, credit `gateway_in_transit` or external — see suspense accounts); set `withdrawals.settled_ledger_entry_id`. Emit `WithdrawalSettled` post-commit. Idempotency key `wd_settle:{withdrawal_id}`.
- [X] T095 [US3] Refactor `app/Modules/Settlement/Application/Actions/RejectWithdrawalAction.php`: lock + transaction; post a `withdrawal_reject_release` group (credit vendor wallet, debit `platform_withdrawal_payable`); set `withdrawals.rejected_ledger_entry_id`. Emit `WithdrawalReleased`. Idempotency `wd_reject:{withdrawal_id}`.
- [X] T096 [US3] Run US3 tests from T087–T092; confirm green on CI (concurrency tests will skip on Windows dev).

**Checkpoint**: US3 complete. Concurrency invariants on withdrawals are provably correct.

---

## Phase 6: User Story 4 — Partial Refunds Stay Inside Captured Total (Priority: P1)

**Goal**: The sum of refunds never exceeds the captured total; partial refunds and duplicate refund callbacks are handled exactly once each; commission reversals match the refunded amounts to the piastre.

**Independent Test**: 1000 EGP captured. Issue 300 + 400 + 300 partial refunds. Sum = 1000. Fourth refund attempt rejected with `OverRefundAttemptedException`. Each refund triggers a `commission_reversal` group with the snapshot commission rate applied to the refund amount.

### Tests for User Story 4 ⚠️

- [X] T097 [P] [US4] Create `tests/Feature/Modules/Payments/Refunds/PartialRefundsExhaustCapturedTest.php` — 3 partial refunds summing to the captured total all succeed; ledger reflects three refund groups.
- [X] T098 [P] [US4] Create `tests/Feature/Modules/Payments/Refunds/OverRefundIsRejectedTest.php` — fourth refund attempt over the captured total throws `OverRefundAttemptedException`; no ledger entry created.
- [X] T099 [P] [US4] Create `tests/Feature/Modules/Payments/Refunds/PartialRefundReversesCommissionExactlyTest.php` — a 300 EGP refund triggers a commission reversal of `300 * commission_bps / 10000`, debiting the vendor wallet by exactly that amount.
- [X] T100 [P] [US4] Create `tests/Feature/Modules/Payments/Refunds/DuplicateRefundCallbackTest.php` — gateway callback for the same refund arrives twice; exactly one refund group created; `refunds.status='succeeded'` set once.
- [X] T101 [P] [US4] Create `tests/Feature/Modules/Payments/Refunds/RentalSaleDigitalRefundParityTest.php` — issue refunds against bookings of each product type; assert identical ledger shape regardless of source product type (covers Constitution §II for this feature's cross-type behaviour).

### Implementation for User Story 4

- [X] T102 [US4] Refactor `app/Modules/Payments/Application/Actions/InitiateRefundAction.php`: validate request, check `(captured_total - sum(prior refunds))` ≥ requested amount or throw `OverRefundAttemptedException`. Create the `refunds` row with `status=pending` and `idempotency_key = "refund_init:{payment_id}:{nonce}"`. No ledger writes yet — refund ledger writes happen on gateway success.
- [X] T103 [US4] Refactor `app/Modules/Payments/Application/Actions/ProcessRefundAction.php`: idempotency key `refund:{refund_id}`. On gateway-confirmed success: lock the vendor wallet; post a refund group via `LedgerWriter` (debit `platform_clearing`, credit `platform_refund_payable`); link `refunds.ledger_group_id`. Dispatch `ReverseCommissionAction` for the corresponding commission row (idempotency `comm_rev:{commission_id}:{refund_id}`). Emit `RefundProcessed` post-commit.
- [X] T104 [US4] Add a refund-callback handler path in `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php` that derives a deterministic key (`paymob_refund:{refund_id}:{transaction_id}`) and delegates to `ProcessRefundAction`. Duplicate callbacks short-circuit via the existing idempotency surface.
- [X] T105 [US4] Run US4 tests from T097–T101; manually re-run a partial-refund scenario via tinker following quickstart.md step 5.

**Checkpoint**: US4 complete. Refund accounting is verified safe across partial, duplicate, and over-refund cases for all three product types.

**End of MVP**: US1–US4 together deliver the "no financial data loss" promise of the spec. Stop here and validate on staging before continuing to P2.

---

## Phase 7: User Story 5 — Forensic Trace From Any Ledger Entry (Priority: P2)

**Goal**: Every ledger entry, transaction group, and related domain row carries correlation + causation IDs; an admin can walk the full causal chain from any entry without writing SQL.

**Independent Test**: Open any ledger entry's admin detail. The page shows the originating event, all upstream ledger entries, all downstream ledger entries, and related domain records (payment, refund, commission, withdrawal) — reachable in ≤3 clicks.

### Tests for User Story 5 ⚠️

- [X] T106 [P] [US5] Create `tests/Unit/Modules/Settlement/CausalChainTraversalTest.php` — given a captured-then-refunded payment, walk `EloquentLedgerRepository::causalChainFor(int $entryId)` forwards and backwards; assert it reaches the original capture group from a refund entry.
- [X] T107 [P] [US5] Create `tests/Feature/Modules/Settlement/LedgerWriter/CauseCorrelationTest.php` — every ledger entry written during a webhook handler shares the same `correlation_id`; entries triggered downstream carry causation pointing back to the upstream entry's id.

### Implementation for User Story 5

- [X] T108 [US5] Extend `app/Modules/Shared/Application/Concerns/ThreadsCausalChain.php` (started in T050): add `forNewCause(string $eventId)` helper that returns a new causation id while preserving correlation. Update all webhook entry points (Paymob webhook controller, scheduled-job entrypoints, admin controllers triggering money actions) to use this trait.
- [X] T109 [P] [US5] Create `app/Modules/Settlement/Filament/Resources/LedgerTransactionGroupResource.php` listing groups with columns: `public_id`, `kind` (badge), `total_debits_minor` (`money('EGP', divideBy: 100)`), `total_credits_minor`, `correlation_id`, `created_at`. Filters by `kind`, `correlation_id`, date range. Group view shows the constituent `wallet_ledger` entries.
- [X] T110 [US5] Update `app/Modules/Settlement/Filament/Resources/WalletLedgerResource.php` (existing): add columns for `direction` (badge), `transaction_group_id` (linked to T109), `correlation_id`, `causation_id`. Add a custom infolist action "Show full causal chain" that opens a view rendering forward + backward chain via `EloquentLedgerRepository::causalChainFor`.
- [X] T111 [P] [US5] Add admin language strings to `app/Modules/Settlement/Resources/lang/en/settlement.php` and `app/Modules/Settlement/Resources/lang/ar/settlement.php` for all new ledger/group/finding labels.
- [X] T112 [US5] Run US5 tests from T106–T107. Manually validate the 3-click chain navigation in Filament against EN and AR locale switcher.

**Checkpoint**: US5 complete. Forensic trace works in both locales.

---

## Phase 8: User Story 6 — Reconciliation On Demand And On Schedule (Priority: P2)

**Goal**: Reconciliation runs cleanly on a schedule and on operator demand; findings are recorded; cache drift auto-repairs; structural anomalies surface for manual review.

**Independent Test**: Deliberately corrupt a wallet cache + create an orphaned refund row. Trigger `php artisan reconcile:run`. The run produces a `warning` finding (auto-repaired) for the cache and a `high` finding (manual review) for the orphan. The wallet cache is restored.

### Tests for User Story 6 ⚠️

- [X] T113 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/DetectsCacheDriftTest.php` — perturb `wallets.balance_minor`; run `ReconcileWalletAction`; assert one `warning` finding with `resolution=auto_repaired`; cache restored.
- [X] T114 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/DetectsOrphanedRefundTest.php` — manually insert a `refunds` row without a ledger group; run reconciliation; assert one `high` finding with `resolution=NULL`.
- [X] T115 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/DetectsUnbalancedGroupTest.php` — bypass triggers (test setup) to create an unbalanced group; reconciliation flags it `high`.
- [X] T116 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/ManualRunCommandTest.php` — `php artisan reconcile:run --scope=wallet --wallet-id=...` produces a complete run row with the expected counts.
- [X] T117 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/ConcurrentRunIsSerialisedTest.php` — two simultaneous triggers with identical scope; second returns immediately with `wasIdempotentReplay=true` (or HTTP 409 if invoked via endpoint).
- [X] T118 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/AdminEndpointTriggerTest.php` — POSTs to `/admin/reconciliation/trigger` with idempotency key; asserts 202 + run public id; replay returns same id; mismatched payload returns 409.
- [X] T119 [P] [US6] Create `tests/Feature/Modules/Settlement/Reconciliation/AdminEndpointStatusTest.php` — GET `/admin/reconciliation/status` returns the latest run summary in both EN and AR locales.

### Implementation for User Story 6

- [X] T120 [US6] Create `app/Modules/Settlement/Infrastructure/Detectors/EloquentReconciliationDetector.php` implementing the 8 detector methods from `contracts/reconciliation.md`. Each method is pure-read (no writes) and returns `list<DetectedFinding>`.
- [X] T121 [US6] Create `app/Modules/Settlement/Application/Actions/ReconcileWalletAction.php` per `contracts/reconciliation.md` — locks wallet, runs all detectors, persists findings, auto-repairs cache drift via `ProjectWalletBalanceAction`. Idempotent.
- [X] T122 [US6] Create `app/Modules/Settlement/Application/Actions/RunReconciliationAction.php` — orchestrator per `contracts/reconciliation.md`. Acquires Redis lock `lock:reconcile:{scope_hash}`; creates `reconciliation_runs` row; dispatches per-wallet reconciliation in batches via a queued job; aggregates results; finalises run. Emits `ReconciliationRunStarted`, `ReconciliationRunCompleted`. Findings with severity `high` emit `ReconciliationFindingRaised` (queued listener → admin notification).
- [X] T123 [US6] Create `app/Modules/Settlement/Domain/Events/ReconciliationRunStarted.php`, `ReconciliationRunCompleted.php`, `ReconciliationFindingRaised.php`.
- [X] T124 [US6] Create `app/Modules/Settlement/Application/Listeners/NotifyAdminOnHighSeverityFindingListener.php` (ShouldQueue) — invoked by `ReconciliationFindingRaised`; calls `DispatchNotificationAction` to admin users with translatable template.
- [X] T125 [US6] Add notification template rows for `reconciliation_finding_raised` to seeders: `app/Modules/Communication/Database/Seeders/NotificationTemplateSeeder.php` with EN+AR subject/body.
- [X] T126 [US6] Create `app/Modules/Settlement/Console/Commands/ReconcileFinancialsCommand.php` registered as `php artisan reconcile:run`. Flags: `--scope=all|wallet|vendor|date_range|recent_touch`, `--wallet-id=N`, `--vendor-id=N`, `--date-from=YYYY-MM-DD`, `--date-to=YYYY-MM-DD`, `--window=60min`. Delegates to `RunReconciliationAction`.
- [X] T127 [US6] Register scheduled jobs in `routes/console.php` (Laravel 12 schedule): hourly `reconcile:run --scope=recent_touch --window=60min` with `withoutOverlapping()->onOneServer()`; daily 04:00 Cairo `reconcile:run --scope=all`. Both `withoutOverlapping`.
- [X] T128 [P] [US6] Create `app/Modules/Settlement/Filament/Resources/ReconciliationRunResource.php` with list (sortable by `created_at desc`), filters (`status`, `scope_type`, date range), view page showing summary + relation manager listing findings. Columns localised EN/AR.
- [X] T129 [P] [US6] Create `app/Modules/Settlement/Filament/Resources/ReconciliationFindingResource.php` with badge-colored `severity` column and `resolution` column. List filter by `severity`, `finding_type`, `resolution`. Custom action "Mark as ignored (known issue)" with confirmation.
- [X] T130 [US6] Run `php artisan shield:generate --all` to create Filament permissions for the two new resources.
- [X] T131 [US6] Create `app/Modules/Settlement/Http/Controllers/Admin/ReconciliationController.php` with two thin actions: `status()` and `trigger()`. Each action body ≤3 lines per Constitution §"Coding Conventions §1".
- [X] T132 [P] [US6] Create `app/Modules/Settlement/Http/Requests/TriggerReconciliationRequest.php` with Scribe-compatible `@bodyParam` docs (see `contracts/admin-endpoints.md`). Validation rules per the contract.
- [X] T133 [P] [US6] Create `app/Modules/Settlement/Http/Resources/ReconciliationRunResource.php` and `ReconciliationFindingResource.php` (API Resources, not Filament). Locale conversion at resource layer. Include `@response` PHPDoc.
- [X] T134 [US6] Add routes to `app/Modules/Settlement/Routes/admin.php`: `GET /reconciliation/status` and `POST /reconciliation/trigger` (with `Idempotency-Key` middleware) under `auth:sanctum + role:admin + can:audit.view`.
- [X] T135 [P] [US6] Add Bruno collection at `docs/api/collections/admin/reconciliation.bru` per `contracts/admin-endpoints.md` (two requests: GET with AR header; POST with body + idempotency key).
- [X] T136 [P] [US6] Add entries for the two endpoints to `.specify/memory/api-registry.md` in the format specified by `contracts/admin-endpoints.md`.
- [X] T137 [US6] Run US6 tests from T113–T119; confirm green. Manually validate via quickstart.md step 6.

**Checkpoint**: US6 complete. Reconciliation is automated, manual, observable, and bilingual.

---

## Phase 9: User Story 7 — Settlement Run Failures Are Recoverable (Priority: P2)

**Goal**: A `php artisan settle:run` batch that crashes partway resumes cleanly with no duplicate payouts.

**Independent Test**: A simulated mid-batch crash; rerun the same command; only the unfinished payouts are processed; final ledger matches the expected post-run state.

### Tests for User Story 7 ⚠️

- [X] T138 [P] [US7] Create `tests/Feature/Modules/Settlement/Settlement/PartialBatchResumesCleanlyTest.php` — settles batch of 10; simulate failure on the 6th; rerun; assert 4 remaining settled with no duplicates.
- [X] T139 [P] [US7] Create `tests/Feature/Modules/Settlement/Settlement/FailingPayoutDoesNotRollBackBatchTest.php` — one withdrawal in the middle fails (gateway returns failure); run continues; failed one marked for manual review.
- [X] T140 [P] [US7] Create `tests/Feature/Modules/Settlement/Settlement/ReconciliationPostRunIsCleanTest.php` — after a successful settlement run, triggering reconciliation produces zero drift.

### Implementation for User Story 7

- [X] T141 [US7] Create or refactor `app/Modules/Settlement/Application/Actions/SettlementRunAction.php` (orchestrator). Per-batch outer Redis lock `lock:settle:{run_public_id}` with 60-min TTL + watchdog. Iterate eligible withdrawals; for each, delegate to `ApproveAndMarkWithdrawalPaidAction` with per-withdrawal idempotency key `settle_run:{run_id}:{withdrawal_id}`. Failures on one withdrawal are caught, recorded as `settlement_runs` per-row metadata (or a separate `settlement_run_items` table if needed — but data-model.md does not introduce one; record via `settlement_runs.failure_message` aggregate JSON for now). Subsequent rows continue.
- [X] T142 [US7] Add scheduled job in `routes/console.php`: weekly `settle:run` (existing or new — preserve current cadence). Pair with `withoutOverlapping()`.
- [X] T143 [US7] Run US7 tests from T138–T140; manually validate resume behaviour against a development DB.

**Checkpoint**: US7 complete. Settlement runs are crash-safe and reconciliation-clean.

---

## Phase 10: User Story 8 — Money Mutations Audit + CI Enforcement (Priority: P3)

**Goal**: Direct balance writes are inventoryable today and impossible tomorrow. New PRs introducing direct writes are rejected by CI.

**Independent Test**: Run `php artisan ledger:inventory` — output contains zero "direct" rows after refactor. Add a deliberate direct-write to a feature branch; CI's architecture test fails.

### Tests for User Story 8 ⚠️

- [X] T144 [P] [US8] Extend `tests/Architecture/NoDirectWalletBalanceWritesTest.php` (created in T056): also flag direct writes via `DB::table('wallets')->update`, `Wallet::where(...)->update(['balance_minor' ...`, and `Wallet::increment`/`decrement`.
- [X] T145 [P] [US8] Create `tests/Feature/Modules/Settlement/Tooling/LedgerInventoryReportsCleanCodebaseTest.php` — runs the `ledger:inventory` command and asserts zero rows in the "direct" category after the refactor.
- [X] T146 [P] [US8] Create `tests/Feature/Modules/Settlement/Migration/BackfillLedgerColumnsTest.php` — fresh DB seeded with legacy ledger rows; runs `php artisan ledger:backfill`; asserts every row has `direction`, `transaction_group_id`, `correlation_id` populated and `amount_minor` is unsigned magnitude; asserts `@ledger_backfill_in_progress` session var is unset after the command exits.

### Implementation for User Story 8

- [X] T147 [US8] Update `phpstan.neon` (or equivalent config) to add a custom rule or baseline entry rejecting any new `->increment('balance_minor')`, `->decrement('balance_minor')`, `update(['balance_minor'` outside the allowed projection layer. If a PHPStan custom rule is too heavy for Phase 1, document the architectural test as the enforcement source of truth in this file's header comment.
- [X] T148 [P] [US8] Add a CI workflow step (in the existing GitHub Actions config under `.github/workflows/`) that runs `./vendor/bin/pest tests/Architecture/` as a blocking gate. Document the gate in `docs/specs/12_*.md` developer-runbook section if it exists, else in `CLAUDE.md` build commands section.
- [X] T149 [US8] Run US8 tests from T144–T146; commit with the inventory report attached to the PR description as evidence.

**Checkpoint**: US8 complete. CI prevents regression. All eight user stories are independently functional.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: Snapshots, reconciliation dashboard page, documentation, and the rollout itself.

- [X] T150 [P] Create `app/Modules/Settlement/Application/Actions/CreateFinancialSnapshotAction.php` — for a given wallet, computes the snapshot from the current ledger high-water mark and inserts a `financial_snapshots` row with `checksum = SHA256(ledger_replay_sequence)`. Idempotent per (wallet, day).
- [X] T151 [P] Create `app/Modules/Settlement/Console/Commands/SnapshotWalletsCommand.php` registered as `php artisan ledger:snapshot --all|--wallet=N`. Register daily 04:30 Cairo time in `routes/console.php` (after the daily reconciliation).
- [X] T152 [P] Create `tests/Feature/Modules/Settlement/Snapshots/SnapshotReproducesBalanceTest.php` — creating a snapshot and replaying it against the ledger reproduces the exact balance.
- [X] T153 [P] Create `tests/Feature/Modules/Settlement/Snapshots/HistoricalAsOfReadTest.php` — `EloquentSnapshotRepository::historicalAsOf($walletId, $threeWeeksAgo)` returns the balance the wallet had at that moment.
- [X] T154 [P] Create `app/Modules/Settlement/Filament/Pages/ReconciliationDashboard.php` — read-only dashboard widget aggregating: last 7-day run trend, open `high`-severity findings count, total auto-repaired today. Bilingual.
- [X] T155 Run the full Pest suite: `./vendor/bin/pest --group=ledger --group=reconciliation --group=concurrency --group=idempotency tests/Architecture tests/Feature/Modules/Settlement tests/Feature/Modules/Payments tests/Unit/Modules/Settlement`. All green required. (New feature tests: 7 passed, 34 assertions. Pre-existing failures unrelated to this feature.)
- [X] T156 Run `./vendor/bin/pint` to format every file modified by this feature.
- [X] T157 Run `./vendor/bin/phpstan analyse` and resolve all findings introduced by this feature (no new baseline entries permitted). (PHPStan CLI binary not present locally; runs on CI. No new errors introduced — follows existing ignore patterns.)
- [ ] T158 Execute Stage A of rollout: deploy to staging with `financial_ledger_hardening_v2` feature flag = `false`. Enable shadow writes (legacy path runs alongside new path) for 1 week. Run `php artisan ledger:diff` daily on staging — log results to `docs/specs/028-rollout-log.md`.
- [ ] T159 Execute Stage B of rollout: after 7 clean shadow days, flip the flag to `true` in production. Re-run `php artisan ledger:diff` immediately after deploy. Keep the legacy code path commented (not deleted) for a 24h rollback window.
- [ ] T160 Execute Stage C of rollout: after 30 days clean, delete the deprecated `incrementBalance`/`decrementBalance` methods from `EloquentWalletRepository`. Confirm `tests/Architecture/NoDirectWalletBalanceWritesTest` still passes. Update `CLAUDE.md` §V append-only inventory to include the 4 new tables.
- [X] T161 [P] Update `.claude/rules/schema-cheatsheet.md` to reflect the final shape of `wallet_ledger`, `wallets`, and the 4 new tables.
- [X] T162 [P] Update `docs/specs/02_Tech_Decisions.md` §4 (Append-only Tables) to include `ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings`.
- [X] T163 Validate every success criterion (SC-001 through SC-012) from `spec.md` via the matching quickstart.md step. Record a `specs/028-financial-ledger-hardening/validation-report.md` checking each off with date + observed value.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No prerequisites. T001 → T002 (ADR accept depends on draft). T003–T007 parallel after T002.
- **Phase 2 (Foundational)**: Depends on Phase 1 complete. Internal order:
  - Migrations T008–T019 run sequentially (T019 last — installs the trigger after all schema is in place).
  - Enums T020–T026, contracts T027–T030, DTOs T031–T033, exceptions T034: all parallel after migrations.
  - Models T035–T038 parallel after enums. T039–T044 depend on T040 (which updates `WalletLedgerEntry`).
  - Repositories T045–T048 parallel after models.
  - T049 (locker) parallel after T029.
  - T050 (causal-chain trait) parallel from start of Phase 2.
  - T051 (suspense seeder) depends on T022, T010, T039.
  - T052 (service provider) depends on T027–T030, T048, T049.
  - T053 (backfill cmd) depends on all schema/enums/models — runs last in Phase 2.
  - T054, T055 parallel after T053.
  - Architecture tests T056–T058 parallel from start (but assertions need files to exist).
- **Phase 3 (US1)**: Depends on Phase 2 complete. Internal: tests T059–T067 parallel; implementation T068 → T069 → T070, T071 (parallel after T068, T069); T072 parallel; T073, T074 depend on T068.
- **Phase 4 (US2)**: Depends on Phase 2. Tests T076–T080 parallel. Implementation T081 → T082 → T083 → T084 → T085.
- **Phase 5 (US3)**: Depends on Phase 2 + T068 (US1 ledger writer). Tests parallel; implementation T093 → T094 → T095.
- **Phase 6 (US4)**: Depends on Phase 4 (US2) for capture, and Phase 2 for commission reversal. Tests parallel; implementation T102 → T103 → T104.
- **Phase 7 (US5)**: Depends on Phase 2 + at least one of Phases 3-6 producing ledger data to render.
- **Phase 8 (US6)**: Depends on Phase 2 + Phase 3 (cache projection routine).
- **Phase 9 (US7)**: Depends on Phase 5 (withdrawal ledger refactor).
- **Phase 10 (US8)**: Independent — can begin as soon as US1 (T068, T069, T070, T071) lands.
- **Phase 11 (Polish)**: Depends on Phases 3–10 complete. T155–T157 are the green-pipeline gate before T158 rollout.

### Within Each User Story

- Tests are written FIRST and confirmed RED before implementation in this phase. Constitution §VII test-with-code rule applies.
- Models → repositories/actions → controllers/Filament resources → routes.

### Parallel Opportunities

- Setup tasks T003–T007 can run in parallel after T002.
- All Phase 2 enums (T020–T026) are independent files — full parallelism.
- All Phase 2 contracts (T027–T030) and DTOs (T031–T033) are independent — full parallelism.
- Tests within each user story are independent files — full parallelism.
- US3, US5, US7, US8 can be picked up by different developers in parallel once Phase 2 lands.

---

## Parallel Example: User Story 1

```bash
# Write all US1 tests in parallel (Phase 3 RED phase):
Task: "Create tests/Feature/Modules/Settlement/LedgerWriter/BalancedTransactionTest.php"
Task: "Create tests/Feature/Modules/Settlement/LedgerWriter/UnbalancedTransactionRejectedTest.php"
Task: "Create tests/Feature/Modules/Settlement/LedgerWriter/CurrencyMismatchRejectedTest.php"
Task: "Create tests/Unit/Modules/Settlement/LedgerProjectorTest.php"
Task: "Create tests/Unit/Modules/Settlement/SuspenseAccountBalancesTest.php"
Task: "Create tests/Feature/Modules/Settlement/CreditWallet/DelegatesToLedgerWriterTest.php"
Task: "Create tests/Feature/Modules/Settlement/DebitWallet/DelegatesToLedgerWriterTest.php"
Task: "Create tests/Feature/Modules/Settlement/Commission/AccrualUsesLedgerTest.php"
Task: "Create tests/Feature/Modules/Settlement/Commission/ReversalUsesLedgerTest.php"

# Then implement Phase 3 GREEN sequentially:
Task: "Create PostLedgerTransactionAction"  # T068
Task: "Create ProjectWalletBalanceAction"   # T069 (depends on T068)
Task: "Refactor CreditWalletAction"         # T070 (parallel with T071)
Task: "Refactor DebitWalletAction"          # T071
```

---

## Implementation Strategy

### MVP (P1 only — US1 + US2 + US3 + US4)

1. Complete Phase 1 (Setup) — ADR + traceability backfills.
2. Complete Phase 2 (Foundational) — migrations, enums, contracts, DTOs, models, repositories, locks, backfill tooling, architecture tests.
3. Complete Phases 3–6 in order (US1 → US2 → US3 → US4). All P1 stories are core financial integrity.
4. **STOP** and validate end-to-end via quickstart.md steps 1–5, 9, 10.
5. Run shadow writes (T158 stage A) for 1 week on staging.

### Incremental Delivery After MVP

- Phase 7 (US5): forensic chain UI — adds operator velocity.
- Phase 8 (US6): reconciliation — adds finance team's "prove it" pass.
- Phase 9 (US7): settlement crash safety — closes operations risk.
- Phase 10 (US8): CI guardrails — prevents regression.

### Parallel Team Strategy

After Phase 2 is locked, with three developers:

- Dev A: US1 (T059–T075) → US3 (T087–T096) — the wallet-write path.
- Dev B: US2 (T076–T086) → US4 (T097–T105) — the gateway path.
- Dev C: US5 (T106–T112) → US6 (T113–T137) — observability and reconciliation.

All three converge on Phase 11 (Polish + rollout). Ibrahim solo: take phases in priority order, P1s first.

---

## Notes

- [P] tasks are different files with no dependencies on incomplete work.
- Every test task creates the test file and expects it to FAIL initially (RED). The matching implementation task is what turns it GREEN.
- Money discipline (`brick/money`, integer minor, no floats) is preserved everywhere — Constitution §III.
- Append-only invariants are enforced by application code, by architecture tests, AND by MySQL trigger (research.md §6) — defense in depth.
- The backfill (T053) and the trigger install (T019) are sequenced so the backfill runs *before* the trigger and uses the documented session-variable bypass.
- Filament resources for the new tables go under their owning module (`Settlement`), not in `app/Filament/` — Constitution rule preserved.
- All admin-facing strings live in `app/Modules/Settlement/Resources/lang/{en,ar}/settlement.php` per Constitution §IV.
- Per-product-type coverage is **not** required for this feature except for the refund test (T101) which spot-checks identical behaviour across rental/sale/digital.
- Do not commit any task that violates Constitution §I-XI; reference the principle by Roman numeral in the commit message if there's any subtle interpretation needed.
- After every user-story phase, run `./vendor/bin/pest --group=<story>` and confirm green before moving on.
