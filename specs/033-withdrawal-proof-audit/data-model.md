# Phase 1 Data Model — Withdrawal Proof & Finance Audit

## 1. `withdrawals` table — column delta

> Existing table (Phase 4.2 + Phase 4.9). This feature adds 5 columns and a UNIQUE index. No drops.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `approved_at` | TIMESTAMP | YES | NULL | Set to `now()` on `Pending → Approved` transition. Never re-written. |
| `approved_by_admin_id` | BIGINT UNSIGNED FK → `users.id` | YES | NULL | `restrictOnDelete`. The admin user who approved. |
| `paid_by_admin_id` | BIGINT UNSIGNED FK → `users.id` | YES | NULL | `restrictOnDelete`. The admin user who marked paid. May equal `approved_by_admin_id`. |
| `bank_transfer_reference` | VARCHAR(120) | YES | NULL | Bank's transfer-reference identifier. Free-text. Required on `MarkPaid`. |
| `admin_payment_note` | JSON | YES | NULL | Translatable `{en?, ar?}`. Spatie translatable cast. Optional on `MarkPaid`. |

**New index**: `UNIQUE (vendor_profile_id, bank_transfer_reference)` named `withdrawals_vendor_transfer_ref_unique` — partial-style: enforced at the DB layer; NULL values do not collide in MySQL 8 (NULL ≠ NULL in UNIQUE indexes).

**Backfill (one-time, inside the migration)**:
```
UPDATE withdrawals
   SET approved_at = processed_at,
       approved_by_admin_id = processed_by_user_id,
       paid_by_admin_id = processed_by_user_id
 WHERE status = 'paid'
   AND processed_at IS NOT NULL;
```

> Rows in any state other than `paid` get NULLs for the three new audit columns — correct, because no approval happened pre-feature in a separate event.

**Columns NOT changed**:
`id`, `public_id`, `vendor_profile_id`, `requested_amount_minor`, `requested_amount_currency`, `paid_amount_minor`, `paid_amount_currency`, `bank_account_snapshot`, `status`, `idempotency_key`, `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id`, `rejected_reason`, `requested_by_user_id`, `processed_by_user_id`, `bank_proof_media_id`, `requested_at`, `processed_at`, `paid_at`, `pending_lock`, `created_at`, `updated_at`.

**Append-only verification**: After this feature, the only mutable columns on a `paid` withdrawal row are still `status`-related (per `11_DB_Schema.md §0`). The 5 new columns are written ONCE per transition and never UPDATEd again — enforced by the state-machine transition class, not just convention.

---

## 2. State machine

```
        ┌──────────────────────────────┐
        │           pending            │  (initial — on RequestWithdrawal)
        └──────┬─────────────────┬─────┘
               │                 │
   Pending     │                 │  Pending
   →Rejected  ▼                 ▼  →Approved
        ┌──────────┐      ┌──────────────┐
        │ rejected │      │   approved   │
        └──────────┘      └──────┬───────┘
                                 │
                                 │  Approved→Paid
                                 ▼
                          ┌──────────┐
                          │   paid   │  (terminal — no further transitions in this feature)
                          └──────────┘
```

**Forbidden transitions** (rejected by `spatie/laravel-model-states`):
- `pending → paid` (the whole point — must go through `approved`)
- `paid → *` (terminal)
- `rejected → *` (terminal)
- `approved → rejected` (out of scope per spec §Edge Cases)
- `approved → pending` (out of scope per spec §Edge Cases)

**Transition classes**:

