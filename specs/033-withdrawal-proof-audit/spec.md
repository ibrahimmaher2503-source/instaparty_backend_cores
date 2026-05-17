# Feature Specification: Withdrawal Proof & Finance Audit

**Feature Branch**: `033-withdrawal-proof-audit`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Enhance withdrawal flow with transfer proof upload and finance audit. Admin can approve withdrawal, execute bank transfer, upload proof, mark paid, and vendor can see proof/status in wallet/withdrawals."

## Traceability *(mandatory)*

### FR Traceability
- **FR-28** (PRD §7.7) — Vendor wallet visibility (earnings, deductions, available balance) → vendor-facing withdrawal status + proof view.
- **FR-29** (PRD §7.7) — Admin reviews and approves withdrawal requests → split into `Approve` and `MarkPaid` admin actions with full audit.
- **FR-EXT-118** (PRD §7.7) — Withdrawal reserve/settle/reject must post double-entry ledger groups linked to the withdrawal row → preserved untouched; this feature only adds custodial metadata (timestamps, admin IDs, transfer reference, proof, payment note) around the existing ledger transitions.
- **FR-EXT-205** (PRD §7.9): A withdrawal MUST progress through an explicit `approved` state before it can be marked `paid`; the system MUST reject any `MarkPaid` attempt on a withdrawal whose status is not `approved`.
    - **FR-EXT-206** (PRD §7.9): Marking a withdrawal `paid` MUST require both (a) a non-empty bank-transfer reference identifier and (b) at least one transfer-proof file attachment; either missing input MUST cause the operation to fail without mutating ledger or status.
    - **FR-EXT-207** (PRD §7.9): The withdrawal record MUST persist who approved it (`approved_by_admin_id`, `approved_at`) separately from who marked it paid (`paid_by_admin_id`, `paid_at`); a single admin user MAY hold both roles but the two events MUST be auditable independently.
    - **FR-EXT-208** (PRD §7.9): The vendor that owns a withdrawal MUST be able to retrieve, for any of their withdrawals: the current lifecycle status, the timestamp of each transition, the bank-transfer reference (once paid), the admin payment note (once paid), and a download link for the transfer proof file (once paid). No other vendor MAY access another vendor's withdrawal record.
    - **FR-EXT-209** (PRD §7.9): The append-only `wallet_ledger` and `ledger_transaction_groups` tables MUST remain unmodified by approve / mark-paid / proof-upload operations except via the existing `LedgerWriter` (no direct UPDATE / DELETE of historical rows).

### Schema Traceability
- **Existing tables (LOCKED — see `11_DB_Schema.md`):** `withdrawals`, `wallets`, `wallet_ledger`, `ledger_transaction_groups`, `audit_logs`, `media` (Spatie).
- **Schema updated** (`11_DB_Schema.md` §9 `withdrawals` table, 2026-05-16 Phase 4.11): the locked schema now reflects the five new columns and the new UNIQUE index added by this feature. The existing fields `approved_by`, `approved_at`, `paid_at`, `transfer_proof_path`, `rejection_reason` remain; the extension columns are documented below.
- **Columns to add or rename on `withdrawals`** (existing table, additive migration):
    - `approved_at TIMESTAMP NULL` (new — split from current `processed_at`)
    - `approved_by_admin_id BIGINT UNSIGNED NULL FK→users` (new — split from current `processed_by_user_id`)
    - `paid_by_admin_id BIGINT UNSIGNED NULL FK→users` (new — explicit name; replaces the dual use of `processed_by_user_id`)
    - `bank_transfer_reference VARCHAR(120) NULL` (new — gateway/bank reference number; UNIQUE per `(vendor_profile_id, bank_transfer_reference)` to prevent duplicate proofs)
    - `admin_payment_note JSON NULL` (new — translatable `{en, ar}` free-text note left by paying admin)
