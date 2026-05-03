# Feature Specification: Settlement — Wallets, Commissions, Withdrawals (Phase 4.2)

**Feature Branch**: `008-settlement-wallets-commissions-withdrawals`  
**Created**: 2026-05-03  
**Status**: Draft  
**Phase**: 4.2 (3 days, Week 5–6) per `docs/specs/09_Phasing_Plan.md`  
**ADR**: [ADR-0009 — Settlement Module](../../docs/adr/0009-settlement-module.md) — *Accepted 2026-05-03*  
**PRD coverage**: FR-28 (vendor wallet visibility), FR-29 (admin approves withdrawals), FR-30 (commission deduction + financial tracking)  
**Input**: User description: *"Phase 4.2 — Settlement module: vendor wallets and commissions on captured payments, plus withdrawal flow with admin approval and bank-transfer proof upload. Six tables: wallets, wallet_ledger, commissions, commission_rates, withdrawals, settlement_runs. Listener on PaymentCaptured calculates commission and credits vendor wallet; refund reverses both. Admin Filament: WithdrawalsQueue, WalletLedgerViewer, CommissionRules. PRD covers FR-28, FR-29, FR-30. ADR-0009 to be drafted on Day 1. Cut-list: defer settlement_runs auto-reconciliation, defer auto-approval rules."*

**Tables touched** (per `docs/specs/11_DB_Schema.md` lines 925–1015):
- `wallets` — per (owner_type, owner_id, currency); UNIQUE
- `wallet_ledger` — append-only entries (credit / debit / commission / refund / withdrawal)
- `commissions` — per `booking_item_id`; snapshot of `commission_bps`
- `commission_rates` — most-specific match: `(category_id × product_type)` → `(category_id × NULL)` → `(NULL × product_type)` → `(NULL × NULL)`
- `withdrawals` — vendor request lifecycle (`pending` → `approved` → `paid` | `rejected`)
- `settlement_runs` — table created, **no auto-reconciliation in Phase 4.2** (cut-list)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Commission Auto-Calculated and Wallet Credited on Payment Capture (Priority: P1) 🎯 MVP

A customer pays for a confirmed booking via Paymob. As soon as the payment is captured, the platform calculates each vendor's commission per `booking_item` (using the most-specific commission rate that matches `category_id × product_type`), credits the vendor's net share to their wallet, and records the platform commission separately.

**Why this priority**: Without commission calculation and wallet credit, vendors cannot be paid. This is the foundation for every other Settlement story — withdrawals are meaningless without a balance.

**Independent Test**: Trigger a `PaymentCaptured` event for a booking with two items (one rental, one sale, different vendors). Verify two `commissions` rows are created with correct `commission_bps` snapshot, two `wallet_ledger` credit entries appear (one per vendor), and each vendor's wallet balance equals their net share.

**Acceptance Scenarios**:

1. **Given** a captured payment for booking item `BI-1` (rental, category=Inflatables, vendor V1, total 1000 EGP) and `commission_rates` has only `(Inflatables × rental, 1500 bps)`, **When** the `PaymentCaptured` listener runs, **Then** a `commissions` row is created with `commission_bps=1500` and `commission_minor=15000` (15000 piastres = 150 EGP), and V1's wallet is credited 850 EGP via a `wallet_ledger` entry of type `commission_credit`.
2. **Given** the same booking but no `(Inflatables × rental)` rate exists and only `(Inflatables × NULL, 1200 bps)` exists, **When** the listener runs, **Then** the resolved rate is 1200 bps (Level 2 fallback).
3. **Given** no rate matches for `(category, type)` or `(category, NULL)` or `(NULL, type)`, but `(NULL, NULL, 1000 bps)` exists, **Then** the resolved rate is 1000 bps (Level 4 fallback).
4. **Given** no `commission_rates` row matches at any of the 4 levels, **Then** the listener logs a warning, uses platform default 0 bps, and the vendor receives the full amount.
5. **Given** a vendor has no wallet yet for the booking's currency, **When** the listener runs, **Then** the wallet is lazy-created and then credited.
6. **Given** the same `PaymentCaptured` event is delivered twice (queue retry), **When** processed, **Then** at most one `commissions` row exists per `booking_item_id` (idempotent on `booking_item_id` UNIQUE constraint).

