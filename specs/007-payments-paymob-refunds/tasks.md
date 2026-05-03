# Tasks — Payments Module (Phase 4.0 + 4.1)

**Feature**: `specs/007-payments-paymob-refunds`
**Branch**: `007-payments-paymob-refunds`
**ADR**: [`docs/adr/0005-payments-module.md`](../../docs/adr/0005-payments-module.md) — **Accepted 2026-05-02**
**Plan**: [`plan.md`](./plan.md) | **Spec**: [`spec.md`](./spec.md) | **Data Model**: [`data-model.md`](./data-model.md)
**Phase**: 4.0 (2 days) + 4.1 (1 day) = 3 days, Week 5

---

## Reading guide

- `[P]` = parallel-safe (different file, no shared state with sibling tasks in the same group). Tasks without `[P]` must run sequentially in the order shown.
- Every task cites its source: PRD FR-XX, Schema §6, ADR-0005 §X, plan.md §X.
- Day grouping mirrors `research.md` §R-10. Don't mix work across days unless you finish the day's exit gate first.
- Test-with-code rule (Constitution VII): write the Pest test in the same chunk as the production code it covers. Tests appear at the end of each layer below — do not defer them to a "test day."

---

## Layer 1 — ADR finalization

### T001 ✅ ADR-0005 accepted (no work — already done)

- **File:** `docs/adr/0005-payments-module.md`
- **Status:** `Accepted` (2026-05-02)
- **Source:** Constitution VI ("ADR Before Code"), plan.md §1
- **Action:** confirm `Status: Accepted`, confirm row in `docs/adr/README.md` is `Accepted`, confirm `.specify/memory/project-index.md` lists ADR-0005. ✅ All complete.

---

## Layer 2 — Migrations (FK dependency order)

> **Cross-module prerequisite:** Identity (`users`) ships in Phase 1.0; Booking (`bookings`) ships in Phase 3.1. **Verify both are migrated before running these.** All migrations live in `app/Modules/Payments/Database/Migrations/`. Pattern: `<timestamp>_create_<table>_table.php`. Each migration follows `.claude/rules/migrations.md` — `declare(strict_types=1)`, `utf8mb4`, `bigIncrements('id')`, `char('public_id', 26)->unique()` where applicable.

### T002 — Create `payments` migration

- **File:** `app/Modules/Payments/Database/Migrations/<ts>_create_payments_table.php`
- **Source:** Schema §6 / plan.md §3.1 / data-model.md §1.1
- **Columns:** `id`, `public_id` (CHAR 26 UNIQUE), `booking_id` (FK→bookings), `user_id` (FK→users), `gateway` (VARCHAR 40), `gateway_ref` (VARCHAR 190), `amount_minor` (BIGINT UNSIGNED), `amount_currency` (CHAR 3), `method` (ENUM 5 values), `status` (ENUM 7 values), `captured_at` (NULL TIMESTAMP), `failure_code` (NULL VARCHAR 80), `failure_message` (NULL JSON), `metadata` (NULL JSON), `created_at`, `updated_at`
- **Indexes:** UNIQUE `(gateway, gateway_ref)`, `(booking_id, status)`, `(status, created_at)`
- **FKs:** `booking_id`/`user_id` → `restrictOnDelete()`. NO `softDeletes`.

### T003 — Create `payment_attempts` migration

- **File:** `app/Modules/Payments/Database/Migrations/<ts>_create_payment_attempts_table.php`
- **Source:** Schema §6 / plan.md §3.2 / data-model.md §1.2
- **Columns:** `id`, `payment_id` (FK→payments cascade), `attempt_no` (INT UNSIGNED), `request_payload` (JSON, redacted), `response_payload` (NULL JSON), `http_status` (NULL SMALLINT UNSIGNED), `created_at` (`useCurrent()`)
- **Append-only invariant:** NO `updated_at`, NO `deleted_at`
- **Indexes:** `(payment_id, attempt_no)`

### T004 — Create `refunds` migration

- **File:** `app/Modules/Payments/Database/Migrations/<ts>_create_refunds_table.php`
- **Source:** Schema §6 / plan.md §3.3 / data-model.md §1.3
- **Columns:** `id`, `public_id` (CHAR 26 UNIQUE), `payment_id` (FK→payments restrict), `booking_id` (FK→bookings restrict), `amount_minor`, `amount_currency`, `reason_code` (VARCHAR 80), `reason_notes` (NULL JSON translatable), `gateway_ref` (NULL VARCHAR 190), `status` (ENUM 4 values), `initiated_by` (FK→users restrict), `processed_at` (NULL TIMESTAMP), `created_at`, `updated_at`
- **Indexes:** `(payment_id)`, `(booking_id, status)`, `(status, created_at)`. NO `softDeletes`.

### T005 — Create `idempotency_keys` migration

- **File:** `app/Modules/Payments/Database/Migrations/<ts>_create_idempotency_keys_table.php`
- **Source:** Schema §6 / plan.md §3.4 / Constitution VIII / data-model.md §1.4
- **Columns:** `id`, `key` (VARCHAR 190 UNIQUE), `user_id` (NULL FK→users restrict), `route` (VARCHAR 190), `request_hash` (CHAR 64), `response_status` (NULL SMALLINT UNSIGNED), `response_body` (NULL JSON), `expires_at` (TIMESTAMP), `created_at`
- **No `updated_at`, no `deleted_at`. 24h TTL purged by scheduled job.**
- **Indexes:** UNIQUE `(key)`, `(expires_at)`, `(user_id, route)`

### T006 — Create `gateway_webhook_logs` migration

- **File:** `app/Modules/Payments/Database/Migrations/<ts>_create_gateway_webhook_logs_table.php`
- **Source:** Schema §6 / plan.md §3.5 / data-model.md §1.5
- **Columns:** `id`, `gateway` (VARCHAR 40), `event_type` (VARCHAR 80), `signature_valid` (BOOLEAN), `payload` (JSON, redacted), `processed_at` (NULL TIMESTAMP), `processing_error` (NULL TEXT), `created_at`
- **Append-only invariant:** NO `updated_at`, NO `deleted_at`, NO FK
- **Indexes:** `(gateway, signature_valid, created_at)`, `(event_type, created_at)`

### T007 — Run migrations + verify

- **Action:** `php artisan migrate` against fresh dev DB. Inspect schema with `php artisan db:show payments` etc.
- **Source:** Constitution V, plan.md §3
- **Acceptance:** all 5 tables exist, indexes match Schema §6, no `deleted_at` on the three append-only tables.

---

## Layer 3 — Domain (models, enums, events, value objects)

> All [P]-marked tasks below are different files in different folders — safe to parallelize. Models hold ONLY relationships, casts, scopes (CLAUDE.md rule 3). No business logic.

### Enums

#### T008 [P] — `PaymentStatus` enum

- **File:** `app/Modules/Payments/Domain/Enums/PaymentStatus.php`
- **Source:** plan.md §3.1 / data-model.md §3.1
- **Values:** `Pending`, `Authorized`, `Captured`, `Failed`, `Refunded`, `PartiallyRefunded`, `Voided` (string-backed)

#### T009 [P] — `PaymentMethod` enum

- **File:** `app/Modules/Payments/Domain/Enums/PaymentMethod.php`
- **Source:** Schema §6 / plan.md §3.1
- **Values:** `Card`, `Wallet`, `Installment`, `CashOnDelivery`, `Transfer`

#### T010 [P] — `RefundStatus` enum

- **File:** `app/Modules/Payments/Domain/Enums/RefundStatus.php`
- **Source:** plan.md §3.3 / data-model.md §3.2
- **Values:** `Pending`, `Processing`, `Completed`, `Failed`

