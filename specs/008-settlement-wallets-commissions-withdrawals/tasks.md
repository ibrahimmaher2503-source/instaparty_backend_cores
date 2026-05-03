# Tasks: Settlement â€” Wallets, Commissions, Withdrawals (Phase 4.2)

**Input**: Design documents from `specs/008-settlement-wallets-commissions-withdrawals/`
**Prerequisites**: spec.md, plan.md, research.md, data-model.md, contracts/

**Tests**: REQUIRED. Pest coverage is mandatory per `CLAUDE.md` Â§Testing Conventions â€” happy + auth + authz + validation + locale + all 3 product types where applicable + idempotency where applicable. Tests are interleaved per user story (not deferred to a final phase) so each story is independently verifiable.

**Organization**: Tasks grouped by user story (from spec.md priorities P1, P2). Setup + Foundational are blocking prerequisites. Polish includes architecture tests, scribe regeneration, and quality gates.

---

## Format: `- [X] **TID** [P?] [Story?] Description with file path â€” Source: X`

- **[P]**: parallel-safe (different file, no incomplete dependencies)
- **[Story]**: USx label for user-story tasks; setup/foundational/polish carry no story label
- File paths use `app/Modules/Settlement/...` per `plan.md` Â§Project Structure
- Source citations: PRD `FR-XX`, Schema `Â§Catalog/Booking/etc.`, ADR `ADR-NNNN Â§X`, plan `plan.md Â§X`, data-model `data-model.md Â§X`, spec `spec.md US-X` or `FR-SET-XXX`

---

## Phase 1: Setup (Module Scaffold)

**Purpose**: Project initialization â€” module directory, ADR, ServiceProvider registration.

- [X] **T001** Draft and accept ADR-0009 in `docs/adr/0009-settlement-module.md` (module ownership, append-only carve-outs, no auto-payout, single-pending DB rule, contracts vs cross-module imports) â€” Source: plan.md Â§ADR + research.md R8
- [X] **T002** Create module directory scaffold under `app/Modules/Settlement/{Domain/{Models,Enums,Events,Exceptions,ValueObjects,Contracts},Application/{Actions,DTOs,Listeners,Services},Infrastructure/Repositories,Http/{Controllers/Vendor,Requests,Resources},Filament/Resources,Routes,Database/{Migrations,Factories,Seeders},Resources/lang/{en,ar},Providers}/.gitkeep` â€” Source: `.claude/rules/modules.md` Â§Layer layout
- [X] **T003** Create `app/Modules/Settlement/Providers/SettlementServiceProvider.php` (skeleton with `register()` + `boot()`) â€” Source: `.claude/rules/modules.md` Â§ServiceProvider
- [X] **T004** Register `SettlementServiceProvider::class` in `bootstrap/app.php` â€” Source: `.claude/rules/modules.md` Â§ServiceProvider

---

## Phase 2: Foundational (Blocking Prerequisites)

**âš ï¸ CRITICAL**: No user story work can begin until this phase is complete.

**Purpose**: Schema, models (shells only), enums, value objects, factories, contracts, DTOs, exceptions, translation files, seeder for default commission rate.

### 2.1 Migrations (strict FK dependency order â€” must run sequentially)

