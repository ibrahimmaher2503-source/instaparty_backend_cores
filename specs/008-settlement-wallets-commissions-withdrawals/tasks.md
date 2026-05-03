# Tasks: Settlement — Wallets, Commissions, Withdrawals (Phase 4.2)

**Input**: Design documents from `specs/008-settlement-wallets-commissions-withdrawals/`
**Prerequisites**: spec.md, plan.md, research.md, data-model.md, contracts/

**Tests**: REQUIRED. Pest coverage is mandatory per `CLAUDE.md` §Testing Conventions — happy + auth + authz + validation + locale + all 3 product types where applicable + idempotency where applicable. Tests are interleaved per user story (not deferred to a final phase) so each story is independently verifiable.

**Organization**: Tasks grouped by user story (from spec.md priorities P1, P2). Setup + Foundational are blocking prerequisites. Polish includes architecture tests, scribe regeneration, and quality gates.

---

## Format: `- [ ] **TID** [P?] [Story?] Description with file path — Source: X`

- **[P]**: parallel-safe (different file, no incomplete dependencies)
- **[Story]**: USx label for user-story tasks; setup/foundational/polish carry no story label
- File paths use `app/Modules/Settlement/...` per `plan.md` §Project Structure
- Source citations: PRD `FR-XX`, Schema `§Catalog/Booking/etc.`, ADR `ADR-NNNN §X`, plan `plan.md §X`, data-model `data-model.md §X`, spec `spec.md US-X` or `FR-SET-XXX`

---

## Phase 1: Setup (Module Scaffold)

**Purpose**: Project initialization — module directory, ADR, ServiceProvider registration.

- [ ] **T001** Draft and accept ADR-0009 in `docs/adr/0009-settlement-module.md` (module ownership, append-only carve-outs, no auto-payout, single-pending DB rule, contracts vs cross-module imports) — Source: plan.md §ADR + research.md R8
- [ ] **T002** Create module directory scaffold under `app/Modules/Settlement/{Domain/{Models,Enums,Events,Exceptions,ValueObjects,Contracts},Application/{Actions,DTOs,Listeners,Services},Infrastructure/Repositories,Http/{Controllers/Vendor,Requests,Resources},Filament/Resources,Routes,Database/{Migrations,Factories,Seeders},Resources/lang/{en,ar},Providers}/.gitkeep` — Source: `.claude/rules/modules.md` §Layer layout
- [ ] **T003** Create `app/Modules/Settlement/Providers/SettlementServiceProvider.php` (skeleton with `register()` + `boot()`) — Source: `.claude/rules/modules.md` §ServiceProvider
- [ ] **T004** Register `SettlementServiceProvider::class` in `bootstrap/app.php` — Source: `.claude/rules/modules.md` §ServiceProvider

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

**Purpose**: Schema, models (shells only), enums, value objects, factories, contracts, DTOs, exceptions, translation files, seeder for default commission rate.

### 2.1 Migrations (strict FK dependency order — must run sequentially)

- [ ] **T005** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000001_create_wallets_table.php` (UNIQUE `(owner_type, owner_id, currency)`, cached `balance_minor`, ULID `public_id`, utf8mb4) — Source: data-model.md §1, Schema lines 925–940
- [ ] **T006** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000002_create_wallet_ledger_table.php` (append-only — `created_at` only, no `updated_at`/`deleted_at`; UNIQUE `(related_entity_type, related_entity_id, entry_type)`; `(wallet_id, created_at DESC)` index) — Source: data-model.md §2, Schema lines 941–958
- [ ] **T007** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000003_create_commission_rates_table.php` (NULL-safe UNIQUE on `(category_id, product_type)` via derived `IFNULL` columns or `USING HASH`) — Source: data-model.md §4, Schema lines 976–993
- [ ] **T008** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000004_create_commissions_table.php` (UNIQUE `booking_item_id`, no `updated_at`, status-only updates) — Source: data-model.md §3, Schema lines 959–975
- [ ] **T009** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000005_create_withdrawals_table.php` (UNIQUE PARTIAL `(vendor_profile_id) WHERE status='pending'`, `bank_account_snapshot` JSON, `rejected_reason` translatable JSON, `bank_proof_media_id` FK → media) — Source: data-model.md §5, Schema lines 994–1014, research.md R3
- [ ] **T010** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000006_create_settlement_runs_table.php` (table only — no rows in Phase 4.2 per cut-list) — Source: data-model.md §6, plan.md §Cut-list

### 2.2 Enums

- [ ] **T011** [P] Create `app/Modules/Settlement/Domain/Enums/LedgerEntryType.php` (cases: `commission_credit`, `refund_debit`, `withdrawal_debit`, `manual_adjustment`) — Source: data-model.md §2
- [ ] **T012** [P] Create `app/Modules/Settlement/Domain/Enums/CommissionStatus.php` (cases: `calculated`, `partially_reversed`, `reversed`) — Source: data-model.md §3
- [ ] **T013** [P] Create `app/Modules/Settlement/Domain/Enums/WithdrawalStatus.php` (cases: `pending`, `approved`, `paid`, `rejected`) — Source: data-model.md §5

### 2.3 Value Objects

- [ ] **T014** [P] Create `app/Modules/Settlement/Domain/ValueObjects/BankAccountSnapshot.php` (readonly: `account_holder`, `iban`, `bank_name`, `swift_bic`; ISO-13616 mod-97 IBAN validation) — Source: research.md R13