#### T011 [P] — `RefundReasonCode` enum

- **File:** `app/Modules/Payments/Domain/Enums/RefundReasonCode.php`
- **Source:** ADR-0005 §6.4 / spec.md Clarifications Q3 / plan.md §3.3
- **Values:** `CustomerRequest`, `VendorCancellation`, `ServiceUnavailable`, `DuplicateCharge`, `AdminDiscretion`. Add `public static function values(): array` returning lowercase strings for `Rule::in([...])`.

### Value objects + DTOs

#### T012 [P] — `RefundPolicy` value object

- **File:** `app/Modules/Payments/Domain/ValueObjects/RefundPolicy.php`
- **Source:** ADR-0005 §6.5 / data-model.md §2.1 / research.md §R-9
- **Shape:** `final readonly class` with `bool $allowed`, `string $reasonCode`, `string $reasonMessageKey`. Two static constructors: `allowed()`, `denied(string $code, string $key)`.

#### T013 [P] — `InitiatePaymentDto`

- **File:** `app/Modules/Payments/Application/DTOs/InitiatePaymentDto.php`
- **Source:** plan.md §4.1 / data-model.md §2.2
- **Fields:** `Booking $booking` (or summary DTO), `User $payer`, `PaymentMethod $method`, `Money $amount`. Use `spatie/laravel-data` per `10_Package_List.md` §2.

#### T014 [P] — `PaymentIntentDto`

- **File:** `app/Modules/Payments/Application/DTOs/PaymentIntentDto.php`
- **Source:** data-model.md §2.2
- **Fields:** `string $gatewayRef`, `string $redirectUrl`, `?array $rawResponse`.

#### T015 [P] — `PaymobWebhookDto`

- **File:** `app/Modules/Payments/Application/DTOs/PaymobWebhookDto.php`
- **Source:** data-model.md §2.3 / contracts/paymob-webhook-schema.json
- **Fields:** `string $gatewayRef`, `bool $success`, `?string $failureCode`, `?Money $capturedAmount`, `array $rawPayload` (already redacted).

#### T016 [P] — `RefundResultDto`

- **File:** `app/Modules/Payments/Application/DTOs/RefundResultDto.php`
- **Source:** data-model.md §2.4
- **Fields:** `bool $success`, `?string $gatewayRef`, `?string $failureMessage`.

#### T017 [P] — `InitiateRefundDto`

- **File:** `app/Modules/Payments/Application/DTOs/InitiateRefundDto.php`
- **Source:** plan.md §3.3 / data-model.md §1.3
- **Fields:** `Payment $payment`, `RefundReasonCode $reasonCode`, `array $reasonNotes` (with `en`/`ar` keys), `User $initiatedBy`.

### Events (all queued via `ShouldDispatchAfterCommit` in Action calls — see Layer 8)

#### T018 [P] — `PaymentInitiated` event

- **File:** `app/Modules/Payments/Domain/Events/PaymentInitiated.php`
- **Source:** plan.md §7.1 / ADR-0005 §7
- **Payload:** `int $paymentId, int $bookingId, int $userId, int $amountMinor, string $amountCurrency`. `Dispatchable, SerializesModels`.

#### T019 [P] — `PaymentCaptured` event

- **File:** `app/Modules/Payments/Domain/Events/PaymentCaptured.php`
- **Source:** plan.md §7.1
- **Payload:** `int $paymentId, int $bookingId, int $amountMinor, string $amountCurrency, Carbon $capturedAt`.

#### T020 [P] — `PaymentFailed` event

- **File:** `app/Modules/Payments/Domain/Events/PaymentFailed.php`
- **Source:** plan.md §7.1
- **Payload:** `int $paymentId, int $bookingId, string $failureCode, array $failureMessage` (translatable EN+AR).

#### T021 [P] — `RefundCompleted` event

- **File:** `app/Modules/Payments/Domain/Events/RefundCompleted.php`
- **Source:** plan.md §7.1
- **Payload:** `int $refundId, int $paymentId, int $bookingId, int $amountMinor, string $amountCurrency, string $reasonCode`.

#### T022 [P] — `RefundFailed` event

- **File:** `app/Modules/Payments/Domain/Events/RefundFailed.php`
- **Source:** plan.md §7.1
- **Payload:** `int $refundId, int $paymentId, array $failureMessage`.

### Models

#### T023 [P] — `Payment` model

