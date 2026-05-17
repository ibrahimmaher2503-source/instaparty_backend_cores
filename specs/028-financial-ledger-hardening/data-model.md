# Phase 1 — Data Model: Financial Ledger Hardening

**Date**: 2026-05-15
**Feature**: 028-financial-ledger-hardening

This document captures the concrete table shapes after the hardening change set. Existing tables are shown with **CHANGED** / **NEW COLUMN** markers; new tables are shown in full.

All columns follow the project conventions: `bigIncrements('id')`, `char('public_id', 26)` ULID on top-level entities, `BIGINT UNSIGNED` `_minor` + `CHAR(3)` `_currency` for money, `JSON` for translatable text, `utf8mb4_unicode_ci`, FKs `restrictOnDelete()` unless cascade is domain-correct.

---

## 1. `wallets` (CHANGED — projection cache reframed)

Existing table from `2026_05_03_000001_create_wallets_table.php`. After this feature, the balance columns are **cache** — written only by `ProjectWalletBalanceAction`.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `public_id` | CHAR(26) ULID | NO | UNIQUE |
| `owner_type` | VARCHAR(50) | NO | `vendor`, `platform_account` |
| `owner_id` | BIGINT UNSIGNED | NO | For `platform_account` this is the integer value of `SuspenseAccount` enum |
| `currency` | CHAR(3) | NO | |
| `balance_minor` | BIGINT (signed) | NO | **Now a projection cache.** Updated only by `ProjectWalletBalanceAction`. Signed because platform suspense accounts may run negative balances by construction. |
| `pending_withdrawal_minor` | BIGINT UNSIGNED | NO | **Now a projection cache.** Sum of reserved-but-not-yet-settled withdrawals. |
| `last_ledger_entry_id` | BIGINT UNSIGNED | YES | **NEW COLUMN.** FK → `wallet_ledger.id`. Marks the high-water mark the cache reflects. Used to detect stale cache. |
| `last_projected_at` | TIMESTAMP | YES | **NEW COLUMN.** When the cache was last written. |
| `created_at`, `updated_at` | TIMESTAMP | NO | |

**Indexes**:
- UNIQUE `(owner_type, owner_id, currency)` — `wallets_owner_currency_unique` (existing)
- INDEX `(owner_type, owner_id)` — `wallets_owner_index` (existing)
- INDEX `(last_projected_at)` — **NEW.** For freshness queries during reconciliation.

**Invariants** (enforced by `ProjectWalletBalanceAction`, by `NoDirectWalletBalanceWritesTest`, and asserted in reconciliation):

- `balance_minor` MUST equal `(SUM(amount_minor WHERE direction='credit') - SUM(amount_minor WHERE direction='debit')) FROM wallet_ledger WHERE wallet_id = self`
- `pending_withdrawal_minor` MUST equal `SUM(amount_minor) FROM wallet_ledger WHERE wallet_id = self AND entry_type='withdrawal_reserve' AND id NOT IN (...settled or rejected counter-entries...)`
- `last_ledger_entry_id` MUST equal `MAX(id) FROM wallet_ledger WHERE wallet_id = self` at the moment of projection.

**Schema status**: ⚠️ NEW COLUMNS — not yet in `docs/specs/11_DB_Schema.md` §`wallets`. Backfill needed.

---

## 2. `wallet_ledger` (CHANGED — direction, group, IDs added)