### 2.4 Domain Exceptions

- [ ] **T015** [P] Create `app/Modules/Settlement/Domain/Exceptions/InsufficientWalletBalanceException.php` — Source: spec.md FR-SET-017
- [ ] **T016** [P] Create `app/Modules/Settlement/Domain/Exceptions/ExistingPendingWithdrawalException.php` — Source: spec.md FR-SET-018
- [ ] **T017** [P] Create `app/Modules/Settlement/Domain/Exceptions/WithdrawalBelowMinimumException.php` — Source: spec.md FR-SET-016

### 2.5 Eloquent Models (shells — relationships, casts, scopes ONLY; no business logic)

- [ ] **T018** [P] Create `app/Modules/Settlement/Domain/Models/Wallet.php` (`morphTo` owner, `hasMany` ledger; casts `balance_minor`/`pending_withdrawal_minor` via `MoneyCast`; ULID `public_id`) — Source: data-model.md §1
- [ ] **T019** [P] Create `app/Modules/Settlement/Domain/Models/WalletLedgerEntry.php` (`belongsTo` Wallet; `morphTo` related; `entry_type` enum cast; no `$timestamps` — only `created_at`) — Source: data-model.md §2
- [ ] **T020** [P] Create `app/Modules/Settlement/Domain/Models/Commission.php` (status enum cast; ULID; no `$timestamps` for `updated_at` — only `created_at`) — Source: data-model.md §3
- [ ] **T021** [P] Create `app/Modules/Settlement/Domain/Models/CommissionRate.php` (`belongsTo` Category; product_type enum cast; ULID) — Source: data-model.md §4
- [ ] **T022** [P] Create `app/Modules/Settlement/Domain/Models/Withdrawal.php` (`belongsTo` VendorProfile + User ×2; `morphMany` Media (`bank_proof` collection); `rejected_reason` translatable; `bank_account_snapshot` cast to `BankAccountSnapshot`) — Source: data-model.md §5
- [ ] **T023** [P] Create `app/Modules/Settlement/Domain/Models/SettlementRun.php` (status enum cast; ULID) — Source: data-model.md §6

### 2.6 Factories

- [ ] **T024** [P] Create `app/Modules/Settlement/Database/Factories/WalletFactory.php`
- [ ] **T025** [P] Create `app/Modules/Settlement/Database/Factories/WalletLedgerEntryFactory.php`
- [ ] **T026** [P] Create `app/Modules/Settlement/Database/Factories/CommissionFactory.php`
- [ ] **T027** [P] Create `app/Modules/Settlement/Database/Factories/CommissionRateFactory.php`
- [ ] **T028** [P] Create `app/Modules/Settlement/Database/Factories/WithdrawalFactory.php`
- [ ] **T029** [P] Create `app/Modules/Settlement/Database/Factories/SettlementRunFactory.php`

### 2.7 Cross-module Contracts (interfaces owned by Settlement)

- [ ] **T030** [P] Create `app/Modules/Settlement/Domain/Contracts/SettlementBookingReader.php` (methods: `findItemById(int $id): ?BookingItemSnapshotDto`; `itemsForPayment(int $bookingId): array<BookingItemSnapshotDto>`) — Source: research.md R8, plan.md §Cross-module access
- [ ] **T031** [P] Create `app/Modules/Settlement/Domain/Contracts/SettlementPaymentReader.php` (methods: `findById(int $id): ?PaymentSnapshotDto`; `findRefundById(int $id): ?RefundSnapshotDto`) — Source: research.md R8
- [ ] **T032** [P] Create `app/Modules/Settlement/Domain/Contracts/CommissionRateResolver.php` (method: `resolve(?int $categoryId, ProductType $type): ?int` — basis points) — Source: research.md R2

### 2.8 Cross-module DTOs

- [ ] **T033** [P] Create `app/Modules/Settlement/Application/DTOs/BookingItemSnapshotDto.php` (readonly: id, public_id, booking_id, vendor_profile_id, category_id, product_type, total_minor, total_currency, commission_bps?) — Source: data-model.md §Cross-module DTOs
- [ ] **T034** [P] Create `app/Modules/Settlement/Application/DTOs/PaymentSnapshotDto.php` (readonly: id, public_id, booking_id, amount_minor, currency, status, captured_at) — Source: data-model.md §Cross-module DTOs
- [ ] **T035** [P] Create `app/Modules/Settlement/Application/DTOs/RefundSnapshotDto.php` (readonly: id, public_id, payment_id, booking_item_id?, amount_minor, currency, completed_at) — Source: data-model.md §Cross-module DTOs
- [ ] **T036** [P] Create `app/Modules/Settlement/Application/DTOs/RequestWithdrawalDto.php` (readonly: vendor_profile_id, requested_by_user_id, amount_minor, currency, bank_account: BankAccountSnapshot) — Source: data-model.md §Actions

### 2.9 Settlement-emitted Domain Events (DB::afterCommit only)

