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
- FR traceability: If the feature maps to existing PRD coverage → cite specific FR numbers from 01_PRD.md. If the feature is NEW or extends beyond the PRD → define local requirement numbers prefixed FR-EXT-NNN and add a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: If using an existing table → cite its name from 11_DB_Schema.md. If this feature introduces NEW tables → list them explicitly with a "⚠️ NEW TABLE — not yet in 11_DB_Schema.md" marker.
- Phase alignment: If the feature belongs to an existing phase → cite the Phase ID from 09_Phasing_Plan.md. If the feature is new work not yet phased → propose a Phase ID extension (e.g., Phase 1.X) and add a "⚠️ PHASE BACKFILL NEEDED" note.
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

# Feature Specification: Financial Ledger Hardening

**Feature Branch**: `028-financial-ledger-hardening`
**Created**: 2026-05-15
**Status**: Draft
**Input**: User description: "Harden the financial architecture of the marketplace. Refactor Wallets, Commissions, Withdrawals, Refunds, Settlements, and Payments into immutable, append-only, ledger-based accounting. Audit money mutations, remove direct balance updates, derive balances from ledger entries, add debit/credit semantics, trace/causation/correlation IDs, idempotency enforcement, reconciliation jobs, financial snapshots, double-entry accounting where appropriate, database constraints, concurrency protection, reconciliation command, and comprehensive concurrency/duplicate/partial-refund/settlement-failure tests. Critical: no financial data loss allowed."

## Traceability & Scope

- **Phase ID**: Proposed **Phase 4.9 – Financial Ledger Hardening**, immediately after Phase 4 (Payments + Settlement) and before Phase 5 (Communications). ⚠️ PHASE BACKFILL NEEDED: `docs/specs/09_Phasing_Plan.md` lists Phase 4 as the settlement build-out but does not yet contain a dedicated hardening phase for the financial subsystem. This work re-enters Phase 4 territory because critical money-integrity invariants were left implicit during the original build.
- **PRD coverage**: PRD §6 (Booking) and §7 (Payments / Wallets / Settlement / Withdrawals) describe the financial flows but do not enumerate ledger-integrity invariants, reconciliation behaviour, idempotency obligations, or correlation/causation traceability. ⚠️ BACKFILL NEEDED: add a "Financial Integrity & Reconciliation" subsection to `docs/specs/01_PRD.md` §7 that absorbs FR-EXT-101…FR-EXT-130 below.
- **Local requirements**: `FR-EXT-101` through `FR-EXT-130` defined in this spec.
- **Schema traceability**:
  - **Existing tables touched** (from `docs/specs/11_DB_Schema.md`): `wallets`, `wallet_ledger`, `commissions`, `commission_rates`, `withdrawals`, `settlement_runs`, `payments`, `payment_attempts`, `refunds`, `idempotency_keys`, `gateway_webhook_logs`, `audit_logs`, `event_outbox`, `bookings`, `booking_items`, `booking_state_transitions`.
  - **Schema changes proposed**:
    - `wallets`: stored balance columns (`balance_available_minor`, `balance_pending_minor`, `balance_reserved_minor`) become **derived/materialised cache columns** populated only by ledger projection, never by direct application writes. A non-nullable `last_ledger_entry_id` foreign key plus `last_projected_at` are added so cache freshness is verifiable. ⚠️ SCHEMA CHANGE — `11_DB_Schema.md` documents these columns as primary-state, not cache.
    - `wallet_ledger`: add columns `direction` ENUM('debit','credit'), `counter_account_type`, `counter_account_id`, `transaction_group_id` (CHAR(26) ULID, NOT NULL), `correlation_id` (CHAR(26) ULID, NOT NULL), `causation_id` (CHAR(26) ULID, NULL), `idempotency_key` (VARCHAR(128), NULL, UNIQUE per `(wallet_id, idempotency_key)`), `posted_at` (timestamp), and `running_balance_minor` (BIGINT, signed). Existing `amount_minor` becomes the unsigned magnitude paired with `direction`. ⚠️ NEW COLUMNS — not yet in `11_DB_Schema.md` §`wallet_ledger`.
    - **NEW TABLE `ledger_transaction_groups`** ⚠️ NEW TABLE — not yet in `11_DB_Schema.md`. Groups every set of ledger entries that together form one balanced double-entry transaction, with columns `id`, `public_id` (ULID), `kind` (ENUM: payment_capture, refund, commission_accrual, commission_reverse, withdrawal_reserve, withdrawal_settle, withdrawal_reject, manual_adjustment, …), `correlation_id`, `causation_id`, `idempotency_key`, `initiated_by_user_id`, `initiated_at`, `description`, and a denormalised `total_debits_minor` / `total_credits_minor` (which MUST be equal — enforced by DB constraint).
    - **NEW TABLE `financial_snapshots`** ⚠️ NEW TABLE — periodic per-wallet point-in-time balance snapshots used to accelerate balance reconstruction and as the authoritative reconciliation anchor. Columns: `id`, `wallet_id`, `snapshot_at`, `as_of_ledger_entry_id`, `available_minor`, `pending_minor`, `reserved_minor`, `currency`, `checksum`.
    - **NEW TABLE `reconciliation_runs`** ⚠️ NEW TABLE — one row per reconciliation execution (scheduled or manual) recording start/end, scope, anomalies detected, repairs applied, and a final status (`clean`, `anomalies_detected`, `repaired`, `requires_manual_review`).
    - **NEW TABLE `reconciliation_findings`** ⚠️ NEW TABLE — child of `reconciliation_runs`; one row per anomaly with `finding_type`, `severity`, `resource_type`, `resource_id`, `expected`, `actual`, `delta`, `resolution` (`auto_repaired`, `manual_review_required`, `ignored_known_issue`), and `resolved_at`.
    - `withdrawals`: add `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id` foreign keys; add `idempotency_key` (UNIQUE per `vendor_profile_id`). Status flow stays append-only via existing pattern.
    - `commissions`: add `accrual_ledger_entry_id`, `reversal_ledger_entry_id` to bind commission rows to the canonical ledger entries.
    - `payments`: add `capture_ledger_entry_id`, `refund_ledger_entry_id` to bind payment outcomes to ledger entries; introduce `correlation_id` if not present.
    - `refunds`: add `ledger_entry_id` linking each refund to its ledger entry.
    - `idempotency_keys`: extend usage to cover all internal money-moving Actions (not just HTTP endpoints).
