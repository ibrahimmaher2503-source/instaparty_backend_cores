# ADR-0032 — Withdrawal Two-Step: Approve → Mark Paid

- **Status:** Accepted
- **Date:** 2026-05-16
- **Decision-makers:** Ibrahim
- **Tags:** settlement, withdrawals, audit, phase-4.11-withdrawal-proof-audit
- **Related:**
  - [`docs/adr/0009-settlement-module.md`](0009-settlement-module.md) — Settlement module baseline
  - [`docs/adr/ADR-0028-financial-ledger-hardening.md`](ADR-0028-financial-ledger-hardening.md) — Ledger hardening (Phase 4.9)
  - [`specs/033-withdrawal-proof-audit/`](../../specs/033-withdrawal-proof-audit/) — Full spec, contracts, data-model

---

## 1. السياق / Context

**EN:** The existing `ApproveAndMarkWithdrawalPaidAction` collapses withdrawal approval and payment confirmation into a single admin action. This prevents any meaningful audit trail: there is no record of _who_ approved a withdrawal separately from _who_ confirmed the bank payment. Finance teams need to know when and by whom approval was granted, independent of when and by whom the bank transfer was confirmed and proof uploaded. Phase 4.11 splits the action, adds five dedicated audit columns, and enforces that `MarkPaid` is only callable on an already-`approved` withdrawal.

**AR:** إجراء `ApproveAndMarkWithdrawalPaidAction` يجمع الاعتماد والدفع في خطوة واحدة، مما يمنع وجود سجل مراجعة مفيد. تحتاج فرق المالية إلى معرفة من اعتمد طلب السحب ومتى، بمعزل عن من أكد التحويل البنكي ورفع الإثبات. Phase 4.11 تفصل الإجراء، وتضيف خمسة أعمدة مراجعة مخصصة، وتُلزم أن `MarkPaid` لا تُستدعى إلا على سحب `approved` مسبقاً.

**Phase:** 4.11 (Withdrawal Proof & Finance Audit)
**Built in:** Week 8

---

## 2. Core Decisions

### 2.1 Split `ApproveAndMarkWithdrawalPaidAction` into two Actions

`ApproveWithdrawalAction` handles the state transition `Pending→Approved`. It writes `approved_at` and `approved_by_admin_id` and inserts an `audit_logs` row with `action='withdrawal_approved'`. It posts no ledger entries. It fires `WithdrawalApproved` after commit.

`MarkWithdrawalPaidAction` handles the state transition `Approved→Paid`. It validates that state is `approved` before any write. It requires both `bank_transfer_reference` (non-empty, unique per vendor) and a proof file. It attaches the file to the `bank_proof` Spatie media collection on `s3_private` disk. It posts the `wd_settle` ledger group (verbatim from the original combined action). It inserts an `audit_logs` row with `action='withdrawal_paid'`. It fires `WithdrawalPaid` after commit.

**Rejected alternative**: Keep the single action and add a flag `$approveOnly: bool`. Rejected because flag-driven branching is harder to permission-gate, harder to test in isolation, and violates the single-responsibility rule for Action classes.

### 2.2 Five new `withdrawals` columns (additive, no breaking change)

| Column | Type | Purpose |
|---|---|---|
| `approved_at` | `TIMESTAMP NULL` | Timestamp when admin clicked Approve |
| `approved_by_admin_id` | `BIGINT UNSIGNED NULL FK→users` | Admin who approved |
| `paid_by_admin_id` | `BIGINT UNSIGNED NULL FK→users` | Admin who confirmed payment + uploaded proof |
| `bank_transfer_reference` | `VARCHAR(120) NULL` | Bank reference string (UNIQUE per vendor) |
| `admin_payment_note` | `JSON NULL` | Bilingual EN/AR payment note from admin |

The existing `processed_by_user_id` and `processed_at` columns are **retained** and continue to reflect the payment step (backfilled for historical rows). They will be dropped in Phase 8 once all consumers use the new columns.