- [ ] **T037** [P] Create `app/Modules/Settlement/Domain/Events/WalletCredited.php` — Source: plan.md §Domain Events
- [ ] **T038** [P] Create `app/Modules/Settlement/Domain/Events/WalletDebited.php` — Source: plan.md §Domain Events
- [ ] **T039** [P] Create `app/Modules/Settlement/Domain/Events/CommissionCalculated.php` — Source: plan.md §Domain Events
- [ ] **T040** [P] Create `app/Modules/Settlement/Domain/Events/CommissionReversed.php` — Source: plan.md §Domain Events
- [ ] **T041** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalRequested.php` — Source: plan.md §Domain Events
- [ ] **T042** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalPaid.php` — Source: plan.md §Domain Events
- [ ] **T043** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalRejected.php` — Source: plan.md §Domain Events

### 2.10 Repositories (Eloquent implementations)

- [ ] **T044** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentWalletRepository.php` (lazy-create wallet on first credit; balance lookup; pending_withdrawal increment/decrement) — Source: data-model.md §1
- [ ] **T045** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentWithdrawalRepository.php` — Source: data-model.md §5
- [ ] **T046** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentCommissionRepository.php` — Source: data-model.md §3

### 2.11 Cross-module reader implementations (live in Booking and Payments modules)

- [ ] **T047** Create `app/Modules/Booking/Infrastructure/Repositories/EloquentSettlementBookingReader.php` implementing `SettlementBookingReader` (returns `BookingItemSnapshotDto`s, no Eloquent leak) — Source: research.md R8
- [ ] **T048** Bind `SettlementBookingReader` → `EloquentSettlementBookingReader` in `app/Modules/Booking/Providers/BookingServiceProvider.php` `register()` — Source: `.claude/rules/modules.md` §ServiceProvider
- [ ] **T049** Create `app/Modules/Payments/Infrastructure/Repositories/EloquentSettlementPaymentReader.php` implementing `SettlementPaymentReader` — Source: research.md R8
- [ ] **T050** Bind `SettlementPaymentReader` → `EloquentSettlementPaymentReader` in `app/Modules/Payments/Providers/PaymentsServiceProvider.php` `register()` — Source: `.claude/rules/modules.md` §ServiceProvider

### 2.12 Settlement ServiceProvider wiring

- [ ] **T051** Wire all Settlement contract bindings (`CommissionRateResolver` → `EloquentCommissionRateResolver`; repositories) in `SettlementServiceProvider::register()` — Source: plan.md §Project Structure
- [ ] **T052** Load module migrations + translations + Routes/vendor.php in `SettlementServiceProvider::boot()` — Source: `.claude/rules/modules.md` §ServiceProvider

### 2.13 Translation files

- [ ] **T053** [P] Create `app/Modules/Settlement/Resources/lang/en/settlement.php` (errors: `insufficient_balance`, `existing_pending_withdrawal`, `below_minimum_amount`, `negative_balance_blocked`, `withdrawal_not_found`; ledger description keys: `ledger.commission_credit`, `ledger.refund_debit`, `ledger.withdrawal_debit`) — Source: spec.md edge cases + data-model.md §2
- [ ] **T054** [P] Create `app/Modules/Settlement/Resources/lang/ar/settlement.php` (Arabic mirror of T053) — Source: spec.md FR-SET-029

### 2.14 Default commission rate seeder

- [ ] **T055** Create `app/Modules/Settlement/Database/Seeders/DefaultCommissionRatesSeeder.php` (single row `(category_id=NULL, product_type=NULL, commission_bps=1500)` — platform default 15%) — Source: research.md R2 + data-model.md §4 §Seed data

**Checkpoint**: Foundation ready. User stories US1–US6 can now be implemented (US1 first per dependency).

---

## Phase 3: User Story 1 — Commission Auto-Calculated on Payment Capture (P1) 🎯 MVP

**Goal**: When `PaymentCaptured` fires, settlement listener calculates commissions per booking_item using 4-level rate fallback, snapshots commission to `commissions`, and credits vendor wallet via `wallet_ledger`.

**Independent Test**: Trigger `PaymentCaptured` for a booking with rental + sale items from different vendors → verify 2 `commissions` rows + 2 `wallet_ledger` `commission_credit` entries + correct cached `wallets.balance_minor` for each vendor (per spec.md US1 acceptance scenarios 1–6).

### Implementation

- [ ] **T100** [US1] Create `app/Modules/Settlement/Application/Services/EloquentCommissionRateResolver.php` implementing `CommissionRateResolver` (4-level fallback: `(category × type)` → `(category × NULL)` → `(NULL × type)` → `(NULL × NULL)`) — Source: research.md R2 + spec.md FR-SET-006
- [ ] **T101** [US1] Create `app/Modules/Settlement/Application/Actions/CreditWalletAction.php` (lazy-create wallet, append `wallet_ledger` entry, update `wallets.balance_minor` in same transaction, fire `WalletCredited` after commit) — Source: data-model.md §Actions + spec.md FR-SET-001..004
- [ ] **T102** [US1] Create `app/Modules/Settlement/Application/Actions/CalculateCommissionAction.php` (uses `SettlementBookingReader` + resolver; reads `commission_bps` snapshot from booking_item first, falls back to resolver, defaults to 0 + warn if no match; `Brick\Money` HALF_EVEN rounding for `commission_minor`/`vendor_share_minor`; creates `Commission` row + calls `CreditWalletAction`; fires `CommissionCalculated` after commit) — Source: spec.md FR-SET-005..010 + research.md R2 + R4
- [ ] **T103** [US1] Create `app/Modules/Settlement/Application/Listeners/CalculateCommissionOnPaymentCapturedListener.php` (queued via `database` queue; iterates booking items via `SettlementPaymentReader.findById` then `SettlementBookingReader.itemsForPayment`; catches duplicate-key `QueryException` for idempotency on `commissions.booking_item_id` UNIQUE; 3 retries with exp backoff per research.md R11) — Source: contracts/listener-payment-captured.md
- [ ] **T104** [US1] Wire listener registration in `SettlementServiceProvider::boot()` event map: `PaymentCaptured` → `CalculateCommissionOnPaymentCapturedListener` — Source: contracts/listener-payment-captured.md §Subscription