- [X] **T005** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000001_create_wallets_table.php` (UNIQUE `(owner_type, owner_id, currency)`, cached `balance_minor`, ULID `public_id`, utf8mb4) â€” Source: data-model.md Â§1, Schema lines 925â€“940
- [X] **T006** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000002_create_wallet_ledger_table.php` (append-only â€” `created_at` only, no `updated_at`/`deleted_at`; UNIQUE `(related_entity_type, related_entity_id, entry_type)`; `(wallet_id, created_at DESC)` index) â€” Source: data-model.md Â§2, Schema lines 941â€“958
- [X] **T007** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000003_create_commission_rates_table.php` (NULL-safe UNIQUE on `(category_id, product_type)` via derived `IFNULL` columns or `USING HASH`) â€” Source: data-model.md Â§4, Schema lines 976â€“993
- [X] **T008** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000004_create_commissions_table.php` (UNIQUE `booking_item_id`, no `updated_at`, status-only updates) â€” Source: data-model.md Â§3, Schema lines 959â€“975
- [X] **T009** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000005_create_withdrawals_table.php` (UNIQUE PARTIAL `(vendor_profile_id) WHERE status='pending'`, `bank_account_snapshot` JSON, `rejected_reason` translatable JSON, `bank_proof_media_id` FK â†’ media) â€” Source: data-model.md Â§5, Schema lines 994â€“1014, research.md R3
- [X] **T010** Create migration `app/Modules/Settlement/Database/Migrations/2026_05_03_000006_create_settlement_runs_table.php` (table only â€” no rows in Phase 4.2 per cut-list) â€” Source: data-model.md Â§6, plan.md Â§Cut-list

### 2.2 Enums

- [X] **T011** [P] Create `app/Modules/Settlement/Domain/Enums/LedgerEntryType.php` (cases: `commission_credit`, `refund_debit`, `withdrawal_debit`, `manual_adjustment`) â€” Source: data-model.md Â§2
- [X] **T012** [P] Create `app/Modules/Settlement/Domain/Enums/CommissionStatus.php` (cases: `calculated`, `partially_reversed`, `reversed`) â€” Source: data-model.md Â§3
- [X] **T013** [P] Create `app/Modules/Settlement/Domain/Enums/WithdrawalStatus.php` (cases: `pending`, `approved`, `paid`, `rejected`) â€” Source: data-model.md Â§5

### 2.3 Value Objects

- [X] **T014** [P] Create `app/Modules/Settlement/Domain/ValueObjects/BankAccountSnapshot.php` (readonly: `account_holder`, `iban`, `bank_name`, `swift_bic`; ISO-13616 mod-97 IBAN validation) â€” Source: research.md R13

### 2.4 Domain Exceptions

- [X] **T015** [P] Create `app/Modules/Settlement/Domain/Exceptions/InsufficientWalletBalanceException.php` â€” Source: spec.md FR-SET-017
- [X] **T016** [P] Create `app/Modules/Settlement/Domain/Exceptions/ExistingPendingWithdrawalException.php` â€” Source: spec.md FR-SET-018
- [X] **T017** [P] Create `app/Modules/Settlement/Domain/Exceptions/WithdrawalBelowMinimumException.php` â€” Source: spec.md FR-SET-016

### 2.5 Eloquent Models (shells â€” relationships, casts, scopes ONLY; no business logic)

- [X] **T018** [P] Create `app/Modules/Settlement/Domain/Models/Wallet.php` (`morphTo` owner, `hasMany` ledger; casts `balance_minor`/`pending_withdrawal_minor` via `MoneyCast`; ULID `public_id`) â€” Source: data-model.md Â§1
- [X] **T019** [P] Create `app/Modules/Settlement/Domain/Models/WalletLedgerEntry.php` (`belongsTo` Wallet; `morphTo` related; `entry_type` enum cast; no `$timestamps` â€” only `created_at`) â€” Source: data-model.md Â§2
- [X] **T020** [P] Create `app/Modules/Settlement/Domain/Models/Commission.php` (status enum cast; ULID; no `$timestamps` for `updated_at` â€” only `created_at`) â€” Source: data-model.md Â§3
- [X] **T021** [P] Create `app/Modules/Settlement/Domain/Models/CommissionRate.php` (`belongsTo` Category; product_type enum cast; ULID) â€” Source: data-model.md Â§4
- [X] **T022** [P] Create `app/Modules/Settlement/Domain/Models/Withdrawal.php` (`belongsTo` VendorProfile + User Ã—2; `morphMany` Media (`bank_proof` collection); `rejected_reason` translatable; `bank_account_snapshot` cast to `BankAccountSnapshot`) â€” Source: data-model.md Â§5
- [X] **T023** [P] Create `app/Modules/Settlement/Domain/Models/SettlementRun.php` (status enum cast; ULID) â€” Source: data-model.md Â§6

### 2.6 Factories

- [X] **T024** [P] Create `app/Modules/Settlement/Database/Factories/WalletFactory.php`
- [X] **T025** [P] Create `app/Modules/Settlement/Database/Factories/WalletLedgerEntryFactory.php`
- [X] **T026** [P] Create `app/Modules/Settlement/Database/Factories/CommissionFactory.php`
- [X] **T027** [P] Create `app/Modules/Settlement/Database/Factories/CommissionRateFactory.php`
- [X] **T028** [P] Create `app/Modules/Settlement/Database/Factories/WithdrawalFactory.php`
- [X] **T029** [P] Create `app/Modules/Settlement/Database/Factories/SettlementRunFactory.php`

### 2.7 Cross-module Contracts (interfaces owned by Settlement)

- [X] **T030** [P] Create `app/Modules/Settlement/Domain/Contracts/SettlementBookingReader.php` (methods: `findItemById(int $id): ?BookingItemSnapshotDto`; `itemsForPayment(int $bookingId): array<BookingItemSnapshotDto>`) â€” Source: research.md R8, plan.md Â§Cross-module access
- [X] **T031** [P] Create `app/Modules/Settlement/Domain/Contracts/SettlementPaymentReader.php` (methods: `findById(int $id): ?PaymentSnapshotDto`; `findRefundById(int $id): ?RefundSnapshotDto`) â€” Source: research.md R8
- [X] **T032** [P] Create `app/Modules/Settlement/Domain/Contracts/CommissionRateResolver.php` (method: `resolve(?int $categoryId, ProductType $type): ?int` â€” basis points) â€” Source: research.md R2

### 2.8 Cross-module DTOs

- [X] **T033** [P] Create `app/Modules/Settlement/Application/DTOs/BookingItemSnapshotDto.php` (readonly: id, public_id, booking_id, vendor_profile_id, category_id, product_type, total_minor, total_currency, commission_bps?) â€” Source: data-model.md Â§Cross-module DTOs
- [X] **T034** [P] Create `app/Modules/Settlement/Application/DTOs/PaymentSnapshotDto.php` (readonly: id, public_id, booking_id, amount_minor, currency, status, captured_at) â€” Source: data-model.md Â§Cross-module DTOs
- [X] **T035** [P] Create `app/Modules/Settlement/Application/DTOs/RefundSnapshotDto.php` (readonly: id, public_id, payment_id, booking_item_id?, amount_minor, currency, completed_at) â€” Source: data-model.md Â§Cross-module DTOs
- [X] **T036** [P] Create `app/Modules/Settlement/Application/DTOs/RequestWithdrawalDto.php` (readonly: vendor_profile_id, requested_by_user_id, amount_minor, currency, bank_account: BankAccountSnapshot) â€” Source: data-model.md Â§Actions

### 2.9 Settlement-emitted Domain Events (DB::afterCommit only)

- [X] **T037** [P] Create `app/Modules/Settlement/Domain/Events/WalletCredited.php` â€” Source: plan.md Â§Domain Events
- [X] **T038** [P] Create `app/Modules/Settlement/Domain/Events/WalletDebited.php` â€” Source: plan.md Â§Domain Events
- [X] **T039** [P] Create `app/Modules/Settlement/Domain/Events/CommissionCalculated.php` â€” Source: plan.md Â§Domain Events
- [X] **T040** [P] Create `app/Modules/Settlement/Domain/Events/CommissionReversed.php` â€” Source: plan.md Â§Domain Events
- [X] **T041** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalRequested.php` â€” Source: plan.md Â§Domain Events
- [X] **T042** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalPaid.php` â€” Source: plan.md Â§Domain Events
- [X] **T043** [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalRejected.php` â€” Source: plan.md Â§Domain Events

