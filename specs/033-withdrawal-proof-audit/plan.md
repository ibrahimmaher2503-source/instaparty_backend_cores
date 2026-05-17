# Implementation Plan: Withdrawal Proof & Finance Audit

**Branch**: `033-withdrawal-proof-audit` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/033-withdrawal-proof-audit/spec.md`

---

## Summary

This is a brownfield audit-hardening of the existing withdrawal flow. Today, `ApproveAndMarkWithdrawalPaidAction` collapses the two-step admin payout into a single click, losing the audit separation between *who approved* and *who paid*, and allowing the operation to complete without a structured bank-transfer reference or admin note. This plan:

1. **Splits the action** into `ApproveWithdrawalAction` and `MarkWithdrawalPaidAction`, each with its own state-machine guard, audit row, and (for `MarkPaid`) ledger group post + media attachment.
2. **Extends `withdrawals`** with five custodial columns: `approved_at`, `approved_by_admin_id`, `paid_by_admin_id`, `bank_transfer_reference`, `admin_payment_note` — without touching any append-only table or breaking the Phase 4.9 ledger contract.
3. **Rewires the Filament admin UI** — `WithdrawalsQueueResource` exposes two distinct row actions; `WithdrawalResource` detail page renders the full audit trail with proof preview and ledger-group links.
4. **Extends the vendor read API** (`GET /api/v1/vendor/withdrawals/{public_id}`) to surface the timeline, reference, payment note, and a signed proof-download URL — strictly read-only and tenant-scoped.
5. **Adds 9 Pest tests** covering the happy path, the validation guards, the state-machine guard, ownership scoping, idempotency replay, and an append-only invariant assertion.

**PRD coverage**: FR-28, FR-29, FR-EXT-118 (existing). Five new FR-EXT-205…209 are introduced in `spec.md` Traceability and ⚠️-marked for backfill into `01_PRD.md §7.7`.

**ADR**: Extends ADR-0009 (Settlement Module) and ADR-0028 (Financial Ledger Hardening). A new lightweight ADR — **ADR-0032 — Withdrawal Two-Step Approve/Mark-Paid Split** — will be created in the same window to document the action split and the addition of the five custodial columns. No new module is introduced.

**Phase**: ⚠️ **PHASE BACKFILL NEEDED** — proposed **Phase 4.11 — Withdrawal Proof & Finance Audit** (1–2 days, Week 8). Predecessors: Phase 4.2 (withdrawals exist), Phase 4.9 (ledger groups + idempotency). No successor blocks.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: brick/money (integer minor units, existing), spatie/laravel-medialibrary (existing `bank_proof` collection on `s3_private` disk), spatie/laravel-translatable (existing for `admin_payment_note`), spatie/laravel-model-states (existing `WithdrawalState` machine), spatie/laravel-permission + bezhansalleh/filament-shield (existing — 3 new permissions added), Filament v3 (existing `WithdrawalsQueueResource` + `WithdrawalResource`)
**Storage**: MySQL 8 / MariaDB 11 — **0 new tables**, **1 existing table extended** (`withdrawals`)
**Testing**: Pest — **9 Feature tests** (1 file, grouped by user story) + 1 Architecture invariant (extends existing `AppendOnlyTablesHaveNoSoftDeletesTest`)
**Target Platform**: Laravel modular monolith — `app/Modules/Settlement/`
**Performance Goals**: Approve action < 200 ms p95 (no ledger write); MarkPaid action < 600 ms p95 (single ledger group + media write + audit row, all within a single DB transaction)
**Constraints**:
- `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, `audit_logs` remain fully append-only (no schema changes; no UPDATE/DELETE of historical rows)
- All ledger writes continue to go through `PostLedgerTransactionAction` / `LedgerWriter` — this feature does NOT add a second writer path
- `MarkPaid` must be idempotent under the existing `wd_settle:{id}` idempotency key scope (Phase 4.9 wallet-lock + `idempotency_keys` table)
- Proof file MIME ∈ {pdf, jpeg, png}, max 10 MB, exactly one per withdrawal
- `bank_transfer_reference` UNIQUE per `(vendor_profile_id, bank_transfer_reference)` — DB-level + Form-Request-level
- Bilingual: `admin_payment_note` is JSON translatable EN/AR (one or both locales required at minimum if any note is supplied)
**Scale/Scope**: Phase 1 — < 100 vendors, < 50 withdrawals/week; no horizontal scaling concerns. Single Redis instance for wallet locks.