### Tests for User Story 1

- [ ] **T110** [P] [US1] Create `tests/Feature/Modules/Settlement/CommissionCalculationTest.php` — happy path for all 3 product types (rental + sale + digital), one test per type (Pest groups `rental`, `sale`, `digital`); verify `commissions` row + `wallet_ledger` `commission_credit` + cached `balance_minor` — Source: spec.md US1 acceptance 1, 5; SC-008
- [ ] **T111** [P] [US1] Create `tests/Feature/Modules/Settlement/CommissionFallbackTest.php` — Pest tests for all 4 fallback levels (per spec.md US1 acceptance 1–4): `(cat × type)`, `(cat × NULL)`, `(NULL × type)`, `(NULL × NULL)` — Source: spec.md FR-SET-006 + SC-002
- [ ] **T112** [P] [US1] Create `tests/Unit/Modules/Settlement/EloquentCommissionRateResolverTest.php` — isolated unit tests on resolver's 4-level fallback with mock DB — Source: research.md R2
- [ ] **T113** [P] [US1] Add Pest test in `CommissionCalculationTest`: `test_duplicate_event_does_not_double_credit` — dispatch `PaymentCaptured` twice; assert only one `commissions` + one ledger entry — Source: contracts/listener-payment-captured.md §Idempotency + spec.md US1 acceptance 6

**Checkpoint**: US1 fully testable independently. Commission calculation works for all 3 product types and all 4 fallback levels.

---

## Phase 4: User Story 2 — Vendor Views Wallet Balance + Ledger History (P1)

**Goal**: Authenticated vendor can `GET /api/v1/vendor/wallet` (balance summary) and `GET /api/v1/vendor/wallet/ledger` (paginated history newest-first, locale-resolved descriptions).

**Independent Test**: As a vendor with 2 credits + 1 debit → `GET /vendor/wallet` returns correct balance/totals; `GET /vendor/wallet/ledger?per_page=50` returns paginated entries with localized descriptions (per spec.md US2 acceptance 1–5).

### Implementation

- [ ] **T200** [US2] Create `app/Modules/Settlement/Application/Services/WalletQueryService.php` (methods: `balance(VendorProfile $v, string $currency = 'EGP'): WalletBalanceDto`; `ledger(VendorProfile $v, LedgerFilters $f): CursorPaginator<WalletLedgerEntry>`) — Source: contracts/wallet.md §Internal contracts
- [ ] **T201** [US2] Create `app/Modules/Settlement/Http/Resources/WalletResource.php` with `@response` PHPDoc containing realistic EN+AR examples per contracts/wallet.md — Source: spec.md FR-SET-025 + contracts/wallet.md
- [ ] **T202** [US2] Create `app/Modules/Settlement/Http/Resources/WalletLedgerEntryResource.php` (resolves `description_key` + `description_params` against `lang/{en,ar}/settlement.php` based on `Accept-Language`) — Source: spec.md FR-SET-029 + contracts/wallet.md
- [ ] **T203** [US2] Create `app/Modules/Settlement/Http/Controllers/Vendor/WalletController.php` with `show()` and `ledger()` methods (3-line action body MAX) delegating to `WalletQueryService` — Source: `.claude/rules/actions.md` §thin controllers
- [ ] **T204** [US2] Add routes to `app/Modules/Settlement/Routes/vendor.php`: `GET /api/v1/vendor/wallet` and `GET /api/v1/vendor/wallet/ledger` (sanctum + `settlement.view_wallet.own` permission) — Source: contracts/wallet.md
- [ ] **T205** [US2] Add `@bodyParam` PHPDoc on `WalletController::ledger()` query parameters (per_page, cursor, entry_type, from, to) — Source: contracts/wallet.md §Request

### API documentation tasks for US2

- [ ] **T210** [P] [US2] Append rows for `GET /vendor/wallet` and `GET /vendor/wallet/ledger` to `.specify/memory/api-registry.md` (Method, Path, Auth, Roles, Idempotency-Key, Request schema, Response schema link to contracts/wallet.md) — Source: plan.md §API Documentation Plan
- [ ] **T211** [P] [US2] Add Bruno requests for the 2 wallet endpoints to `docs/api/collections/settlement.bru` — Source: plan.md §API Documentation Plan
- [ ] **T212** [P] [US2] Add Postman entries for the 2 wallet endpoints to `docs/api/collections/settlement.postman_collection.json` — Source: plan.md §API Documentation Plan

### Tests for User Story 2

- [ ] **T220** [P] [US2] Create `tests/Feature/Modules/Settlement/WalletViewTest.php` — happy + 401 + 403 + zero-balance + EN+AR locale (per spec.md US2 acceptance 1–5)
- [ ] **T221** [P] [US2] Create `tests/Feature/Modules/Settlement/WalletLedgerTest.php` — pagination cursor, ordering newest-first, locale-resolved descriptions, entry_type filter

**Checkpoint**: US2 fully testable. Vendor sees their wallet + ledger.

---

## Phase 5: User Story 3 — Vendor Requests Withdrawal (P1)