- **ADR required**: **ADR-0028 Financial Ledger Hardening — Append-Only Double-Entry Accounting**, capturing (a) the decision to make `wallet_ledger` the sole source of truth, (b) stored balances becoming derived cache, (c) double-entry semantics with platform-suspense accounts, (d) correlation/causation/transaction-group identifiers, (e) reconciliation contract, and (f) concurrency invariants.
- **API impact**: No external customer or vendor API contracts change. Two internal admin-only endpoints are added (read-only reconciliation status + trigger manual reconciliation), gated by the existing admin role and Idempotency-Key header on the trigger endpoint. Vendor wallet balance read endpoints continue to return the same shape, sourced from the projected cache (which is now provably consistent with the ledger).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Wallet Balance Cannot Drift From Ledger (Priority: P1)

The finance lead must be able to point at any vendor wallet at any moment in time and be confident that the displayed balance is exactly the sum of the immutable ledger entries belonging to that wallet, with zero possibility of drift caused by application code, partial failures, concurrent writes, or duplicate events.

**Why this priority**: The current implementation maintains two parallel sources of truth — stored balances on `wallets` and the `wallet_ledger`. Any divergence is silent financial corruption and is the highest possible risk in a marketplace that pays vendors real money. Eliminating the divergence is the entire reason this work exists.

**Independent Test**: A single seeded vendor wallet is taken through every money-moving operation (capture, refund, commission accrual, commission reversal, withdrawal reserve, withdrawal settle, withdrawal reject, manual adjustment). After each operation, the displayed balance equals the signed sum of all ledger entries for that wallet, and an independently-recomputed reconciliation report shows zero drift.

**Acceptance Scenarios**:

1. **Given** a vendor wallet at zero balance, **When** a sequence of captures, commissions, refunds, and withdrawals is executed, **Then** at every step the displayed available/pending/reserved balances equal the corresponding signed sum of ledger entries for that wallet.
2. **Given** an attempt to update a wallet's stored balance without a matching ledger entry, **When** the write is issued, **Then** the system rejects the write and records the attempt for investigation rather than silently corrupting the balance.
3. **Given** a wallet whose stored balance is artificially perturbed (e.g., by a bad DB migration or a manual hotfix), **When** reconciliation runs, **Then** the perturbation is detected, reported, and automatically repaired by re-projecting from the ledger, with the repair captured in an immutable audit trail.

---

### User Story 2 — Duplicate Payment Webhook Never Double-Credits (Priority: P1)