Existing table from `2026_05_03_000002_create_wallet_ledger_table.php`. After this feature it remains append-only and adds the new identifier columns.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `wallet_id` | FK → wallets | NO | cascadeOnDelete (existing) |
| `entry_type` | ENUM | NO | **Expanded.** Existing: `commission_credit`, `refund_debit`, `withdrawal_debit`, `manual_adjustment`. **Added**: `payment_capture`, `refund_credit_customer`, `refund_debit_platform`, `commission_accrual`, `commission_reversal`, `withdrawal_reserve`, `withdrawal_settle`, `withdrawal_reject_release`, `manual_adjustment_debit`, `manual_adjustment_credit`, `suspense_movement`. ENUM is widened; old values retained for historical rows. |
| `direction` | ENUM('debit','credit') | NO | **NEW COLUMN.** Replaces the signed-amount semantic. |
| `amount_minor` | BIGINT UNSIGNED | NO | **CHANGED** — magnitude only, paired with `direction`. Existing signed values backfilled to magnitude + direction. |
| `currency` | CHAR(3) | NO | (existing) |
| `running_balance_minor` | BIGINT (signed) | NO | **NEW COLUMN.** Wallet's projected balance after this entry was applied. Used for fast reconciliation diffs. |
| `transaction_group_id` | BIGINT UNSIGNED | NO | **NEW COLUMN.** FK → `ledger_transaction_groups.id`. restrictOnDelete (groups are append-only). |
| `counter_account_type` | VARCHAR(50) | YES | **NEW COLUMN.** For double-entry: the type of the counter wallet in the same group. NULL only for legacy backfill rows. |
| `counter_account_id` | BIGINT UNSIGNED | YES | **NEW COLUMN.** The id of the counter wallet. |
| `correlation_id` | CHAR(26) ULID | NO | **NEW COLUMN.** Shared across the entire causal chain. NOT NULL after backfill. |
| `causation_id` | CHAR(26) ULID | YES | **NEW COLUMN.** The id of the immediately upstream cause (event, command, or another ledger entry). |
| `idempotency_key` | VARCHAR(128) | YES | **NEW COLUMN.** When non-NULL, makes this entry de-duplicatable. |
| `description_key` | VARCHAR(100) | YES | (existing) |
| `description_params` | JSON | YES | (existing) |
| `related_entity_type` | VARCHAR(50) | YES | (existing) |
| `related_entity_id` | BIGINT UNSIGNED | YES | (existing) |
| `posted_at` | TIMESTAMP | NO | **NEW COLUMN.** Application-set; equals `created_at` in steady state but settable for backfill clarity. |
| `created_at` | TIMESTAMP | NO | `useCurrent()` (existing; append-only — no `updated_at`) |

**Indexes**:
- INDEX `(wallet_id, created_at)` — existing
- INDEX `(entry_type)` — existing
- UNIQUE `(related_entity_type, related_entity_id, entry_type)` — existing
- UNIQUE `(wallet_id, idempotency_key)` — **NEW** — enforces idempotency per wallet
- INDEX `(transaction_group_id)` — **NEW**
- INDEX `(correlation_id)` — **NEW** — for forensic queries
- INDEX `(causation_id)` — **NEW**
- INDEX `(wallet_id, id DESC)` — **NEW** — high-water-mark lookups during projection

**Append-only invariants** (enforced by MySQL triggers per `research.md` §6):
- No UPDATE allowed.
- No DELETE allowed.
- Only INSERT, only through `PostLedgerTransactionAction` (architecture test).

**Schema status**: ⚠️ NEW COLUMNS — not yet in `docs/specs/11_DB_Schema.md` §`wallet_ledger`.

---

## 3. `ledger_transaction_groups` (NEW TABLE)

The atomic unit of money movement. Every group's debits MUST equal its credits.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `public_id` | CHAR(26) ULID | NO | UNIQUE — used in admin URLs |
| `kind` | ENUM | NO | `payment_capture`, `refund`, `commission_accrual`, `commission_reversal`, `withdrawal_reserve`, `withdrawal_settle`, `withdrawal_reject_release`, `manual_adjustment`, `suspense_movement`, `legacy_backfill`. |
| `currency` | CHAR(3) | NO | All entries in a group share a currency. |
| `total_debits_minor` | BIGINT UNSIGNED | NO | Aggregate over the group's `wallet_ledger` rows; maintained by AFTER INSERT trigger. |
| `total_credits_minor` | BIGINT UNSIGNED | NO | Same. CHECK constraint: `total_debits_minor = total_credits_minor`. |
| `entry_count` | INT UNSIGNED | NO | Defaults to 0; incremented per entry. |
| `correlation_id` | CHAR(26) ULID | NO | Same correlation as the underlying entries. |
| `causation_id` | CHAR(26) ULID | YES | The cause of the group itself (e.g., a webhook id). |
| `idempotency_key` | VARCHAR(128) | YES | UNIQUE — duplicate calls with same key short-circuit to existing group. |
| `initiated_by_user_id` | BIGINT UNSIGNED | YES | FK → users, restrictOnDelete. NULL when initiated by the system. |
| `initiator_type` | ENUM('system','webhook','admin','vendor','customer','scheduler') | NO | |
| `description_key` | VARCHAR(100) | YES | Translatable description for admin display. |
| `description_params` | JSON | YES | |
| `metadata` | JSON | YES | Free-form context (gateway response id, batch id, etc.). |
| `posted_at` | TIMESTAMP | NO | |
| `created_at` | TIMESTAMP | NO | `useCurrent()`. Append-only — no `updated_at`. |