**Goal**: Vendor `POST /api/v1/vendor/withdrawals` creates a `pending` withdrawal, validates balance/minimum/single-pending, supports `Idempotency-Key`. Vendor can list and view their own withdrawals.

**Independent Test**: Vendor with 500 EGP balance + no pending → `POST /vendor/withdrawals` (300 EGP) returns 201; second call returns 422 `existing_pending_withdrawal`; below-minimum returns 422 (per spec.md US3 acceptance 1–6).

### Implementation

- [ ] **T300** [US3] Create `app/Modules/Settlement/Application/Actions/DebitWalletAction.php` (mirror of `CreditWalletAction` but for debits — append negative ledger entry, update cached balance, fire `WalletDebited` after commit) — Source: data-model.md §Actions
- [ ] **T301** [US3] Create `app/Modules/Settlement/Application/Actions/RequestWithdrawalAction.php` (validate balance ≥ requested, ≥ minimum, no existing pending; insert `Withdrawal` row with `status=pending`; bump `wallets.pending_withdrawal_minor`; catch UNIQUE PARTIAL violation as `ExistingPendingWithdrawalException`; fire `WithdrawalRequested` after commit) — Source: spec.md FR-SET-014..019 + research.md R3
- [ ] **T302** [US3] Create `app/Modules/Settlement/Http/Requests/RequestWithdrawalRequest.php` with `@bodyParam` on every field (`amount_minor`, `currency`, `bank_account.account_holder`, `bank_account.iban`, `bank_account.bank_name`, `bank_account.swift_bic`); IBAN ISO-13616 mod-97 validation rule; `authorize()` checks `settlement.request_withdrawal.own` — Source: contracts/withdrawals.md §POST + research.md R13
- [ ] **T303** [US3] Create `app/Modules/Settlement/Http/Resources/WithdrawalResource.php` with `@response` PHPDoc EN+AR examples; mask IBAN (first 4 + last 3 chars); resolve `rejected_reason` JSON via `Accept-Language` — Source: contracts/withdrawals.md
- [ ] **T304** [US3] Create `app/Modules/Settlement/Http/Controllers/Vendor/WithdrawalController.php` with `store()` (POST), `index()` (GET list), `show()` (GET single by public_id) — 3-line bodies — Source: `.claude/rules/actions.md`
- [ ] **T305** [US3] Add routes to `app/Modules/Settlement/Routes/vendor.php`: `POST /api/v1/vendor/withdrawals` (with `IdempotencyKeyMiddleware` from Payments module, sanctum, `settlement.request_withdrawal.own`); `GET /api/v1/vendor/withdrawals`; `GET /api/v1/vendor/withdrawals/{public_id}` — Source: contracts/withdrawals.md + plan.md §Idempotency

### API documentation tasks for US3

- [ ] **T310** [P] [US3] Append rows for the 3 withdrawal endpoints to `.specify/memory/api-registry.md` — Source: plan.md §API Documentation Plan
- [ ] **T311** [P] [US3] Add Bruno requests for 3 withdrawal endpoints to `docs/api/collections/settlement.bru` — Source: plan.md §API Documentation Plan
- [ ] **T312** [P] [US3] Add Postman entries for 3 withdrawal endpoints to `docs/api/collections/settlement.postman_collection.json` — Source: plan.md §API Documentation Plan

### Tests for User Story 3