---

## Constitution Check

*GATE: All 11 constitution principles verified. Re-checked post-design — all still pass.*

| # | Principle | Status | How satisfied |
|---|---|---|---|
| I | Modular monolith — module boundary respected | ✅ PASS | Everything lives under `app/Modules/Settlement/`. No new module. No cross-module model import (admin user reference goes via `User::class` from Identity, which is the existing pattern in Settlement — see `Withdrawal::processedBy()`). |
| II | `match($enum)` not if/elseif on type strings | ✅ PASS | No product-type branching in this feature (withdrawals are not type-aware). State-machine transitions use `spatie/laravel-model-states` transitions, not string compares. |
| III | Money as integer minor units (Brick\Money) | ✅ PASS | Existing `requested_amount_minor` / `paid_amount_minor` (BIGINT + CHAR(3)) reused. `bank_transfer_reference` is a free-text VARCHAR, not a money column. No new money columns. |
| IV | Bilingual EN+AR mandatory | ✅ PASS | `admin_payment_note` is a JSON translatable column (mirror of existing `rejected_reason`). Both locales accepted on the Filament form; vendor Resource resolves to `App::getLocale()`. Pest tests assert both EN and AR responses. |
| V | Append-only tables respected | ✅ PASS | Zero schema or row changes on `wallet_ledger`, `ledger_transaction_groups`, `audit_logs`, `payments`, `commissions`, `booking_state_transitions`, `event_outbox`, `analytics_events`, `loyalty_ledger`, `booking_snapshots`, `chat_message_log`, `search_logs`, `financial_snapshots`. The new `withdrawals` columns are on a NON-append-only table (per `11_DB_Schema.md §0` `withdrawals` is "status-only updates" — and `approved_at`/`paid_at`/`approved_by_admin_id`/`paid_by_admin_id`/`bank_transfer_reference`/`admin_payment_note` are all set exactly ONCE per status transition, never edited afterwards, enforced by the state-machine guard). Architecture test extension confirms invariant. |
| VI | ADR before code | ✅ PASS | ADR-0032 will be written and accepted before any migration is committed. The slash command `/new-module-adr` is NOT used (no new module); instead a focused decision ADR follows the same template structure. |
| VII | Test-first for critical paths (money flows) | ✅ PASS | This is a money-flow feature → Pest test plan defined up-front in `data-model.md §6` and `quickstart.md §3` with the 9 enumerated cases from `spec.md FR-018`. Tests are written same-day as the Actions, not deferred. 80%+ coverage on the two new Actions. |
| VIII | Idempotency on state-changing endpoints | ✅ PASS | `MarkPaid` is currently a Filament admin action, not a public API endpoint, but inherits the existing `wd_settle:{id}` ledger idempotency key (Phase 4.9). Approve also gets an idempotency key (`wd_approve:{id}`) for symmetry, even though it does not post a ledger group, so a double-click cannot create two audit rows. |
| IX | Domain events fire `DB::afterCommit` | ✅ PASS | New `WithdrawalApproved` event + existing `WithdrawalPaid` event both fire via `DB::afterCommit(fn () => event(...))`. No synchronous listeners on these events in this feature (notification wiring deferred to Phase 5 Communications per spec §Out of Scope). |
| X | Vendor approval two-step gate | ✅ PASS | Not directly applicable — this is admin-facing settlement work. The vendor permissions check (`settlement.view_withdrawals.own` for the read API) remains unchanged. |
| XI | Document storage — typed vs. gallery | ⚠️ PASS (with documented precedent) | Per constitution §XI strict reading, settlement proofs should use "Direct S3 private with explicit columns". The existing Settlement implementation (accepted in plan 008) uses `spatie/laravel-medialibrary` on the `s3_private` disk with the typed `bank_proof` single-file collection. This plan **continues that precedent** — a switch to typed columns would require a data migration of existing media rows and is out of scope for this feature. **Recommendation**: log a follow-up in ADR-0032 §Consequences proposing constitution §XI to be amended OR a Phase 8 cleanup ticket to migrate. See Complexity Tracking. |

**GATE RESULT: 10 ✅ PASS, 1 ⚠️ PASS-with-precedent. Proceed to Phase 0 — the §XI item is flagged in Complexity Tracking, not blocking.**