| Class | From → To | Side effects (inside `DB::transaction`) |
|---|---|---|
| `PendingToApproved` | pending → approved | `approved_at = now()`, `approved_by_admin_id = $admin->id`, audit row, idempotency key `wd_approve:{id}`, `DB::afterCommit(fn () => WithdrawalApproved::dispatch(...))` |
| `PendingToRejected` (existing) | pending → rejected | unchanged from current implementation |
| `ApprovedToPaid` (new) | approved → paid | attach proof media, `paid_at = now()`, `paid_by_admin_id = $admin->id`, `bank_transfer_reference`, `admin_payment_note`, `paid_amount_minor/currency = requested_amount_minor/currency`, `pending_lock = NULL`, post `wd_settle:{id}` ledger group via `PostLedgerTransactionAction`, link `settled_ledger_entry_id`, audit row, `DB::afterCommit(fn () => WithdrawalPaid::dispatch(...))` |

---

## 3. New / changed Actions

### 3.1 `ApproveWithdrawalAction` (new)

```
public function execute(Withdrawal $w, User $admin): Withdrawal
```

**Pre-conditions**:
- `$w->status === WithdrawalStatus::Pending` — else throw `InvalidWithdrawalTransitionException`
- `$admin->can('withdrawal.approve')` — caller responsibility (Filament `visible()` + middleware on any future API)

**Behavior** (inside `DB::transaction`):
1. Idempotency: if `idempotency_keys` already has `wd_approve:{$w->id}` for this user with a non-expired entry, return cached response.
2. Update row: `status = approved`, `approved_at = now()`, `approved_by_admin_id = $admin->id`.
3. Insert `audit_logs` row: `action = 'withdrawal_approved'`, `changes = {before: {status: pending}, after: {status: approved}}`.
4. `DB::afterCommit(fn () => event(new WithdrawalApproved(...)))`.
5. Return refreshed `$w`.

**Postconditions**: no ledger group posted (Approve does not touch wallet ledger). No media attached.

---

### 3.2 `MarkWithdrawalPaidAction` (new)

```
public function execute(Withdrawal $w, MarkWithdrawalPaidInput $input, User $admin): Withdrawal
```

**Pre-conditions**:
- `$w->status === WithdrawalStatus::Approved` — else throw `InvalidWithdrawalTransitionException`
- `$input->bankTransferReference` non-empty, length ≤ 120
- `$input->proofFile` valid `UploadedFile`, MIME ∈ {pdf, jpeg, png}, size ≤ 10 MB
- `$input->paymentNote` either fully absent or contains at least one non-empty locale (en/ar)
- `$admin->can('withdrawal.mark_paid')`

**Behavior**:
1. Acquire Redis wallet lock on vendor's EGP wallet (`WalletLocker::tryAcquire(ttl=60s, wait=10s)` — reuses Phase 4.9 infrastructure).
2. Inside `DB::transaction`:
    a. Verify state again under lock.
    b. Check UNIQUE constraint preemptively: SELECT 1 FROM withdrawals WHERE vendor_profile_id = ? AND bank_transfer_reference = ? — if exists, throw `DuplicateBankTransferReferenceException`.
    c. Attach proof: `$w->addMedia($input->proofFile)->toMediaCollection('bank_proof')` — Spatie handles upload to `s3_private` disk; collection is `singleFile()` so any prior file is replaced (defensive; should not happen given the state machine).
    d. Post settle ledger group via `LedgerWriter::post(...)` with idempotency key `wd_settle:{$w->id}` (exactly as in current `ApproveAndMarkWithdrawalPaidAction` lines 70–104 — copy-paste-faithful preservation of Phase 4.9 contract).
    e. Update row: `status = paid`, `paid_at = now()`, `paid_by_admin_id = $admin->id`, `bank_transfer_reference = $input->bankTransferReference`, `admin_payment_note = $input->paymentNote` (cast to JSON), `paid_amount_minor = requested_amount_minor`, `paid_amount_currency = requested_amount_currency`, `pending_lock = NULL`, `bank_proof_media_id = $w->getFirstMedia('bank_proof')->id`, `settled_ledger_entry_id = $result->settleEntryId`.
    f. Insert `audit_logs` row: `action = 'withdrawal_paid'`, `changes = {before: {status: approved}, after: {status: paid, bank_transfer_reference, paid_by_admin_id}}`.
    g. `DB::afterCommit(fn () => event(new WithdrawalPaid(...)))`.