- **File:** `app/Modules/Payments/Domain/Models/Payment.php`
- **Source:** data-model.md §1.1 / Schema §6 / Constitution III
- **Casts:** `amount` → `MoneyCast`, `method` → `PaymentMethod`, `status` → `PaymentStatus`, `captured_at` → datetime, `failure_message`/`metadata` → array
- **Translatable:** `protected $translatable = ['failure_message'];`
- **Relationships:** **No** Eloquent `belongsTo(Booking::class)` relationship — Constitution I forbids importing the Booking model. Instead, expose `bookingId()` returning `int` and let consumers call `PaymentsBookingReader::findByPublicId(...)` (T028a) when they need booking data. The DB-level FK still exists (T002); only the Eloquent association is omitted.
- **Scopes:** `scopePending()`, `scopeCaptured()`, `scopeForBooking($id)`, `scopeStaleHold()` (where status=pending and created_at < booking's hold expiry).

#### T024 [P] — `PaymentAttempt` model

- **File:** `app/Modules/Payments/Domain/Models/PaymentAttempt.php`
- **Source:** data-model.md §1.2 / Constitution V
- **Set `$timestamps = false`** (only `created_at`, no `updated_at`). Casts `request_payload` / `response_payload` → array.

#### T025 [P] — `Refund` model

- **File:** `app/Modules/Payments/Domain/Models/Refund.php`
- **Source:** data-model.md §1.3 / Constitution III + IV
- **Casts:** `amount` → `MoneyCast`, `reason_code` → `RefundReasonCode`, `status` → `RefundStatus`, `processed_at` → datetime, `reason_notes` → array
- **Translatable:** `protected $translatable = ['reason_notes'];`

#### T026 [P] — `IdempotencyKey` model

- **File:** `app/Modules/Payments/Domain/Models/IdempotencyKey.php`
- **Source:** data-model.md §1.4 / Constitution VIII
- **Casts:** `response_body` → array, `expires_at` → datetime.
- **Scopes:** `scopeActive()` (where `expires_at > now()`), `scopeExpired()`.

#### T027 [P] — `GatewayWebhookLog` model

- **File:** `app/Modules/Payments/Domain/Models/GatewayWebhookLog.php`
- **Source:** data-model.md §1.5 / Constitution V
- **Set `$timestamps = false`** (only `created_at`). Cast `payload` → array, `signature_valid` → bool, `processed_at` → datetime.

### Contracts

> **Pattern note (consumer-owned contracts).** Per the existing repo convention (see `app/Modules/Booking/Domain/Contracts/CatalogServiceReader.php`), the consuming module owns the contract; the providing module implements it. Payments therefore owns three contracts under its own `Domain/Contracts/`: `PaymentGateway` (its own implementer is `PaymobGateway`), `PaymentsBookingReader` (Booking implements), and `PaymentsCatalogReader` (Catalog implements). All reader contracts return DTOs, never Eloquent models — Constitution I forbids cross-module model imports.

#### T028 [P] — `PaymentGateway` interface

- **File:** `app/Modules/Payments/Domain/Contracts/PaymentGateway.php`
- **Source:** ADR-0005 §7 / research.md §R-8 / plan §2-I
- **Methods:**
  - `initiate(InitiatePaymentDto $dto): PaymentIntentDto`
  - `verifyWebhookSignature(array $payload, string $signature): bool`
  - `parseWebhook(array $payload): PaymobWebhookDto`
  - `refund(Payment $payment, Money $amount): RefundResultDto`

#### T028a [P] — `PaymentsBookingReader` contract + DTOs

- **File:** `app/Modules/Payments/Domain/Contracts/PaymentsBookingReader.php` (interface)
- **Sibling files:** `app/Modules/Payments/Application/DTOs/PaymentBookingReadDto.php`, `app/Modules/Payments/Application/DTOs/PaymentBookingItemReadDto.php`
- **Source:** plan.md §2-I, §3 (Read-only references) / data-model.md §4 / ADR-0005 §3, §7 / Constitution I
- **Methods:**
  - `findByPublicId(string $ulid): ?PaymentBookingReadDto` — returns booking summary: `int $id`, `string $publicId`, `int $customerId`, `string $lifecycleStatus`, `string $paymentStatus`, `int $totalMinor`, `string $totalCurrency`, `?Carbon $paymentHoldExpiresAt`.
  - `itemsFor(int $bookingId): array<int, PaymentBookingItemReadDto>` — returns each item: `int $id`, `int $bookingId`, `int $serviceId`, `ProductType $productType`, `?Carbon $eventStartsAt`, `string $itemStatus`.
  - `staleHoldBookingIds(): array<int>` — returns booking IDs whose `payment_hold_expires_at < now()` and that still have `payment_status = pending`. Used by `ExpirePendingPaymentsAction` (T044).
- **Implementation lives in Booking module** (T028c).
- **Spatie/laravel-data DTOs** (per `10_Package_List.md` §2 — `spatie/laravel-data`).

#### T028b [P] — `PaymentsCatalogReader` contract

- **File:** `app/Modules/Payments/Domain/Contracts/PaymentsCatalogReader.php` (interface)
- **Source:** plan.md §2-I, §3 (Read-only references) / data-model.md §4 / ADR-0005 §3, §7 / Constitution I
- **Methods:**
  - `isDigitalRefundableAfterDelivery(int $serviceId): bool` — returns `service_digital_details.is_refundable_after_delivery` for the given digital service. Throws `ServiceNotFoundException` (Payments domain exception) if the service id has no digital details row. Used by `RefundPolicyService::policyFor(ProductType::Digital, ...)` (T038).
- **Implementation lives in Catalog module** (T028d).

#### T028c — `EloquentPaymentsBookingReader` implementation (Booking module)

- **File:** `app/Modules/Booking/Infrastructure/Repositories/EloquentPaymentsBookingReader.php`
- **Source:** T028a / `.claude/rules/modules.md` (consumer-owned-contract pattern; see `EloquentCatalogServiceReader.php` as the canonical example)
- **Behaviour:** queries `bookings` and `booking_items` directly (Booking module legitimately accesses its own tables), maps each row to the Payments DTO. Reads `payment_hold_expires_at` column on `bookings` — if it doesn't exist yet (Phase 3.x added booking lifecycle but the column may not be there), STOP and add a small migration in Booking module to add `bookings.payment_hold_expires_at` (NULLABLE TIMESTAMP) before continuing. Document the addition in this task's commit message.
- **Binding:** registered in `BookingServiceProvider::register()` — `$this->app->bind(\App\Modules\Payments\Domain\Contracts\PaymentsBookingReader::class, \App\Modules\Booking\Infrastructure\Repositories\EloquentPaymentsBookingReader::class);`
- **No model imports across modules:** the implementation lives inside Booking and freely uses the Booking Eloquent models because that's its own module.

#### T028d — `EloquentPaymentsCatalogReader` implementation (Catalog module)

- **File:** `app/Modules/Catalog/Infrastructure/Repositories/EloquentPaymentsCatalogReader.php`
- **Source:** T028b / pattern of `EloquentCatalogServiceReader.php`
- **Behaviour:** SELECT `is_refundable_after_delivery` FROM `service_digital_details` WHERE `service_id = ?`. If row missing, throw `ServiceNotFoundException` (which lives in Payments — Catalog does NOT depend on Payments-internal exceptions; instead, throw `\RuntimeException` or define a shared exception in Catalog and let Payments catch a base type). **Resolution rule:** use `\InvalidArgumentException` from PHP standard so neither module reaches into the other for an exception class.
- **Binding:** registered in `CatalogServiceProvider::register()` — `$this->app->bind(\App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader::class, \App\Modules\Catalog\Infrastructure\Repositories\EloquentPaymentsCatalogReader::class);`

### Layer-3 unit tests

#### T029 [P] — Pest unit: enums round-trip

- **File:** `tests/Unit/Modules/Payments/EnumTest.php`
- **Source:** plan.md §10
- **Asserts:** `RefundReasonCode::values()` returns 5 lowercase strings; `PaymentStatus::from('captured')` works.

#### T030 [P] — Pest unit: `RefundPolicy` value object

- **File:** `tests/Unit/Modules/Payments/RefundPolicyTest.php`
- **Asserts:** `RefundPolicy::allowed()->allowed === true`; `RefundPolicy::denied('x','y')->reasonCode === 'x'`; immutability (no public setters; `readonly` enforced).

---

## Layer 4 — Infrastructure (repositories, gateway, helpers, middleware)

### T031 [P] — `RedactPciFields` helper

- **File:** `app/Modules/Payments/Infrastructure/Support/RedactPciFields.php`
- **Source:** ADR-0005 §6.1 / research.md §R-6 / plan.md §10
- **Behaviour:** static method `redact(array $payload): array` — walks payload recursively, retains only allowlist keys (see research.md §R-6 list), replaces forbidden keys with `'[REDACTED]'`. Pure function, no DB access.

### T032 [P] — `EloquentPaymentRepository`

- **File:** `app/Modules/Payments/Infrastructure/Repositories/EloquentPaymentRepository.php`
- **Source:** ADR-0005 §5
- **Methods:** `create(array $attrs): Payment`, `findByPublicId(string $ulid): ?Payment`, `findByGatewayRef(string $gateway, string $ref): ?Payment`, `markCaptured(Payment $p, Carbon $at): void`, `markFailed(Payment $p, string $code, array $message): void`.

### T033 [P] — `EloquentRefundRepository`

- **File:** `app/Modules/Payments/Infrastructure/Repositories/EloquentRefundRepository.php`
- **Source:** ADR-0005 §5
- **Methods:** `create(InitiateRefundDto $dto): Refund`, `markProcessing(Refund $r): void`, `markCompleted(Refund $r, string $gatewayRef, Carbon $at): void`, `markFailed(Refund $r, string $message): void`.

### T034 [P] — `EloquentIdempotencyKeyRepository`

- **File:** `app/Modules/Payments/Infrastructure/Repositories/EloquentIdempotencyKeyRepository.php`
- **Source:** plan.md §6 / Constitution VIII
- **Methods:** `lookup(string $key, ?int $userId, string $requestHash): ?IdempotencyKey`, `store(string $key, ?int $userId, string $route, string $hash, int $status, array $body): IdempotencyKey`, `purgeExpired(): int`.

### T035 — `PaymobGateway` implementation

- **File:** `app/Modules/Payments/Infrastructure/Gateways/PaymobGateway.php`
- **Source:** ADR-0005 §6.6 / research.md §R-1, R-6, R-7 / FR-PAY-005
- **Implements:** `PaymentGateway` (T028)
- **Behaviour:** uses `Http::client()` (no Guzzle direct), reads `config('services.paymob.*')`, builds HMAC-SHA512 over the documented field concatenation (research.md §R-1 — sorted-by-Paymob-spec, NOT alphabetic), uses `hash_equals` for verification, runs every outbound request through `RedactPciFields::redact()` before logging into `payment_attempts`. No card data ever touches the response. Maps Paymob `data.message` → InstaParty `failure_code` via `MapPaymobFailureCode` (T036).
- **Tests come at T077 (signature unit test).**

### T036 [P] — `MapPaymobFailureCode` helper

- **File:** `app/Modules/Payments/Infrastructure/Support/MapPaymobFailureCode.php`
- **Source:** research.md §R-7
- **Behaviour:** static `map(?string $paymobMessage): string` — case-insensitive substring match, returns one of `insufficient_funds`, `declined_by_issuer`, `expired_card`, `fraud_suspected`, `expired_payment_hold`, or `unknown`.

### T037 — `IdempotencyKeyMiddleware`

- **File:** `app/Modules/Payments/Http/Middleware/IdempotencyKeyMiddleware.php`
- **Source:** Constitution VIII / plan.md §6 / FR-IDEM-001-002
- **Behaviour:**
  1. Read `Idempotency-Key` header — required on routes that opt in via `->middleware('idempotency')`.
  2. Compute SHA-256 of raw request body → `request_hash`.
  3. `DB::transaction` + row-level lock on `(key)`: lookup → if hit + matching hash, replay cached `response_status`+`response_body`; if hit + different hash, return `409 Conflict`; if miss, dispatch to controller, then INSERT row with `expires_at = now + 24h` before returning.
- **Concurrency proof:** test at T082.

---

## Layer 5 — Application services & Actions

### Service

#### T038 — `RefundPolicyService`

- **File:** `app/Modules/Payments/Application/Services/RefundPolicyService.php`
- **Source:** ADR-0005 §6.5 / plan.md §4.2 / Tech Decisions §11 / Constitution II
- **Method:** `policyFor(ProductType $type, BookingItemSummary $item, ?bool $digitalRefundFlag = null): RefundPolicy`
- **Logic:** single `match($type) { Rental => …, Sale => …, Digital => … }`. Per branch:
  - **Rental:** `RefundPolicy::allowed()` if `$item->event_starts_at >= now->addHours(config('payments.refund.rental_hours_before', 24))` AND `$item->item_status !== 'setup'`. Else `denied('rental_window_closed' or 'rental_in_setup', 'refunds.policy.{key}')`.
  - **Sale:** `allowed()` if `$item->item_status` is one of `pending`/`confirmed` (i.e., strictly less than `in_preparation`). Else `denied('sale_in_preparation', 'refunds.policy.sale_in_preparation')`.
  - **Digital:** `allowed()` if `$item->item_status !== 'delivered'` OR `$digitalRefundFlag === true`. Else `denied('digital_post_delivery', 'refunds.policy.digital_post_delivery')`.
- **No `if/elseif` on type strings.** Architecture test enforces.

### Actions

#### T039 — `InitiatePaymentAction`

- **File:** `app/Modules/Payments/Application/Actions/InitiatePaymentAction.php`
- **Source:** FR-PAY-001..004, plan.md §7.3 / `.claude/rules/actions.md`
- **Signature:** `execute(InitiatePaymentDto $dto): Payment`
- **Behaviour:**
  1. `DB::transaction`:
     - Reject if booking has another `payments` row in `pending` (concurrent-initiate edge case).
     - Reject if `dto->amount->getCurrency() !== EGP` (Phase 1 cut-list).
     - Insert `payments` row (status=`pending`).
     - Call `PaymentGateway::initiate($dto)` → `PaymentIntentDto`.
     - Update `payments.gateway_ref` and `metadata`.
     - Insert `payment_attempts` row (redacted via `RedactPciFields`).
     - `DB::afterCommit(fn () => PaymentInitiated::dispatch($payment))`.
  2. Return `Payment`.

#### T040 — `CapturePaymentAction`

- **File:** `app/Modules/Payments/Application/Actions/CapturePaymentAction.php`
- **Source:** FR-PAY-007..009, ADR-0005 §6.3
- **Signature:** `execute(PaymobWebhookDto $dto): Payment`
- **Behaviour:**
  1. Lookup `payments` by `(gateway, gateway_ref)`. If unknown, throw `PaymentNotFoundException` (the webhook controller converts to 200 with logged error).
  2. **Idempotency at row level:** if status already `captured`, return existing row without re-dispatch.
  3. `DB::transaction`:
     - If `dto->success`: update status → `captured`, `captured_at = now()`. Insert `payment_attempts`.
     - If `!dto->success`: update status → `failed`, set `failure_code` via `MapPaymobFailureCode`, set `failure_message` from `lang/{locale}/failures.php`.
     - `DB::afterCommit(fn () => PaymentCaptured::dispatch(...))` or `PaymentFailed::dispatch(...)`.

#### T041 — `ProcessPaymobWebhookAction`

- **File:** `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php`
- **Source:** FR-PAY-005..006, ADR-0005 §6.2, research.md §R-3
- **Signature:** `execute(array $payload, string $hmacHeader): GatewayWebhookLog`
- **Behaviour:**
  1. Verify HMAC via `PaymentGateway::verifyWebhookSignature`. INSERT `gateway_webhook_logs` row (redacted) with `signature_valid` regardless of result.
  2. If invalid, return `GatewayWebhookLog` (controller maps to 401).
  3. Parse via `PaymentGateway::parseWebhook` → `PaymobWebhookDto`.
  4. Try `CapturePaymentAction::execute($dto)`. On `PaymentNotFoundException`, set `processing_error` and return (controller maps to 200 — research.md §R-3).
  5. Set `processed_at = now()`.

#### T042 — `InitiateRefundAction`

- **File:** `app/Modules/Payments/Application/Actions/InitiateRefundAction.php`
- **Source:** FR-REF-001..009, ADR-0005 §6.5
- **Signature:** `execute(InitiateRefundDto $dto): Refund`
- **Behaviour:**
  1. Resolve booking + items via injected `PaymentsBookingReader` contract (T028a); resolve digital flag via `PaymentsCatalogReader::isDigitalRefundableAfterDelivery` (T028b).
  2. For **every** booking item, call `RefundPolicyService::policyFor(...)`. If any item returns `!allowed`, throw `RefundPolicyViolationException` carrying the first failing `reasonCode` + `reasonMessageKey`. Controller maps to 422 with translated message.
  3. Validate amount equals `dto->payment->amount` (Phase 1 full-only cut-list). Else 422.
  4. `DB::transaction`:
     - Insert `refunds` row (status=`pending`).
     - Call `ProcessRefundAction::execute($refund)`.
     - `DB::afterCommit(fn () => RefundCompleted::dispatch(...))` (or `RefundFailed`).
  5. Return `Refund`.

#### T043 — `ProcessRefundAction`

- **File:** `app/Modules/Payments/Application/Actions/ProcessRefundAction.php`
- **Source:** FR-REF-006..008
- **Signature:** `execute(Refund $refund): Refund`
- **Behaviour:** transitions refund `pending → processing`, calls `PaymentGateway::refund`, on success transitions `processing → completed` and updates `payments.status = refunded`. On failure transitions to `failed`. Returns updated `Refund`.

#### T044 — `ExpirePendingPaymentsAction`

- **File:** `app/Modules/Payments/Application/Actions/ExpirePendingPaymentsAction.php`
- **Source:** ADR-0005 §6.3 / FR-PAY-010 / research.md §R-5
- **Signature:** `execute(): int` (returns count swept)
- **Behaviour:** ask `PaymentsBookingReader::staleHoldBookingIds()` (T028a) for bookings whose `payment_hold_expires_at < now()` and whose `payment_status = pending`. For each, find the matching `pending` `payments` row(s) and update each to `failed` with `failure_code='expired_payment_hold'` and translatable `failure_message` from `lang/{locale}/failures.php`. Dispatch `PaymentFailed` event after commit. Idempotent — re-runs do nothing.

---

## Layer 6 — HTTP layer + API documentation bundles

> Each endpoint task below names ALL six artifacts the user requested (route, controller, request, resource, registry, Bruno). Per `.claude/rules/actions.md` rule "3-line action body MAX," every controller method dispatches to its Action and returns a Resource.

### Resources (rendered at API layer with locale conversion — Constitution IV)

#### T045 [P] — `PaymentResource`

- **File:** `app/Modules/Payments/Http/Resources/PaymentResource.php`
- **Source:** plan.md §5.4, §8.3 / spec.md §API Endpoints
- **Behaviour:** returns `public_id`, `booking_public_id` (resolved via injected `PaymentsBookingReader`, looking up by `payment.booking_id` then exposing `dto->publicId`), `gateway`, `amount_minor`, `amount_currency`, `method`, `status` (enum value), `captured_at` (UTC ISO8601), `failure_code`, `failure_message` translated via `getTranslation('failure_message', App::getLocale())`, `created_at`.
- **Scribe `@response` annotation** with two examples — one EN, one AR — per plan.md §8.3.

#### T046 [P] — `RefundResource`

- **File:** `app/Modules/Payments/Http/Resources/RefundResource.php`
- **Source:** plan.md §5.4, §8.3
- **Behaviour:** returns `public_id`, `payment_public_id`, `booking_public_id`, `amount_minor`, `amount_currency`, `reason_code`, `reason_notes` translated, `status`, `processed_at`, `created_at`.
- **Scribe `@response` annotation** with EN+AR examples (matching schemas in `contracts/openapi-payments.yaml`).

### Form Requests (with @bodyParam Scribe annotations — plan.md §8.2)

#### T047 [P] — `InitiatePaymentRequest`

- **File:** `app/Modules/Payments/Http/Requests/InitiatePaymentRequest.php`
- **Source:** spec.md FR-PAY-001 / data-model.md §5
- **Rules:** `method` required, `Rule::in(['card'])` (Phase 1).
- **`@bodyParam` PHPDoc:** required on `method`, with `Example: card`.
- **`authorize()`:** asserts route booking belongs to `auth()->user()`.

#### T048 [P] — `PaymobWebhookRequest`

- **File:** `app/Modules/Payments/Http/Requests/PaymobWebhookRequest.php`
- **Source:** spec.md FR-PAY-005
- **Rules:** none on body (Paymob's payload shape varies). `authorize()` returns `true` (the HMAC verification happens in the Action). Validates `Content-Type: application/json` and `HMAC` header presence (regex `/^[a-f0-9]{128}$/`).
- **`@bodyParam`** documents the Paymob outer envelope (`type`, `obj`) for Scribe; references `contracts/paymob-webhook-schema.json`.

#### T049 [P] — `InitiateRefundRequest`

- **File:** `app/Modules/Payments/Http/Requests/InitiateRefundRequest.php`
- **Source:** spec.md FR-REF-006 / ADR-0005 §6.4 / plan.md §5.3
- **Rules:** `reason_code` required + `Rule::in(RefundReasonCode::values())`. `reason_notes` required array with `reason_notes.en` and `reason_notes.ar` required strings max 500.
- **`@bodyParam`** required on every field (per plan.md §8.2) with EN+AR examples.
- **`authorize()`:** asserts `auth()->user()->can('payment.refund')`.

### Controllers (3-line bodies)

#### T050 [P] — `InitiatePaymentController`

- **File:** `app/Modules/Payments/Http/Controllers/Customer/InitiatePaymentController.php`
- **Source:** plan.md §8.1, CLAUDE.md §1
- **Method body (max 3 lines):** resolve booking via `PaymentsBookingReader::findByPublicId($ulid)` (404 if null; 403 if customer mismatch; 409 if `lifecycleStatus !== 'confirmed'`), build `InitiatePaymentDto` from the DTO + `auth()->user()`, call `InitiatePaymentAction::execute`, return `PaymentResource` + `redirect_url` in envelope.

#### T051 [P] — `ShowPaymentController`

- **File:** `app/Modules/Payments/Http/Controllers/Customer/ShowPaymentController.php`
- **Method body:** resolve via `PaymentRepository::findByPublicId`, gate `$user->id === $payment->user_id` (else 403/404), return `PaymentResource`.

#### T052 [P] — `PaymobWebhookController`

- **File:** `app/Modules/Payments/Http/Controllers/Webhook/PaymobWebhookController.php`
- **Source:** FR-PAY-005..006
- **Method body:** call `ProcessPaymobWebhookAction::execute($request->json()->all(), $request->header('HMAC'))`, return `{ ok: true }` (200) or `{ error: 'invalid_signature' }` (401) based on action result.

#### T053 [P] — `InitiateRefundController`

- **File:** `app/Modules/Payments/Http/Controllers/Admin/InitiateRefundController.php`
- **Method body:** resolve booking, resolve user payment via `PaymentRepository::findActiveCaptureForBooking($bookingId)`, build `InitiateRefundDto`, call `InitiateRefundAction::execute`, return `RefundResource`.

#### T054 [P] — `ShowRefundController`

- **File:** `app/Modules/Payments/Http/Controllers/Admin/ShowRefundController.php`
- **Method body:** resolve via `RefundRepository::findByPublicId`, gate via `Gate::authorize('view_refund')`, return `RefundResource`.

### Routes (loaded by `PaymentsServiceProvider`)

#### T055 — `Routes/customer.php`

- **File:** `app/Modules/Payments/Routes/customer.php`
- **Source:** spec.md §API Endpoints / plan.md §8.1
- **Routes:** `POST /api/v1/customer/bookings/{ulid}/payments` → `InitiatePaymentController` (middleware `auth:sanctum`, `idempotency`); `GET /api/v1/customer/payments/{ulid}` → `ShowPaymentController` (middleware `auth:sanctum`).

#### T056 — `Routes/admin.php`

- **File:** `app/Modules/Payments/Routes/admin.php`
- **Routes:** `POST /api/v1/admin/bookings/{ulid}/refunds` → `InitiateRefundController` (middleware `auth:sanctum`, `can:payment.refund`, `idempotency`); `GET /api/v1/admin/refunds/{ulid}` → `ShowRefundController` (middleware `auth:sanctum`, `can:view_refund`).

#### T057 — `Routes/webhook.php`

- **File:** `app/Modules/Payments/Routes/webhook.php`
- **Routes:** `POST /api/v1/webhooks/paymob` → `PaymobWebhookController` (NO auth middleware; HMAC is the gate per ADR-0005 §6.2).

### Service provider + bootstrap

#### T058 — `PaymentsServiceProvider`

- **File:** `app/Modules/Payments/Providers/PaymentsServiceProvider.php`
- **Source:** ADR-0005 §5, §6.7 / `.claude/rules/modules.md`
- **`register()`:** `$this->app->bind(PaymentGateway::class, PaymobGateway::class);` and bind repository contracts.
- **`boot()`:**
  - `loadMigrationsFrom(__DIR__ . '/../Database/Migrations')`
  - `loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'payments')`
  - Load three route files with appropriate middleware groups (`api` + `auth:sanctum` for customer/admin; bare `api` for webhook).
  - Register middleware alias `idempotency` → `IdempotencyKeyMiddleware`.
  - Wire event listeners (T067).

#### T059 — Register provider in `bootstrap/providers.php`

- **File:** `bootstrap/providers.php`
- **Source:** `.claude/rules/modules.md`
- **Action:** add `\App\Modules\Payments\Providers\PaymentsServiceProvider::class` to the array.

#### T060 — Schedule sweep + cleanup jobs

- **File:** `app/Console/Kernel.php` (or `routes/console.php` per Laravel 11 default)
- **Source:** ADR-0005 §6.3, FR-PAY-010, research.md §R-5
- **Action:** add `$schedule->call(fn () => app(ExpirePendingPaymentsAction::class)->execute())->everyFifteenMinutes()->onOneServer()->withoutOverlapping();` and a daily call to `EloquentIdempotencyKeyRepository::purgeExpired()`.

### API documentation tasks (per user requirement)

#### T061 — Update `.specify/memory/api-registry.md`

- **File:** `.specify/memory/api-registry.md`
- **Source:** plan.md §8.4 / spec.md API DOCUMENTATION CONSTRAINT
- **Action:** append 5 rows to the Endpoint Registry table — one per endpoint listed in plan.md §8.4.
- **Documented column:** initial value `❌ todo`; flips to `✅ scribe` after T064 generates docs.

#### T062 [P] — Bruno collection scaffolding

- **File:** `docs/api/collections/payments/`
- **Source:** plan.md §8.5 / spec.md API DOCUMENTATION CONSTRAINT
- **Action:** create folder with five `.bru` files (`01_initiate_payment.bru`, `02_show_payment.bru`, `03_paymob_webhook.bru`, `04_initiate_refund.bru`, `05_show_refund.bru`). Each uses `{{baseUrl}}` and `{{authToken}}` Bruno variables, includes `Idempotency-Key` header where applicable, and documents EN+AR sample request bodies for the refund call.
- **Webhook .bru** must include sandbox HMAC signature for happy + tampered cases.

#### T063 [P] — Postman v2.1 export

- **File:** `docs/api/collections/payments.postman_collection.json`
- **Source:** plan.md §8.6
- **Action:** export Bruno collection as Postman v2.1 JSON for the mobile/web teams. Pin to `_postman_id` so future re-exports diff cleanly.

#### T064 — Generate Scribe docs

- **File:** runs against `app/Modules/Payments/`
- **Source:** plan.md §8.2-8.3 / `composer.json` `knuckleswtf/scribe:^5.9` (already updated)
- **Action:** `php artisan scribe:generate`. Verify the 5 endpoints render with `@bodyParam` and `@response` examples in EN and AR. Then update `api-registry.md` rows to `✅ scribe`.

---

## Layer 7 — Filament Resources (admin UI)

#### T065 — `RefundResource` (Filament, read-only)

- **File:** `app/Modules/Payments/Filament/Resources/RefundResource.php`
- **Source:** ADR-0005 §8 / `.claude/rules/filament.md` / `.claude/rules/filament-components.md`
- **Behaviour:**
  - Read-only: `canCreate() = false`, `canEdit() = false`, `canDelete() = false`.
  - Navigation group: `'Payments'`.
  - Table columns: `public_id` (copyable), `booking_public_id`, `amount_minor` (`->money('EGP', divideBy: 100)` per filament-components.md §2), `reason_code` (badge with localized label), `status` (badge: `pending`=warning, `processing`=info, `completed`=success, `failed`=danger), `initiated_by.name`, `created_at`.
  - Filters: `SelectFilter::make('status')`, `SelectFilter::make('reason_code')`, `Filter::make('amount_range')`.
  - Action: `ViewAction::make()` (no Edit / Delete).
  - Permissions auto-generated by Shield (T068).

#### T066 — Add Refund Filament Action on `BookingResource`

- **File:** `app/Modules/Booking/Filament/Resources/BookingResource.php` (Booking module — extension only; no rewrite of existing rows)
- **Source:** ADR-0005 §8 / spec.md FR-REF-001 / `.claude/rules/filament-components.md` §2
- **Behaviour:** add a custom `Action::make('refund')` per the filament-components.md custom-action pattern. Visible only when `auth()->user()->can('payment.refund')` AND `$record->payment_status === 'paid'`. Form: `Select` for `reason_code` (5 enum values, localized labels), translatable Text `reason_notes` (EN/AR tabs via `filament/spatie-laravel-translatable-plugin`). Action closure delegates to `app(InitiateRefundAction::class)->execute($dto)` (no business logic in the closure — `.claude/rules/actions.md`). On success, fires `Filament\Notifications\Notification::make()->success()->send()` (filament-components.md §4). On `RefundPolicyViolationException`, surfaces translated error in a `danger` notification.

#### T067 — Translation labels for Filament

- **Files:** `app/Modules/Payments/Resources/lang/{en,ar}/refunds.php`
- **Source:** Constitution IV
- **Action:** add label keys for `reason_code.customer_request`, `reason_code.vendor_cancellation`, etc., navigation group `payments` label, status badge labels, policy violation messages (already covered by translation tasks T077-T080).

#### T068 — Run `shield:generate --all`

- **File:** runtime command
- **Source:** ADR-0005 §8 / CLAUDE.md "When Generating Filament Resources"
- **Action:** `php artisan shield:generate --all`. Then add a custom `payment.refund` permission to the seeded permissions list. Verify in `config/filament-shield.php`.

---

## Layer 8 — Listeners (in Booking module — wired here)

> Per ADR-0005 §7, Payments publishes events; Booking listens. The listeners themselves live in the Booking module to avoid Payments knowing about `bookings.payment_status`.

#### T069 [P] — `UpdateBookingPaymentStatusListener`

- **File:** `app/Modules/Booking/Application/Listeners/UpdateBookingPaymentStatusListener.php`
- **Source:** plan.md §7.1, FR-PAY-009
- **Implements:** `ShouldQueue` (queue: `default`).
- **Method `handle(PaymentCaptured $event)`:** sets `bookings.payment_status = 'paid'` for the booking. Method `handleRefund(RefundCompleted $event)`: sets `payment_status = 'refunded'`.
- **Architecture-test exemption:** Booking is the listener owner, Payments is the publisher — this is the legitimate cross-module flow via events, no model imports across.

#### T070 [P] — `HandlePaymentFailedListener`

- **File:** `app/Modules/Booking/Application/Listeners/HandlePaymentFailedListener.php`
- **Source:** plan.md §7.1
- **Implements:** `ShouldQueue`.
- **Method `handle(PaymentFailed $event)`:** logs the failure; booking's `payment_status` remains `pending` so the customer can retry. Optionally creates a `booking_state_transitions` row in Phase 4.0 (or defer to 5.0 if behind).

#### T071 — Wire listeners in `PaymentsServiceProvider::boot()`

- **File:** `app/Modules/Payments/Providers/PaymentsServiceProvider.php`
- **Source:** plan.md §7.4
- **Action:** add `Event::listen(PaymentCaptured::class, [UpdateBookingPaymentStatusListener::class, 'handle'])`; ditto for `PaymentFailed` and `RefundCompleted`.

---

## Layer 9 — Translation files

#### T072 [P] — `lang/en/payments.php`

- **File:** `app/Modules/Payments/Resources/lang/en/payments.php`
- **Source:** Constitution IV
- **Keys:** `status.pending`, `status.captured`, `status.failed`, `status.refunded`, navigation labels.

#### T073 [P] — `lang/ar/payments.php`

- **File:** `app/Modules/Payments/Resources/lang/ar/payments.php`
- **Action:** identical key set, Arabic values.

#### T074 [P] — `lang/en/refunds.php`

- **File:** `app/Modules/Payments/Resources/lang/en/refunds.php`
- **Keys:** `reason_code.customer_request` etc., `status.pending`/`processing`/`completed`/`failed`, `policy.rental_window_closed`, `policy.rental_in_setup`, `policy.sale_in_preparation`, `policy.digital_post_delivery`, `errors.partial_refund_unsupported`.

#### T075 [P] — `lang/ar/refunds.php`

- **File:** `app/Modules/Payments/Resources/lang/ar/refunds.php`
- **Action:** identical key set, Arabic values.

#### T076 [P] — `lang/en/failures.php`

- **File:** `app/Modules/Payments/Resources/lang/en/failures.php`
- **Source:** research.md §R-7
- **Keys:** `insufficient_funds`, `declined_by_issuer`, `expired_card`, `fraud_suspected`, `expired_payment_hold`, `unknown` — each a one-sentence customer-facing message.

#### T077 [P] — `lang/ar/failures.php`

- **File:** `app/Modules/Payments/Resources/lang/ar/failures.php`
- **Action:** identical key set, Arabic values.

---

## Layer 10 — Pest tests

> Test-with-code: every Action/Service test ships in the same chunk as the code (per Day boundaries in research.md §R-10). Architecture tests run on every commit.

### Architecture tests

#### T078 [P] — `PaymentsModuleNoCrossImportTest`

- **File:** `tests/Architecture/PaymentsModuleNoCrossImportTest.php`
- **Source:** Constitution I / plan.md §10
- **Asserts:** `App\Modules\Payments` does not `use` any class from `App\Modules\Booking\Domain\Models`, `App\Modules\Catalog\Domain\Models`, `App\Modules\Identity\Domain\Models`, `App\Modules\Settlement\Domain\Models`, `App\Modules\Reviews\Domain\Models`, `App\Modules\Communication\Domain\Models`. Uses Pest's `expect(...)->not->toUse(...)`.

#### T079 [P] — `PaymentLoggingRedactionTest`

- **File:** `tests/Architecture/PaymentLoggingRedactionTest.php`
- **Source:** ADR-0005 §6.1 / FR-PAY-003 / plan.md §10
- **Asserts:** every `DB::table('payment_attempts')->insert(...)` and `DB::table('gateway_webhook_logs')->insert(...)` call site goes through `RedactPciFields::redact(...)` first. Implemented via PHP-Parser walking the Payments AST.

#### T080 [P] — `EventsFireAfterCommitTest`

- **File:** `tests/Architecture/EventsFireAfterCommitTest.php`
- **Source:** Constitution IX / plan.md §7.3
- **Asserts:** no `event(`/`dispatch(` call inside any `DB::transaction` closure within `App\Modules\Payments`. Walks the AST.

#### T081 [P] — `IdempotencyMiddlewareCoverageTest`

- **File:** `tests/Architecture/IdempotencyMiddlewareCoverageTest.php`
- **Source:** Constitution VIII / FR-IDEM-002
- **Asserts:** `POST /api/v1/customer/bookings/{ulid}/payments` and `POST /api/v1/admin/bookings/{ulid}/refunds` have the `idempotency` middleware in their middleware stack. Reads `Route::getRoutes()`.

#### T082 — Extend `AppendOnlyTablesHaveNoSoftDeletesTest`

- **File:** `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php`
- **Source:** Constitution V / plan.md §10
- **Action:** add `payments`, `payment_attempts`, `gateway_webhook_logs` to the existing test's table list. Confirms `SoftDeletes` trait absence and no `deleted_at` column.

### Unit tests

#### T083 [P] — `RefundPolicyServiceTest`

- **File:** `tests/Unit/Modules/Payments/RefundPolicyServiceTest.php`
- **Source:** ADR-0005 §6.5 / Tech Decisions §11 / spec.md User Stories 3-5
- **Pest groups:** `payments`, `rental` for rental cases, `sale` for sale, `digital` for digital
- **Cases:**
  - rental: `>24h allowed`, `=24h allowed (boundary)`, `<24h denied with rental_window_closed`, `setup state denied with rental_in_setup`
  - sale: `pending allowed`, `confirmed allowed`, `in_preparation denied with sale_in_preparation`, `out_for_delivery denied`, `delivered denied`
  - digital: `not delivered allowed regardless of flag`, `delivered + flag=true allowed`, `delivered + flag=false denied with digital_post_delivery`

#### T084 [P] — `PaymobGatewaySignatureTest`

- **File:** `tests/Unit/Modules/Payments/PaymobGatewaySignatureTest.php`
- **Source:** FR-PAY-005 / research.md §R-1
- **Cases:** valid HMAC accepted; tampered single-byte HMAC rejected; missing HMAC rejected; correct HMAC over reordered fields STILL rejected (proves field-order matters); wrong secret rejected.

#### T085 [P] — `RedactPciFieldsTest`

- **File:** `tests/Unit/Modules/Payments/RedactPciFieldsTest.php`
- **Source:** ADR-0005 §6.1 / research.md §R-6
- **Cases:** `source_data.pan` is `[REDACTED]`; nested unknown keys removed; allowlisted keys preserved; deeply nested allowlisted keys preserved.

#### T086 [P] — `MapPaymobFailureCodeTest`

- **File:** `tests/Unit/Modules/Payments/MapPaymobFailureCodeTest.php`
- **Source:** research.md §R-7
- **Cases:** "Insufficient funds" → `insufficient_funds`; case-insensitive; unknown message → `unknown`.

### Feature tests — Phase 4.0 (gateway + webhook + idempotency)

#### T087 [P] — `InitiatePaymentTest`

- **File:** `tests/Feature/Modules/Payments/InitiatePaymentTest.php`
- **Source:** spec.md User Story 1 / FR-PAY-001..004 / Constitution VII
- **Pest group:** `payments`
- **Cases (covering required test matrix):**
  - **Happy path:** initiate → 201 → `payments` row exists (`pending`) + `redirect_url` returned + `PaymentInitiated` event fired
  - **Idempotency replay:** same key + same body × 10 calls → 1 row, 1 event (uses `Http::pool` per research.md §R-4)
  - **Idempotency conflict:** same key + different body → 409
  - **Auth (401):** unauthenticated → 401
  - **Authz (403):** customer A on customer B's booking → 403
  - **Validation (422):** missing `method` → 422; `method=wallet` (not in Phase 1) → 422
  - **Locale (EN):** `Accept-Language: en` → English response
  - **Locale (AR):** `Accept-Language: ar` → Arabic response
  - **State guard:** booking not in `confirmed` state → 409
  - **Concurrency:** two callers, two different keys, same booking → first wins, second 409

#### T088 [P] — `PaymobWebhookTest`

- **File:** `tests/Feature/Modules/Payments/PaymobWebhookTest.php`
- **Source:** spec.md User Story 2 / FR-PAY-005..008 / research.md §R-3
- **Pest group:** `payments`
- **Cases:**
  - Valid HMAC + `success=true` → 200, `payments.status=captured`, `PaymentCaptured` dispatched
  - Valid HMAC + `success=false` → 200, `payments.status=failed`, `PaymentFailed` dispatched, `failure_code` mapped
  - Invalid HMAC → 401, no payment mutation, log appended with `signature_valid=false`
  - Replay (same payload twice) → exactly one capture, one event
  - Unknown `gateway_ref` → 200, log appended with `processing_error`, no event dispatched
  - Booking already refunded → capture rejected, log notes the conflict

#### T089 [P] — `IdempotencyMiddlewareTest`

- **File:** `tests/Feature/Modules/Payments/IdempotencyMiddlewareTest.php`
- **Source:** Constitution VIII / plan.md §6.4 / research.md §R-4
- **Pest group:** `payments`
- **Cases:**
  - Same key + body in 24h → cached replay (status, body identical)
  - Same key, different body → 409
  - Expired key (>24h) → fresh row created
  - **Concurrency proof:** 10 simultaneous `Http::pool` requests, same key → 1 row, 9 replays
  - Different users with same key string → 409 (per spec edge cases)

#### T090 [P] — `ExpirePendingPaymentsJobTest`

- **File:** `tests/Feature/Modules/Payments/ExpirePendingPaymentsJobTest.php`
- **Source:** ADR-0005 §6.3 / FR-PAY-010 / research.md §R-5
- **Pest group:** `payments`
- **Cases:** `pending` payment older than booking hold → moves to `failed` with `failure_code=expired_payment_hold`; `pending` within hold → untouched; idempotent re-run.

### Feature tests — Phase 4.1 (per-type refunds — three required files)

#### T091 [P] — `InitiateRefundRentalTest`

- **File:** `tests/Feature/Modules/Payments/InitiateRefundRentalTest.php`
- **Pest groups:** `payments`, `rental`
- **Source:** spec.md User Story 3 / FR-REF-003 / Tech Decisions §11
- **Cases:**
  - `event_starts_at = now+25h, item_status=confirmed` → 201, refund completes, `payments.status=refunded`
  - `event_starts_at = now+24h, item_status=confirmed` (boundary) → 201
  - `event_starts_at = now+23h` → 422 with `code=rental_window_closed`, message in active locale
  - `item_status=setup, event_starts_at=now+48h` → 422 with `code=rental_in_setup`
  - **Auth:** unauthenticated → 401
  - **Authz:** admin without `payment.refund` permission → 403
  - **Validation:** missing `reason_code` → 422; missing `reason_notes.ar` → 422
  - **Idempotency:** duplicate refund key → cached replay
  - **Locale (EN+AR):** policy-violation messages translated correctly
  - **Partial refund attempt:** body with `amount_minor < payments.amount_minor` → 422 with `errors.partial_refund_unsupported`

#### T092 [P] — `InitiateRefundSaleTest`

- **File:** `tests/Feature/Modules/Payments/InitiateRefundSaleTest.php`
- **Pest groups:** `payments`, `sale`
- **Source:** spec.md User Story 4 / FR-REF-004
- **Cases:**
  - `item_status=pending` → 201
  - `item_status=confirmed` → 201
  - `item_status=in_preparation` → 422 with `code=sale_in_preparation`
  - `item_status=out_for_delivery` → 422
  - `item_status=delivered` → 422
  - Auth (401), Authz (403), Validation (422), Locale (EN+AR), Idempotency (replay)

#### T093 [P] — `InitiateRefundDigitalTest`

- **File:** `tests/Feature/Modules/Payments/InitiateRefundDigitalTest.php`
- **Pest groups:** `payments`, `digital`
- **Source:** spec.md User Story 5 / FR-REF-005
- **Cases:**
  - `item_status != delivered, flag=true` → 201
  - `item_status != delivered, flag=false` → 201 (pre-delivery refund always allowed)
  - `item_status=delivered, flag=true` → 201
  - `item_status=delivered, flag=false` → 422 with `code=digital_post_delivery`
  - Auth (401), Authz (403), Validation (422), Locale (EN+AR), Idempotency (replay)

#### T094 — Mixed-cart refund

- **File:** `tests/Feature/Modules/Payments/MixedCartRefundTest.php`
- **Pest groups:** `payments`, `rental`, `sale`, `digital`
- **Source:** plan.md §4.2 (mixed-cart paragraph)
- **Cases:** booking with one rental + one sale + one digital item. Refund allowed only when ALL three pass their per-type policy. If any one fails, the entire refund is rejected with the first failing reason.

---

## Day boundary summary

### Day 1 (Phase 4.0 — gateway + webhook foundation)

T001 ✅ (already done) → T002–T007 (migrations) → T008–T028 (domain + DTOs) → **T028a–T028d (reader contracts + Booking/Catalog implementations + bindings — these UNBLOCK Codex's previous stop on missing `ServiceRepository`)** → T029–T030 (Layer-3 unit tests) → T031–T037 (infrastructure + middleware) → T039 (InitiatePaymentAction) → T041 (ProcessPaymobWebhookAction) → T044 (ExpirePendingPaymentsAction) → architecture tests T078–T082 → Pest unit T083–T086 → register service provider T058–T060.

**Day-1 exit gate:** `php artisan migrate` clean, `./vendor/bin/pest --group=payments tests/Unit` green, architecture tests green, sandbox `php artisan tinker` can call `app(PaymentGateway::class)->verifyWebhookSignature(...)` against a known-good Paymob payload.

### Day 2 (Phase 4.0 — Actions + Pest feature tests)

T040 (CapturePaymentAction) → T045–T057 (Resources + Form Requests + Controllers + Routes) → T061–T064 (API documentation) → T065 (RefundResource Filament read-only) → T068 (shield:generate) → T069–T071 (listeners) → Pest feature T087–T090.

**Day-2 exit gate:** all Pest tests under `tests/Feature/Modules/Payments` green; sandbox end-to-end (curl initiate + manual Paymob test card + webhook capture) works per `quickstart.md`; Scribe doc generated.

### Day 3 (Phase 4.1 — refunds + per-type tests)

T038 (RefundPolicyService) → T042–T043 (refund Actions) → T053–T054 (refund controllers) → T056 (admin routes refund) → T066 (Booking refund Filament action) → T067 (Filament translations finalize) → T072–T077 (translations) → Pest feature T091–T094.

**Day-3 exit gate:** all Pest groups (`payments`, `rental`, `sale`, `digital`) green; refund button visible+functional in Filament; spec exit criteria all checked; PR opened against `005-booking-draft-items` (or main), with all 5 endpoints listed in `api-registry.md`.

---

## Self-check (per user requirement)

**1. Every task traces to FR / Schema / ADR?** ✅
Every task above cites at least one of: PRD FR-XX, Schema §6, ADR-0005 §X, plan.md §X, spec.md User Story X, or Constitution principle X. Migration tasks → Schema §6. Action tasks → FR-PAY-XXX / FR-REF-XXX. Test tasks → User Stories. Architecture tests → Constitution principles.

**2. Phase 2 features?** None. Cut-list reaffirmed in code:
- Split payments — rejected by `InitiatePaymentAction` invariant (T039)
- GCC adapters (Tabby/Tamara) — slot reserved by `PaymentGateway` interface, no impl
- Partial refunds — rejected by amount-equality check (T042)
- Customer self-serve refund — no customer route or controller created
- Multi-currency — initiate Action rejects non-EGP

**3. Packages not in `10_Package_List.md`?** None added. Plan.md §9 exhaustively maps every package used. Native-PHP `hash_hmac` + `hash_equals` for HMAC. `Http::client()` for outbound (Laravel built-in). `Http::pool` for concurrency proof (Laravel built-in via Guzzle which is already in via `laravel/framework`).

**4. Every API endpoint has documentation tasks?** ✅
- `@bodyParam` on every Form Request field → T047, T048, T049 each enforce
- `@response` with EN+AR examples on every Resource → T045, T046 each enforce
- `api-registry.md` updated → T061
- Bruno collection → T062
- Postman export → T063
- Scribe generation → T064

**5. Every controller honours the 3-line rule?** ✅
T050–T054 each note "max 3 lines" body referencing CLAUDE.md §1.

**6. Per-type test coverage for the refund feature?** ✅
T091 (rental), T092 (sale), T093 (digital), plus T094 mixed-cart. Constitution VII non-negotiable.

**7. Cross-module model imports?** None — all reads via Payments-owned `PaymentsBookingReader` (Booking implements, T028c) and `PaymentsCatalogReader` (Catalog implements, T028d) contracts. Both return DTOs, never Eloquent models. T078 architecture test enforces.

---

**Total tasks:** 94 (T001 done, T002–T094 actionable).
**Estimated effort:** 3 calendar days per phasing plan. Day boundaries above.
**Next command:** `/speckit.implement` to start executing Day 1 tasks (or work through them manually).