**Indexes**:
- UNIQUE `(public_id)`
- UNIQUE `(idempotency_key)` — sparse unique index (MySQL: standard UNIQUE allows NULLs)
- INDEX `(kind, created_at)`
- INDEX `(correlation_id)`
- INDEX `(causation_id)`
- INDEX `(initiated_by_user_id)`

**Append-only invariants**: no UPDATE except via the AFTER INSERT trigger on `wallet_ledger` that increments the aggregate columns; no DELETE.

**Schema status**: ⚠️ NEW TABLE — not yet in `docs/specs/11_DB_Schema.md`. Backfill needed.

---

## 4. `withdrawals` (CHANGED — ledger links added)

Existing table from `2026_05_03_000005_create_withdrawals_table.php`. New columns link state transitions to canonical ledger entries.

New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `idempotency_key` | VARCHAR(128) | YES | UNIQUE per `vendor_profile_id`. Set on creation; lets duplicate request calls short-circuit. |
| `reserved_ledger_entry_id` | BIGINT UNSIGNED | YES | FK → wallet_ledger.id, restrictOnDelete. The `withdrawal_reserve` entry. |
| `settled_ledger_entry_id` | BIGINT UNSIGNED | YES | FK → wallet_ledger.id, restrictOnDelete. The `withdrawal_settle` entry. |
| `rejected_ledger_entry_id` | BIGINT UNSIGNED | YES | FK → wallet_ledger.id, restrictOnDelete. The `withdrawal_reject_release` entry. |

**Invariants**:
- A withdrawal MUST have a `reserved_ledger_entry_id` after `RequestWithdrawalAction` succeeds.
- Exactly one of `settled_ledger_entry_id` or `rejected_ledger_entry_id` MUST be set after the withdrawal terminates.

**Schema status**: ⚠️ NEW COLUMNS — not yet in `docs/specs/11_DB_Schema.md` §`withdrawals`.

---

## 5. `commissions` (CHANGED — ledger links added)

Existing table from `2026_05_03_000004_create_commissions_table.php`. New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `accrual_ledger_entry_id` | BIGINT UNSIGNED | YES | FK → wallet_ledger.id, restrictOnDelete. The `commission_accrual` entry. |
| `reversal_ledger_entry_id` | BIGINT UNSIGNED | YES | FK → wallet_ledger.id, restrictOnDelete. The `commission_reversal` entry (NULL until/unless refunded). |
| `idempotency_key` | VARCHAR(128) | YES | UNIQUE per `booking_item_id`. |

**Schema status**: ⚠️ NEW COLUMNS.

---

## 6. `payments` (CHANGED — ledger links + correlation added)

Existing table from `2026_05_02_000001_create_payments_table.php`. New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `correlation_id` | CHAR(26) ULID | YES | Shared across the full causal chain. NOT NULL after backfill. |
| `capture_ledger_group_id` | BIGINT UNSIGNED | YES | FK → ledger_transaction_groups.id, restrictOnDelete. |

**Schema status**: ⚠️ NEW COLUMNS.

---

## 7. `refunds` (CHANGED — ledger link added)

Existing table from `2026_05_02_000003_create_refunds_table.php`. New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `ledger_group_id` | BIGINT UNSIGNED | YES | FK → ledger_transaction_groups.id, restrictOnDelete. |
| `idempotency_key` | VARCHAR(128) | YES | UNIQUE per `payment_id`. |

**Schema status**: ⚠️ NEW COLUMNS.

---

## 8. `idempotency_keys` (CHANGED — scope + TTL added)

Existing table from `2026_05_02_000004_create_idempotency_keys_table.php`. New columns:

| Column | Type | Null | Notes |
|---|---|---|---|
| `scope` | ENUM('http','internal') | NO | Default `http`. |
| `ttl_seconds` | INT UNSIGNED | NO | Default `86400`. Per-row TTL. |
| `payload_hash` | CHAR(64) | YES | SHA-256 of the canonical payload. Used to detect mismatched-payload duplicate calls. |

**Cleanup job**: existing scheduled job is reframed to `WHERE created_at < NOW() - INTERVAL ttl_seconds SECOND`.

**Schema status**: ⚠️ NEW COLUMNS.

---

## 9. `financial_snapshots` (NEW TABLE)