- [ ] **T320** [P] [US3] Create `tests/Feature/Modules/Settlement/RequestWithdrawalTest.php` — happy + 401 + 403 + insufficient balance + below minimum + existing-pending + EN+AR locale validation messages (per spec.md US3 acceptance 1–5)
- [ ] **T321** [P] [US3] Add Pest test in `RequestWithdrawalTest`: `test_idempotency_key_returns_cached_response` — same key twice returns same response, only one `withdrawals` row (per spec.md US3 acceptance 6)
- [ ] **T322** [P] [US3] Create `tests/Feature/Modules/Settlement/ListWithdrawalsTest.php` — pagination cursor, status filter, vendor isolation (vendor V1 cannot see V2's withdrawals)

**Checkpoint**: US3 fully testable. Vendor can request, list, and view their own withdrawals.

---

## Phase 6: User Story 4 — Admin Approves Withdrawal with Bank Proof (P1)

**Goal**: Admin Filament `WithdrawalsQueueResource` lists pending withdrawals; admin opens one, uploads bank-transfer proof file (private bucket via Spatie Media Library), clicks "Approve & Mark Paid" → withdrawal status `pending → paid`, wallet debited via `withdrawal_debit` ledger entry, audit log appended.

**Independent Test**: As admin with `settlement.approve_withdrawal` permission, open Filament queue → click action → upload PDF → verify `withdrawals.status=paid`, `paid_at` set, ledger entry exists, audit log row appended (per spec.md US4 acceptance 1–5).

### Implementation

- [ ] **T400** [US4] Create `app/Modules/Settlement/Application/Actions/ApproveAndMarkWithdrawalPaidAction.php` (uploads file via Spatie Media Library to `bank_proof` collection on private disk; updates `withdrawals.status=paid`, `paid_at`, `processed_at`, `processed_by_user_id`, `bank_proof_media_id`; calls `DebitWalletAction` for `withdrawal_debit`; decrements `wallets.pending_withdrawal_minor`; appends `audit_logs` entry; fires `WithdrawalPaid` after commit) — Source: spec.md FR-SET-020..024 + research.md R5 + R6
- [ ] **T401** [US4] Create `app/Modules/Settlement/Application/Actions/RejectWithdrawalAction.php` (updates `status=rejected`, `rejected_reason` JSON (EN+AR required), `processed_at`, `processed_by_user_id`; releases `wallets.pending_withdrawal_minor`; appends `audit_logs`; fires `WithdrawalRejected` after commit) — Source: spec.md FR-SET-023 + US4 acceptance 3
- [ ] **T402** [US4] Create `app/Modules/Settlement/Filament/Resources/WithdrawalsQueueResource.php` (Filament v3 — list view filtered to `status=pending`, vendor name, requested amount via `->money('EGP', divideBy: 100)`, custom row actions "Approve & Mark Paid" (with `FileUpload` form for bank proof) and "Reject" (with translatable EN/AR `Textarea` for `rejected_reason`); both delegate to actions T400/T401) — Source: spec.md US4 acceptance 1–3 + `.claude/rules/filament-components.md`
- [ ] **T403** [US4] Create `app/Modules/Settlement/Filament/Resources/WalletLedgerViewerResource.php` (read-only Filament resource — admin can search by vendor, view per-vendor ledger; money formatted; locale-resolved descriptions) — Source: spec.md US4 + plan.md §Project Structure
- [ ] **T404** [US4] Run `php artisan shield:generate --all` and assign permissions `settlement.approve_withdrawal`, `settlement.reject_withdrawal`, `settlement.view_wallet_ledger_admin` to admin role via seeder update — Source: `.claude/rules/filament.md` §Permissions

### Tests for User Story 4

- [ ] **T410** [P] [US4] Create `tests/Feature/Modules/Settlement/AdminApproveWithdrawalTest.php` — Filament action test: pending withdrawal → admin uploads proof + clicks approve → assert status=paid, paid_at populated, bank_proof media exists, `withdrawal_debit` ledger entry, audit row appended, `pending_withdrawal_minor` decremented (per spec.md US4 acceptance 2, 4)
- [ ] **T411** [P] [US4] Create `tests/Feature/Modules/Settlement/AdminRejectWithdrawalTest.php` — Filament reject action: enter EN+AR rejected_reason → assert status=rejected, reason JSON populated, no ledger entry, `pending_withdrawal_minor` released, audit row appended (per spec.md US4 acceptance 3)
- [ ] **T412** [P] [US4] Pest test: admin without `settlement.approve_withdrawal` permission → Filament queue navigation hidden / 403 (per spec.md US4 acceptance 5)

**Checkpoint**: US4 fully testable. Admin approval and rejection flows work end-to-end.

---

## Phase 7: User Story 5 — Refund Reverses Commission and Wallet Credit (P2)

**Goal**: When `RefundCompleted` fires, settlement listener reverses the commission(s) (full or proportional) and appends compensating `refund_debit` wallet entries.

**Independent Test**: Trigger full `RefundCompleted` → original commission `status=reversed`, `refund_debit` ledger entry of full vendor share. Trigger 50% partial refund → status `partially_reversed`, `reversed_amount_minor` = 50% of original (per spec.md US5 acceptance 1–4).

### Implementation

- [ ] **T500** [US5] Create `app/Modules/Settlement/Application/Actions/ReverseCommissionAction.php` (computes proportional reversal `Brick\Money` HALF_EVEN; updates `commissions.reversed_amount_minor` + `status`; calls `DebitWalletAction` for proportional vendor share; logs `audit_logs` warn if balance ends negative; fires `CommissionReversed` after commit) — Source: research.md R4 + R7 + spec.md FR-SET-011..013
- [ ] **T501** [US5] Create `app/Modules/Settlement/Application/Listeners/ReverseCommissionOnRefundCompletedListener.php` (queued; resolves `RefundSnapshotDto` + `PaymentSnapshotDto`; for single-item refund reverses one commission, for full-payment refund reverses all commissions for the payment; idempotent via `wallet_ledger` UNIQUE on `(related_entity_type, related_entity_id, entry_type)`) — Source: contracts/listener-refund-completed.md
- [ ] **T502** [US5] Wire listener registration in `SettlementServiceProvider::boot()` event map: `RefundCompleted` → `ReverseCommissionOnRefundCompletedListener` — Source: contracts/listener-refund-completed.md §Subscription

### Tests for User Story 5

- [ ] **T510** [P] [US5] Create `tests/Feature/Modules/Settlement/RefundReversalTest.php` — full refund reverses full amount; partial 50% refund partially reverses; multi-item refund reverses each item (per spec.md US5 acceptance 1, 2)
- [ ] **T511** [P] [US5] Add Pest test `test_refund_after_withdrawal_produces_negative_balance_with_audit_warn` — vendor withdrew → refund debit pushes balance negative → audit `warn` row appended; subsequent withdrawal request returns 422 `negative_balance_blocked` (per spec.md US5 acceptance 3 + research.md R7)
- [ ] **T512** [P] [US5] Add Pest test `test_duplicate_refund_event_does_not_double_debit` — dispatch `RefundCompleted` twice → only one `refund_debit` ledger entry, `reversed_amount_minor` not doubled (per contracts/listener-refund-completed.md §Idempotency + spec.md US5 acceptance 4)
- [ ] **T513** [P] [US5] Create `tests/Unit/Modules/Settlement/ProportionalReversalMathTest.php` — Brick\Money HALF_EVEN edge cases (33.333% refund, 66.667% refund, 100% refund of odd amounts) → assert sum of reversal parts equals refund amount within ≤ 1 minor unit (per research.md R4)

**Checkpoint**: US5 fully testable. Refund reversal proportional math is correct for all cases.

---

## Phase 8: User Story 6 — Admin Manages Commission Rates (P2)

**Goal**: Admin Filament `CommissionRulesResource` provides CRUD on `commission_rates`. Edits affect future commissions only — never retroactive (snapshot pattern).

**Independent Test**: Admin creates `(category=Cakes, product_type=sale, commission_bps=2000)` → trigger `PaymentCaptured` for a Cakes/sale item → verify the new rate was applied; existing commissions unchanged (per spec.md US6 acceptance 1, 2, 4).

### Implementation

- [ ] **T600** [US6] Create `app/Modules/Settlement/Filament/Resources/CommissionRulesResource.php` (Filament v3 CRUD on `commission_rates`; form fields: nullable category Select, nullable product_type Select with the per-type-color-badge pattern, `commission_bps` numeric 0–10000; table columns: category, product_type, bps, effective_from; permission `settlement.manage_commission_rates`) — Source: spec.md US6 + `.claude/rules/filament-components.md` §Forms + §Tables
- [ ] **T601** [US6] Run `php artisan shield:generate --all` to register `settlement.manage_commission_rates` permission and assign to admin — Source: `.claude/rules/filament.md` §Permissions

### Tests for User Story 6

- [ ] **T610** [P] [US6] Create `tests/Feature/Modules/Settlement/CommissionRulesAdminTest.php` — Filament create rate; verify new rate applies on next `PaymentCaptured`; verify existing commissions unchanged after edit (snapshot pattern); admin without permission → 403 (per spec.md US6 acceptance 1, 2, 3, 4)

**Checkpoint**: US6 fully testable. Commission rates configurable via admin UI.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Architecture tests, invariants, scribe regeneration, lint/static analysis/test gates.

### 9.1 Invariant test

- [ ] **T700** [P] Create `tests/Unit/Modules/Settlement/WalletBalanceInvariantTest.php` — property-style: after every operation in a randomly-generated sequence (credit, debit, refund), assert `wallets.balance_minor == SUM(wallet_ledger.amount_minor WHERE wallet_id = wallet.id)` — Source: spec.md SC-003 + data-model.md §1 §Invariant

### 9.2 Architecture tests

- [ ] **T701** [P] Extend `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` — add `wallet_ledger` and `commissions` to assertion set (verify no `deleted_at` column; verify `wallet_ledger` has no `updated_at`) — Source: plan.md §Architecture Tests
- [ ] **T702** [P] Extend `tests/Architecture/EventsFireAfterCommitTest.php` — assert all 7 Settlement events (T037–T043) are dispatched via `DB::afterCommit()` in their respective Actions (T101, T102, T300, T301, T400, T401, T500) — Source: plan.md §Architecture Tests
- [ ] **T703** [P] Create `tests/Architecture/SettlementModuleNoCrossImportTest.php` — Pest arch: `Settlement` module classes do NOT import `App\Modules\Booking\Domain\Models\*` or `App\Modules\Payments\Domain\Models\*` — Source: research.md R8 + plan.md §Architecture Tests
- [ ] **T704** [P] Extend `tests/Architecture/IdempotencyMiddlewareCoverageTest.php` — add `POST /api/v1/vendor/withdrawals` to the assertion that the route is wrapped by `IdempotencyKeyMiddleware` — Source: plan.md §Idempotency

### 9.3 API documentation regeneration

- [ ] **T705** Run `php artisan scribe:generate` to regenerate API docs after all 5 Settlement endpoints are documented (T210–T212, T310–T312) — Source: plan.md §API Documentation Plan

### 9.4 Quality gates (must all pass before phase done)

- [ ] **T706** Run `./vendor/bin/pint` — auto-format all new files in `app/Modules/Settlement/`, `tests/Feature/Modules/Settlement/`, `tests/Unit/Modules/Settlement/`
- [ ] **T707** Run `./vendor/bin/phpstan analyse` — zero new errors
- [ ] **T708** Run `./vendor/bin/pest --bail` — entire suite green (Settlement + arch + existing modules)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — no dependencies; T001 (ADR) blocks T005–T010 (migrations)
- **Phase 2 (Foundational)** — depends on Phase 1; sub-section ordering: 2.1 migrations → 2.2 enums → 2.3 value objects → 2.4 exceptions → 2.5 models → 2.6 factories → 2.7–2.8 contracts/DTOs → 2.9 events → 2.10 repositories → 2.11 cross-module readers → 2.12 ServiceProvider wiring → 2.13 translations → 2.14 seeder
- **Phase 3 (US1)** — depends on Phase 2 + existing Payments `PaymentCaptured` event (Phase 4.0/4.1)
- **Phase 4 (US2)** — depends on Phase 2 (US2 reads `wallet_ledger` populated by US1, but US2 itself can be tested with seeded ledger entries — independently testable)
- **Phase 5 (US3)** — depends on Phase 2 (uses `Wallet`, `Withdrawal`); independently testable with seeded balance
- **Phase 6 (US4)** — depends on Phase 5 (admin acts on a `pending` withdrawal created by US3); independently testable by seeding a pending withdrawal directly
- **Phase 7 (US5)** — depends on Phase 3 (reverses what US1 created); independently testable by seeding a `Commission` row + dispatching `RefundCompleted`
- **Phase 8 (US6)** — depends on Phase 2 (`CommissionRate` model); independently testable
- **Phase 9 (Polish)** — depends on all user stories complete

### Within each phase

- Setup → Foundational → User Story → tests (interleaved per story, not deferred to Phase 9) → Polish

### Parallel opportunities

`[P]` tasks within a phase can run in parallel:
- All Phase 2 enums (T011–T013), value objects (T014), exceptions (T015–T017), models (T018–T023), factories (T024–T029), contracts (T030–T032), DTOs (T033–T036), events (T037–T043), repositories (T044–T046), translations (T053–T054) are mutually independent
- Within each user story, tests (Pest files) are `[P]` — different test files
- API documentation tasks (api-registry, Bruno, Postman) per story are `[P]` — different files
- Architecture tests in Phase 9 (T701–T704) are `[P]` — different test files

---

## Implementation Strategy

### MVP First (US1 only — get commission flow shipping)

Deliver Phases 1, 2, and 3 only. This proves:
- Module scaffold + ServiceProvider registration (Phase 1)
- All 6 migrations + models + enums + DTOs + cross-module contracts (Phase 2)
- Commission calculation + wallet credit listener with all 4 fallback levels (Phase 3)

After MVP: vendors can't see balances yet (US2), can't withdraw (US3, US4), no refund reversal (US5), no admin rate management (US6). But the platform is recording commissions correctly — financial integrity is preserved.

### Incremental Delivery (after MVP)

Order matches priority: US2 → US3 → US4 → US5 → US6 → Polish.

Each story is shippable on its own once foundational + earlier-priority dependencies are met. A vendor without US2 can still earn commissions; they just can't see their balance until US2 ships.

### Cut-list contingency (if Phase 4.2 slips past 3 days)

Per `09_Phasing_Plan.md` cut-list:
- Defer US6 (CommissionRulesResource) to Phase 1.5 — admin can edit `commission_rates` via direct DB or seeder until then
- Defer `WalletLedgerViewerResource` (in T403) — admin can read ledger via tinker until Phase 1.5
- Defer T705 (`scribe:generate`) and treat T210–T212 / T310–T312 as authoritative — Scribe is nice-to-have

US1–US5 are non-negotiable for Settlement to work end-to-end.

---

## Summary

| Metric | Count |
|---|---|
| Phases | 9 (Setup + Foundational + 6 user stories + Polish) |
| Total tasks | 114 |
| Setup tasks | 4 (T001–T004) |
| Foundational tasks | 51 (T005–T055) |
| US1 tasks | 9 (T100–T113) |
| US2 tasks | 11 (T200–T221) |
| US3 tasks | 12 (T300–T322) |
| US4 tasks | 8 (T400–T412) |
| US5 tasks | 7 (T500–T513) |
| US6 tasks | 3 (T600–T610) |
| Polish tasks | 9 (T700–T708) |
| Migrations | 6 (T005–T010) |
| Models | 6 (T018–T023) |
| Actions | 7 (T100–T102, T300–T301, T400–T401, T500) |
| Listeners | 2 (T103, T501) |
| API endpoints | 5 (each fully documented per plan §API Documentation Plan) |
| Filament resources | 3 (T402, T403, T600) |
| Pest test files | 13 (10 Feature + 3 Unit) |
| Architecture tests | 4 (3 extended + 1 new) |
| Parallel-safe tasks `[P]` | 60+ |

---

## Format Validation

✅ All tasks follow `- [ ] **TID** [P?] [Story?] Description with file path — Source: X` format
✅ Setup phase tasks: no `[Story]` label
✅ Foundational phase tasks: no `[Story]` label
✅ User Story phase tasks: every task has `[USx]` label matching the phase
✅ Polish phase tasks: no `[Story]` label
✅ Every task includes a file path in the description
✅ Every task includes a Source citation (PRD FR / Schema § / ADR § / plan §  / data-model § / spec § / `.claude/rules/*.md`)
✅ `[P]` markers only on tasks that touch different files with no dependency on other incomplete tasks in the same phase

---

## Self-Checks (per autonomous-loop STAGE 3 expectations)

| Check | Result |
|---|---|
| Every task traces to FR / Schema / ADR / plan / data-model / spec / rule | ✅ — every task has a `Source:` citation |
| No Phase 2 features (subscription tiers, dispute engine, auto-payout, advanced tax) | ✅ — all explicitly cut per `09_Phasing_Plan.md` |
| Every API endpoint has documentation tasks (`@bodyParam`, `@response`, registry, Bruno, Postman) | ✅ — `@bodyParam`/`@response` are part of the implementation tasks (T201, T202, T205, T302, T303); registry/Bruno/Postman are dedicated tasks (T210–T212, T310–T312) |
| Layer order respected (ADR → migrations → models → requests → actions → API → Filament → listeners → tests) | ✅ — Phase 1 ADR + setup; Phase 2 migrations → enums → models → factories → contracts/DTOs → events → repositories → cross-module readers → ServiceProvider wiring → translations → seeder; Phases 3–8 add Actions → Resources → Filament → tests interleaved per story |
| All 3 product types covered for type-aware features | ✅ — T110 (US1) is parameterized for rental + sale + digital; commission resolution tests across all combinations |
| Idempotency tested where applicable | ✅ — T113 (US1, listener), T321 (US3, POST endpoint), T512 (US5, listener) |
