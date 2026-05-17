# Phase 0 — Research: Financial Ledger Hardening

**Date**: 2026-05-15
**Feature**: 028-financial-ledger-hardening
**Status**: Complete — all NEEDS CLARIFICATION resolved before Phase 1.

This document records the decisions taken to resolve every open question that surfaced from `spec.md` and `plan.md`. Each section follows: **Decision → Rationale → Alternatives considered**.

---

## 1. Ledger shape: signed amount vs. direction + unsigned magnitude

**Decision**: `wallet_ledger.amount_minor` becomes **BIGINT UNSIGNED** (magnitude) paired with a non-nullable `direction` ENUM('debit','credit'). The signed running balance lives only in the new `running_balance_minor` column (signed) and in the projected cache on `wallets`.

**Rationale**:
- Aligns with Constitution §III ("Every money column stored as BIGINT UNSIGNED `_minor`") and the existing money convention. Today's `wallet_ledger.amount_minor` is signed BIGINT, which contradicts the constitution and produces ambiguity when reading raw rows.
- Direction-as-enum makes intent explicit at every entry — no need to reason about sign flips in SQL aggregations.
- Double-entry pair enforcement (debits == credits) is trivially expressed as `SUM(amount WHERE direction='debit') = SUM(amount WHERE direction='credit')` per `transaction_group_id`.

**Alternatives considered**:
- *Keep signed BIGINT*: rejected — violates Constitution §III and complicates the balanced-entries DB check.
- *Two columns `debit_minor` / `credit_minor` each nullable*: rejected — nullable money fields are an established anti-pattern; one of the two is always zero and forces every aggregator to coalesce.

**Migration impact**: existing rows have signed `amount_minor`. Backfill command computes `direction = amount_minor > 0 ? credit : debit` and `amount_minor = ABS(amount_minor)`. Backfill runs in batches of 10k inside transactions; idempotent (re-running is a no-op when `direction IS NOT NULL`).

---

## 2. Double-entry chart of accounts: which suspense accounts?

**Decision**: A **minimal closed set of platform suspense accounts** materialised as `wallets` rows with `owner_type='platform_account'` and `owner_id` matching the enum value:

| `SuspenseAccount` enum case | Purpose |
|---|---|
| `gateway_in_transit` | Funds in motion between customer card and platform clearing (between webhook arrival and capture commit) |
| `platform_clearing` | Captured funds awaiting allocation to commission and vendor wallet |
| `platform_commission_receivable` | Commission earned but not yet "realised" (still subject to refund) |
| `platform_commission_realised` | Commission realised after refund window or fulfillment confirmation |
| `platform_refund_payable` | Refund liability owed to customer until gateway refund settles |
| `platform_withdrawal_payable` | Withdrawal liability owed to vendor until bank transfer settles |
| `platform_adjustments` | Manual adjustments by admin (e.g., write-off, goodwill credit) |

Each transaction is balanced across exactly two or more of these accounts plus the vendor wallet. Example: payment capture posts `debit gateway_in_transit, credit platform_clearing`; commission accrual posts `debit platform_clearing, credit vendor_wallet (less commission)` and `debit platform_clearing, credit platform_commission_receivable (commission portion)`.

**Rationale**:
- This is the **smallest set that lets every transaction balance** without requiring on-the-fly account invention.
- Mirrors the structure used by Stripe Connect's ledger model and TigerBeetle's documented examples, adapted for a single-currency marketplace.
- Keeps the chart of accounts inside an enum (a closed set under code review) rather than a free-form admin form, which would invite operational drift.

**Alternatives considered**:
- *Single "platform" suspense account*: rejected — cannot distinguish in-transit, realised, and payable balances, which is precisely the information finance needs for daily reconciliation.
- *Full general ledger with chart-of-accounts admin*: rejected — out of scope for Phase 1.X. Spec's "Out of Scope" section explicitly defers this.
- *Per-payment / per-booking transient accounts*: rejected — explosion of account rows, no operational benefit.

---

## 3. Transaction group enforcement: DB constraint vs. application invariant