**Procedural note**: Constitution §"Spec-Kit Workflow Integration → /speckit.clarify" requires `/speckit.clarify` before `/speckit.plan` for money flows. This feature is a money flow. The clarify step was skipped because the `spec.md` ships with 0 `[NEEDS CLARIFICATION]` markers, an explicit "Out of Scope" section, and an "Assumptions" section that pre-resolves the typically-clarified questions. If any clarification is requested mid-implementation, pause and run `/speckit.clarify` retroactively.

---

## Project Structure

### Documentation (this feature)

```text
specs/033-withdrawal-proof-audit/
├── plan.md              ← This file (/speckit.plan output)
├── spec.md              ← Feature specification
├── research.md          ← Phase 0: 7 design decisions resolved
├── data-model.md        ← Phase 1: withdrawals delta + new Actions + DTOs + state machine + test plan
├── quickstart.md        ← Phase 1: migration order, seeders, smoke test, manual verification
├── contracts/
│   ├── vendor-show-withdrawal.md       ← GET /api/v1/vendor/withdrawals/{public_id} response shape (extended)
│   ├── vendor-list-withdrawals.md      ← GET /api/v1/vendor/withdrawals response shape (extended)
│   ├── admin-approve-action.md         ← Filament action contract: ApproveWithdrawalAction
│   └── admin-mark-paid-action.md       ← Filament action contract: MarkWithdrawalPaidAction (form + validation + media + ledger)
├── checklists/
│   └── requirements.md                 ← Spec validation checklist (from /speckit.specify)
└── tasks.md             ← Phase 2 output (/speckit.tasks — not yet generated)
```

### Source Code (repository root)

```text
app/Modules/Settlement/
├── Domain/
│   ├── Models/
│   │   └── Withdrawal.php                  ← MODIFIED — add casts/fillable for 5 new columns + media collection notes
│   ├── Enums/
│   │   └── WithdrawalStatus.php            ← UNCHANGED (enum already has approved + paid)
│   ├── States/WithdrawalStatus/
│   │   ├── Transitions/
│   │   │   ├── PendingToApproved.php       ← MODIFIED (or NEW if missing) — sets approved_at + approved_by_admin_id
│   │   │   ├── PendingToRejected.php       ← UNCHANGED
│   │   │   ├── ApprovedToPaid.php          ← NEW — sets paid_at + paid_by_admin_id + bank_transfer_reference + admin_payment_note
│   │   │   └── (no PendingToPaid)          ← REMOVED if exists — state-machine guard
│   │   └── ApprovedState.php, PaidState.php, PendingState.php, RejectedState.php ← UNCHANGED
│   ├── Events/
│   │   ├── WithdrawalApproved.php          ← NEW
│   │   └── WithdrawalPaid.php              ← UNCHANGED (existing)
│   └── Exceptions/
│       └── InvalidWithdrawalTransitionException.php ← NEW (or reuse ModelStates exception)
├── Application/
│   ├── Actions/
│   │   ├── ApproveWithdrawalAction.php     ← NEW — execute(Withdrawal, User $admin): Withdrawal
│   │   ├── MarkWithdrawalPaidAction.php    ← NEW — execute(Withdrawal, MarkWithdrawalPaidInput, User $admin): Withdrawal
│   │   ├── ApproveAndMarkWithdrawalPaidAction.php ← DEPRECATED (kept as thin wrapper for one release, marked @deprecated, then deletable in a follow-up cleanup)
│   │   ├── RequestWithdrawalAction.php     ← UNCHANGED
│   │   └── RejectWithdrawalAction.php      ← UNCHANGED
│   └── DTOs/
│       └── MarkWithdrawalPaidInput.php     ← NEW — readonly DTO {bankTransferReference, proofFile: UploadedFile, paymentNote: array{en?, ar?}}
├── Http/
│   └── Resources/
│       └── WithdrawalResource.php          ← MODIFIED — adds approved_at, paid_at, bank_transfer_reference, admin_payment_note (locale-resolved), proof_download_url (signed, per-request), timeline[]
├── Filament/
│   ├── Resources/
│   │   ├── WithdrawalsQueueResource.php    ← MODIFIED — split combined action into Approve + MarkPaid; remove FileUpload from Approve
│   │   └── WithdrawalResource.php          ← MODIFIED — detail page adds audit infolist section + ledger-group links + proof preview
│   └── Vendor/Pages/
│       └── VendorWalletPage.php            ← MODIFIED — withdrawals table column for status timeline + proof-download button (paid only)
└── Database/
    ├── Migrations/
    │   └── 2026_05_16_000001_alter_withdrawals_add_approve_pay_audit_columns.php  ← NEW
    └── Seeders/
        └── SettlementPermissionsSeeder.php ← MODIFIED — adds withdrawal.approve, withdrawal.mark_paid, withdrawal.view_audit

tests/
└── Feature/Modules/Settlement/
    └── WithdrawalApproveMarkPaidTest.php   ← NEW — 9 cases (1 file, grouped via Pest describe/it)

tests/Architecture/
└── AppendOnlyTablesHaveNoSoftDeletesTest.php ← UNCHANGED (already covers withdrawals via its "status-only" exception list)

docs/adr/
└── 0032-withdrawal-two-step-approve-mark-paid.md ← NEW

docs/api/collections/settlement/
└── 05_show_withdrawal.bru                  ← MODIFIED — response example reflects new fields (no new endpoints added)

.specify/memory/api-registry.md             ← MODIFIED — bump existing 3 endpoints' notes column to mark "extended response" + a new "WithdrawalAudit" Resource (no new rows)
```