A payment gateway operator (or a network retry) can deliver the same successful-payment webhook two, five, or twenty times. The marketplace must capture funds exactly once per booking and credit the vendor wallet exactly once, no matter how many duplicates arrive or how closely spaced they are.

**Why this priority**: Duplicate webhooks are routine in payment gateway integrations and are the most common cause of accidental over-crediting in marketplace ledgers. A single duplicate that slips through is a financial loss to the platform.

**Independent Test**: The same payment webhook payload is delivered concurrently and serially against the same payment record. Exactly one ledger transaction group is created; the vendor wallet is credited exactly once; subsequent duplicate deliveries are acknowledged successfully but produce no additional state change.

**Acceptance Scenarios**:

1. **Given** a paid Paymob webhook, **When** the same webhook is delivered ten times sequentially, **Then** exactly one capture-ledger transaction group is created, the payment row is captured exactly once, and the vendor wallet is credited exactly once.
2. **Given** a paid Paymob webhook, **When** twenty identical deliveries arrive concurrently from queued workers, **Then** the system serialises them safely and still produces exactly one capture transaction group with the wallet credited exactly once.
3. **Given** a capture has already succeeded, **When** the same webhook arrives a week later, **Then** the system returns success without producing any new ledger entry or any change to wallets, commissions, or payment records.

---

### User Story 3 — Concurrent Withdrawal Requests Never Overdraw The Wallet (Priority: P1)

A vendor with a balance of 1,000 EGP must never be able to start two withdrawal requests for 1,000 EGP each at the same instant and have both succeed. The system must serialise withdrawal decisions per wallet and reject any request that would push the wallet's available balance below zero, regardless of concurrency.

**Why this priority**: Race conditions between concurrent withdrawal initiations are the canonical financial-corruption bug in marketplaces. Allowing even one overdraw is a real cash loss to the platform.

**Independent Test**: Two withdrawal requests for the full available balance are submitted in parallel against a single vendor wallet. Exactly one succeeds; the other is rejected with a clear "insufficient available balance" outcome; the wallet ends with zero available balance; the ledger contains exactly one withdrawal-reserve transaction group.

**Acceptance Scenarios**:

1. **Given** a vendor wallet with 1,000 EGP available, **When** two withdrawal requests for 1,000 EGP each are submitted concurrently, **Then** exactly one is accepted and the other is rejected for insufficient funds, and the ledger contains exactly one reserve transaction group.
2. **Given** a vendor wallet with 1,000 EGP available and a 700 EGP reserved withdrawal, **When** the vendor requests a further 400 EGP withdrawal, **Then** the request is rejected because available-minus-reserved is below 400, and the rejection is observable to the vendor without exposing internal ledger detail.
3. **Given** an approved withdrawal, **When** the admin marks it paid, **Then** the reserved balance moves to a settled outflow via a single ledger transaction group atomically and the wallet's available balance is unchanged because the funds were already reserved.

---

### User Story 4 — Partial And Repeated Refunds Stay Inside The Captured Total (Priority: P1)

Customers and admins may issue multiple partial refunds against a single booking. The sum of all refunds — across attempts, retries, gateway failures, and duplicate webhook callbacks — must never exceed the captured total, and the corresponding commission and vendor-wallet debits must stay perfectly aligned with the refunded amounts.

**Why this priority**: Partial-refund accounting is where most marketplace ledgers drift in production. A 1 EGP over-refund per booking compounds quickly across thousands of bookings.

**Independent Test**: A single 1,000 EGP captured payment is subjected to three partial refunds (300, 400, 300) issued through a mix of successful and retried gateway calls. The captured-minus-refunded total reaches zero with no over-refund possible. Commission reversals match each partial refund. The vendor wallet debit matches the net commission impact.

**Acceptance Scenarios**:

1. **Given** a captured payment of 1,000 EGP, **When** partial refunds of 300, 400, and 300 are issued successfully, **Then** the sum of refunds equals 1,000 EGP and a fourth refund attempt for any positive amount is rejected as over-refund.
2. **Given** a successful 300 EGP refund whose gateway callback arrives twice, **When** both callbacks are processed, **Then** exactly one refund ledger transaction group is created and the refund row is finalised exactly once.
3. **Given** a partial refund of 300 EGP, **When** the corresponding commission reversal runs, **Then** the commission reversal amount equals the snapshot commission rate applied to 300 EGP and the vendor wallet is debited by exactly that amount.

---

### User Story 5 — Forensic Trace From Any Ledger Entry Back To Its Origin (Priority: P2)