**Decision**: Enforce balanced entries via **both**:
1. **Application invariant**: `PostLedgerTransactionAction` rejects any unbalanced group before insert.
2. **Database guard**: a row in `ledger_transaction_groups` carries `total_debits_minor` and `total_credits_minor` columns plus a `CHECK (total_debits_minor = total_credits_minor)` constraint; an AFTER INSERT trigger on `wallet_ledger` increments these aggregates and rejects writes that would unbalance the group within the same transaction.

**Rationale**:
- Belt-and-suspenders. The application invariant is the primary check (clear error messages, testable in PHP). The DB constraint protects against any code path that bypasses the writer (a hotfix, a future module that imports the model directly, an admin SQL session).
- MySQL 8 supports `CHECK` constraints (since 8.0.16) and BEFORE/AFTER INSERT triggers natively. No package needed.

**Alternatives considered**:
- *Application invariant only*: rejected — Constitution §V's spirit ("a wallet credit must be reversible by a counter-entry, never by editing") is best protected by DB-level guards.
- *Stored procedure for ledger insert*: rejected — fragments business logic across PHP and SQL, hard to test.

---

## 4. Idempotency-key scoping and TTL

**Decision**: The existing `idempotency_keys` table is extended with two columns:
- `scope` ENUM('http','internal') NOT NULL — distinguishes inbound HTTP idempotency keys from internal Action idempotency keys.
- `ttl_seconds` INT UNSIGNED NOT NULL — per-row TTL (existing rows default to 86400 seconds = 24h).

Internal Actions use either a 30-day TTL (for monotonic external references like webhooks and refunds) or a 24-hour TTL (for caller-supplied request nonces).

**Rationale**:
- 24h is the right window for HTTP idempotency (per `CLAUDE.md` §11) — beyond that, retries are operator decisions, not client retries.
- 30d is appropriate for webhook deliveries: payment gateways routinely retry for days, and we never want to double-credit a stale webhook that resurfaces.
- Per-row TTL keeps the cleanup job simple (`WHERE created_at < NOW() - INTERVAL ttl_seconds SECOND`).

**Alternatives considered**:
- *Single 7-day TTL globally*: rejected — too short for webhooks, too long for HTTP nonces.
- *Per-table idempotency*: rejected — duplication of cleanup logic.
- *Redis-only idempotency*: rejected — durability matters for financial idempotency; Redis is a cache, not a system of record.

---

## 5. Concurrency primitive: DB row lock vs. Redis lock

**Decision**: **Both**, layered:
1. **DB row lock** (`SELECT ... FOR UPDATE` on the `wallets` row) is the canonical lock during the actual ledger insert + projection. It is held only for the duration of the DB transaction (~milliseconds).
2. **Redis lock** (`SET NX EX 30`) is acquired *before* opening the DB transaction for Actions that involve external I/O inside their critical section (e.g., `ApproveAndMarkWithdrawalPaidAction` which calls the gateway). Redis lock TTL is generous (30s) but the Action carries a deadman: if the gateway call exceeds the TTL, the lock is renewed via watchdog. The lock key is `lock:wallet:{wallet_id}`.

**Rationale**:
- DB row lock is sufficient for pure DB transactions and is the strongest serialisation primitive available.
- Redis lock protects against the rare-but-real case where two workers both want to call the gateway for the same wallet at the same time — the DB lock alone cannot prevent that, because the gateway call sits between two DB transactions.
- Layering matches Sidekiq Pro's "ent_unique" pattern and is the industry default for marketplace settlement workers.

**Alternatives considered**:
- *DB advisory lock (`GET_LOCK`)*: rejected — works in MySQL but ties the lock to the DB connection lifetime, which complicates queue worker recycling.
- *Redis-only*: rejected — Redis is not a system of record; a Redis outage would compromise serialisation.
- *No lock (rely on optimistic concurrency)*: rejected — too easy to deadlock under contention, and "rejected for insufficient funds" is the correct outcome for the loser of the race, not a retry.

---

## 6. Append-only enforcement: MySQL trigger