Per-wallet point-in-time balance snapshots.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `wallet_id` | FK → wallets.id | NO | cascadeOnDelete |
| `snapshot_at` | TIMESTAMP | NO | |
| `as_of_ledger_entry_id` | BIGINT UNSIGNED | NO | FK → wallet_ledger.id, restrictOnDelete. The high-water mark of this snapshot. |
| `available_minor` | BIGINT (signed) | NO | Snapshot of `balance_minor`. |
| `pending_minor` | BIGINT UNSIGNED | NO | Snapshot of `pending_withdrawal_minor`. |
| `currency` | CHAR(3) | NO | |
| `checksum` | CHAR(64) | NO | SHA-256 of the deterministic ledger replay used to produce this snapshot; detects future drift. |
| `created_at` | TIMESTAMP | NO | `useCurrent()`. No `updated_at`. |

**Indexes**:
- INDEX `(wallet_id, snapshot_at DESC)` — for "most recent snapshot" lookups
- INDEX `(snapshot_at)` — for global "as of yesterday" reads

**Append-only**: no UPDATE, no DELETE. Enforced by trigger.

**Schema status**: ⚠️ NEW TABLE.

---

## 10. `reconciliation_runs` (NEW TABLE)

One row per reconciliation execution.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `public_id` | CHAR(26) ULID | NO | UNIQUE |
| `scope_type` | ENUM('all','wallet','vendor','date_range','recent_touch') | NO | |
| `scope_params` | JSON | YES | The parsed scope (wallet_id, vendor_id, date_from, date_to, recent_touch_window). |
| `status` | ENUM('queued','running','clean','anomalies_detected','repaired','requires_manual_review','failed') | NO | |
| `triggered_by_user_id` | BIGINT UNSIGNED | YES | FK → users, restrictOnDelete. NULL when scheduled. |
| `trigger_kind` | ENUM('scheduled','manual','admin_endpoint') | NO | |
| `idempotency_key` | VARCHAR(128) | YES | UNIQUE. From the trigger endpoint. |
| `correlation_id` | CHAR(26) ULID | NO | |
| `wallets_scanned` | INT UNSIGNED | NO | Default 0. |
| `findings_count` | INT UNSIGNED | NO | Default 0. |
| `auto_repaired_count` | INT UNSIGNED | NO | Default 0. |
| `manual_review_count` | INT UNSIGNED | NO | Default 0. |
| `started_at` | TIMESTAMP | YES | |
| `completed_at` | TIMESTAMP | YES | |
| `failure_message` | TEXT | YES | |
| `created_at` | TIMESTAMP | NO | `useCurrent()`. No `updated_at` (status updates use a column-status-only exception per Constitution §V). |

**Indexes**:
- UNIQUE `(public_id)`
- UNIQUE `(idempotency_key)`
- INDEX `(status, created_at)`
- INDEX `(scope_type, completed_at)`

**Append-only with status exception**: `status`, `wallets_scanned`, `findings_count`, `auto_repaired_count`, `manual_review_count`, `started_at`, `completed_at`, `failure_message` may be updated by the run worker itself; all other columns immutable. Matches the existing exception pattern for `payments`, `commissions`.

**Schema status**: ⚠️ NEW TABLE.

---

## 11. `reconciliation_findings` (NEW TABLE)

One row per detected anomaly within a reconciliation run.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED auto-inc | NO | |
| `public_id` | CHAR(26) ULID | NO | UNIQUE |
| `reconciliation_run_id` | FK → reconciliation_runs.id | NO | cascadeOnDelete (run is the aggregate root) |
| `finding_type` | ENUM | NO | `wallet_cache_drift`, `orphaned_refund_row`, `orphaned_ledger_entry`, `unbalanced_transaction_group`, `commission_without_snapshot_rate`, `withdrawal_without_reserve_entry`, `negative_vendor_balance`, `currency_mismatch`. |
| `severity` | ENUM('info','warning','high') | NO | Per `research.md` §12. |
| `resource_type` | VARCHAR(50) | YES | e.g., `wallet`, `refund`, `wallet_ledger`. |
| `resource_id` | BIGINT UNSIGNED | YES | |
| `expected` | JSON | YES | e.g., `{"balance_minor": 12345}` |
| `actual` | JSON | YES | e.g., `{"balance_minor": 12300}` |
| `delta` | JSON | YES | e.g., `{"balance_minor": -45}` |
| `resolution` | ENUM('auto_repaired','manual_review_required','ignored_known_issue','superseded_by_later_run') | YES | NULL while pending. |
| `resolved_by_user_id` | BIGINT UNSIGNED | YES | FK → users, restrictOnDelete. |
| `resolved_at` | TIMESTAMP | YES | |
| `description_key` | VARCHAR(100) | YES | Translatable description for admin display. |
| `description_params` | JSON | YES | |
| `created_at` | TIMESTAMP | NO | `useCurrent()`. No `updated_at` (status exception for `resolution`, `resolved_*`). |