When the finance team or an auditor opens any ledger entry, they must be able to walk the full causal chain backwards (this entry was caused by that ledger entry, which was caused by that webhook, which was triggered by that booking event) and forwards (this entry caused these downstream entries) without piecing together joins by hand.

**Why this priority**: Forensic-grade traceability is essential for fraud investigations, regulator inquiries, and customer disputes. It is also the foundation that lets every other reconciliation feature actually work.

**Independent Test**: Pick any ledger entry. From its identifiers alone, retrieve (a) the original triggering event, (b) every preceding ledger entry that caused it, (c) every downstream ledger entry it caused, and (d) all related domain records (payment, booking, commission, withdrawal) — without writing any custom SQL.

**Acceptance Scenarios**:

1. **Given** a commission accrual ledger entry, **When** the auditor inspects it, **Then** they can see the originating payment capture, the booking, the booking item, the commission rate snapshot, and the user (or system actor) that initiated the chain.
2. **Given** a withdrawal-settle ledger entry, **When** the admin clicks "show full causal chain", **Then** the chain shows the original reserve entry, the approval action, the admin user, and the bank-transfer reference.
3. **Given** a reconciliation finding that flags a wallet drift, **When** the finance lead inspects the finding, **Then** the finding lists the suspect ledger entries with their full correlation and causation context so the root cause can be diagnosed.

---

### User Story 6 — Reconciliation On Demand And On Schedule (Priority: P2)

A finance administrator must be able to trigger a full reconciliation pass at any time, and the system must also run reconciliation automatically on a predictable schedule. Every run produces a clear report listing any anomalies, any auto-repairs applied, and any items that need manual review.

**Why this priority**: Even with the strongest invariants, finance teams need an explicit "prove it" pass and a tracked record of the system's financial health over time.

**Independent Test**: Trigger reconciliation manually after seeding a deliberate drift in one wallet and a deliberate orphaned refund row. The run produces a finding for each anomaly, auto-repairs the wallet drift, flags the orphaned refund for manual review, and writes an immutable run record visible to the admin.

**Acceptance Scenarios**:

1. **Given** the daily scheduled reconciliation, **When** all wallets reconcile cleanly, **Then** the run is recorded with status "clean" and no findings.
2. **Given** a manually triggered reconciliation in a wallet that has drifted, **When** the run completes, **Then** the drift is reported as a finding, the wallet is re-projected from the ledger, the repair is recorded, and the wallet's projected balance equals the signed ledger sum.
3. **Given** an orphaned refund row (a refund with no corresponding ledger entry, or a ledger entry with no refund row), **When** reconciliation runs, **Then** the orphan is flagged for manual review with severity "high" rather than auto-deleted, and the admin can act on it from the admin panel.

---

### User Story 7 — Settlement Run Failures Are Recoverable Without Data Loss (Priority: P2)

A scheduled settlement run that processes hundreds of vendor payouts may fail partway (DB timeout, gateway outage, deploy interruption). When this happens, every successful payout must already be durably recorded, no payout is duplicated on retry, and the next run cleanly resumes the unfinished work.

**Why this priority**: Settlement runs are the single most expensive recurring operation in the financial subsystem. A failed run that needs hand-cleanup is hours of operator time at best and double-payouts at worst.

**Independent Test**: A settlement run is simulated to crash after processing half of a batch. The crashed run is re-executed; only the remaining payouts are processed; no duplicate payouts occur; the final ledger state matches the expected post-run state exactly.

**Acceptance Scenarios**:

1. **Given** a settlement run that crashes after partial completion, **When** the run is retried, **Then** the already-settled withdrawals are skipped via idempotency and only the remaining ones are processed.
2. **Given** a settlement run that encounters one failing payout in the middle of a batch, **When** the run continues, **Then** the failing payout is recorded as failed for manual review and the run does not roll back the successful payouts.
3. **Given** a settlement run, **When** it finishes, **Then** the run record links every settled withdrawal to a ledger transaction group, and reconciliation immediately afterwards reports zero drift.

---

### User Story 8 — Money Mutations Audit Is Discoverable And Continuous (Priority: P3)

An engineering lead or auditor must be able to enumerate every line of application code that mutates a financial row, see a verdict on whether each mutation is ledger-correct, and have CI block new violations from being merged.

**Why this priority**: One-time audits decay. A persistent, enforceable inventory is what stops the next quarter's hotfix from re-introducing direct balance updates.

