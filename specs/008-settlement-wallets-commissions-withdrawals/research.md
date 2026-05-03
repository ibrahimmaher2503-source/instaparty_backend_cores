# Research: Settlement — Wallets, Commissions, Withdrawals

**Feature**: 008-settlement-wallets-commissions-withdrawals | **Phase**: Plan / Phase 0 | **Date**: 2026-05-03

This document resolves design decisions made during planning. Spec.md had **0 [NEEDS CLARIFICATION] markers** because the feature was scoped tightly against the locked DB schema and Phasing Plan. The decisions below clarify *how*, not *what*.

---

## R1 — Wallet balance: cached vs. computed

**Decision**: Cache `balance_minor` on the `wallets` row, updated in the same DB transaction as the ledger insert. `DB::afterCommit()` fires the domain event; the balance write itself is inside the transaction.

**Rationale**:
- Read p95 target is under 100ms. `SUM(amount_minor) FROM wallet_ledger WHERE wallet_id = ?` scales linearly with ledger size; a busy vendor accumulates 1000+ entries per year.
- Cached balance with same-transaction update guarantees consistency: rollback rolls back both.
- Pest invariant test verifies `wallets.balance_minor == SUM(wallet_ledger.amount_minor WHERE wallet_id = wallet.id)` after every test scenario.

**Alternatives considered**:
- *Pure derived (no cache)*: Cleaner conceptually but fails read-perf goal at scale.
- *Materialized view refreshed periodically*: Adds eventual consistency and a refresh job.
- *Event-sourced wallet aggregate*: Overkill for Phase 1.

---

## R2 — Commission rate fallback: SQL ladder vs. cached lookup

**Decision**: Implement `EloquentCommissionRateResolver` with **4 sequential SQL queries** (one per fallback level), short-circuiting on first match. Each query uses the `(category_id, product_type)` index.

**Rationale**:
- Worst-case 4 indexed queries is acceptable: each runs in <5ms on the small `commission_rates` table (<100 rows for Phase 1).
- Single complex SQL with `UNION ALL ... ORDER BY specificity` would also work but is harder to reason about and test per level.
- Caching is premature optimization — `commission_rates` is write-rare (admin edits once a quarter).

**Alternatives considered**:
- *Single SQL with CASE/COALESCE*: Less readable; harder per-level isolation testing.
- *In-memory cache (Redis)*: Adds invalidation surface for a small table.
- *Pre-compute resolved rate at booking time*: Already done per `03_Three_Product_Types.md` §Commission rates → snapshot lives on `booking_items.commission_bps`. The resolver is the **fallback** when the snapshot is null.

**Implementation note**: `Settlement` reads `booking_items.commission_bps` first via `SettlementBookingReader`. The resolver only runs when the snapshot is null (legacy data or test fixtures).

---

## R3 — Single pending withdrawal rule: app-layer vs. DB-layer enforcement

**Decision**: Enforce at **DB level** via UNIQUE PARTIAL index: `UNIQUE (vendor_profile_id) WHERE status = 'pending'`.

**Rationale**:
- App-layer check (query then insert) has TOCTOU race — concurrent requests could both pass and both insert.
- DB-level partial index makes the rule unforgivable; second insert fails, caught and surfaced as 422 `existing_pending_withdrawal`.
- MariaDB 11 supports partial indexes via WHERE clause.

**Alternatives considered**:
- *App-layer check + retry on conflict*: Racier, slower.
- *Pessimistic lock on row*: Overkill for low-traffic vendor flows.
- *Separate `pending_withdrawals` projection table with PK on `vendor_profile_id`*: Adds a table; partial index achieves same result.

---

## R4 — Refund reversal proportionality math

**Decision**: Reverse commission and wallet credit **proportional to refund amount**:
```
reversal_ratio        = refund_amount_minor / payment_amount_minor
commission_to_reverse = commission_minor * reversal_ratio   (HALF_EVEN rounding)
wallet_share_reverse  = vendor_share_minor * reversal_ratio (HALF_EVEN rounding)
```
After full reversal: `commission_to_reverse + wallet_share_reverse == refund_amount_minor` (within 1 minor unit due to rounding).