---

### User Story 2 — Vendor Views Wallet Balance and Ledger History (Priority: P1)

A vendor opens their dashboard and sees their current wallet balance and a paginated history of every credit and debit (each row showing source, amount, timestamp, and a link to the originating booking item).

**Why this priority**: Vendors need transparency into their earnings before they request a withdrawal. Without balance visibility, FR-28 isn't met.

**Independent Test**: As an authenticated vendor with two `commission_credit` entries totaling 500 EGP and one `withdrawal_debit` of 200 EGP, call `GET /api/v1/vendor/wallet` → returns `balance_minor: 30000, balance_currency: EGP`. Call `GET /api/v1/vendor/wallet/ledger?per_page=50` → returns paginated entries newest first.

**Acceptance Scenarios**:

1. **Given** an authenticated vendor with no ledger entries, **When** they `GET /api/v1/vendor/wallet`, **Then** the response includes `balance_minor: 0, balance_currency: EGP, total_credits_minor: 0, total_debits_minor: 0`.
2. **Given** a vendor with 3 credits and 1 debit, **When** they `GET /api/v1/vendor/wallet`, **Then** the response shows correct balance and a summary of `total_credits_minor`, `total_debits_minor`, `pending_withdrawal_minor`.
3. **Given** the same vendor, **When** they `GET /api/v1/vendor/wallet/ledger?per_page=20`, **Then** entries are returned newest first, paginated, each with `entry_type`, `amount_minor`, `currency`, `description`, `related_entity_type`, `related_entity_id`, `created_at`.
4. **Given** an unauthenticated caller, **When** they call either endpoint, **Then** API returns 401.
5. **Given** vendor V1 tries to access vendor V2's wallet, **When** they call `GET /api/v1/vendor/wallet` (auth as V1), **Then** they only ever see their own wallet — no parameter exists to view someone else's.

---

### User Story 3 — Vendor Requests Withdrawal (Priority: P1)

A vendor with a positive wallet balance submits a withdrawal request specifying an amount up to their available balance. The system validates the request, creates a `pending` withdrawal record, and reserves the amount (visible as `pending_withdrawal_minor` on the wallet view).

**Why this priority**: Without a withdrawal request flow, vendors cannot get paid. FR-28/29 require this.

**Independent Test**: As a vendor with 500 EGP balance and no pending withdrawal, `POST /api/v1/vendor/withdrawals` with `amount_minor: 30000` → 201 with `status: pending, public_id: ...`. Repeat → 422 (single pending rule).

**Acceptance Scenarios**:

1. **Given** a vendor with balance 500 EGP and no pending withdrawal, **When** they `POST /api/v1/vendor/withdrawals` with valid amount + bank account snapshot, **Then** a `withdrawals` row is created with `status=pending`, `requested_amount_minor` set, and an `Idempotency-Key` is honored if provided.
2. **Given** a vendor with balance 100 EGP, **When** they request withdrawal of 200 EGP, **Then** API returns 422 with `insufficient_balance` error in the request locale.
3. **Given** a vendor with an existing `pending` withdrawal, **When** they request another, **Then** API returns 422 with `existing_pending_withdrawal` error referencing the existing `public_id`.
4. **Given** a vendor below the minimum withdrawal amount (default 100 EGP), **When** they request below the minimum, **Then** API returns 422 with `below_minimum_amount` error.
5. **Given** an unauthenticated caller, **When** they call the endpoint, **Then** API returns 401.
6. **Given** the vendor calls the endpoint twice in quick succession with the same `Idempotency-Key`, **When** processed, **Then** the second call returns the cached response (no duplicate withdrawal).

---

### User Story 4 — Admin Reviews and Approves Withdrawal with Bank-Transfer Proof (Priority: P1)

An admin opens the Filament `WithdrawalsQueue`, sees pending withdrawals, opens one, performs the bank transfer outside the system, uploads a proof PDF/image, and marks the withdrawal `paid`. The vendor's wallet is debited and the `paid_at` timestamp is recorded.

**Why this priority**: Without admin approval + payout marking, money cannot leave the platform. FR-29 explicitly requires admin approval.