**Independent Test**: An auditor runs the inventory and gets a list of all money-mutation call sites, each tagged "ledger-routed" or "direct" with file:line. After this feature ships, the inventory contains zero "direct" entries, and an attempt to merge a pull request introducing a new direct update is rejected by CI.

**Acceptance Scenarios**:

1. **Given** the codebase as it is today, **When** the inventory is generated, **Then** every direct balance write, every increment/decrement on financial fields, and every unsafe refund/commission calculation is listed with file path and line number.
2. **Given** the post-refactor codebase, **When** the inventory is regenerated, **Then** the only entries it contains are inside the canonical ledger-projection layer (the single place stored balances are allowed to be written).
3. **Given** a developer adding a new money-mutating Action, **When** they introduce a direct balance update, **Then** CI rejects the pull request with a clear pointer to the ledger-routing pattern.

---

### Edge Cases

- A payment is captured but the wallet credit fails partway through the transaction — system MUST roll back atomically so neither the capture nor the credit is observable; webhook MUST be retriable safely.
- A wallet receives a credit in a currency that does not match its declared currency — system MUST reject the credit before any partial state is written.
- A reconciliation run is triggered while another reconciliation is already in progress — the second run MUST wait, or be deduplicated, but MUST NOT proceed concurrently against the same wallets.
- A vendor wallet's stored cache row is deleted by a buggy migration — the next read MUST re-project the wallet from the ledger rather than reporting zero balance.
- A withdrawal is approved by an admin and then the admin user is deleted — the ledger entry's audit chain MUST remain interpretable (foreign keys to users use `restrictOnDelete` and historical user references stay readable).
- A platform-level adjustment moves funds between platform suspense accounts — the double-entry pair MUST balance and MUST NOT touch any vendor wallet.
- A refund is requested for an amount that, combined with prior refunds, exceeds the captured total by 1 piastre due to rounding — the system MUST reject the refund and log a clear "over-refund attempted" finding.
- A duplicate idempotency key arrives with a different payload — the system MUST reject the second call as a conflict rather than silently returning the previous result.
- A reconciliation finding is repaired automatically and the repair itself fails — the failure MUST be recorded, the wallet's cache MUST be marked stale, and the next reconciliation MUST re-attempt.
- An append-only ledger entry is targeted by an UPDATE or DELETE statement (e.g., through Eloquent or a manual SQL fix) — the database itself MUST reject the write.

## Requirements *(mandatory)*

### Functional Requirements

#### Ledger Integrity & Single Source Of Truth

- **FR-EXT-101**: System MUST treat `wallet_ledger` as the sole authoritative record of money movement. No application code path may produce a change in a vendor or platform balance without writing a corresponding ledger entry in the same database transaction.
- **FR-EXT-102**: System MUST forbid UPDATE and DELETE on any row in `wallet_ledger`, `payments` (except documented status-only updates), `commissions`, `refunds` ledger linkage, `settlement_runs` post-completion, and any new ledger-style table introduced by this feature. The prohibition MUST be enforced both at the application layer (model events / repository contracts) and at the database layer (triggers or equivalent constraints).
- **FR-EXT-103**: System MUST treat the stored balance columns on `wallets` as a materialised projection cache of the ledger. The cache MUST be updated only by the canonical ledger-projection routine, which executes inside the same transaction as the ledger insert.
- **FR-EXT-104**: System MUST expose, for every wallet, a deterministic recomputation routine that reproduces the available, pending, and reserved balances from the immutable ledger alone, without consulting the cache.
- **FR-EXT-105**: System MUST tag every ledger entry with an explicit direction (debit or credit), an entry type (e.g., payment_capture, refund, commission_accrual, commission_reversal, withdrawal_reserve, withdrawal_settle, withdrawal_reject, manual_adjustment), and a non-null transaction group identifier.

#### Double-Entry Accounting

- **FR-EXT-106**: System MUST group every money movement into a transaction group whose constituent ledger entries balance to zero — total debits equal total credits in the same currency.
- **FR-EXT-107**: System MUST maintain a small set of platform suspense accounts (at minimum: platform_clearing, platform_commission_receivable, platform_refund_payable, platform_withdrawal_payable, gateway_in_transit) sufficient to balance every transaction type without inventing on-the-fly accounts.
- **FR-EXT-108**: System MUST enforce, at the database level, that every transaction group satisfies the balanced-entries invariant before it is considered committed.

#### Idempotency Enforcement