- **Columns that stay as-is**: `status` ENUM (`pending`, `approved`, `paid`, `rejected`), `bank_proof_media_id`, `pending_lock`, `reserved_ledger_entry_id`, `settled_ledger_entry_id`, `rejected_ledger_entry_id`, `idempotency_key`, `bank_account_snapshot`, `rejected_reason`, `requested_at`, `paid_at`, `requested_by_user_id`, `paid_amount_minor/_currency`.
- **Media collection** `bank_proof` (Spatie, `singleFile()`, PDF/JPEG/PNG) — already registered; this feature keeps the collection and only formalizes its mandatoriness on `MarkPaid`.
- **No new top-level entity tables.** No append-only table is modified by this feature.

### Phase Alignment
- Settlement core lived in **Phase 4.2** (`09_Phasing_Plan.md`) with a stub of "approve + paid in one step".
- Ledger hardening was **Phase 4.9** (ADR-0028) and added `(reserved|settled|rejected)_ledger_entry_id` foreign keys.
- **Phase inserted** (`09_Phasing_Plan.md`, 2026-05-16): **Phase 4.11 — Withdrawal Proof & Finance Audit** (1–2 days, Week 8) has been added between Phase 4.10 and Phase 5.5. Predecessors: Phase 4.2 (withdrawals exist), Phase 4.9 (ledger groups + idempotency exist). Successors: none blocking. ADR: `docs/adr/ADR-0032-withdrawal-proof-audit.md`.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Two-step admin payout with required proof and reference (Priority: P1)

A finance-team admin opens the **Withdrawals Queue** in the admin panel, picks a pending request, performs the bank transfer outside the system, and records the outcome inside the system in two distinct steps:

1. **Approve** — the admin reviews the vendor's request and bank-account snapshot, and explicitly approves it. The withdrawal moves from `pending → approved`. The wallet's pending-reservation ledger entry has already been posted at `pending` time (Phase 4.9 behavior) and is not re-posted here.
2. **Mark Paid** — the admin uploads the bank's transfer proof (PDF or image), enters the bank's transfer-reference number, optionally writes an EN/AR payment note, and confirms. The withdrawal moves from `approved → paid` and the existing settle ledger group is posted (existing Phase 4.9 behavior).

**Why this priority**: This is the entire goal of the feature — separating approval from payment closes the current gap where the implementation collapses both into one click, losing the audit trail for "who approved" vs "who paid" and allowing payment without proof.

**Independent Test**: An admin can take a pending withdrawal through both steps, with the system rejecting any attempt to skip Approve, and rejecting any MarkPaid that lacks proof or a reference. After MarkPaid, the withdrawal row contains both timestamps, both admin IDs, the reference, the optional note, and a downloadable proof media item — without any further code change required to verify the audit fields.

**Acceptance Scenarios**:

1. **Given** a withdrawal in `pending` status with a valid bank-account snapshot, **When** an admin with `withdrawal.approve` permission calls Approve, **Then** the status becomes `approved`, `approved_at` is now, `approved_by_admin_id` is the acting admin, and no ledger group is posted (Phase 4.9 reserve already exists from request time).
2. **Given** a withdrawal in `approved` status, **When** the paying admin submits a valid bank-transfer reference and a transfer-proof file, **Then** the status becomes `paid`, `paid_at` is now, `paid_by_admin_id` is the acting admin, the reference is stored, the proof is attached as a media item in the `bank_proof` collection, and the existing settle ledger group is posted with the same idempotency key as before.
3. **Given** a withdrawal in `pending` status, **When** an admin attempts `MarkPaid` directly (skipping Approve), **Then** the operation is refused with a validation/state-machine error, the status remains `pending`, no ledger entries are posted, and no media is attached.
4. **Given** a withdrawal in `approved` status, **When** the admin submits the form with an empty bank-transfer reference, **Then** the operation is refused with a validation error, the status remains `approved`, and no ledger group is posted.
5. **Given** a withdrawal in `approved` status, **When** the admin submits the form without any proof file, **Then** the operation is refused with a validation error, the status remains `approved`, and the existing media collection contains no `bank_proof` item.
6. **Given** the same withdrawal is submitted to MarkPaid twice with the same `Idempotency-Key`, **When** the second request arrives, **Then** the cached response from the first attempt is replayed and no second ledger group is created.