### 2.10 Repositories (Eloquent implementations)

- [X] **T044** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentWalletRepository.php` (lazy-create wallet on first credit; balance lookup; pending_withdrawal increment/decrement) â€” Source: data-model.md Â§1
- [X] **T045** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentWithdrawalRepository.php` â€” Source: data-model.md Â§5
- [X] **T046** [P] Create `app/Modules/Settlement/Infrastructure/Repositories/EloquentCommissionRepository.php` â€” Source: data-model.md Â§3

### 2.11 Cross-module reader implementations (live in Booking and Payments modules)

- [X] **T047** Create `app/Modules/Booking/Infrastructure/Repositories/EloquentSettlementBookingReader.php` implementing `SettlementBookingReader` (returns `BookingItemSnapshotDto`s, no Eloquent leak) â€” Source: research.md R8
- [X] **T048** Bind `SettlementBookingReader` â†’ `EloquentSettlementBookingReader` in `app/Modules/Booking/Providers/BookingServiceProvider.php` `register()` â€” Source: `.claude/rules/modules.md` Â§ServiceProvider
- [X] **T049** Create `app/Modules/Payments/Infrastructure/Repositories/EloquentSettlementPaymentReader.php` implementing `SettlementPaymentReader` â€” Source: research.md R8
- [X] **T050** Bind `SettlementPaymentReader` â†’ `EloquentSettlementPaymentReader` in `app/Modules/Payments/Providers/PaymentsServiceProvider.php` `register()` â€” Source: `.claude/rules/modules.md` Â§ServiceProvider

### 2.12 Settlement ServiceProvider wiring

- [X] **T051** Wire all Settlement contract bindings (`CommissionRateResolver` â†’ `EloquentCommissionRateResolver`; repositories) in `SettlementServiceProvider::register()` â€” Source: plan.md Â§Project Structure
- [X] **T052** Load module migrations + translations + Routes/vendor.php in `SettlementServiceProvider::boot()` â€” Source: `.claude/rules/modules.md` Â§ServiceProvider

### 2.13 Translation files

- [X] **T053** [P] Create `app/Modules/Settlement/Resources/lang/en/settlement.php` (errors: `insufficient_balance`, `existing_pending_withdrawal`, `below_minimum_amount`, `negative_balance_blocked`, `withdrawal_not_found`; ledger description keys: `ledger.commission_credit`, `ledger.refund_debit`, `ledger.withdrawal_debit`) â€” Source: spec.md edge cases + data-model.md Â§2
- [X] **T054** [P] Create `app/Modules/Settlement/Resources/lang/ar/settlement.php` (Arabic mirror of T053) â€” Source: spec.md FR-SET-029

### 2.14 Default commission rate seeder

- [X] **T055** Create `app/Modules/Settlement/Database/Seeders/DefaultCommissionRatesSeeder.php` (single row `(category_id=NULL, product_type=NULL, commission_bps=1500)` â€” platform default 15%) â€” Source: research.md R2 + data-model.md Â§4 Â§Seed data

**Checkpoint**: Foundation ready. User stories US1â€“US6 can now be implemented (US1 first per dependency).

---

## Phase 3: User Story 1 â€” Commission Auto-Calculated on Payment Capture (P1) ðŸŽ¯ MVP

**Goal**: When `PaymentCaptured` fires, settlement listener calculates commissions per booking_item using 4-level rate fallback, snapshots commission to `commissions`, and credits vendor wallet via `wallet_ledger`.

**Independent Test**: Trigger `PaymentCaptured` for a booking with rental + sale items from different vendors â†’ verify 2 `commissions` rows + 2 `wallet_ledger` `commission_credit` entries + correct cached `wallets.balance_minor` for each vendor (per spec.md US1 acceptance scenarios 1â€“6).

### Implementation

- [X] **T100** [US1] Create `app/Modules/Settlement/Application/Services/EloquentCommissionRateResolver.php` implementing `CommissionRateResolver` (4-level fallback: `(category Ã— type)` â†’ `(category Ã— NULL)` â†’ `(NULL Ã— type)` â†’ `(NULL Ã— NULL)`) â€” Source: research.md R2 + spec.md FR-SET-006
- [X] **T101** [US1] Create `app/Modules/Settlement/Application/Actions/CreditWalletAction.php` (lazy-create wallet, append `wallet_ledger` entry, update `wallets.balance_minor` in same transaction, fire `WalletCredited` after commit) â€” Source: data-model.md Â§Actions + spec.md FR-SET-001..004
- [X] **T102** [US1] Create `app/Modules/Settlement/Application/Actions/CalculateCommissionAction.php` (uses `SettlementBookingReader` + resolver; reads `commission_bps` snapshot from booking_item first, falls back to resolver, defaults to 0 + warn if no match; `Brick\Money` HALF_EVEN rounding for `commission_minor`/`vendor_share_minor`; creates `Commission` row + calls `CreditWalletAction`; fires `CommissionCalculated` after commit) â€” Source: spec.md FR-SET-005..010 + research.md R2 + R4
- [X] **T103** [US1] Create `app/Modules/Settlement/Application/Listeners/CalculateCommissionOnPaymentCapturedListener.php` (queued via `database` queue; iterates booking items via `SettlementPaymentReader.findById` then `SettlementBookingReader.itemsForPayment`; catches duplicate-key `QueryException` for idempotency on `commissions.booking_item_id` UNIQUE; 3 retries with exp backoff per research.md R11) â€” Source: contracts/listener-payment-captured.md
- [X] **T104** [US1] Wire listener registration in `SettlementServiceProvider::boot()` event map: `PaymentCaptured` â†’ `CalculateCommissionOnPaymentCapturedListener` â€” Source: contracts/listener-payment-captured.md Â§Subscription