**Rationale**:
- Brick\Money's `RoundingMode::HALF_EVEN` (banker's rounding) is the financial industry standard for minimizing aggregate bias.
- Proportional reversal preserves the invariant: total platform commission collected = sum of `(commission_minor − reversed_amount_minor)` across all commissions.

**Alternatives considered**:
- *Reverse the full commission on any refund*: Simple but unfair to platform when refund is partial.
- *Always-round-up vendor share*: Optically vendor-friendly but introduces consistency-breaking bias auditors flag.

---

## R5 — Bank-transfer proof storage

**Decision**: `spatie/laravel-medialibrary` with `bank_proof` collection on the `Withdrawal` model. Disk = `s3_private` (prod) / `minio_private` (dev). URLs signed with 1-hour TTL; never publicly accessible.

**Rationale**:
- Spatie Media Library is already the project standard (`10_Package_List.md`).
- Private bucket: bank documents include account numbers; never publicly indexable.
- 1-hour TTL: long enough for admin verification, short enough to mitigate URL-leak risk.
- Allowed types enforced at upload: PDF/PNG/JPG; max 10 MB.

**Alternatives considered**:
- *Direct Laravel storage*: Loses Media Library registration/conversion benefits.
- *External document store (Google Drive)*: Adds integration; not worth it for Phase 1.

---

## R6 — Admin "Approve" vs. "Approve & Mark Paid" UX

**Decision**: Phase 4.2 ships **single combined action** "Approve & Mark Paid". Admin uploads bank proof and clicks once → withdrawal moves directly `pending` → `paid`.

**Rationale**:
- Phase 4.2 has no automated payout integration; admin always performs the bank transfer externally before clicking. Splitting "Approve" (status=approved) from "Mark Paid" (status=paid) adds a state with no operational meaning.
- The `WithdrawalApproved` event is documented but **not emitted in Phase 4.2** (collapsed into `WithdrawalPaid`).
- If Phase 1.5 adds automated payout (Paymob payout API), the intermediate `approved` state becomes meaningful (waiting for payout webhook); we'll split the action then.

**Alternatives considered**:
- *Always two-step*: Friction without value in Phase 1.
- *Auto-approve under threshold*: Explicitly cut from Phase 4.2.

---

## R7 — Negative wallet balance: allow vs. prevent

**Decision**: Allow negative balance temporarily. Append the debit ledger entry, update cached balance to negative value, log a warning to `audit_logs` flagging it for admin review, **block further withdrawal requests until balance ≥ 0**.

**Rationale**:
- Scenario: customer pays → vendor wallet credited → vendor withdraws → customer refunds. The refund debit must land somewhere.
- Refusing the debit would corrupt the wallet ledger invariant.
- Blocking new withdrawals while negative is the right business response — vendor must "earn back" the negative.

**Alternatives considered**:
- *Hold a portion of vendor's balance against potential refunds (escrow)*: Complex; vendor experience suffers.
- *Charge vendor's bank account directly to recover*: Out of scope for Phase 1.

---

## R8 — Cross-module integration: contracts vs. direct model imports

**Decision**: Settlement defines `SettlementBookingReader` and `SettlementPaymentReader` interfaces in its own `Domain/Contracts/`. Booking and Payments modules implement them and bind in their respective `ServiceProvider::register()`.

**Rationale**:
- Strict adherence to `.claude/rules/modules.md` — never import another module's Eloquent model.
- Contracts return DTOs (`BookingItemSnapshotDto`, `PaymentSnapshotDto`, `RefundSnapshotDto`), not models, preventing accidental Eloquent leak.
- Architecture test (`SettlementModuleNoCrossImportTest`) enforces this.

**Alternatives considered**:
- *Direct model imports*: Violates module isolation; couples Settlement to Booking/Payments schema.
- *Shared "Domain Events" module hosting all events + DTOs*: Considered for Phase 2; for Phase 1 the per-module-owns-its-contracts pattern is simpler.

---

## R9 — `commissions.booking_item_id` UNIQUE — what about modifications?