**Indexes**:
- UNIQUE `(public_id)`
- INDEX `(reconciliation_run_id, severity)`
- INDEX `(finding_type, severity, created_at)`
- INDEX `(resolution)`

**Append-only with resolution-only exception**: only `resolution`, `resolved_by_user_id`, `resolved_at` may be updated.

**Schema status**: ⚠️ NEW TABLE.

---

## Entity relationship diagram (logical)

```
                  ┌───────────────────────┐
                  │     bookings          │
                  └─────────┬─────────────┘
                            │
              ┌─────────────┴──────────────┐
              ▼                            ▼
     ┌────────────────┐           ┌────────────────┐
     │ booking_items  │           │ payments       │
     └──────┬─────────┘           └──────┬─────────┘
            │                            │
            ▼                            ▼
     ┌────────────────┐           ┌────────────────┐           ┌────────────────────────────┐
     │ commissions    │           │ refunds        │           │ ledger_transaction_groups  │
     │  + ledger fks  │           │  + ledger fk   │           │ (NEW)                      │
     └──────┬─────────┘           └──────┬─────────┘           │ kind, totals, correlation, │
            │                            │                     │ idempotency, balanced      │
            └────────────┬───────────────┘                     └──────────┬─────────────────┘
                         │                                                │
                         ▼                                                │
                  ┌────────────────┐ <───────── transaction_group_id ─────┘
                  │ wallet_ledger  │ (CHANGED: direction, group_id, correlation_id,
                  │  (append-only) │  causation_id, idempotency_key, running_balance,
                  └──────┬─────────┘  counter_account_*)
                         │
                         ▼
                  ┌────────────────┐
                  │ wallets        │ ◄─── projection cache; last_ledger_entry_id pointer
                  │ (CHANGED:      │      written ONLY by ProjectWalletBalanceAction
                  │  + last_*,     │
                  │  cache role)   │
                  └──────┬─────────┘
                         │
                         ▼
                  ┌────────────────┐
                  │ withdrawals    │ ◄─── reserved_/settled_/rejected_ledger_entry_id
                  │  + ledger fks  │
                  └────────────────┘


                  ┌──────────────────────────┐
                  │ reconciliation_runs (NEW)│ ── public_id, scope, status, idempotency
                  └──────────┬───────────────┘
                             │
                             ▼
                  ┌──────────────────────────┐
                  │ reconciliation_findings  │ ── finding_type, severity, expected/actual/delta
                  │ (NEW)                    │     resolution
                  └──────────────────────────┘


                  ┌──────────────────────────┐
                  │ financial_snapshots (NEW)│ ── per-wallet point-in-time anchor
                  └──────────────────────────┘
```

---

## State transitions

### `ledger_transaction_groups.kind` lifecycle

Append-only — no state transitions on the group itself. The group is born complete: created with all its `wallet_ledger` rows inside one DB transaction.

### `reconciliation_runs.status` state machine

```
       ┌──────────► clean
queued ─► running ─┤
                   ├──► anomalies_detected ─► repaired (when auto-repair fixes everything)
                   │                       └─► requires_manual_review (when something is structural)
                   └──► failed (worker crash / DB outage)
```

### `reconciliation_findings.resolution` state machine

```
NULL (pending) ─► auto_repaired (system fixed it)
              ├─► manual_review_required (queued for admin)
              │      └─► ignored_known_issue (admin marks won't-fix)
              │      └─► superseded_by_later_run (re-detected and resolved in a newer run)
              └─► superseded_by_later_run (rare — orphan was cured by upstream code change)
```

---

## Validation rules from spec → data model mapping