### Tests for User Story 1

- [X] **T110** [P] [US1] Create `tests/Feature/Modules/Settlement/CommissionCalculationTest.php` â€” happy path for all 3 product types (rental + sale + digital), one test per type (Pest groups `rental`, `sale`, `digital`); verify `commissions` row + `wallet_ledger` `commission_credit` + cached `balance_minor` â€” Source: spec.md US1 acceptance 1, 5; SC-008
- [X] **T111** [P] [US1] Create `tests/Feature/Modules/Settlement/CommissionFallbackTest.php` â€” Pest tests for all 4 fallback levels (per spec.md US1 acceptance 1â€“4): `(cat Ã— type)`, `(cat Ã— NULL)`, `(NULL Ã— type)`, `(NULL Ã— NULL)` â€” Source: spec.md FR-SET-006 + SC-002
- [X] **T112** [P] [US1] Create `tests/Unit/Modules/Settlement/EloquentCommissionRateResolverTest.php` â€” isolated unit tests on resolver's 4-level fallback with mock DB â€” Source: research.md R2
- [X] **T113** [P] [US1] Add Pest test in `CommissionCalculationTest`: `test_duplicate_event_does_not_double_credit` â€” dispatch `PaymentCaptured` twice; assert only one `commissions` + one ledger entry â€” Source: contracts/listener-payment-captured.md Â§Idempotency + spec.md US1 acceptance 6

**Checkpoint**: US1 fully testable independently. Commission calculation works for all 3 product types and all 4 fallback levels.

---

## Phase 4: User Story 2 â€” Vendor Views Wallet Balance + Ledger History (P1)

**Goal**: Authenticated vendor can `GET /api/v1/vendor/wallet` (balance summary) and `GET /api/v1/vendor/wallet/ledger` (paginated history newest-first, locale-resolved descriptions).

**Independent Test**: As a vendor with 2 credits + 1 debit â†’ `GET /vendor/wallet` returns correct balance/totals; `GET /vendor/wallet/ledger?per_page=50` returns paginated entries with localized descriptions (per spec.md US2 acceptance 1â€“5).

### Implementation

- [X] **T200** [US2] Create `app/Modules/Settlement/Application/Services/WalletQueryService.php` (methods: `balance(VendorProfile $v, string $currency = 'EGP'): WalletBalanceDto`; `ledger(VendorProfile $v, LedgerFilters $f): CursorPaginator<WalletLedgerEntry>`) â€” Source: contracts/wallet.md Â§Internal contracts
- [X] **T201** [US2] Create `app/Modules/Settlement/Http/Resources/WalletResource.php` with `@response` PHPDoc containing realistic EN+AR examples per contracts/wallet.md â€” Source: spec.md FR-SET-025 + contracts/wallet.md
- [X] **T202** [US2] Create `app/Modules/Settlement/Http/Resources/WalletLedgerEntryResource.php` (resolves `description_key` + `description_params` against `lang/{en,ar}/settlement.php` based on `Accept-Language`) â€” Source: spec.md FR-SET-029 + contracts/wallet.md
- [X] **T203** [US2] Create `app/Modules/Settlement/Http/Controllers/Vendor/WalletController.php` with `show()` and `ledger()` methods (3-line action body MAX) delegating to `WalletQueryService` â€” Source: `.claude/rules/actions.md` Â§thin controllers
- [X] **T204** [US2] Add routes to `app/Modules/Settlement/Routes/vendor.php`: `GET /api/v1/vendor/wallet` and `GET /api/v1/vendor/wallet/ledger` (sanctum + `settlement.view_wallet.own` permission) â€” Source: contracts/wallet.md
- [X] **T205** [US2] Add `@bodyParam` PHPDoc on `WalletController::ledger()` query parameters (per_page, cursor, entry_type, from, to) â€” Source: contracts/wallet.md Â§Request

### API documentation tasks for US2

- [X] **T210** [P] [US2] Append rows for `GET /vendor/wallet` and `GET /vendor/wallet/ledger` to `.specify/memory/api-registry.md` (Method, Path, Auth, Roles, Idempotency-Key, Request schema, Response schema link to contracts/wallet.md) â€” Source: plan.md Â§API Documentation Plan
- [X] **T211** [P] [US2] Add Bruno requests for the 2 wallet endpoints to `docs/api/collections/settlement.bru` â€” Source: plan.md Â§API Documentation Plan
- [X] **T212** [P] [US2] Add Postman entries for the 2 wallet endpoints to `docs/api/collections/settlement.postman_collection.json` â€” Source: plan.md Â§API Documentation Plan

### Tests for User Story 2

- [X] **T220** [P] [US2] Create `tests/Feature/Modules/Settlement/WalletViewTest.php` â€” happy + 401 + 403 + zero-balance + EN+AR locale (per spec.md US2 acceptance 1â€“5)
- [X] **T221** [P] [US2] Create `tests/Feature/Modules/Settlement/WalletLedgerTest.php` â€” pagination cursor, ordering newest-first, locale-resolved descriptions, entry_type filter

**Checkpoint**: US2 fully testable. Vendor sees their wallet + ledger.

---

## Phase 5: User Story 3 â€” Vendor Requests Withdrawal (P1)