3. Release lock in `finally`.
4. Return refreshed `$w`.

**Postconditions**: exactly one new `wallet_ledger` group (settle), exactly one new `media` row, exactly one new `audit_logs` row, no UPDATE on any append-only table.

---

### 3.3 `ApproveAndMarkWithdrawalPaidAction` (deprecated wrapper)

```php
/** @deprecated use ApproveWithdrawalAction + MarkWithdrawalPaidAction explicitly */
public function execute(Withdrawal $w, UploadedFile $proof, User $admin): Withdrawal
{
    $w = app(ApproveWithdrawalAction::class)->execute($w, $admin);

    return app(MarkWithdrawalPaidAction::class)->execute(
        $w,
        new MarkWithdrawalPaidInput(
            bankTransferReference: "LEGACY-{$w->public_id}",
            proofFile: $proof,
            paymentNote: [
                'en' => 'Legacy combined action — see ADR-0032',
                'ar' => 'إجراء قديم مُجمَّع — راجع ADR-0032',
            ],
        ),
        $admin,
    );
}
```

Kept for one release window. Phase 8 cleanup deletes it.

---

## 4. DTO: `MarkWithdrawalPaidInput`

```php
final readonly class MarkWithdrawalPaidInput
{
    public function __construct(
        public string $bankTransferReference,
        public UploadedFile $proofFile,
        /** @var array{en?: string, ar?: string} */
        public array $paymentNote = [],
    ) {}
}
```

Validation lives in the Filament form (admin-only) and a Form Request will be added the moment a future admin API surfaces this action publicly.

---

## 5. Domain events

### `WithdrawalApproved` (new)

```php
final readonly class WithdrawalApproved
{
    public function __construct(
        public int $withdrawalId,
        public string $withdrawalPublicId,
        public int $vendorProfileId,
        public int $approvedByAdminId,
    ) {}
}
```

No listener wired in this feature (notification dispatch is Phase 5).

### `WithdrawalPaid` (existing) — payload unchanged

Already dispatched by current `ApproveAndMarkWithdrawalPaidAction`. The new `MarkWithdrawalPaidAction` dispatches the same event with the same payload — no consumer changes.

---

## 6. Audit log payload schemas

```jsonc
// withdrawal_approved
{
  "before": { "status": "pending" },
  "after":  { "status": "approved", "approved_by_admin_id": 42 }
}

// withdrawal_paid
{
  "before": { "status": "approved" },
  "after":  {
    "status": "paid",
    "paid_by_admin_id": 42,
    "bank_transfer_reference": "EGTBNK-2026-05-16-00041",
    "admin_payment_note": { "en": "Transfer confirmed by branch.", "ar": "تم تأكيد التحويل من الفرع." }
  }
}
```

---

## 7. Test plan — 9 cases in `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php`

Pest `describe`/`it` style, each test independent (no shared mutable state):