- **FR-EXT-109**: System MUST require, for every money-mutating Action (CapturePaymentAction, ProcessRefundAction, CreditWalletAction, DebitWalletAction, RequestWithdrawalAction, ApproveAndMarkWithdrawalPaidAction, RejectWithdrawalAction, CalculateCommissionAction, ReverseCommissionAction, SettlementRunAction, and any new equivalent), an explicit idempotency key. The key MUST be either supplied by the caller or deterministically derived from the inbound event (e.g., gateway webhook id + payment id).
- **FR-EXT-110**: System MUST persist idempotency results so duplicate calls within the documented window return the original outcome without producing additional ledger entries or domain side effects.
- **FR-EXT-111**: System MUST detect idempotency-key collisions where the payload differs from the original call and respond as a conflict rather than returning the original outcome.
- **FR-EXT-112**: System MUST apply idempotency to internal events as well as inbound HTTP webhooks — the same protection applies to queue retries, manual operator reruns, and replay tooling.

#### Trace, Causation, And Correlation Identifiers

- **FR-EXT-113**: System MUST stamp every ledger entry, every transaction group, every outbox event, and every audit-log row with a correlation identifier shared across the full causal chain originating from a single external trigger (webhook, customer action, admin action, scheduled job).
- **FR-EXT-114**: System MUST stamp every internally-derived ledger entry with a causation identifier pointing at the immediate cause (the entry, event, or command that directly triggered it).
- **FR-EXT-115**: System MUST allow forward and backward traversal of the causal graph from any ledger entry, transaction group, or domain row that participates in the financial subsystem.

#### Concurrency Protection

- **FR-EXT-116**: System MUST serialise money-mutating decisions on a single wallet so that no two transactions can simultaneously compute "available balance" and commit conflicting writes against the same wallet. Serialisation MUST use a combination of database row-level locking and an application-level distributed lock (Redis) where row-locking is insufficient (e.g., long-running settlement runs that span batches).
- **FR-EXT-117**: System MUST recompute available balance inside the locked critical section immediately before authorising a withdrawal or other debit, never relying on a previously-read cached balance.
- **FR-EXT-118**: System MUST detect lock acquisition timeouts and reject the operation with a clear retryable outcome rather than blocking indefinitely.

#### Reconciliation

- **FR-EXT-119**: System MUST provide a reconciliation routine, runnable both on a schedule and on operator demand, that:
  (a) recomputes every wallet's balances from the ledger,
  (b) compares them to the cached balances,
  (c) detects orphaned domain rows (payment without ledger entry, ledger entry without payment, refund without ledger entry, commission without snapshot rate, withdrawal without reserve entry),
  (d) auto-repairs the wallet cache from the ledger when the only inconsistency is in the cache,
  (e) flags structural orphans for manual review without auto-deleting them,
  (f) records every step in immutable `reconciliation_runs` / `reconciliation_findings` rows.
- **FR-EXT-120**: System MUST guarantee that two reconciliation runs cannot execute concurrently against the same scope.
- **FR-EXT-121**: System MUST allow an admin to view the most recent reconciliation run, its findings, and the historical timeline of past runs through the admin panel.

#### Financial Snapshots

- **FR-EXT-122**: System MUST persist periodic per-wallet point-in-time snapshots (`financial_snapshots`) anchored to a specific ledger entry id so that balance reconstruction does not need to scan the entire ledger from the beginning of time once volume grows.
- **FR-EXT-123**: System MUST be able to reconstruct a wallet's balance "as of" any historic moment by combining the nearest preceding snapshot with the ledger entries between that snapshot and the requested moment.

#### Refactor Of Existing Actions

- **FR-EXT-124**: System MUST refactor `CapturePaymentAction`, `ProcessRefundAction`, `CreditWalletAction`, `DebitWalletAction`, `RequestWithdrawalAction`, `ApproveAndMarkWithdrawalPaidAction`, `RejectWithdrawalAction`, `ReverseCommissionAction`, `CalculateCommissionAction`, and the settlement run orchestration to route all money mutations through the ledger writer with explicit idempotency, locking, and balanced transaction groups. No direct `incrementBalance`/`decrementBalance` calls remain in any of these paths after refactor.
- **FR-EXT-125**: System MUST preserve all existing observable behaviours of those Actions — same domain events fire, same audit logs are written, same responses are returned to callers — while replacing their internal mutation strategy.

#### Audit, Tooling, And Enforcement