**Goal**: Vendor `POST /api/v1/vendor/withdrawals` creates a `pending` withdrawal, validates balance/minimum/single-pending, supports `Idempotency-Key`. Vendor can list and view their own withdrawals.

**Independent Test**: Vendor with 500 EGP balance + no pending â†’ `POST /vendor/withdrawals` (300 EGP) returns 201; second call returns 422 `existing_pending_withdrawal`; below-minimum returns 422 (per spec.md US3 acceptance 1â€“6).

### Implementation

- [X] **T300** [US3] Create `app/Modules/Settlement/Application/Actions/DebitWalletAction.php` (mirror of `CreditWalletAction` but for debits â€” append negative ledger entry, update cached balance, fire `WalletDebited` after commit) â€” Source: data-model.md Â§Actions
- [X] **T301** [US3] Create `app/Modules/Settlement/Application/Actions/RequestWithdrawalAction.php` (validate balance â‰¥ requested, â‰¥ minimum, no existing pending; insert `Withdrawal` row with `status=pending`; bump `wallets.pending_withdrawal_minor`; catch UNIQUE PARTIAL violation as `ExistingPendingWithdrawalException`; fire `WithdrawalRequested` after commit) â€” Source: spec.md FR-SET-014..019 + research.md R3
- [X] **T302** [US3] Create `app/Modules/Settlement/Http/Requests/RequestWithdrawalRequest.php` with `@bodyParam` on every field (`amount_minor`, `currency`, `bank_account.account_holder`, `bank_account.iban`, `bank_account.bank_name`, `bank_account.swift_bic`); IBAN ISO-13616 mod-97 validation rule; `authorize()` checks `settlement.request_withdrawal.own` â€” Source: contracts/withdrawals.md Â§POST + research.md R13
- [X] **T303** [US3] Create `app/Modules/Settlement/Http/Resources/WithdrawalResource.php` with `@response` PHPDoc EN+AR examples; mask IBAN (first 4 + last 3 chars); resolve `rejected_reason` JSON via `Accept-Language` â€” Source: contracts/withdrawals.md
- [X] **T304** [US3] Create `app/Modules/Settlement/Http/Controllers/Vendor/WithdrawalController.php` with `store()` (POST), `index()` (GET list), `show()` (GET single by public_id) â€” 3-line bodies â€” Source: `.claude/rules/actions.md`
- [X] **T305** [US3] Add routes to `app/Modules/Settlement/Routes/vendor.php`: `POST /api/v1/vendor/withdrawals` (with `IdempotencyKeyMiddleware` from Payments module, sanctum, `settlement.request_withdrawal.own`); `GET /api/v1/vendor/withdrawals`; `GET /api/v1/vendor/withdrawals/{public_id}` â€” Source: contracts/withdrawals.md + plan.md Â§Idempotency

### API documentation tasks for US3

- [X] **T310** [P] [US3] Append rows for the 3 withdrawal endpoints to `.specify/memory/api-registry.md` â€” Source: plan.md Â§API Documentation Plan
- [X] **T311** [P] [US3] Add Bruno requests for 3 withdrawal endpoints to `docs/api/collections/settlement.bru` â€” Source: plan.md Â§API Documentation Plan
- [X] **T312** [P] [US3] Add Postman entries for 3 withdrawal endpoints to `docs/api/collections/settlement.postman_collection.json` â€” Source: plan.md Â§API Documentation Plan

### Tests for User Story 3