One-time backfill on migration: `UPDATE withdrawals SET approved_at = processed_at, approved_by_admin_id = processed_by_user_id, paid_by_admin_id = processed_by_user_id WHERE status = 'paid'`.

### 2.3 `bank_transfer_reference` UNIQUE per vendor (not globally)

A bank reference from Bank A might collide with a reference from Bank B for a different vendor. The UNIQUE index is on `(vendor_profile_id, bank_transfer_reference)`. MySQL 8 treats NULL ≠ NULL in UNIQUE indexes, so NULL values never conflict.

**Rejected alternative**: Global unique on `bank_transfer_reference`. Rejected because Egyptian bank references are not globally unique across banks.

### 2.4 Proof file stored via Spatie MediaLibrary on `s3_private` disk

This follows the precedent established in Plan 008 (Settlement module baseline, accepted). The `bank_proof` Spatie media collection uses `->useDisk('s3_private')` and `->singleFile()`. Proof download URLs are generated per-request via `getTemporaryUrl(now()->addMinutes(15))` — never stored on the withdrawal row (they expire and caching them would be a security hole).

**Constitution §XI note**: The constitution specifies "typed S3 columns" for settlement proofs. This feature deviates by using Spatie media instead of a raw `proof_path` column, matching the Plan 008 precedent. A future amendment to the constitution should either ratify Spatie-on-s3-private as the standard for binary proofs or define when typed columns are preferred.

### 2.5 Deprecated wrapper retained for one release

`ApproveAndMarkWithdrawalPaidAction` is converted to a `@deprecated` wrapper that calls the two new actions sequentially. No consumers outside Filament exist; the wrapper is kept to avoid a breaking change mid-release and will be deleted in Phase 8 (`@todo Phase 8`).

### 2.6 `InvalidWithdrawalTransitionException` throws on wrong pre-state

`ApproveWithdrawalAction` throws if status ≠ `Pending`. `MarkWithdrawalPaidAction` throws if status ≠ `Approved`. The state machine's `transitionTo()` would also throw, but throwing explicitly from the Action provides a richer message (translatable via `settlement.errors.withdrawal_state`) and avoids the generic Spatie exception leaking to the UI.

### 2.7 Idempotency keys

- `wd_approve:{withdrawal_id}` — new key for `ApproveWithdrawalAction`
- `wd_settle:{withdrawal_id}` — existing key from Phase 4.9, unchanged in `MarkWithdrawalPaidAction`

---

## 3. Consequences

### Positive

- Finance teams have a full two-step audit trail in `audit_logs` (one row per step, different admin IDs if roles differ)
- State machine blocks `Pending→Paid` (existing `ApproveWithdrawalTransition` + `MarkWithdrawalPaidTransition` enforce `Pending→Approved→Paid`)
- Vendor API surfaces the full timeline including `approved_at` and proof download URL
- Permissions can now be separated: `withdrawal.approve` vs `withdrawal.mark_paid` (different roles possible)

### Negative / Risks

- Migration adds five columns and a UNIQUE index — additive, online-safe on MySQL 8, no row rewrites except the backfill
- `processed_by_user_id` / `processed_at` are now semantically ambiguous (they reflect payment, not approval) — risk of misuse until dropped in Phase 8
- The §XI deviation is undocumented in the constitution; follow-up needed

### Neutral

- No package additions — all dependencies (Spatie MediaLibrary, Laravel Sanctum, Spatie Permission) were already in `10_Package_List.md`
- No Pest test changes required for existing tests — the new Actions are additive

---

## 4. Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Single action with `$approveOnly` flag | Flag-driven branching, hard to permission-gate separately |
| Global unique on `bank_transfer_reference` | Egyptian bank refs not globally unique across banks |
| Store signed proof URL in withdrawal row | URLs expire; caching creates security holes |
| Drop `processed_*` columns immediately | Breaking change; safer to defer to Phase 8 |