### User Story 2 — Vendor sees status timeline, reference, and proof in their wallet (Priority: P1)

A vendor opens their **Wallet → Withdrawals** screen, sees the lifecycle of each of their requests, and — for any paid withdrawal — can view the bank-transfer reference, the admin's optional EN/AR payment note, and download the transfer-proof file.

**Why this priority**: Without this, the audit fields exist only for admins. Vendors must be able to self-serve "did you pay me?" without contacting support, which is the user-facing payoff of the whole feature.

**Independent Test**: A vendor can request a withdrawal, then (after admin actions) refresh their wallet page and see a full timeline (requested → approved → paid) with timestamps, the reference string, the note in their current locale, and a working download link for the proof. A different vendor on a different account can NOT see this withdrawal at all (404).

**Acceptance Scenarios**:

1. **Given** a vendor has a withdrawal currently in `approved` status, **When** they fetch their withdrawal detail, **Then** they see the requested timestamp, the approved timestamp, no paid timestamp, no reference, no note, and no proof.
2. **Given** a vendor has a withdrawal currently in `paid` status, **When** they fetch their withdrawal detail, **Then** they see all three timestamps, the bank-transfer reference, the admin payment note resolved to their current locale, and a time-limited URL (or pre-signed link, per the platform's media disk convention) that lets them download the proof file.
3. **Given** vendor A and vendor B both have withdrawals, **When** vendor B requests vendor A's withdrawal by its `public_id`, **Then** the system returns 404 (not 403, to avoid revealing the ID's existence).
4. **Given** a vendor has a withdrawal in `rejected` status, **When** they fetch its detail, **Then** they see the rejection reason in their current locale, no reference, no note, no proof.
5. **Given** a vendor's bank-transfer-reference field contains a value, **When** the vendor's locale switches between EN and AR, **Then** the reference itself is shown verbatim (it is not translatable) while the payment note shows the matching locale.

### User Story 3 — Admin detail page surfaces the full approval + payment audit (Priority: P2)

When opening any withdrawal in the admin panel, the admin sees a single page that shows: vendor, bank-account snapshot, amount, current status, who requested it & when, who approved it & when (if applicable), who paid it & when (if applicable), the bank-transfer reference, the payment note, an inline preview of the proof file, and links to the related ledger transaction groups (`reserved`, `settled`, or `rejected`).

**Why this priority**: This is the operational/finance use case — answering "what happened" in one place. It rides on the same fields as P1, but it's independent because P1 changes only the action flow; P3 changes only the view.

**Independent Test**: An admin opens a paid withdrawal detail page and confirms every audit element is present and renders, with the proof file viewable (image inline / PDF in a link) and the linked ledger group reachable in one click.

**Acceptance Scenarios**:

1. **Given** a paid withdrawal, **When** the admin opens its detail page, **Then** all approve + paid audit fields are visible and the proof file is rendered.
2. **Given** a pending or rejected withdrawal, **When** the admin opens its detail page, **Then** the approve / paid sections show a clear empty / "not yet" state rather than blank fields.
3. **Given** an admin without `withdrawal.view_audit` permission, **When** they open the page, **Then** the bank-transfer reference and the proof file area are not visible (the rest of the page renders).

### Edge Cases