- [X] **T320** [P] [US3] Create `tests/Feature/Modules/Settlement/RequestWithdrawalTest.php` â€” happy + 401 + 403 + insufficient balance + below minimum + existing-pending + EN+AR locale validation messages (per spec.md US3 acceptance 1â€“5)
- [X] **T321** [P] [US3] Add Pest test in `RequestWithdrawalTest`: `test_idempotency_key_returns_cached_response` â€” same key twice returns same response, only one `withdrawals` row (per spec.md US3 acceptance 6)
- [X] **T322** [P] [US3] Create `tests/Feature/Modules/Settlement/ListWithdrawalsTest.php` â€” pagination cursor, status filter, vendor isolation (vendor V1 cannot see V2's withdrawals)

**Checkpoint**: US3 fully testable. Vendor can request, list, and view their own withdrawals.

---

## Phase 6: User Story 4 â€” Admin Approves Withdrawal with Bank Proof (P1)

**Goal**: Admin Filament `WithdrawalsQueueResource` lists pending withdrawals; admin opens one, uploads bank-transfer proof file (private bucket via Spatie Media Library), clicks "Approve & Mark Paid" â†’ withdrawal status `pending â†’ paid`, wallet debited via `withdrawal_debit` ledger entry, audit log appended.

**Independent Test**: As admin with `settlement.approve_withdrawal` permission, open Filament queue â†’ click action â†’ upload PDF â†’ verify `withdrawals.status=paid`, `paid_at` set, ledger entry exists, audit log row appended (per spec.md US4 acceptance 1â€“5).

### Implementation

- [X] **T400** [US4] Create `app/Modules/Settlement/Application/Actions/ApproveAndMarkWithdrawalPaidAction.php` (uploads file via Spatie Media Library to `bank_proof` collection on private disk; updates `withdrawals.status=paid`, `paid_at`, `processed_at`, `processed_by_user_id`, `bank_proof_media_id`; calls `DebitWalletAction` for `withdrawal_debit`; decrements `wallets.pending_withdrawal_minor`; appends `audit_logs` entry; fires `WithdrawalPaid` after commit) â€” Source: spec.md FR-SET-020..024 + research.md R5 + R6
- [X] **T401** [US4] Create `app/Modules/Settlement/Application/Actions/RejectWithdrawalAction.php` (updates `status=rejected`, `rejected_reason` JSON (EN+AR required), `processed_at`, `processed_by_user_id`; releases `wallets.pending_withdrawal_minor`; appends `audit_logs`; fires `WithdrawalRejected` after commit) â€” Source: spec.md FR-SET-023 + US4 acceptance 3
- [X] **T402** [US4] Create `app/Modules/Settlement/Filament/Resources/WithdrawalsQueueResource.php` (Filament v3 â€” list view filtered to `status=pending`, vendor name, requested amount via `->money('EGP', divideBy: 100)`, custom row actions "Approve & Mark Paid" (with `FileUpload` form for bank proof) and "Reject" (with translatable EN/AR `Textarea` for `rejected_reason`); both delegate to actions T400/T401) â€” Source: spec.md US4 acceptance 1â€“3 + `.claude/rules/filament-components.md`
- [X] **T403** [US4] Create `app/Modules/Settlement/Filament/Resources/WalletLedgerViewerResource.php` (read-only Filament resource â€” admin can search by vendor, view per-vendor ledger; money formatted; locale-resolved descriptions) â€” Source: spec.md US4 + plan.md Â§Project Structure
- [X] **T404** [US4] Run `php artisan shield:generate --all` and assign permissions `settlement.approve_withdrawal`, `settlement.reject_withdrawal`, `settlement.view_wallet_ledger_admin` to admin role via seeder update â€” Source: `.claude/rules/filament.md` Â§Permissions

### Tests for User Story 4

- [X] **T410** [P] [US4] Create `tests/Feature/Modules/Settlement/AdminApproveWithdrawalTest.php` â€” Filament action test: pending withdrawal â†’ admin uploads proof + clicks approve â†’ assert status=paid, paid_at populated, bank_proof media exists, `withdrawal_debit` ledger entry, audit row appended, `pending_withdrawal_minor` decremented (per spec.md US4 acceptance 2, 4)
- [X] **T411** [P] [US4] Create `tests/Feature/Modules/Settlement/AdminRejectWithdrawalTest.php` â€” Filament reject action: enter EN+AR rejected_reason â†’ assert status=rejected, reason JSON populated, no ledger entry, `pending_withdrawal_minor` released, audit row appended (per spec.md US4 acceptance 3)
- [X] **T412** [P] [US4] Pest test: admin without `settlement.approve_withdrawal` permission â†’ Filament queue navigation hidden / 403 (per spec.md US4 acceptance 5)

**Checkpoint**: US4 fully testable. Admin approval and rejection flows work end-to-end.

---

## Phase 7: User Story 5 â€” Refund Reverses Commission and Wallet Credit (P2)

**Goal**: When `RefundCompleted` fires, settlement listener reverses the commission(s) (full or proportional) and appends compensating `refund_debit` wallet entries.

**Independent Test**: Trigger full `RefundCompleted` â†’ original commission `status=reversed`, `refund_debit` ledger entry of full vendor share. Trigger 50% partial refund â†’ status `partially_reversed`, `reversed_amount_minor` = 50% of original (per spec.md US5 acceptance 1â€“4).

### Implementation

- [X] **T500** [US5] Create `app/Modules/Settlement/Application/Actions/ReverseCommissionAction.php` (computes proportional reversal `Brick\Money` HALF_EVEN; updates `commissions.reversed_amount_minor` + `status`; calls `DebitWalletAction` for proportional vendor share; logs `audit_logs` warn if balance ends negative; fires `CommissionReversed` after commit) â€” Source: research.md R4 + R7 + spec.md FR-SET-011..013
- [X] **T501** [US5] Create `app/Modules/Settlement/Application/Listeners/ReverseCommissionOnRefundCompletedListener.php` (queued; resolves `RefundSnapshotDto` + `PaymentSnapshotDto`; for single-item refund reverses one commission, for full-payment refund reverses all commissions for the payment; idempotent via `wallet_ledger` UNIQUE on `(related_entity_type, related_entity_id, entry_type)`) â€” Source: contracts/listener-refund-completed.md
- [X] **T502** [US5] Wire listener registration in `SettlementServiceProvider::boot()` event map: `RefundCompleted` â†’ `ReverseCommissionOnRefundCompletedListener` â€” Source: contracts/listener-refund-completed.md Â§Subscription

### Tests for User Story 5

- [X] **T510** [P] [US5] Create `tests/Feature/Modules/Settlement/RefundReversalTest.php` â€” full refund reverses full amount; partial 50% refund partially reverses; multi-item refund reverses each item (per spec.md US5 acceptance 1, 2)
- [X] **T511** [P] [US5] Add Pest test `test_refund_after_withdrawal_produces_negative_balance_with_audit_warn` â€” vendor withdrew â†’ refund debit pushes balance negative â†’ audit `warn` row appended; subsequent withdrawal request returns 422 `negative_balance_blocked` (per spec.md US5 acceptance 3 + research.md R7)
- [X] **T512** [P] [US5] Add Pest test `test_duplicate_refund_event_does_not_double_debit` â€” dispatch `RefundCompleted` twice â†’ only one `refund_debit` ledger entry, `reversed_amount_minor` not doubled (per contracts/listener-refund-completed.md Â§Idempotency + spec.md US5 acceptance 4)
- [X] **T513** [P] [US5] Create `tests/Unit/Modules/Settlement/ProportionalReversalMathTest.php` â€” Brick\Money HALF_EVEN edge cases (33.333% refund, 66.667% refund, 100% refund of odd amounts) â†’ assert sum of reversal parts equals refund amount within â‰¤ 1 minor unit (per research.md R4)

**Checkpoint**: US5 fully testable. Refund reversal proportional math is correct for all cases.

---

## Phase 8: User Story 6 â€” Admin Manages Commission Rates (P2)

**Goal**: Admin Filament `CommissionRulesResource` provides CRUD on `commission_rates`. Edits affect future commissions only â€” never retroactive (snapshot pattern).

**Independent Test**: Admin creates `(category=Cakes, product_type=sale, commission_bps=2000)` â†’ trigger `PaymentCaptured` for a Cakes/sale item â†’ verify the new rate was applied; existing commissions unchanged (per spec.md US6 acceptance 1, 2, 4).

### Implementation

- [X] **T600** [US6] Create `app/Modules/Settlement/Filament/Resources/CommissionRulesResource.php` (Filament v3 CRUD on `commission_rates`; form fields: nullable category Select, nullable product_type Select with the per-type-color-badge pattern, `commission_bps` numeric 0â€“10000; table columns: category, product_type, bps, effective_from; permission `settlement.manage_commission_rates`) â€” Source: spec.md US6 + `.claude/rules/filament-components.md` Â§Forms + Â§Tables
- [X] **T601** [US6] Run `php artisan shield:generate --all` to register `settlement.manage_commission_rates` permission and assign to admin â€” Source: `.claude/rules/filament.md` Â§Permissions

### Tests for User Story 6

- [X] **T610** [P] [US6] Create `tests/Feature/Modules/Settlement/CommissionRulesAdminTest.php` â€” Filament create rate; verify new rate applies on next `PaymentCaptured`; verify existing commissions unchanged after edit (snapshot pattern); admin without permission â†’ 403 (per spec.md US6 acceptance 1, 2, 3, 4)

**Checkpoint**: US6 fully testable. Commission rates configurable via admin UI.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Architecture tests, invariants, scribe regeneration, lint/static analysis/test gates.

### 9.1 Invariant test

- [X] **T700** [P] Create `tests/Unit/Modules/Settlement/WalletBalanceInvariantTest.php` â€” property-style: after every operation in a randomly-generated sequence (credit, debit, refund), assert `wallets.balance_minor == SUM(wallet_ledger.amount_minor WHERE wallet_id = wallet.id)` â€” Source: spec.md SC-003 + data-model.md Â§1 Â§Invariant

### 9.2 Architecture tests

- [X] **T701** [P] Extend `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` â€” add `wallet_ledger` and `commissions` to assertion set (verify no `deleted_at` column; verify `wallet_ledger` has no `updated_at`) â€” Source: plan.md Â§Architecture Tests
- [X] **T702** [P] Extend `tests/Architecture/EventsFireAfterCommitTest.php` â€” assert all 7 Settlement events (T037â€“T043) are dispatched via `DB::afterCommit()` in their respective Actions (T101, T102, T300, T301, T400, T401, T500) â€” Source: plan.md Â§Architecture Tests
- [X] **T703** [P] Create `tests/Architecture/SettlementModuleNoCrossImportTest.php` â€” Pest arch: `Settlement` module classes do NOT import `App\Modules\Booking\Domain\Models\*` or `App\Modules\Payments\Domain\Models\*` â€” Source: research.md R8 + plan.md Â§Architecture Tests
- [X] **T704** [P] Extend `tests/Architecture/IdempotencyMiddlewareCoverageTest.php` â€” add `POST /api/v1/vendor/withdrawals` to the assertion that the route is wrapped by `IdempotencyKeyMiddleware` â€” Source: plan.md Â§Idempotency

### 9.3 API documentation regeneration

- [X] **T705** Run `php artisan scribe:generate` to regenerate API docs after all 5 Settlement endpoints are documented (T210â€“T212, T310â€“T312) â€” Source: plan.md Â§API Documentation Plan

### 9.4 Quality gates (must all pass before phase done)

- [X] **T706** Run `./vendor/bin/pint` â€” auto-format all new files in `app/Modules/Settlement/`, `tests/Feature/Modules/Settlement/`, `tests/Unit/Modules/Settlement/`
- [X] **T707** Run `./vendor/bin/phpstan analyse` â€” zero new errors
- [X] **T708** Run `./vendor/bin/pest --bail` â€” entire suite green (Settlement + arch + existing modules)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** â€” no dependencies; T001 (ADR) blocks T005â€“T010 (migrations)
- **Phase 2 (Foundational)** â€” depends on Phase 1; sub-section ordering: 2.1 migrations â†’ 2.2 enums â†’ 2.3 value objects â†’ 2.4 exceptions â†’ 2.5 models â†’ 2.6 factories â†’ 2.7â€“2.8 contracts/DTOs â†’ 2.9 events â†’ 2.10 repositories â†’ 2.11 cross-module readers â†’ 2.12 ServiceProvider wiring â†’ 2.13 translations â†’ 2.14 seeder
- **Phase 3 (US1)** â€” depends on Phase 2 + existing Payments `PaymentCaptured` event (Phase 4.0/4.1)
- **Phase 4 (US2)** â€” depends on Phase 2 (US2 reads `wallet_ledger` populated by US1, but US2 itself can be tested with seeded ledger entries â€” independently testable)
- **Phase 5 (US3)** â€” depends on Phase 2 (uses `Wallet`, `Withdrawal`); independently testable with seeded balance
- **Phase 6 (US4)** â€” depends on Phase 5 (admin acts on a `pending` withdrawal created by US3); independently testable by seeding a pending withdrawal directly
- **Phase 7 (US5)** â€” depends on Phase 3 (reverses what US1 created); independently testable by seeding a `Commission` row + dispatching `RefundCompleted`
- **Phase 8 (US6)** â€” depends on Phase 2 (`CommissionRate` model); independently testable
- **Phase 9 (Polish)** â€” depends on all user stories complete

### Within each phase

- Setup â†’ Foundational â†’ User Story â†’ tests (interleaved per story, not deferred to Phase 9) â†’ Polish

### Parallel opportunities

`[P]` tasks within a phase can run in parallel:
- All Phase 2 enums (T011â€“T013), value objects (T014), exceptions (T015â€“T017), models (T018â€“T023), factories (T024â€“T029), contracts (T030â€“T032), DTOs (T033â€“T036), events (T037â€“T043), repositories (T044â€“T046), translations (T053â€“T054) are mutually independent
- Within each user story, tests (Pest files) are `[P]` â€” different test files
- API documentation tasks (api-registry, Bruno, Postman) per story are `[P]` â€” different files
- Architecture tests in Phase 9 (T701â€“T704) are `[P]` â€” different test files

---

## Implementation Strategy

### MVP First (US1 only â€” get commission flow shipping)

Deliver Phases 1, 2, and 3 only. This proves:
- Module scaffold + ServiceProvider registration (Phase 1)
- All 6 migrations + models + enums + DTOs + cross-module contracts (Phase 2)
- Commission calculation + wallet credit listener with all 4 fallback levels (Phase 3)

After MVP: vendors can't see balances yet (US2), can't withdraw (US3, US4), no refund reversal (US5), no admin rate management (US6). But the platform is recording commissions correctly â€” financial integrity is preserved.

### Incremental Delivery (after MVP)

Order matches priority: US2 â†’ US3 â†’ US4 â†’ US5 â†’ US6 â†’ Polish.

Each story is shippable on its own once foundational + earlier-priority dependencies are met. A vendor without US2 can still earn commissions; they just can't see their balance until US2 ships.

### Cut-list contingency (if Phase 4.2 slips past 3 days)

Per `09_Phasing_Plan.md` cut-list:
- Defer US6 (CommissionRulesResource) to Phase 1.5 â€” admin can edit `commission_rates` via direct DB or seeder until then
- Defer `WalletLedgerViewerResource` (in T403) â€” admin can read ledger via tinker until Phase 1.5
- Defer T705 (`scribe:generate`) and treat T210â€“T212 / T310â€“T312 as authoritative â€” Scribe is nice-to-have

US1â€“US5 are non-negotiable for Settlement to work end-to-end.

---

## Summary

| Metric | Count |
|---|---|
| Phases | 9 (Setup + Foundational + 6 user stories + Polish) |
| Total tasks | 114 |
| Setup tasks | 4 (T001â€“T004) |
| Foundational tasks | 51 (T005â€“T055) |
| US1 tasks | 9 (T100â€“T113) |
| US2 tasks | 11 (T200â€“T221) |
| US3 tasks | 12 (T300â€“T322) |
| US4 tasks | 8 (T400â€“T412) |
| US5 tasks | 7 (T500â€“T513) |
| US6 tasks | 3 (T600â€“T610) |
| Polish tasks | 9 (T700â€“T708) |
| Migrations | 6 (T005â€“T010) |
| Models | 6 (T018â€“T023) |
| Actions | 7 (T100â€“T102, T300â€“T301, T400â€“T401, T500) |
| Listeners | 2 (T103, T501) |
| API endpoints | 5 (each fully documented per plan Â§API Documentation Plan) |
| Filament resources | 3 (T402, T403, T600) |
| Pest test files | 13 (10 Feature + 3 Unit) |
| Architecture tests | 4 (3 extended + 1 new) |
| Parallel-safe tasks `[P]` | 60+ |

---

## Format Validation

âœ… All tasks follow `- [X] **TID** [P?] [Story?] Description with file path â€” Source: X` format
âœ… Setup phase tasks: no `[Story]` label
âœ… Foundational phase tasks: no `[Story]` label
âœ… User Story phase tasks: every task has `[USx]` label matching the phase
âœ… Polish phase tasks: no `[Story]` label
âœ… Every task includes a file path in the description
âœ… Every task includes a Source citation (PRD FR / Schema Â§ / ADR Â§ / plan Â§  / data-model Â§ / spec Â§ / `.claude/rules/*.md`)
âœ… `[P]` markers only on tasks that touch different files with no dependency on other incomplete tasks in the same phase

---

## Self-Checks (per autonomous-loop STAGE 3 expectations)

| Check | Result |
|---|---|
| Every task traces to FR / Schema / ADR / plan / data-model / spec / rule | âœ… â€” every task has a `Source:` citation |
| No Phase 2 features (subscription tiers, dispute engine, auto-payout, advanced tax) | âœ… â€” all explicitly cut per `09_Phasing_Plan.md` |
| Every API endpoint has documentation tasks (`@bodyParam`, `@response`, registry, Bruno, Postman) | âœ… â€” `@bodyParam`/`@response` are part of the implementation tasks (T201, T202, T205, T302, T303); registry/Bruno/Postman are dedicated tasks (T210â€“T212, T310â€“T312) |
| Layer order respected (ADR â†’ migrations â†’ models â†’ requests â†’ actions â†’ API â†’ Filament â†’ listeners â†’ tests) | âœ… â€” Phase 1 ADR + setup; Phase 2 migrations â†’ enums â†’ models â†’ factories â†’ contracts/DTOs â†’ events â†’ repositories â†’ cross-module readers â†’ ServiceProvider wiring â†’ translations â†’ seeder; Phases 3â€“8 add Actions â†’ Resources â†’ Filament â†’ tests interleaved per story |
| All 3 product types covered for type-aware features | âœ… â€” T110 (US1) is parameterized for rental + sale + digital; commission resolution tests across all combinations |
| Idempotency tested where applicable | âœ… â€” T113 (US1, listener), T321 (US3, POST endpoint), T512 (US5, listener) |