| Spec FR | Where enforced in the data model |
|---|---|
| FR-EXT-101 (ledger is sole truth) | `wallets.balance_minor` written only by `ProjectWalletBalanceAction`; arch test `NoDirectWalletBalanceWritesTest` |
| FR-EXT-102 (no UPDATE/DELETE on append-only) | MySQL triggers on `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs` (status-only exception), `reconciliation_findings` (resolution-only exception) |
| FR-EXT-103 (balances are cache) | `wallets.last_ledger_entry_id`, `wallets.last_projected_at` — verifiable freshness |
| FR-EXT-104 (deterministic recompute) | `ReconcileWalletAction` recomputes purely from `wallet_ledger`; tested in `LedgerProjectorTest` |
| FR-EXT-105 (direction, type, group on every entry) | `wallet_ledger.direction NOT NULL`, `wallet_ledger.entry_type NOT NULL`, `wallet_ledger.transaction_group_id NOT NULL` |
| FR-EXT-106 (balanced groups) | `ledger_transaction_groups.total_debits_minor = total_credits_minor` CHECK constraint + AFTER INSERT trigger on `wallet_ledger` |
| FR-EXT-107 (suspense accounts) | `SuspenseAccount` enum + matching `wallets` rows seeded by `BackfillLedgerColumnsCommand` |
| FR-EXT-108 (DB-level enforcement) | CHECK constraint + trigger combo |
| FR-EXT-109 (idempotency on every money Action) | `idempotency_keys.scope='internal'` rows; each Action accepts a key argument |
| FR-EXT-110 (duplicate returns original) | Existing idempotency middleware behavior; extended in `IdempotencyService` |
| FR-EXT-111 (mismatched payload = conflict) | `idempotency_keys.payload_hash` SHA-256 comparison |
| FR-EXT-112 (queue retries protected) | Same — internal Actions use the same `IdempotencyService` |
| FR-EXT-113 (correlation on every entry) | `wallet_ledger.correlation_id NOT NULL` after backfill |
| FR-EXT-114 (causation on internal entries) | `wallet_ledger.causation_id` — non-null for everything except the originating event of a chain |
| FR-EXT-115 (forward/backward traversal) | Indexed `correlation_id` + `causation_id`; admin UI surfaces a "causal chain" view |
| FR-EXT-116 (row + Redis lock) | `WalletLocker` contract; both layers tested |
| FR-EXT-117 (recompute inside critical section) | `RequestWithdrawalAction` reads balance via `SELECT ... FOR UPDATE` on the row |
| FR-EXT-118 (lock timeout) | `WalletLocker::tryAcquire(timeoutSeconds)` returns boolean; caller raises retryable error on timeout |
| FR-EXT-119 (reconciliation routine) | `RunReconciliationAction` + `reconciliation_runs` + `reconciliation_findings` |
| FR-EXT-120 (no concurrent runs) | Redis lock `lock:reconcile:{scope_hash}` |
| FR-EXT-121 (admin views) | `ReconciliationRunResource` + `ReconciliationFindingResource` Filament resources |
| FR-EXT-122 (snapshots) | `financial_snapshots` table |
| FR-EXT-123 (as-of reads) | `financial_snapshots.as_of_ledger_entry_id` + ledger entries with `id > as_of` |
| FR-EXT-124 (refactor 12 Actions) | Each Action delegates to `PostLedgerTransactionAction` |
| FR-EXT-125 (preserve observable behavior) | Existing tests stay green; events still fire; audit logs unchanged |
| FR-EXT-126 (inventory tool) | `php artisan ledger:inventory` console command |
| FR-EXT-127 (CI enforcement) | `tests/Architecture/NoDirectWalletBalanceWritesTest.php` |
| FR-EXT-128 (admin command) | `php artisan reconcile:run` |
| FR-EXT-129 (admin HTTP surface) | `GET/POST /admin/reconciliation/*` |
| FR-EXT-130 (test suite) | `tests/Feature/Modules/{Settlement,Payments}/...` |

---

## Backfill strategy (per `research.md` §14)

The backfill is a one-shot artisan command `php artisan ledger:backfill --batch=10000` that:

1. Seeds platform suspense `wallets` rows (idempotent — `firstOrCreate`).
2. For every existing `wallet_ledger` row, in batches:
   - Creates a `ledger_transaction_groups` row with `kind='legacy_backfill'`, fresh ULIDs, and the appropriate `total_*_minor` aggregates.
   - Sets `direction = amount_minor > 0 ? credit : debit`.
   - Rewrites `amount_minor = ABS(amount_minor)`.
   - Sets fresh `correlation_id` (legacy entries don't have a reconstructable chain).
   - Sets `running_balance_minor` by re-projecting in chronological order per wallet.
3. Projects every `wallets.balance_minor` and `wallets.pending_withdrawal_minor` from the rewritten ledger.
4. Runs a final reconciliation pass and writes a `reconciliation_runs` row with `trigger_kind='manual'` and `scope_type='all'`. Asserts zero findings.

The command sets `SET @ledger_backfill_in_progress = 1` in its DB session before any UPDATE on `wallet_ledger`, then unsets it on exit. The MySQL trigger respects this session variable.