**Decision**: One commission per `booking_item_id` for the lifetime of the row. Booking modifications (Phase 3.2) create new `booking_items` under a Modification record, so each modification has a fresh commission lineage automatically.

**Rationale**:
- `booking_modification_items` (Phase 3.2) creates new `booking_items` — no need to re-key.
- Without UNIQUE, retried `PaymentCaptured` events could double-credit.

**Alternatives considered**:
- *UNIQUE on `(booking_item_id, payment_id)`*: Allows multiple commissions per item if split payments (Phase 4.0 cut split payments → not relevant in 4.2).

---

## R10 — Multi-currency support in Phase 4.2

**Decision**: Schema is multi-currency from Day 1 (`UNIQUE (owner_type, owner_id, currency)` on `wallets`; `currency` column on every money table). UI/API in Phase 4.2 surfaces only EGP. Vendors with mixed-currency activity get multiple wallet rows automatically.

**Rationale**:
- Future-proofs against Phase 2 GCC expansion.
- No additional cost — schema supports it; the UI just doesn't expose currency selection.

**Alternatives considered**:
- *Single-currency schema (no `currency` column)*: Cheaper now but expensive migration later.

---

## R11 — Listener queue choice + retry semantics

**Decision**: Both listeners use `database` queue (Laravel default). Retries: 3 attempts with exponential backoff (10s, 60s, 300s). On final failure, exception goes to `failed_jobs` and triggers an `audit_logs` entry of severity `error`.

**Rationale**:
- `database` queue is already configured (Phase 0.0); no new infra.
- Idempotency at schema level (UNIQUE constraints) makes retries safe — duplicate event delivery cannot create duplicate financial entries.
- 3 attempts × exponential backoff covers transient DB hiccups.
- Hard failures (e.g., booking item deleted, vendor profile deleted) surface in `failed_jobs` for admin review.

**Alternatives considered**:
- *Redis queue*: Faster but adds dependency for a low-throughput listener.
- *Synchronous (in-process) listener*: Would block the webhook response; bad UX for Paymob's retry behavior.
- *Exactly-once semantics via outbox pattern*: Phase 2 — for now, "at least once + idempotent receiver" is enough.

---

## R12 — Migration dependency order (verified)

**Decision** (final order, all created in `app/Modules/Settlement/Database/Migrations/`):

```
2026_05_03_000001_create_wallets_table              ← needs vendor_profiles (Phase 0.2)
2026_05_03_000002_create_wallet_ledger_table        ← needs wallets (#1)
2026_05_03_000003_create_commission_rates_table     ← needs categories (Phase 2.0)
2026_05_03_000004_create_commissions_table          ← needs booking_items (Phase 3.1) + payments (Phase 4.0) + vendor_profiles + categories
2026_05_03_000005_create_withdrawals_table          ← needs vendor_profiles + users + media (Spatie)
2026_05_03_000006_create_settlement_runs_table      ← standalone (no FK)
```

All FK behaviors verified against the existing tables in their respective module migrations. No FK to non-existent tables.

---

## R13 — `withdrawals.bank_account_snapshot` JSON schema

**Decision**: Stored as JSON object with these required fields:

```json
{
  "account_holder": "string (max 100 chars)",
  "iban": "string (ISO 13616 format)",
  "bank_name": "string (max 100 chars)",
  "swift_bic": "string (8 or 11 chars)"
}
```

Validation at Form Request layer: required fields, IBAN basic checksum (mod-97), SWIFT/BIC pattern match.

**Rationale**:
- JSON column avoids needing a separate `vendor_bank_accounts` table in Phase 1.
- Storing as snapshot (not FK) means historical withdrawals are immune to vendor editing their bank account later.
- ISO 13616 IBAN validation is a 30-line PHP check; no library needed.

**Alternatives considered**:
- *Encrypted column*: Adds key-management complexity; bank accounts are sensitive but not credit-card-grade. Private DB + audit logging is sufficient for Phase 1.

---

## Open Questions

None remaining. All design questions resolved above. Proceed to `/speckit-tasks`.