| # | Test | Pre-state | Action | Expected |
|---|---|---|---|---|
| 1 | `it('vendor requests a withdrawal and posts a reserve ledger entry')` | wallet with sufficient `available_minor` | `RequestWithdrawalAction` | status `pending`, `reserved_ledger_entry_id` non-null, wallet `available_minor` decremented, `pending_lock = vendor_profile_id` |
| 2 | `it('admin approves a pending withdrawal')` | pending withdrawal | `ApproveWithdrawalAction` | status `approved`, `approved_at` and `approved_by_admin_id` set, no new ledger group, audit row inserted |
| 3 | `it('admin marks paid with valid proof and reference')` | approved withdrawal | `MarkWithdrawalPaidAction` with valid input | status `paid`, all 5 audit columns set, `bank_proof` media attached, settle ledger group posted, audit row inserted |
| 4 | `it('cannot mark paid without bank transfer reference')` | approved withdrawal | `MarkWithdrawalPaidAction` with empty `bankTransferReference` | exception, status unchanged, no ledger group, no media |
| 5 | `it('cannot mark paid without proof file')` | approved withdrawal | `MarkWithdrawalPaidAction` with null `proofFile` | exception, status unchanged, no ledger group, no media |
| 6 | `it('cannot mark paid on a pending withdrawal')` | pending withdrawal (Approve skipped) | `MarkWithdrawalPaidAction` directly | `InvalidWithdrawalTransitionException`, status remains `pending`, no ledger group, no media |
| 7 | `it('a different vendor cannot view this withdrawal')` | paid withdrawal owned by vendor A | `GET /api/v1/vendor/withdrawals/{public_id}` as vendor B | 404 |
| 8 | `it('wallet_ledger remains append-only')` | run cases 1–3 in sequence, snapshot all `wallet_ledger` row IDs after each | `DB::table('wallet_ledger')` assertions | every prior row ID still exists with identical values; only new IDs added |
| 9 | `it('mark paid is idempotent under duplicate idempotency key')` | approved withdrawal | call `MarkWithdrawalPaidAction` twice with same context (same `wd_settle:{id}`) | second call returns cached result; exactly one settle ledger group exists; exactly one media row; exactly one audit row |

**Bilingual coverage** (constitution §IV): test 3 asserts the `admin_payment_note` round-trips with both `en` and `ar` keys. Test 7 asserts the vendor read API resolves the note via `App::getLocale()` for both `en` and `ar` request locales — implemented as a Pest dataset.

---

## 8. Permissions

| Permission | Guard | Who gets it (seeder) |
|---|---|---|
| `withdrawal.approve` | admin | `Settlement Admin`, `Super Admin` roles |
| `withdrawal.mark_paid` | admin | `Finance Admin`, `Super Admin` roles |
| `withdrawal.view_audit` | admin | `Settlement Admin`, `Finance Admin`, `Super Admin`, `Auditor` roles |
| `settlement.view_withdrawals.own` | vendor | existing — unchanged |
| `settlement.request_withdrawal.own` | vendor | existing — unchanged |

Added to `SettlementPermissionsSeeder`. Run `php artisan shield:generate --all` after `WithdrawalsQueueResource` and `WithdrawalResource` are modified to ensure Shield picks them up.

---

## 9. API Resource shape — `WithdrawalResource` delta

Existing fields kept; the following are added or extended:

```json
{
  "data": {
    "public_id": "01H8M…",
    "amount": { "minor": 50000, "currency": "EGP", "formatted": "EGP 500.00" },
    "status": "paid",
    "timeline": [
      { "event": "requested",  "at": "2026-05-12T08:00:00Z", "by_type": "vendor", "by_public_id": "01H8…" },
      { "event": "approved",   "at": "2026-05-14T10:30:00Z", "by_type": "admin",  "by_public_id": "01H9…" },
      { "event": "paid",       "at": "2026-05-16T11:00:00Z", "by_type": "admin",  "by_public_id": "01H9…" }
    ],
    "bank_transfer_reference": "EGTBNK-2026-05-16-00041",
    "admin_payment_note": "Transfer confirmed by branch.",
    "proof_download_url": "https://s3-private.example.com/…?X-Amz-Expires=900&…",
    "rejected_reason": null
  }
}
```

Fields are nulled per state:
- `pending`: `timeline` has only `requested`; `bank_transfer_reference`, `admin_payment_note`, `proof_download_url` are `null`.
- `approved`: `timeline` adds `approved`; payment fields still `null`.
- `paid`: all populated.
- `rejected`: `timeline` adds `rejected`; payment fields `null`; `rejected_reason` populated.

`admin_payment_note` is a single string (locale-resolved per request) on the vendor-facing Resource. On the admin Filament Infolist it is rendered as two columns (EN + AR) side-by-side.

---

**Phase 1 data model complete. Next: contracts/* + quickstart.md.**