- A withdrawal is reverted from `approved` back to `pending` — **out of scope**; per the locked state machine and append-only ledger, an approved withdrawal can only go to `paid` or `rejected`. If an admin needs to undo an approval, this feature does NOT enable that (raise a follow-up if required).
- A withdrawal is rejected after it has been approved — currently the state machine only allows reject from `pending`. This feature does NOT change that. If finance later asks for "reject after approve" it requires a separate state-machine change.
- The bank transfer fails after MarkPaid was recorded — out of scope here; the existing operational pattern is to record a manual wallet adjustment (Phase 4.X admin wallet adjustments).
- Duplicate `bank_transfer_reference` per vendor — must be rejected at the application level with a clear validation message; the database enforces the same via a UNIQUE index across `(vendor_profile_id, bank_transfer_reference)`.
- Proof file size exceeds limit (10 MB) or wrong MIME type (must be PDF / JPEG / PNG) — rejected at the request-validation layer; the MarkPaid action is never invoked.
- The proof file disk is unavailable when admin clicks save — the entire DB transaction must roll back; the ledger group must NOT be written; the status must NOT advance to `paid`.
- The Redis wallet lock on `MarkPaid` cannot be acquired within the configured wait window — the operation must fail with a user-facing "please retry" message; status and ledger remain unchanged.
- A vendor tries to upload a proof to their own withdrawal — must fail with 403 / hidden endpoint; proof upload is admin-only.
- The admin payment note is provided in only one locale — the other locale stores `null`; the vendor view falls back to the available locale.
- The withdrawal is older than the proof file retention policy — out of scope; if the proof file is purged from storage, the vendor download link returns the platform's standard "file not found" page and the audit row still shows the reference and metadata.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001 (FR-EXT-205)**: The withdrawal state machine MUST require an explicit `approved` step before any `paid` transition; the system MUST refuse a `MarkPaid` call on a withdrawal not currently in `approved` state.
- **FR-002 (FR-EXT-206)**: The system MUST reject a `MarkPaid` call that lacks a non-empty bank-transfer reference, AND the system MUST reject a `MarkPaid` call that lacks a transfer-proof file. Both errors MUST occur before any DB transaction, wallet lock, or ledger write begins.
- **FR-003 (FR-EXT-207)**: On successful Approve, the system MUST persist `approved_at` (UTC) and `approved_by_admin_id`. On successful MarkPaid, the system MUST persist `paid_at` (UTC), `paid_by_admin_id`, the bank-transfer reference, and the optional EN/AR admin payment note.
- **FR-004**: Approve and MarkPaid MUST each be wrapped in a single DB transaction; ledger writes (where applicable), media attachment, and `withdrawals` row update MUST commit atomically. Domain events (`WithdrawalApproved`, `WithdrawalPaid`) MUST fire only after commit (per `.claude/rules/actions.md`).
- **FR-005**: Approve and MarkPaid MUST each write an `audit_logs` row with `auditable_type=Withdrawal`, the acting admin's `user_id`, the action name, and a JSON `changes` payload describing the before/after status and the key fields touched. The audit row is part of the same transaction as the status change.
- **FR-006 (FR-EXT-208)**: Vendors MUST be able to GET their own withdrawal list and detail through the existing `/api/v1/vendor/withdrawals` and `/api/v1/vendor/withdrawals/{public_id}` endpoints, with the detail response extended to include `approved_at`, `paid_at`, `bank_transfer_reference` (when present), `admin_payment_note` resolved to the request locale, the lifecycle timeline, and a download URL for the proof file (when present). Listing and detail MUST 404 for withdrawals not owned by the requesting vendor.
- **FR-007**: The admin Filament `WithdrawalsQueueResource` MUST expose two distinct row actions: `Approve` (no form fields beyond optional reviewer note) and `MarkPaid` (form: `bank_transfer_reference` required, proof upload required, EN payment note optional, AR payment note optional). The single combined `Approve & Mark Paid` action MUST be replaced.
- **FR-008**: The admin Filament `WithdrawalResource` detail/view page MUST render: vendor + bank snapshot, amount, current status badge, timestamps (`requested_at`, `approved_at`, `paid_at`), admin IDs (`requested_by`, `approved_by_admin`, `paid_by_admin`), `bank_transfer_reference`, `admin_payment_note` (both locales side-by-side), proof file preview, and links to the `reserved`, `settled`, `rejected` ledger transaction groups when present.
- **FR-009 (FR-EXT-209)**: This feature MUST NOT change `wallet_ledger`, `ledger_transaction_groups`, `financial_snapshots`, or any other append-only table's schema, and MUST NOT mutate existing rows in any of those tables. All ledger interaction MUST continue to go through `PostLedgerTransactionAction` / `LedgerWriter`.
- **FR-010**: Wallet balance projection logic (Phase 4.9, ADR-0028) MUST NOT be re-implemented; this feature relies on the pre-existing reserve-at-request and settle-at-paid ledger flow. Approve does NOT post a ledger group of its own.
- **FR-011**: `MarkPaid` MUST be idempotent via the same `Idempotency-Key` scope already used for withdrawal mutations (Phase 4.9). A duplicate key within 24 h MUST replay the stored response and MUST NOT create a second ledger group, second media attachment, second audit row, or second event.
- **FR-012**: Permissions MUST be enforced as follows: `withdrawal.approve` for the Approve action, `withdrawal.mark_paid` for the MarkPaid action, `withdrawal.view_audit` for visibility of reference + proof on the admin detail page, and the existing `settlement.view_withdrawals.own` for vendor read. Roles MUST be added to the seeder so Shield's `php artisan shield:generate` recognises them.
- **FR-013**: `bank_transfer_reference` MUST be unique per `(vendor_profile_id, bank_transfer_reference)` and MUST reject any duplicate at the request-validation layer with a clear error message in the user's locale.
- **FR-014**: Proof file constraints MUST be: MIME type in `{application/pdf, image/jpeg, image/png}`, maximum 10 MB, exactly one file per withdrawal (single-file media collection); the file MUST live on the platform's private media disk (Spaces in prod, MinIO in dev).
- **FR-015**: The vendor's download URL for the proof file MUST be a time-limited / signed URL (per the platform's existing media-disk convention) and MUST be generated server-side per request — never persisted on the withdrawal row.
- **FR-016**: A vendor MUST NOT be able to mutate the bank-transfer reference, the proof file, or any of the approve/paid audit fields by any API path. These fields are admin-write, vendor-read.
- **FR-017**: The combined `ApproveAndMarkWithdrawalPaidAction` MUST be removed (or kept temporarily as a deprecated facade that delegates to the two new Actions and is no longer wired to the Filament action). The new Actions MUST be `ApproveWithdrawalAction::execute(Withdrawal $w, User $admin): Withdrawal` and `MarkWithdrawalPaidAction::execute(Withdrawal $w, MarkWithdrawalPaidInput $input, User $admin): Withdrawal`, where `MarkWithdrawalPaidInput` is a typed DTO carrying `bankTransferReference`, `proofFile`, and `paymentNote: array{en?: string, ar?: string}`.
- **FR-018**: Tests (Pest, under `tests/Feature/Modules/Settlement/`) MUST cover at minimum:
    - Vendor requests withdrawal — wallet reserved entry posted.
    - Admin approves a pending withdrawal — status becomes `approved`, no ledger group posted.
    - Admin marks paid with proof + reference — status becomes `paid`, settle ledger group posted, reference + note + paid timestamps persisted, audit row written.
    - Admin cannot mark paid without proof — request fails validation, status unchanged, no ledger change.
    - Admin cannot mark paid without reference — request fails validation, status unchanged, no ledger change.
    - Admin cannot mark paid on a `pending` withdrawal — state-machine error, status unchanged, no ledger change.
    - A different vendor cannot fetch this withdrawal (404).
    - `wallet_ledger` is append-only — no UPDATE / DELETE issued by either Action (assert via DB query or repository spy).
    - MarkPaid is idempotent — replaying with the same `Idempotency-Key` returns the cached response and produces no second ledger group.