**Structure Decision**: Brownfield modification within an existing module (Settlement). No new module, no new top-level folders. All new files slot into existing layer directories. The 5 new `withdrawals` columns are added in a single additive migration that backfills `approved_at` / `approved_by_admin_id` for historical `paid` rows from `processed_at` / `processed_by_user_id` (since those columns dual-served both meanings before this feature).

---

## Complexity Tracking

> Filled because Constitution Check noted one ⚠️ (Principle XI — settlement proof storage).

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Bank proof stored via `spatie/laravel-medialibrary` collection rather than direct S3 columns (strict reading of constitution §XI) | (a) Existing implementation already uses it and was accepted in plan 008. (b) Spatie media gives free per-file MIME + size validation, multi-conversion (PDF thumbnail), and a centralized `media` table that admin audit pages can join against. (c) The file already sits on the `s3_private` disk — the "private" intent of §XI is met. (d) Migrating existing media rows to typed columns would require either a downtime data migration or a double-write window — high blast radius for a Phase 4 hardening feature. | The typed-columns alternative (`bank_proof_file_path VARCHAR(500)`, `bank_proof_file_name`, `bank_proof_file_mime`, `bank_proof_file_size_bytes`) is technically purer but would (i) require migrating the existing rows that already use Spatie, (ii) lose the per-request signed URL helper that `Media::getTemporaryUrl()` provides, and (iii) diverge from plan 008's accepted approach without amending the constitution. ADR-0032 §Consequences will log the inconsistency and propose either a constitution §XI amendment OR a Phase 8 cleanup ticket. |

---

## Phase 0 / Phase 1 artifact map

- `research.md` — 7 decisions (approve-vs-paid split, idempotency key naming, column naming, payment-note translatable shape, proof URL signing, deprecation strategy for combined action, ADR scope)
- `data-model.md` — `withdrawals` delta diagram, new state-machine transitions, `MarkWithdrawalPaidInput` DTO shape, audit row payloads, the 9-case test plan
- `contracts/*.md` — vendor read endpoint response examples (EN + AR), Filament admin action contracts
- `quickstart.md` — migration command order, seeder regen, manual smoke test on local + staging, troubleshooting

---

## Next Step

`/speckit.tasks` will turn this plan into the ordered task list following the layer order from constitution §"`/speckit.tasks`":

1. ADR-0032 finalization
2. Migration: `alter_withdrawals_add_approve_pay_audit_columns`
3. `Withdrawal` model fillable/casts + media-collection registration confirmation
4. `MarkWithdrawalPaidInput` DTO
5. `ApproveWithdrawalAction` + `MarkWithdrawalPaidAction` + state-machine transition classes
6. `WithdrawalResource` (HTTP) — add new fields + signed proof URL
7. `WithdrawalsQueueResource` + `WithdrawalResource` (Filament) split + audit infolist
8. `VendorWalletPage` — wire vendor proof download
9. Domain event `WithdrawalApproved` + dispatch via `DB::afterCommit`
10. Pest test file with 9 cases — same day as code
11. Update api-registry + Bruno collection example responses

---

**END OF PLAN — proceed with `/speckit.tasks`.**