- **FR-EXT-126**: System MUST provide a one-shot inventory tooling command that enumerates, with file:line, every code path that touches a financial column (whether through ledger or directly), and labels each one as ledger-routed or direct.
- **FR-EXT-127**: System MUST provide a continuous enforcement mechanism (static analysis rule or equivalent) that fails CI when a new pull request introduces a direct balance write outside the canonical ledger-projection layer.
- **FR-EXT-128**: System MUST provide a reconciliation administrative command (artisan console command) that can be run manually with scope flags (single wallet, single vendor, all wallets, single date range) and that produces structured output suitable for finance review.
- **FR-EXT-129**: System MUST provide a small admin-only HTTP surface to (a) read the latest reconciliation status and findings and (b) trigger an ad-hoc reconciliation. Both endpoints require the admin role and the trigger endpoint requires an Idempotency-Key header. No vendor- or customer-facing endpoints are added.

#### Testing & Verification

- **FR-EXT-130**: System MUST be backed by an automated test suite that exercises, at minimum: concurrent withdrawals against the same wallet, repeated identical webhook deliveries (sequential and concurrent), partial-refund sequences that exhaust the captured total, an over-refund attempt that must be rejected, a deliberately-induced wallet cache drift that must be detected and repaired, a deliberately-induced orphan that must be flagged but not auto-deleted, a mid-batch settlement-run failure followed by a clean resume, an idempotency-key collision with a differing payload, and a full ledger replay that reproduces every wallet's current balance exactly.

### Key Entities *(include if feature involves data)*

- **Wallet**: An ownership record for a balance held in a specific currency for a specific owner (vendor or platform suspense account). Holds *cached* projections of available, pending, and reserved balances. Cache is updated only by the canonical ledger projection routine.
- **Ledger Entry**: An immutable, append-only record of one half of a money movement. Carries a wallet reference, a direction (debit or credit), an unsigned amount, an entry type, a transaction group reference, correlation/causation identifiers, an idempotency key, and a snapshot of the running balance at the time of posting.
- **Transaction Group**: An immutable grouping of two or more ledger entries that together represent one balanced double-entry transaction (debits equal credits). Carries the high-level kind of operation, the correlation/causation/idempotency identifiers, and the initiating actor.
- **Platform Suspense Account**: A non-vendor wallet representing a specific platform-side accounting bucket (clearing, commission receivable, refund payable, withdrawal payable, gateway in transit). Required to keep transactions balanced.
- **Financial Snapshot**: A periodic point-in-time per-wallet record of computed balances anchored to a specific ledger entry id, used to accelerate replay and as the authoritative reconciliation anchor.
- **Reconciliation Run**: An immutable record of one scheduled or manual reconciliation execution, with start/end timestamps, scope, status, and a count of findings.
- **Reconciliation Finding**: A child of a reconciliation run, recording one detected anomaly with expected vs actual values, severity, and a resolution disposition.
- **Idempotency Record**: A persisted result of a prior money-mutating Action keyed by (action, key, payload hash), used to short-circuit duplicate calls and to detect payload-mismatch conflicts.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After this feature ships, the inventory of direct money-column writes outside the canonical ledger-projection layer is exactly zero across the entire `app/Modules/` codebase.
- **SC-002**: Every vendor wallet's displayed available, pending, and reserved balance equals the signed sum of its ledger entries (and equivalently, the nearest financial snapshot plus subsequent entries) for 100% of wallets, verified daily.
- **SC-003**: Across a load test that delivers 100 identical successful payment webhooks for the same payment, the system produces exactly 1 capture transaction group and credits the destination wallet exactly once, with all 100 deliveries acknowledged successfully.
- **SC-004**: Across a load test of 50 concurrent withdrawal requests against a wallet whose balance covers only 1 of them, exactly 1 succeeds and 49 are rejected for insufficient funds, with zero over-withdrawal observed.
- **SC-005**: Across a partial-refund regression suite of at least 20 scenarios (multiple partial refunds, retried gateway calls, duplicate callbacks, currency mismatches, over-refund attempts), the sum of refunds never exceeds the captured total and commission reversals match the refunded amounts to the piastre.
- **SC-006**: A reconciliation run scoped to all wallets completes within an agreed time bound for the current data volume (initial target: under 5 minutes for the production data shape at launch, with the design accommodating 10× growth via snapshots).
- **SC-007**: After a deliberately-induced wallet cache drift, the next reconciliation detects it within one run, repairs the cache from the ledger, and records the finding and the repair immutably.
- **SC-008**: 100% of money-mutating Actions enforce idempotency; a duplicate call with the same key within the documented window produces no new ledger entries, no duplicate events, and returns the original outcome.
- **SC-009**: 100% of ledger entries carry a non-null correlation identifier; 100% of internally-derived ledger entries carry a non-null causation identifier; 100% of ledger entries belong to a transaction group whose debits and credits balance.
- **SC-010**: Settlement runs that are interrupted mid-batch resume on retry with zero duplicate payouts and zero lost payouts, verified by automated tests covering at least one failure mode per externally-dependent step.
- **SC-011**: From the admin UI, a finance lead can open any ledger entry and reach its originating event, full causal chain, and related domain records in no more than three clicks.
- **SC-012**: A new pull request introducing a direct balance write outside the canonical ledger projection is blocked by CI before merge.