### Key Entities

- **Withdrawal** (existing): the user-facing payout request. New custodial fields: `approved_at`, `approved_by_admin_id`, `paid_by_admin_id`, `bank_transfer_reference`, `admin_payment_note`. Existing fields unchanged: `requested_at`, `paid_at`, `bank_account_snapshot`, `rejected_reason`, status, ledger-link FKs.
- **Withdrawal Media (`bank_proof`)** (existing Spatie collection): the transfer-proof file. One per withdrawal. Admin-uploaded only.
- **Audit Log Row** (existing `audit_logs`): records each Approve and MarkPaid event with before/after status and acting admin.
- **Ledger Transaction Group** (existing, append-only): the `reserved` group at request time and the `settled` group at paid time. This feature does not change how or when those are written.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of withdrawal records moved to `paid` after this feature ships carry a non-null `bank_transfer_reference`, a non-null `paid_by_admin_id`, and exactly one attached file in the `bank_proof` media collection. (Verify via a one-off query in staging after manual test runs.)
- **SC-002**: An admin can complete the two-step Approve → MarkPaid flow on a single withdrawal in under 60 seconds end-to-end, including file upload, on a typical office network.
- **SC-003**: A vendor with one paid withdrawal can locate and download their transfer proof in under 30 seconds from opening the wallet screen (≤ 3 clicks).
- **SC-004**: Zero withdrawal records reach `paid` status without proof + reference, as enforced by the database UNIQUE constraint and the request-validation layer. The Pest suite contains assertions that prevent this in CI.
- **SC-005**: `php artisan ledger:diff` (Phase 4.9 reconciliation command) returns clean (zero drift) before and after running the new test suite — proving the new flow has not introduced any wallet projection drift.
- **SC-006**: An auditor can determine, for any paid withdrawal, the answer to all five questions — "who requested, who approved, who paid, what bank reference, what proof file" — from the admin detail page alone, with no SQL access required.
- **SC-007**: Support tickets of the form "I was approved but never received my money" or "proof of transfer?" decline measurably once the vendor-facing view ships (qualitative — assess two weeks after rollout).