**Independent Test**: As an admin with a `pending` withdrawal in the queue, open the Filament page, click "Approve & Mark Paid", upload a proof file, and submit. Verify `withdrawals.status=paid`, `paid_at` set, proof file in private storage, vendor wallet debited via a `withdrawal_debit` ledger entry.

**Acceptance Scenarios**:

1. **Given** a `pending` withdrawal exists, **When** admin opens Filament `WithdrawalsQueue`, **Then** it appears in the list with vendor name, requested amount in EGP, request timestamp.
2. **Given** the admin opens the row and clicks "Approve & Mark Paid", **When** they upload a bank-transfer proof and submit, **Then** withdrawal `status=paid`, `paid_at=now()`, `bank_proof_media_id` populated (via Spatie Media Library, private bucket), and a `wallet_ledger` entry of type `withdrawal_debit` is created.
3. **Given** an admin clicks "Reject", **When** they enter a rejection reason and submit, **Then** withdrawal `status=rejected`, `rejected_reason` translatable JSON populated, no wallet ledger entry, vendor sees rejection reason in their locale.
4. **Given** any status transition (pending → approved, approved → paid, pending → rejected), **When** it occurs, **Then** an `audit_logs` row is appended with `actor_type=admin`, `actor_id`, `action`, `old_status`, `new_status`, timestamp.
5. **Given** an admin without `settlement.approve_withdrawal` permission, **When** they try to view the queue, **Then** Filament denies access (403 / hidden navigation).

---

### User Story 5 — Refund Reverses Commission and Wallet Credit (Priority: P2)

When a refund is processed (Phase 4.1 work, listener wired here), the corresponding commission row is marked `reversed`, and a compensating `wallet_ledger` entry of type `refund_debit` is created on the vendor's wallet, debiting the previously credited net amount. If the refund is partial, the reversal is proportional.

**Why this priority**: Without reversal, refunds would leave vendors holding money for transactions that no longer exist. Required for end-to-end financial integrity.

**Independent Test**: Trigger a `RefundCompleted` event for a `booking_item` whose commission was credited 850 EGP. Verify a new ledger entry of type `refund_debit` appears with `amount_minor=85000`, vendor wallet balance decreases by 850 EGP, and the original `commissions` row is marked `status=reversed` (status update is allowed on commissions per §15 carve-out).

**Acceptance Scenarios**:

1. **Given** a captured payment with commission 150 EGP and vendor wallet credit 850 EGP, **When** `RefundCompleted` fires for the full amount, **Then** a `refund_debit` ledger entry of 850 EGP is appended and the `commissions` row's `status` becomes `reversed`.
2. **Given** the same setup but a partial refund of 500 EGP (50% of original), **When** processed, **Then** a `refund_debit` of 425 EGP is appended (50% of original 850 EGP credit) and the commission row's `status` becomes `partially_reversed` with `reversed_amount_minor` set.
3. **Given** a refund causes the wallet balance to go negative (because vendor already withdrew), **When** processed, **Then** the debit entry is still recorded (negative balance is permitted) and an `audit_logs` row is appended flagging the negative balance for admin review.
4. **Given** a refund event arrives twice (queue retry), **When** processed, **Then** at most one reversal entry exists per `(commission_id, refund_id)` combination.

---

### User Story 6 — Admin Manages Commission Rates with Most-Specific Match (Priority: P2)

An admin opens the Filament `CommissionRules` resource and creates a new commission rate, e.g., `(Inflatables × rental, 1500 bps)`. The rule takes effect for all subsequent `PaymentCaptured` events. Existing commissions are not retroactively recalculated (snapshot pattern).

**Why this priority**: Configurable rates per (category, type) are required for the locked decision in `docs/specs/02_Tech_Decisions.md`. Without this UI, the platform team cannot adjust commission rates without DB access.

**Independent Test**: Open Filament `CommissionRules`, create a new row `(category=Cakes, product_type=sale, commission_bps=2000)`, then trigger a `PaymentCaptured` for a Cakes/sale item → verify the new rate was applied.

**Acceptance Scenarios**:

1. **Given** an admin opens Filament `CommissionRules`, **When** they create a row with `(category_id=null, product_type=rental, commission_bps=1000)`, **Then** the rate is saved and active immediately for new PaymentCaptured events.
2. **Given** two overlapping rates `(Inflatables × rental, 1500 bps)` and `(NULL × rental, 1000 bps)`, **When** a Inflatables/rental payment is captured, **Then** the more-specific rate (1500 bps) is applied.
3. **Given** an admin without `settlement.manage_commission_rates` permission, **When** they try to access the resource, **Then** Filament denies access.
4. **Given** an admin edits a rate's `commission_bps`, **When** they save, **Then** the change is audit-logged but past `commissions` rows retain their snapshotted rate (no recalculation).

---

### Edge Cases

- **Vendor with no wallet when payment captured** → Wallet is lazy-created with currency from the booking's `total_currency`.
- **No `commission_rates` row matches any fallback level** → Use platform default 0 bps, log a warning to `audit_logs` with severity `warn`, vendor receives full amount.
- **Vendor requests withdrawal exceeding balance** → 422 `insufficient_balance`.
- **Vendor has a pending withdrawal already** → 422 `existing_pending_withdrawal` (single-pending rule).
- **Withdrawal amount below platform minimum** → 422 `below_minimum_amount` (default 100 EGP, configurable via `app_settings`).
- **Admin approves but bank transfer fails externally** → Admin can mark `rejected` after the fact via a separate "Reverse Approval" admin action; otherwise the withdrawal stays `paid` and admin handles externally.
- **Refund happens after vendor withdrew (negative balance)** → Negative balance is allowed; audit log flags it; vendor cannot request further withdrawals while negative.
- **Multiple captured payments for the same `booking_item` (shouldn't happen but defensive)** → `commissions.booking_item_id` UNIQUE constraint enforces single commission row.
- **Currency mismatch between booking and existing wallet** → Lazy-create a separate wallet for the new currency (multi-currency supported at the schema level even though Phase 4.2 ships EGP-only).
- **Vendor account suspended after wallet has balance** → Withdrawal endpoint returns 403; balance frozen until reinstated.

---

## Requirements *(mandatory)*

### Functional Requirements

#### Wallet & Ledger
- **FR-SET-001**: System MUST maintain at most one wallet per `(owner_type, owner_id, currency)` (UNIQUE).
- **FR-SET-002**: `wallet_ledger` MUST be append-only — no `UPDATE`, no `DELETE`, no `softDeletes`. Schema enforces this via the absence of `updated_at` and `deleted_at` columns.
- **FR-SET-003**: Wallet balance MUST equal the sum of its ledger entries at all times (invariant testable via Pest).
- **FR-SET-004**: Vendor wallets MUST be lazy-created on first credit if not yet existing.

#### Commission Calculation
- **FR-SET-005**: On every `PaymentCaptured` domain event, the system MUST compute commission for each `booking_item` belonging to the captured payment, asynchronously via a queued listener that fires after `DB::afterCommit()`.
- **FR-SET-006**: Commission rate resolution MUST follow the most-specific match order: (1) `(category_id × product_type)`, (2) `(category_id × NULL)`, (3) `(NULL × product_type)`, (4) `(NULL × NULL)`. The first match wins.
- **FR-SET-007**: If no rate matches, the system MUST default to `commission_bps=0`, log a warning to `audit_logs`, and credit the vendor the full amount.
- **FR-SET-008**: System MUST snapshot the resolved `commission_bps` and `commission_minor` onto the `commissions` row at calculation time. Subsequent changes to `commission_rates` MUST NOT retroactively change existing `commissions`.
- **FR-SET-009**: System MUST enforce one `commissions` row per `booking_item_id` (UNIQUE).
- **FR-SET-010**: Commission calculation MUST credit the vendor's net share (`total_minor − commission_minor`) to their wallet via a `wallet_ledger` entry of type `commission_credit`.

#### Refund Reversal
- **FR-SET-011**: On every `RefundCompleted` domain event, the system MUST locate the originating `commissions` row, mark it `status=reversed` (full) or `status=partially_reversed` (partial), and append a compensating `wallet_ledger` entry of type `refund_debit` with the proportional reversal amount.
- **FR-SET-012**: Reversal MUST be idempotent on `(commission_id, refund_id)` — repeated event delivery MUST NOT double-reverse.
- **FR-SET-013**: Negative wallet balance MUST be permitted (refund-after-withdrawal scenario) but MUST be flagged in `audit_logs` for admin review and MUST block further withdrawal requests until balance ≥ 0.

#### Withdrawal Flow
- **FR-SET-014**: Vendors MUST be able to view their wallet balance and ledger via the vendor API.
- **FR-SET-015**: Vendors MUST be able to request a withdrawal via `POST /api/v1/vendor/withdrawals` with an amount and bank-account snapshot.
- **FR-SET-016**: System MUST reject withdrawal requests below the configured minimum amount (default 100 EGP, configurable via `app_settings`).
- **FR-SET-017**: System MUST reject withdrawal requests exceeding the vendor's current available balance (balance minus pending withdrawals).
- **FR-SET-018**: System MUST allow at most one `pending` withdrawal per vendor (single-pending rule); concurrent attempts return 422.
- **FR-SET-019**: `POST /api/v1/vendor/withdrawals` MUST support `Idempotency-Key` header per the Idempotency middleware (24h TTL).

#### Admin Approval & Bank Proof
- **FR-SET-020**: Admin MUST be able to view all `pending` withdrawals in a Filament `WithdrawalsQueue` resource.
- **FR-SET-021**: Admin MUST be able to approve a withdrawal by uploading a bank-transfer proof file (PDF/PNG/JPG, max 10 MB) via Spatie Media Library to a private bucket, and the action MUST mark the withdrawal `paid`.
- **FR-SET-022**: Approving a withdrawal MUST create a `wallet_ledger` entry of type `withdrawal_debit` for the requested amount.
- **FR-SET-023**: Admin MUST be able to reject a withdrawal with a translatable rejection reason (EN+AR JSON).
- **FR-SET-024**: Every withdrawal status transition (`pending` → `approved` → `paid`, or any → `rejected`) MUST append an `audit_logs` row with actor, action, old/new status, timestamp.

#### Read Models & API
- **FR-SET-025**: `GET /api/v1/vendor/wallet` MUST return current balance, total credits, total debits, and pending withdrawal amount in the request locale.
- **FR-SET-026**: `GET /api/v1/vendor/wallet/ledger` MUST return paginated ledger entries (default 25/page, max 100/page) newest first, with `entry_type`, `amount_minor`, `currency`, `description` (locale-resolved), and `related_entity_type` + `related_entity_id`.
- **FR-SET-027**: `GET /api/v1/vendor/withdrawals` MUST list the vendor's own withdrawals across all statuses, paginated.
- **FR-SET-028**: `GET /api/v1/vendor/withdrawals/{public_id}` MUST return a single withdrawal's details including current status, requested amount, paid amount, paid date, and rejection reason if applicable.

#### Locale & Audit
- **FR-SET-029**: All vendor-facing responses MUST resolve translatable fields (`description`, `rejected_reason`) at the API Resource layer based on `Accept-Language`.
- **FR-SET-030**: All admin-facing Filament views MUST display monetary values via `->money('EGP', divideBy: 100)` and display `product_type` (where shown) via the standard per-type colored badge.

### Key Entities

- **Wallet**: Per `(owner_type, owner_id, currency)`. UNIQUE. Soft-balance derived from `wallet_ledger` (or cached on the row, refreshed post-commit).
- **WalletLedgerEntry**: Append-only. Types: `commission_credit`, `refund_debit`, `withdrawal_debit`, `manual_adjustment` (admin-only). Each entry references the originating entity polymorphically (`related_entity_type` + `related_entity_id`).
- **Commission**: One per `booking_item_id` (UNIQUE). Snapshots `commission_bps`, `commission_minor`, `vendor_share_minor`. Status: `calculated` | `partially_reversed` | `reversed`. Append-only except for `status` field.
- **CommissionRate**: Configurable rate per `(category_id NULLABLE, product_type NULLABLE)`. Most-specific match wins. Editable via Filament; edits affect future commissions only (not historical).
- **Withdrawal**: Per request. Status state machine: `pending` → `approved` → `paid` | `rejected` (rejected can be entered from `pending` or `approved`). Carries bank account snapshot, requested amount, paid amount, rejection reason, bank proof media reference, audit timestamps.
- **SettlementRun**: Table created with minimal columns; **no jobs in Phase 4.2** (cut-list defers auto-reconciliation).

---

## Constitution Check

*GATE: All 16 rules verified against the spec before plan generation.*

| Rule | Status | Notes |
|---|---|---|
| Modular monolith — `app/Modules/Settlement/` | ✅ PASS | New module; full layer layout (Domain/Application/Infrastructure/Http/Filament/Routes/Database/Providers) |
| Thin controllers (3-line action body max) | ✅ PASS | `WithdrawalController`, `WalletController` delegate to Settlement Actions |
| `match($enum)` not if/elseif on type strings | ✅ PASS | `LedgerEntryType` enum branched via `match()` where needed |
| No cross-module Eloquent Model imports | ✅ PASS | Booking item access via `EloquentSettlementBookingReader` contract; Payments access via `EloquentSettlementPaymentReader` contract |
| Money as integer minor units | ✅ PASS | All amount columns BIGINT `_minor` + CHAR(3) `_currency`; `MoneyCast` returns `Brick\Money\Money` |
| ULID `public_id` on top-level entities | ✅ PASS | `wallets`, `withdrawals`, `commissions`, `commission_rates`, `settlement_runs` all have `public_id` CHAR(26) UNIQUE |
| Translatable JSON columns where user-facing text exists | ✅ PASS | `withdrawals.rejected_reason` is JSON via `spatie/laravel-translatable` |
| `wallet_ledger` append-only (no `updated_at`, no `deleted_at`) | ✅ PASS | Migration uses `timestamp('created_at')->useCurrent()` only |
| `commissions` append-only except `status` | ✅ PASS | §15 carve-out for status-only updates; no `updated_at` on the table |
| Soft-deletes only on whitelisted tables | ✅ PASS | None of the 6 Settlement tables have `softDeletes()` |
| Domain events fire after `DB::afterCommit()` | ✅ PASS | `CommissionCalculated`, `WalletCredited`, `WithdrawalRequested`, `WithdrawalApproved`, `WithdrawalPaid`, `WithdrawalRejected`, `RefundReversed` all dispatched via `DB::afterCommit()` |
| `Idempotency-Key` middleware on payment-mutating endpoints | ✅ PASS | `POST /api/v1/vendor/withdrawals` is wrapped by Idempotency middleware |
| `ApiResponse` envelope on every API response | ✅ PASS | All vendor endpoints |
| Locale conversion at API Resource layer | ✅ PASS | `WithdrawalResource::toArray()` resolves `rejected_reason` from JSON via `Accept-Language` |
| Spatie Media Library for bank-proof upload (private bucket) | ✅ PASS | `bank_proof` collection, disk = `s3_private` (or `minio_private` in dev) |
| `filament-shield` permissions per resource | ✅ PASS | `settlement.view_wallet`, `settlement.request_withdrawal`, `settlement.approve_withdrawal`, `settlement.reject_withdrawal`, `settlement.manage_commission_rates`; `php artisan shield:generate --all` after each new resource |

**GATE RESULT: ALL PASS — proceed to plan generation.**

---

## API Endpoints Exposed

| Method | Path | Auth | Roles | Idempotency-Key | Purpose |
|---|---|---|---|---|---|
| `GET`  | `/api/v1/vendor/wallet` | sanctum | `vendor` (own only) | — | Wallet balance + summary |
| `GET`  | `/api/v1/vendor/wallet/ledger` | sanctum | `vendor` (own only) | — | Paginated ledger entries |
| `GET`  | `/api/v1/vendor/withdrawals` | sanctum | `vendor` (own only) | — | List withdrawal history |
| `POST` | `/api/v1/vendor/withdrawals` | sanctum | `vendor` | required | Request withdrawal |
| `GET`  | `/api/v1/vendor/withdrawals/{public_id}` | sanctum | `vendor` (own only) | — | View single withdrawal |

**No customer-facing API in Phase 4.2.**
**Admin operations live in Filament resources, not API:** `WithdrawalsQueue`, `WalletLedgerViewer`, `CommissionRules`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Commission is calculated and the vendor wallet is credited within 5 seconds of a payment being captured (queued listener latency).
- **SC-002**: All 4 commission rate fallback levels can be tested independently and yield the most-specific match in every case (covered by Pest).
- **SC-003**: Wallet balance equals the sum of `wallet_ledger` entries at all times — invariant verified by Pest with property-style tests.
- **SC-004**: A vendor can complete a withdrawal request in under 1 minute from opening their wallet view (3 form fields max: amount, bank account snapshot, optional notes).
- **SC-005**: An admin can approve a withdrawal in under 2 minutes including bank-proof file upload (upload + click "Approve & Mark Paid").
- **SC-006**: A refund reduces the vendor's wallet balance and reverses the commission within 5 seconds of `RefundCompleted` firing.
- **SC-007**: 100% of withdrawal status transitions are captured in `audit_logs` with actor and old/new state.
- **SC-008**: Pest test suite covers commission flows for all 3 product types (rental, sale, digital) with all 4 rate fallback levels.

---

## Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §Phase 4.2)

- **Defer `settlement_runs` auto-reconciliation** — manual reconciliation is OK for soft launch. The table is created in Phase 4.2 (so the schema is stable) but no scheduled job populates it.
- **Defer auto-approval rules** — admin approves every withdrawal manually in Phase 4.2; auto-approval rules (e.g., "auto-approve under 500 EGP for trusted vendors") deferred to Phase 1.5.

---

## Exit Criteria (from `docs/specs/09_Phasing_Plan.md` §Phase 4.2)

- [ ] Commission per `(category × type)` working with all 4 fallback levels
- [ ] Wallet credit on payment, debit on refund
- [ ] Admin approves withdrawal with proof
- [ ] Pest covers all 3 product types' commission flows

---

## Assumptions

- **Single currency (EGP) for Phase 4.2.** Multi-currency wallets are supported at the schema level (UNIQUE on `(owner_type, owner_id, currency)`) but no UI for currency switching ships in 4.2.
- **Bank transfer is manual/external.** No automatic Paymob payout API integration in Phase 4.2 — admin performs the transfer in their banking portal and uploads proof.
- **`settlement_runs` table is created but unused.** Auto-reconciliation is Phase 1.5 work.
- **Auto-approval rules are deferred.** Every withdrawal goes through admin manually in Phase 4.2.
- **Wallet balance is cached on the `wallets` row** as `balance_minor` for read performance, refreshed after each ledger insert via `DB::afterCommit()`. Pest invariant tests verify cache equals sum of ledger.
- **Commission rates in basis points (bps).** `commission_bps INT NOT NULL` on both `commissions` and `commission_rates` (e.g., 1500 = 15.00%).
- **Minimum withdrawal amount = 100 EGP** by default. Configurable via `app_settings` (Phase 6.2 surfaces this in the admin UI; in 4.2 it's a seeded value).
- **Single pending withdrawal per vendor** — no queueing of withdrawal requests. Vendor must wait for current pending to be resolved.
- **Negative wallet balance allowed** in the refund-after-withdrawal scenario; flagged in audit log; blocks further withdrawals until balance ≥ 0.
- **Bank proof is stored on a private S3/MinIO bucket** (`s3_private` disk in prod, `minio_private` in dev). URLs are signed with 1-hour TTL.
- **Refund proportionality** — partial refunds reverse a proportional share of commission and wallet credit (e.g., 50% refund → 50% reversal of both).
- **Listener idempotency** is enforced via UNIQUE constraints (`commissions.booking_item_id` UNIQUE; `wallet_ledger.related_entity_type + related_entity_id + entry_type` UNIQUE for refund reversal lookups). Queue retries cannot create duplicate financial entries.
- **Commission rate edits do not retroactively recalculate** existing `commissions` rows (snapshot pattern).
- **Vendor authentication** uses Sanctum (per existing Identity module setup). No new auth scheme.
- **The `RefundCompleted` and `PaymentCaptured` events already exist** in the Payments module (shipped in Phase 4.0/4.1). This phase only adds Settlement listeners that subscribe to them.
- **`booking_items.commission_bps`** column is already populated at booking confirmation time (per `docs/specs/03_Three_Product_Types.md` §Commission rates). Settlement reads this snapshot rather than re-resolving the rate at payment capture.