## Assumptions

- The existing `wallet_ledger` table can be evolved with additive columns and database-level prohibitions on UPDATE/DELETE without a destructive migration; existing rows back-populate via a one-shot data migration that synthesises the new identifiers (transaction group, correlation, direction) from the present data.
- Eliminating stored-state primacy on `wallets` and treating those columns as a cache is acceptable; the schema cheat-sheet and `11_DB_Schema.md` are updated in the same change set to reflect this.
- Double-entry semantics are introduced with the smallest viable set of platform suspense accounts; expansion to a full general-ledger chart of accounts is explicitly out of scope and deferred to a later phase if/when needed.
- Money continues to be stored as unsigned BIGINT minor units plus a three-letter currency code in line with the constitution; signed amounts only appear as derived running balances and as the conceptual direction encoded by the `direction` column.
- The reconciliation routine is a single artisan command plus a scheduled job; a richer reconciliation dashboard, charts, and per-wallet drill-down views are nice-to-have but not in scope for this phase.
- Only one currency is in production at the time of this work; the system stays multi-currency-safe in design (every entry carries a currency code) but cross-currency conversion remains out of scope for Phase 1.
- Existing Filament admin resources for `wallet_ledger`, `commissions`, and `withdrawals` continue to render correctly; their forms are adjusted only insofar as they previously allowed editing fields that this feature makes immutable.
- The performance impact of switching to ledger-derived balances at read time is mitigated by the materialised cache columns plus periodic snapshots; no full-table scans of `wallet_ledger` are required for hot-path balance reads.
- Existing audit-log conventions and the `event_outbox` table continue to be the canonical extension points for cross-cutting auditability and event publication respectively; this feature adds rows to them but does not replace them.
- All concurrency primitives (DB row locks, Redis locks) are already available in the stack (`predis/predis`, `laravel/framework`) and require no new package additions outside the locked `docs/specs/10_Package_List.md`.

## Dependencies

- Payments module (`app/Modules/Payments/`) — must complete its capture, refund, and webhook paths refactor in lockstep with this feature.
- Settlement module (`app/Modules/Settlement/`) — owns wallets, ledger, commissions, withdrawals, and settlement runs; carries the majority of the change.
- Booking module (`app/Modules/Booking/`) — read-only dependency for resolving the chain back to bookings/items during forensic trace.
- Identity module (`app/Modules/Identity/`) — read-only dependency for vendor and admin user identifiers in causal chains.
- Shared module (`app/Modules/Shared/`) — owner of `audit_logs` and the cross-cutting append-only writer; minor additions for correlation/causation columns.
- Existing scheduled-job infrastructure (`php artisan schedule:work`, Redis-backed queues) — no new infrastructure required.
- `docs/specs/11_DB_Schema.md` — must be updated in the same change set to absorb the new tables and the cache reframing of the `wallets` table.
- `docs/specs/01_PRD.md` §7 — must be backfilled with the financial-integrity subsection that owns FR-EXT-101…FR-EXT-130.
- `docs/specs/09_Phasing_Plan.md` — must add a Phase 4.9 entry for this hardening pass.
- A new ADR-0028 in `docs/adr/` capturing the architectural decision.

## Out Of Scope (Explicit)

- Multi-currency conversion, FX accounting, hedging, or cross-currency reconciliation.
- A full chart-of-accounts general ledger beyond the minimum platform suspense accounts required for balanced double-entry.
- Tax invoicing changes beyond what is already implemented.
- Dispute resolution workflows for refund/chargeback investigation (Phase 2).
- Vendor-facing balance APIs beyond what already exists (no new vendor endpoints).
- Customer-facing financial APIs of any kind.
- A redesigned reconciliation dashboard or charting; the admin surface in scope here is a minimal status + trigger UI.
- Migration to a third-party ledger product (e.g., TigerBeetle, hosted ledger SaaS). This feature stays inside the locked stack.