---

## Assumptions

- The existing Phase 4.9 wallet-lock + ledger-writer + idempotency infrastructure is functioning and remains unchanged. This feature is custodial-metadata + UX-only on top of that.
- The current `bank_proof` Spatie media collection on `Withdrawal` is acceptable; we do NOT migrate to the legacy `transfer_proof_path` string named in the locked schema. The schema spec will be backfilled to match the implementation.
- The existing combined `ApproveAndMarkWithdrawalPaidAction` is to be split, with its current Filament wiring replaced. No external consumer outside Filament calls it (verified: only `WithdrawalsQueueResource` uses it).
- Permissions `withdrawal.approve`, `withdrawal.mark_paid`, `withdrawal.view_audit` are new but follow the existing Shield + Spatie permission naming pattern; they are added to the `BookingPermissionsSeeder`-style seeder for Settlement.
- The vendor wallet detail UI lives in the Vendor Filament panel (`app/Modules/Settlement/Filament/Vendor/Pages/VendorWalletPage.php`) — this feature extends that page; it does NOT introduce a separate Next.js / Flutter view (frontend builds come after backend per CLAUDE.md).
- The proof file disk is private — proof URLs are signed per-request, never embedded in the API response as a permanent CDN URL.
- The bank-transfer reference is single-line free text (max 120 chars). It is not a structured field; banks vary too widely to validate format. Uniqueness is enforced per-vendor (one bank's reference number can legitimately collide with another bank's).
- `admin_payment_note` is translatable EN/AR JSON, mirroring `rejected_reason`. The admin enters one or both locales; the vendor view uses Laravel's translatable fallback.
- The feature operates within Phase 1 scope: it formalises an existing flow rather than introducing new business capability. It does NOT add dispute resolution, manual reversal, partial-payout, or multi-currency handling — all of which remain explicit out-of-scope per PRD §5.2.

---

## Out of Scope *(explicit)*

The following are explicitly **not** part of this feature and require a separate spec if requested:

- Reverting an approved withdrawal back to `pending`.
- Rejecting a withdrawal after it has been approved.
- Partial payouts (one request → multiple proofs).
- Multi-currency withdrawals.
- Bank-API direct integration (proof remains a manually-uploaded file).
- Per-withdrawal dispute resolution flow.
- Bulk Approve or bulk MarkPaid actions.
- Vendor-facing notification dispatch on each transition (Phase 5 Communications handles notification templates — only the event firing is in scope; the channel templates and dispatch wiring are not).