**Decision**: Install a MySQL trigger on `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, and `reconciliation_findings` that raises a SIGNAL on any UPDATE or DELETE, with a documented bypass mechanism for the backfill command only:

```sql
CREATE TRIGGER tr_wallet_ledger_no_update BEFORE UPDATE ON wallet_ledger
FOR EACH ROW
BEGIN
  IF @ledger_backfill_in_progress IS NULL OR @ledger_backfill_in_progress != 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wallet_ledger is append-only';
  END IF;
END;

CREATE TRIGGER tr_wallet_ledger_no_delete BEFORE DELETE ON wallet_ledger
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wallet_ledger is append-only';
END;
```

The backfill command sets `SET @ledger_backfill_in_progress = 1` for its own session only, scoped to the explicit one-shot migration window. The session variable is **never** set outside the backfill command — a Pest assertion in `BackfillLedgerColumnsTest` verifies this.

**Rationale**:
- Constitution §V demands these tables be immutable. Application-layer protection is insufficient — a stray manual SQL session or a buggy migration would silently violate the invariant. The trigger makes it impossible.
- Session variable bypass is the standard pattern (used in PostgreSQL with `SET LOCAL` and in MySQL with `SET @var`). It is opt-in, explicit, and auditable.

**Alternatives considered**:
- *Eloquent model events only*: rejected — does not protect against raw DB writes.
- *Database role with read-only access on those tables*: rejected — Laravel's connection pool reuses a single DB user; switching users mid-request is heavyweight.
- *Soft enforcement (log + alert)*: rejected — the cost of an undetected mutation is too high.

---

## 7. Reconciliation cadence and scope

**Decision**:
- **Scheduled run**: every 60 minutes, scope = "all wallets touched in the last 60 minutes + a random sample of 100 untouched wallets". Daily at 04:00 Cairo time, scope = "all wallets".
- **On-demand run**: admin can trigger scope = "single wallet", "single vendor", "all wallets", or "date range". The trigger endpoint requires Idempotency-Key and admin role.
- **Concurrency**: only one reconciliation per scope at a time, enforced by a Redis lock `lock:reconcile:{scope_hash}` with a 30-minute TTL and watchdog renewal.

**Rationale**:
- Hourly recent-touch reconciliation gives near-real-time drift detection without overwhelming the DB.
- Daily full sweep catches anything the hourly run missed (e.g., a stale untouched wallet whose cache was perturbed by a migration).
- 04:00 Cairo is a low-traffic window for Egyptian commerce; minimises contention with hot-path workloads.

**Alternatives considered**:
- *Continuous reconciliation (per-write)*: rejected — re-runs the projection every write, which is what the cache is for; defeats the purpose.
- *Daily-only*: rejected — leaves a 24-hour window for drift to go undetected.
- *Per-request reconciliation on balance reads*: rejected — turns every balance read into a ledger replay; performance regression.

---

## 8. Financial snapshot cadence

**Decision**: Daily per-wallet snapshot at 04:30 Cairo time (after the daily full reconciliation), anchored to the highest `wallet_ledger.id` for that wallet at snapshot time. Snapshots older than 90 days are retained but their per-wallet "current" pointer advances forward — the historical snapshots are kept for forensic queries.

**Rationale**:
- Daily granularity balances query speedup (no full-ledger replay needed for "as of yesterday" or earlier) against storage cost.
- 90-day retention is below the typical regulatory retention threshold but above the operational use case (finance reconciliation rarely looks back further than 90 days).
- Anchoring to a specific ledger id (rather than a timestamp) makes "reconstruct balance at snapshot time" deterministic — no clock-skew ambiguity.

**Alternatives considered**:
- *Hourly snapshots*: rejected — 24× storage cost for marginal benefit at current volume.
- *Snapshot only on demand*: rejected — defeats the snapshot's purpose, which is to accelerate routine reconciliation.

---

## 9. Correlation and causation ID format

**Decision**: Both correlation and causation IDs are **ULIDs** (26-char Crockford base32). Correlation ID is generated at the boundary of every external trigger (HTTP request, webhook, scheduled job, queue worker job) and propagated via a Laravel `Context` (Laravel 12 built-in) so every downstream Action sees it without explicit threading. Causation ID is set per-Action to the originating event's identifier.

**Rationale**:
- ULIDs are already the canonical external identifier in this project (Constitution + `CLAUDE.md` §5 — `public_id` is ULID). Reusing the format avoids inventing a parallel scheme.
- Laravel 12 `Context` solves the "thread the value through 12 method calls" problem cleanly; was added precisely for this kind of cross-cutting concern.
- ULIDs are time-sortable, which makes causal-chain queries naturally chronological.

**Alternatives considered**:
- *UUIDv4*: rejected — not time-sortable, harder to debug.
- *Manual middleware-set request id*: rejected — works for HTTP but breaks down at queue/scheduler boundaries.

---

## 10. Webhook idempotency: how to derive the deterministic key

**Decision**: Paymob webhook idempotency key = `paymob:{event_type}:{order_id}:{transaction_id}:{success}`. Stored in `idempotency_keys` with scope='internal' and 30-day TTL. The handler first looks up by this key; if found and status='completed', it returns the original response. If found and status='in_progress', it 409s. If not found, it inserts a row with status='in_progress' under a unique constraint, processes the webhook, and updates to 'completed' inside the same DB transaction.

**Rationale**:
- The four fields collectively identify a unique gateway event. Including `success` distinguishes a success and a later failure for the same transaction (rare but possible during retries).
- Existing `idempotency_keys` schema supports this with minimal additions.

**Alternatives considered**:
- *Use Paymob's `hmac`*: rejected — HMAC verifies authenticity, not uniqueness; same payload can have different HMACs if the gateway rotates secrets.
- *Use the body hash*: rejected — payload variations across retries (e.g., differing timestamps) would generate different keys.

---

## 11. Rollout strategy: how to switch on without data loss

**Decision**: Three-stage rollout, each gated by a feature flag in `feature_flags` (existing table):

1. **Stage A — Shadow writes (1 week)**: New columns added (nullable). New `PostLedgerTransactionAction` is wired into every Action *in addition to* the existing `incrementBalance`/`decrementBalance` calls. Both paths run inside the same DB transaction. A new artisan command `php artisan ledger:diff` compares projected balance to cached balance for every wallet and reports drift. Goal: zero drift across the shadow week.
2. **Stage B — Cut-over (1 day, with rollback)**: The `incrementBalance`/`decrementBalance` calls are removed from Actions; cached balance is updated solely by `ProjectWalletBalanceAction`. The MySQL append-only trigger is installed. Rollback path: re-enable the legacy path via feature flag (the data is still consistent because shadow writes covered the entire window).
3. **Stage C — Cleanup (after 30 days clean)**: Old `entry_type` enum values that are no longer used are deprecated (not dropped — append-only). `EloquentWalletRepository::incrementBalance`/`decrementBalance` methods are deleted. Architecture test enforces no resurrection.

**Rationale**:
- Shadow writes catch any divergence before cut-over without risk.
- Cut-over is atomic per-Action and reversible via the feature flag for the first 24 hours.
- Cleanup is delayed long enough to validate clean operation under production load.

**Alternatives considered**:
- *Big-bang cut-over*: rejected — too much risk of silent drift.
- *Strangler pattern with new Action names*: rejected — would leave both names in the codebase for months; the per-Action refactor is more surgical.

---

## 12. Reconciliation finding severity model

**Decision**: Three severity levels:
- **`info`**: e.g., snapshot created, run completed clean.
- **`warning`**: cache drift detected and auto-repaired (system self-healed).
- **`high`**: structural anomaly (orphaned refund row, missing commission rate, transaction group with unbalanced entries) — auto-notifies admins.

Findings with severity `high` trigger a `ReconciliationFindingRaised` event whose listener dispatches an admin notification via the existing `DispatchNotificationAction`. No customer or vendor notification on any finding.

**Rationale**:
- Three levels are enough for ops triage; more levels invite inconsistent labelling.
- Auto-repair of cache drift is safe (the ledger is the source of truth) and routine — it shouldn't page anyone, but it should be logged.
- Structural anomalies require human judgment and MUST surface immediately.

**Alternatives considered**:
- *Two levels (ok/not-ok)*: rejected — loses the distinction between "system fixed itself" and "human needed".
- *Five levels (debug/info/warning/error/critical)*: rejected — overkill for this surface area.

---

## 13. Tests for concurrency: how to actually run them

**Decision**: Pest tests in the `concurrency` group use `pcntl_fork` (PHP CLI extension, available in the CI image already) to fork N child processes each making the same Action call. Parent collects results and asserts the expected outcome (e.g., 1 success + N-1 rejections). For Windows dev (Ibrahim's primary), the test is skipped with a clear `markTestSkipped('pcntl required')` and CI runs it under Linux.

**Rationale**:
- `pcntl_fork` is the simplest available primitive for true parallelism in PHP tests.
- Threading via parallel test groups in Pest's `--parallel` mode does **not** test concurrency on the same row — those tests run on isolated databases.
- The `pestphp/pest-plugin-stressless` plugin would be ideal but is not in `10_Package_List.md` — skipped per Constitution.

**Alternatives considered**:
- *Pest `--parallel`*: rejected — runs on separate DBs, doesn't exercise actual lock contention.
- *Shell `xargs -P`*: rejected — works but is harder to assert against from Pest.
- *Add `pestphp/pest-plugin-stressless`*: rejected — would require a package list update; the fork approach is sufficient.

---

## 14. Backfill ordering and safety

**Decision**: Backfill runs in this fixed order:
1. Add nullable columns to `wallet_ledger`, `wallets`, `withdrawals`, `commissions`, `payments`, `refunds`.
2. Create new tables (`ledger_transaction_groups`, `financial_snapshots`, `reconciliation_runs`, `reconciliation_findings`).
3. Run `php artisan ledger:backfill` — for every existing `wallet_ledger` row, create a synthetic `ledger_transaction_groups` row (one group per row, marked `kind='legacy_backfill'`), set `direction` based on `amount_minor` sign, set `correlation_id` and `transaction_group_id` to fresh ULIDs (no historical correlation reconstructable), and rewrite `amount_minor` to its absolute value. Runs in batches inside a single DB transaction per batch, idempotent.
4. Run `php artisan ledger:diff` and confirm zero drift.
5. Install the MySQL append-only trigger.
6. Flip the feature flag for Stage B cut-over.

**Rationale**:
- Trigger installation is **last** so the backfill itself doesn't trip it.
- Synthetic single-row transaction groups for legacy entries preserves the new invariant (every entry belongs to a group) without trying to reconstruct historical causation that no longer exists.
- Each step is independently reversible up to step 5.

**Alternatives considered**:
- *Backfill historical correlation IDs by joining to event_outbox*: rejected — partial coverage; would leave gaps and create false confidence.
- *Skip backfill for legacy rows*: rejected — would leave a permanent shape inconsistency in the table.

---

## 15. Admin endpoint surface — minimal vs. richer

**Decision**: Exactly two endpoints, both gated by `admin` middleware and the `audit.view` permission:
- `GET /admin/reconciliation/status` → latest run status, summary counts, recent findings.
- `POST /admin/reconciliation/trigger` (Idempotency-Key required) → enqueues a reconciliation run with optional scope (`wallet_id`, `vendor_id`, `date_from`, `date_to`).

Returns standard `ApiResponse` envelope (`{ data, meta, errors }`) per Constitution. Bilingual via the Resource layer.

**Rationale**:
- The endpoints exist to support a future ops dashboard or external alerting. Adding more endpoints (e.g., individual finding resolution) is in scope only via Filament for now — keeps the HTTP surface tiny.

**Alternatives considered**:
- *No HTTP endpoints, Filament only*: rejected — operators want webhook-able triggers; the POST endpoint is needed for that.
- *Many endpoints (per-finding CRUD, per-run cancel, etc.)*: rejected — Phase 2 scope.

---

## All NEEDS CLARIFICATION resolved. Phase 1 (Design & Contracts) follows.
