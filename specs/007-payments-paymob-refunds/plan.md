# Implementation Plan: Payments — Paymob Gateway + Per-Type Refunds

**Branch**: `007-payments-paymob-refunds` | **Date**: 2026-05-02 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/007-payments-paymob-refunds/spec.md`
**Phase**: 4.0 (Paymob Gateway, 2 days) + 4.1 (Refunds per-type, 1 day) — Week 5

---

## Summary

Customer pays for a `confirmed` booking through Paymob's hosted checkout; webhook captures the payment and the Booking module flips `payment_status = paid`. Admin can issue a full refund through Filament, gated by a per-product-type policy (rental: ≥24h before event + not in `setup`; sale: before `in_preparation`; digital: per `is_refundable_after_delivery` flag). The work introduces the **Payments module** (`app/Modules/Payments/`) with five tables, a `PaymentGateway` interface (Paymob in Phase 1; Tabby/Tamara slot for Phase 2), an idempotency-key middleware reused by Settlement (Phase 4.2), and a single `RefundPolicyService` that branches via `match($enum)`. PCI scope is held at SAQ-A (zero card data on InstaParty servers). Webhook auth is HMAC-SHA512 only, no IP allowlist. New `payments` row per initiate; a 15-minute sweep job ages out `pending` rows once the booking's 24h hold expires.

---

## 1. ADR Reference

- **ADR-0005 — Payments Module** ([`docs/adr/0005-payments-module.md`](../../docs/adr/0005-payments-module.md))
- **Status:** `Accepted` (2026-05-02). Tasks generation is unblocked.
- **Why required:** This is a new module under `app/Modules/Payments/` and the constitution principle VI ("ADR Before Code") is non-negotiable.
- **Indexed in:** `docs/adr/README.md` (row 5).
- **Spec clarifications captured in ADR §6:** PCI SAQ-A scope (§6.1), HMAC-only webhook auth (§6.2), no row reuse + sweep job (§6.3), fixed `RefundReasonCode` enum (§6.4), single `RefundPolicyService` over per-type Actions (§6.5), custom Paymob adapter — no SDK (§6.6), idempotency middleware homed in this module (§6.7).

---

## 2. Constitution Check

*GATE: All "Applies" rows must be satisfied before Phase 0 research and re-checked after Phase 1 design.*

| # | Principle | Applies? | How satisfied |
|---|---|---|---|
| **I** | Modular Monolith — never microservices, never cross-module model imports | **Yes** | New `app/Modules/Payments/` follows the standard layer layout (Domain / Application / Infrastructure / Http / Filament / Routes / Database / Resources / Providers). Booking and Catalog reads happen via **Payments-owned** Contracts `Payments\Domain\Contracts\PaymentsBookingReader` and `Payments\Domain\Contracts\PaymentsCatalogReader`, implemented by Booking and Catalog respectively (consumer-owned-contract pattern, same as `Booking\Domain\Contracts\CatalogServiceReader`). Both contracts return DTOs, never Eloquent models. Architecture test `tests/Architecture/PaymentsModuleNoCrossImportTest.php` enforces. Outbound dependency: this module exposes `Payments\Domain\Contracts\PaymentGateway`. |
| **II** | Three Product Types — `match($enum)`, never if/elseif on type strings | **Yes** | This module is **cross-type infrastructure** (single shape of `payments` / `refunds` / etc.). Refund eligibility is the only per-type concern, resolved by `RefundPolicyService::policyFor(ProductType $type): RefundPolicy` using a single `match($enum)`. Architecture test `tests/Architecture/NoIfElseOnProductTypeStringTest.php` already covers all modules; new `RefundPolicyService` is included automatically. Pest groups: `->group('rental')`, `->group('sale')`, `->group('digital')` for the three policy variants. |
| **III** | Money Discipline — integer minor units + `Brick\Money` | **Yes** | `payments.amount_minor` (BIGINT UNSIGNED) + `amount_currency` (CHAR(3)); same for `refunds`. Cast through reused `App\Modules\Shared\Domain\Casts\MoneyCast` returning `Brick\Money\Money`. All arithmetic via `Money::plus()` / `Money::minus()` with explicit `RoundingMode::UNNECESSARY` (full refund only — Phase 1). `tests/Architecture/NoFloatForMoneyTest.php` blocks any `float` type-hint in this module. |
| **IV** | Bilingual EN+AR — both locales mandatory | **Yes** | `payments.failure_message`, `refunds.reason_notes` are JSON translatable columns. Validation on `InitiateRefundRequest` requires both `reason_notes.en` AND `reason_notes.ar` — empty string fails. `PaymentResource` and `RefundResource` convert at the API Resource layer using `App::getLocale()`. Filament refund button uses the translatable plugin EN/AR tabs for the reason notes. Translation files: `app/Modules/Payments/Resources/lang/en/{payments,refunds}.php` and `…/ar/{payments,refunds}.php`. |
| **V** | Append-Only Tables — no soft deletes, status-only updates | **Yes** | `payment_attempts` and `gateway_webhook_logs` have NO `updated_at` and NO `deleted_at` (per Schema §6). `payments` is status-only (mutable: `status`, `failure_code`, `failure_message`, `captured_at`). `refunds` is status-only (mutable: `status`, `gateway_ref`, `processed_at`). `idempotency_keys` is short-lived but follows no-soft-delete rule (purged daily by scheduled job). Architecture test `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` already enforces. |
| **VI** | ADR Before Code | **Yes — Accepted** | ADR-0005 accepted on 2026-05-02. Implementation gate cleared. |
| **VII** | Test-First on Money / Auth / Bookings | **Yes** | Pest tests live in the same chunk as the Action they cover. Coverage targets: ≥80% on `InitiatePaymentAction`, `CapturePaymentAction`, `ProcessPaymobWebhookAction`, `InitiateRefundAction`, `ProcessRefundAction`, `ExpirePendingPaymentsAction`, `RefundPolicyService`, `IdempotencyKeyMiddleware`, `PaymobGateway`. Three per-type refund test files mandated: `InitiateRefundRentalTest`, `InitiateRefundSaleTest`, `InitiateRefundDigitalTest`. |
| **VIII** | Idempotency on State-Changing Endpoints | **Yes** | `IdempotencyKeyMiddleware` lives here (`app/Modules/Payments/Http/Middleware/IdempotencyKeyMiddleware.php`) and is registered globally in `bootstrap/app.php`. Applied to: `POST /api/v1/customer/bookings/{ulid}/payments`, `POST /api/v1/admin/bookings/{ulid}/refunds`. Phase 4.2 will reuse for `POST /api/v1/vendor/withdrawals`. Concurrency test required. |
| **IX** | Domain Events Fire `DB::afterCommit` | **Yes** | `PaymentInitiated`, `PaymentCaptured`, `PaymentFailed`, `RefundCompleted`, `RefundFailed` all dispatched via `DB::afterCommit(fn () => event(...))` inside Action methods. The Booking-side listener `UpdateBookingPaymentStatusListener` (lives in Booking module) is queued (`ShouldQueue`) so it runs after the webhook transaction commits, never inside it. |
| **X** | Vendor Approval Two-Step Gate | **N/A** | This feature does not approve vendors. Booking confirmation already required approved-for-type vendors upstream. |
| **XI** | Document Storage — direct S3 vs MediaLibrary | **N/A** | No uploaded documents. Webhook payloads are JSON (redacted) stored in MySQL. |

**Locked stack compliance:** Paymob is the locked Phase 1 gateway. No new Composer packages introduced (full list in §9). HMAC verification uses native PHP `hash_hmac` + `hash_equals`. Idempotency middleware is custom (no third-party package).

**Phase 1 forbidden-feature check (Constitution §):** No vendor tiers, no platform-owned packages, no dispute engine, no GCC adapters. Multi-currency activation is explicitly *not* added — runtime rejects non-EGP `amount_currency`.

**Gate result: PASS.**

---

## 3. Schema

All tables match `docs/specs/11_DB_Schema.md` §6 (Payments — 5 tables) exactly. No deviations from the locked schema.

### Migration order (FK dependency creation order)

```
1. {ts}_create_payments_table.php
2. {ts}_create_payment_attempts_table.php
3. {ts}_create_refunds_table.php
4. {ts}_create_idempotency_keys_table.php
5. {ts}_create_gateway_webhook_logs_table.php
```

**Cross-module prerequisite:** Identity (`users`) ships in Phase 1.0; Booking (`bookings`, `booking_items`) ships in Phase 3.1. Both must be present before this module's migrations run.

### 3.1 `payments`

> Append-only — only `status`, `failure_code`, `failure_message`, `captured_at` are mutable post-insert.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | `bigIncrements` |
| `public_id` | CHAR(26) UNIQUE | NO | ULID, exposed in API |
| `booking_id` | BIGINT UNSIGNED FK→`bookings.id` | NO | `restrictOnDelete()` |
| `user_id` | BIGINT UNSIGNED FK→`users.id` | NO | Payer; `restrictOnDelete()` |
| `gateway` | VARCHAR(40) | NO | `paymob` for Phase 1 |
| `gateway_ref` | VARCHAR(190) | NO | Paymob transaction ref |
| `amount_minor` | BIGINT UNSIGNED | NO | Money discipline |
| `amount_currency` | CHAR(3) | NO | EGP only in Phase 1 |
| `method` | ENUM('card','wallet','installment','cash_on_delivery','transfer') | NO | |
| `status` | ENUM('pending','authorized','captured','failed','refunded','partially_refunded','voided') | NO | Mutable |
| `captured_at` | TIMESTAMP | YES | Set by webhook |
| `failure_code` | VARCHAR(80) | YES | Includes `expired_payment_hold` for sweep |
| `failure_message` | JSON | YES | Translatable EN+AR |
| `metadata` | JSON | YES | |
| `created_at`, `updated_at` | TIMESTAMPS | | |

**Indexes:** UNIQUE `(gateway, gateway_ref)` (idempotency at DB level), `(booking_id, status)`, `(status, created_at)` (sweep query).

**Charset:** `utf8mb4` / `utf8mb4_unicode_ci`.

### 3.2 `payment_attempts`

> Fully immutable. No `updated_at`. No `deleted_at`.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | |
| `payment_id` | BIGINT UNSIGNED FK→`payments.id` | NO | `cascadeOnDelete()` (forensic only — payments are never deleted in Phase 1) |
| `attempt_no` | INT UNSIGNED | NO | Monotonic per payment |
| `request_payload` | JSON | NO | Sanitized via PCI allowlist (FR-PAY-003) |
| `response_payload` | JSON | YES | Sanitized |
| `http_status` | SMALLINT UNSIGNED | YES | |
| `created_at` | TIMESTAMP | NO | `useCurrent()` |

**Indexes:** `(payment_id, attempt_no)`.

### 3.3 `refunds`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | |
| `public_id` | CHAR(26) UNIQUE | NO | ULID |
| `payment_id` | BIGINT UNSIGNED FK→`payments.id` | NO | `restrictOnDelete()` |
| `booking_id` | BIGINT UNSIGNED FK→`bookings.id` | NO | Denormalized for reporting; `restrictOnDelete()` |
| `amount_minor` | BIGINT UNSIGNED | NO | Phase 1: equals `payments.amount_minor` (full only) |
| `amount_currency` | CHAR(3) | NO | |
| `reason_code` | VARCHAR(80) | NO | Validated `Rule::in([...5 values...])` |
| `reason_notes` | JSON | YES | Translatable EN+AR |
| `gateway_ref` | VARCHAR(190) | YES | Set when gateway accepts |
| `status` | ENUM('pending','processing','completed','failed') | NO | Mutable |
| `initiated_by` | BIGINT UNSIGNED FK→`users.id` | NO | Admin user; `restrictOnDelete()` |
| `processed_at` | TIMESTAMP | YES | |
| `created_at`, `updated_at` | TIMESTAMPS | | |

**Indexes:** `(payment_id)`, `(booking_id, status)`, `(status, created_at)`.

### 3.4 `idempotency_keys`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | |
| `key` | VARCHAR(190) UNIQUE | NO | Header value |
| `user_id` | BIGINT UNSIGNED FK→`users.id` | YES | `restrictOnDelete()` |
| `route` | VARCHAR(190) | NO | Route name from `\Route::currentRouteName()` |
| `request_hash` | CHAR(64) | NO | SHA-256 of body |
| `response_status` | SMALLINT UNSIGNED | YES | |
| `response_body` | JSON | YES | Replayed verbatim |
| `expires_at` | TIMESTAMP | NO | now + 24h |
| `created_at` | TIMESTAMP | NO | |

**Indexes:** UNIQUE `(key)`, `(expires_at)` (sweep), `(user_id, route)`.

### 3.5 `gateway_webhook_logs`

> Fully immutable. No `updated_at`. No `deleted_at`.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | |
| `gateway` | VARCHAR(40) | NO | `paymob` |
| `event_type` | VARCHAR(80) | NO | `transaction_processed`, `transaction_response_callback` |
| `signature_valid` | BOOLEAN | NO | |
| `payload` | JSON | NO | Redacted via PCI allowlist (FR-PAY-006) |
| `processed_at` | TIMESTAMP | YES | |
| `processing_error` | TEXT | YES | |
| `created_at` | TIMESTAMP | NO | |

**Indexes:** `(gateway, signature_valid, created_at)`, `(event_type, created_at)`.

---

## 4. Per-Type Coverage

The Payments module is **cross-type infrastructure** (single-shape `payments` / `refunds` tables — no per-type detail tables). Per-type behavior is concentrated in **refund eligibility only**, resolved through one `RefundPolicyService` using `match($enum)` (per ADR-0005 §6.5).

### 4.1 Cross-type classes (single implementation)

| Concern | Class | Notes |
|---|---|---|
| Payment intent | `Application\Actions\InitiatePaymentAction` | One Action; no per-type variants |
| Webhook capture | `Application\Actions\ProcessPaymobWebhookAction`, `Application\Actions\CapturePaymentAction` | One pair |
| Refund initiation | `Application\Actions\InitiateRefundAction` | One Action; calls `RefundPolicyService` then `ProcessRefundAction` |
| Refund execution | `Application\Actions\ProcessRefundAction` | One Action |
| Sweep job | `Application\Actions\ExpirePendingPaymentsAction` | One Action |
| Gateway adapter | `Infrastructure\Gateways\PaymobGateway` | One adapter |
| Form Requests | `InitiatePaymentRequest`, `PaymobWebhookRequest`, `InitiateRefundRequest` | One per endpoint |
| API Resources | `PaymentResource`, `RefundResource` | One pair |
| Filament Resources | `RefundResource` (admin read-only) | One |

### 4.2 Per-type refund policy resolution

`Application\Services\RefundPolicyService::policyFor(ProductType $type, BookingItem $item): RefundPolicy`

```text
match ($type) {
    ProductType::Rental  => $this->rentalPolicy($item),   // ≥24h before event_starts_at AND item.status != 'setup'
    ProductType::Sale    => $this->salePolicy($item),     // item.status < in_preparation
    ProductType::Digital => $this->digitalPolicy($item),  // !delivered OR service.is_refundable_after_delivery
};
```

**Rental** branch (`rentalPolicy`):
- Test class: `tests/Feature/Modules/Payments/InitiateRefundRentalTest.php` (`->group('rental')`)
- Test cases: `>24h allowed`, `=24h allowed (boundary)`, `<24h rejected`, `setup state rejected`
- Spec scenarios: User Story 3 acceptance scenarios 1–5

**Sale** branch (`salePolicy`):
- Test class: `tests/Feature/Modules/Payments/InitiateRefundSaleTest.php` (`->group('sale')`)
- Test cases: `pre in_preparation allowed`, `in_preparation rejected`, `out_for_delivery rejected`, `delivered rejected`
- Spec scenarios: User Story 4 acceptance scenarios 1–3

**Digital** branch (`digitalPolicy`):
- Test class: `tests/Feature/Modules/Payments/InitiateRefundDigitalTest.php` (`->group('digital')`)
- Test cases: `not delivered allowed`, `delivered + flag=true allowed`, `delivered + flag=false rejected`
- Spec scenarios: User Story 5 acceptance scenarios 1–3

**Mixed-cart booking:** A single booking may contain items of all three types. The refund is allowed only if **every** booking item passes its respective per-type policy (logical AND). One failing item rejects the entire refund attempt.

---

## 5. Locale Coverage

All user-facing text is bilingual (EN + AR mandatory per Constitution IV).

### 5.1 Translatable JSON columns

| Table.Column | Where rendered | Validation rule |
|---|---|---|
| `payments.failure_message` | `PaymentResource` (customer-facing) | Auto-populated by listener — required when `status=failed`; both `en` and `ar` keys non-empty |
| `refunds.reason_notes` | `RefundResource` (admin-facing) | `InitiateRefundRequest` requires `reason_notes.en` (string, max 500) AND `reason_notes.ar` (string, max 500); empty string fails |

### 5.2 Validation messages

User-facing validation errors (e.g., "Rental refund window has closed", "Digital product not refundable after delivery") live in:
- `app/Modules/Payments/Resources/lang/en/refunds.php`
- `app/Modules/Payments/Resources/lang/ar/refunds.php`

Both files MUST be present and have identical key sets. Pest test `LocaleParityTest` enforces.

### 5.3 Form Request rules

```php
// InitiateRefundRequest::rules()
return [
    'reason_code'      => ['required', Rule::in(RefundReasonCode::values())],
    'reason_notes'     => ['required', 'array'],
    'reason_notes.en'  => ['required', 'string', 'max:500'],
    'reason_notes.ar'  => ['required', 'string', 'max:500'],
];
```

### 5.4 API Resource locale conversion

```php
// RefundResource::toArray()
return [
    'public_id'      => $this->public_id,
    'amount_minor'   => $this->amount_minor,
    'amount_currency'=> $this->amount_currency,
    'reason_code'    => $this->reason_code,
    'reason_notes'   => $this->getTranslation('reason_notes', App::getLocale()),
    'status'         => $this->status->value,
    'created_at'     => $this->created_at->toIso8601String(),  // UTC
];
```

### 5.5 Filament EN/AR tabs

`RefundResource` (read-only) and the refund Action on `BookingResource` both use `filament/spatie-laravel-translatable-plugin` — EN and العربية tabs at the top of the modal form for `reason_notes`.

### 5.6 Test coverage for locale

Each Feature test asserts the response in both locales:
```php
$this->withHeader('Accept-Language', 'en')->postJson(...)->assertJsonPath('errors.0.message', 'Rental refund window has closed');
$this->withHeader('Accept-Language', 'ar')->postJson(...)->assertJsonPath('errors.0.message', 'انتهت فترة استرداد الإيجار');
```

---

## 6. Idempotency

### 6.1 Endpoints requiring `Idempotency-Key`

| Method | Path | Idempotent | Notes |
|---|---|---|---|
| `POST` | `/api/v1/customer/bookings/{ulid}/payments` | **Required** | 24h replay window |
| `POST` | `/api/v1/admin/bookings/{ulid}/refunds` | **Required** | 24h replay window |
| `POST` | `/api/v1/webhooks/paymob` | **Naturally idempotent** | Idempotency enforced by `(gateway, gateway_ref)` UNIQUE — no `Idempotency-Key` header expected from Paymob |
| `GET` | `/api/v1/customer/payments/{ulid}` | N/A | Safe |
| `GET` | `/api/v1/admin/refunds/{ulid}` | N/A | Safe |

### 6.2 Middleware contract

`IdempotencyKeyMiddleware` (in `app/Modules/Payments/Http/Middleware/`) intercepts requests with the `Idempotency-Key` header on routes that opt in via `->middleware('idempotency')`:

1. **Lookup:** `idempotency_keys` row matching `(key, user_id, request_hash)` where `expires_at > now()`.
2. **Hit:** Replay cached `response_status` + `response_body` verbatim — short-circuits the controller; no Action runs; no second gateway call.
3. **Same key + different `request_hash`:** Return `409 Conflict` with `{ "error": "idempotency_conflict" }` — request body changed.
4. **Miss:** Proceed to controller. After response is generated, INSERT a row with `expires_at = now()->addHours(24)`. Wrap in `DB::transaction` so the row and the side-effect rollback together if the controller throws.
5. **Concurrent miss (race):** `DB::lockForUpdate()` on a row keyed by `(key)` — losing requests block, then re-read and replay.

### 6.3 Cleanup job

`php artisan schedule:run` invokes a daily job that deletes `idempotency_keys` rows where `expires_at < now()`. Registered in `app/Console/Kernel.php` schedule.

### 6.4 Required tests

- `IdempotencyMiddlewareTest::it replays cached response for same key+body within 24h`
- `IdempotencyMiddlewareTest::it returns 409 for same key + different body`
- `IdempotencyMiddlewareTest::it expires after 24h and a fresh request creates a new row`
- `IdempotencyMiddlewareTest::it serializes 10 concurrent requests to one Action call` (concurrency proof — uses `\React\Promise` or PHP `pcntl_fork` in test)

---

## 7. Domain Events

### 7.1 Events published by Payments

| Event | Where fired | `DB::afterCommit`? | Listener(s) | Queue |
|---|---|---|---|---|
| `Payments\Domain\Events\PaymentInitiated` | `InitiatePaymentAction::execute()` after `payments` row inserted | **Yes** — `DB::afterCommit(fn () => event(...))` | None in Phase 1 (Communication will subscribe in Phase 5.0) | — |
| `Payments\Domain\Events\PaymentCaptured` | `CapturePaymentAction::execute()` (called from `ProcessPaymobWebhookAction` after HMAC verify) | **Yes** | `Booking\Application\Listeners\UpdateBookingPaymentStatusListener` (in Booking module — sets `bookings.payment_status = paid`); future Settlement listener (Phase 4.2); future Communication listener (Phase 5.0 receipt email) | `default` queue, `ShouldQueue` |
| `Payments\Domain\Events\PaymentFailed` | `CapturePaymentAction::execute()` on failed webhook OR `ExpirePendingPaymentsAction::execute()` on sweep | **Yes** | `Booking\Application\Listeners\HandlePaymentFailedListener` (no-op for Phase 1; logs only — booking stays `pending`) | `default` queue, `ShouldQueue` |
| `Payments\Domain\Events\RefundCompleted` | `ProcessRefundAction::execute()` after gateway confirms | **Yes** | Future Settlement listener (Phase 4.2 — writes negative `wallet_ledger` entry); `Booking\Application\Listeners\UpdateBookingPaymentStatusListener` (sets `payment_status = refunded`) | `default` queue, `ShouldQueue` |
| `Payments\Domain\Events\RefundFailed` | `ProcessRefundAction::execute()` on gateway rejection | **Yes** | Admin notification listener (Phase 5.0); for now, log + Sentry breadcrumb | `default` queue, `ShouldQueue` |

### 7.2 Events consumed by Payments

**None in Phase 4.0 / 4.1.** Phase 4.2+ may add `BookingCancelled` consumption to auto-trigger refunds; out of scope here.

### 7.3 `DB::afterCommit` enforcement pattern

Every Action that mutates state and dispatches events follows this exact shape:

```php
public function execute(InitiatePaymentDto $dto): Payment
{
    return DB::transaction(function () use ($dto) {
        $payment = $this->payments->create([...]);
        $this->attempts->log($payment, $request, $response);

        DB::afterCommit(fn () => PaymentInitiated::dispatch($payment));

        return $payment;
    });
}
```

**Forbidden:** `event(...)` or `Event::dispatch(...)` inside a transaction. Architecture test `tests/Architecture/EventsFireAfterCommitTest.php` greps for `event(` and `dispatch(` calls inside `DB::transaction` closures and fails the build.

### 7.4 Listener registration

Listeners are registered in `PaymentsServiceProvider::boot()` via `Event::listen(...)` — NOT auto-discovered, so the Booking module's listeners are explicitly wired:

```php
Event::listen(PaymentCaptured::class, [UpdateBookingPaymentStatusListener::class, 'handle']);
Event::listen(PaymentFailed::class,   [HandlePaymentFailedListener::class, 'handle']);
Event::listen(RefundCompleted::class, [UpdateBookingPaymentStatusListener::class, 'handleRefund']);
```

---

## 8. API Documentation Plan

### 8.1 Endpoint inventory

| Method | Path | Auth | Roles | Request fields | Response shape |
|---|---|---|---|---|---|
| `POST` | `/api/v1/customer/bookings/{ulid}/payments` | sanctum-cookie+token | `customer` (booking owner) | `Idempotency-Key` header (required); body: `method` (enum: `card`) | `{ data: { payment: PaymentResource, redirect_url: string }, meta: {}, errors: [] }` |
| `GET` | `/api/v1/customer/payments/{ulid}` | sanctum-cookie+token | `customer` (owner) | — | `{ data: PaymentResource, meta: {}, errors: [] }` |
| `POST` | `/api/v1/webhooks/paymob` | none (HMAC-SHA512) | — | Raw Paymob payload (gateway-specific) — verified via `HMAC` header | `{ "ok": true }` (200) or `{ "error": "invalid_signature" }` (401) |
| `POST` | `/api/v1/admin/bookings/{ulid}/refunds` | sanctum-cookie+token | `admin` with `payment.refund` permission | `Idempotency-Key` header; body: `reason_code` (enum 5 values), `reason_notes` ({ en, ar }) | `{ data: RefundResource, meta: {}, errors: [] }` |
| `GET` | `/api/v1/admin/refunds/{ulid}` | sanctum-cookie+token | `admin` (with `view_refund`) | — | `{ data: RefundResource, meta: {}, errors: [] }` |

### 8.2 Scribe `@bodyParam` annotations (every Form Request field)

`InitiatePaymentRequest`:
```php
/**
 * @bodyParam method string required Payment method. Phase 1 supports `card` only. Example: card
 */
```

`InitiateRefundRequest`:
```php
/**
 * @bodyParam reason_code string required One of: customer_request, vendor_cancellation, service_unavailable, duplicate_charge, admin_discretion. Example: customer_request
 * @bodyParam reason_notes object required Translatable reason notes. Both keys required.
 * @bodyParam reason_notes.en string required English notes (max 500 chars). Example: Customer requested cancellation due to schedule change.
 * @bodyParam reason_notes.ar string required Arabic notes (max 500 chars). Example: طلب العميل الإلغاء بسبب تغير الجدول.
 */
```

### 8.3 Scribe `@response` annotations (every API Resource)

`PaymentResource` (`@response 200`):
```json
{
  "data": {
    "public_id": "01HE5T8K2P7M9R4Q6V8Y0X3Z2A",
    "booking_public_id": "01HE5T7G9N5K2P7M9R4Q6V8Y0X",
    "gateway": "paymob",
    "amount_minor": 125000,
    "amount_currency": "EGP",
    "method": "card",
    "status": "captured",
    "captured_at": "2026-05-02T14:32:00Z",
    "failure_message": null,
    "created_at": "2026-05-02T14:30:00Z"
  },
  "meta": {},
  "errors": []
}
```

`RefundResource` `failure_message` and `reason_notes` are returned in the `Accept-Language` locale. Two example responses (EN and AR) are documented in Scribe.

### 8.4 `api-registry.md` update

After implementation, append five rows to `.specify/memory/api-registry.md` ordered by phase ascending (4.0 / 4.1) then path:

```
| POST | /api/v1/customer/bookings/{ulid}/payments | Payments | 4.0 | sanctum-cookie+token | customer | InitiatePaymentRequest | { payment: PaymentResource, redirect_url: string } | ❌ todo |
| GET  | /api/v1/customer/payments/{ulid}          | Payments | 4.0 | sanctum-cookie+token | customer | — | PaymentResource | ❌ todo |
| POST | /api/v1/webhooks/paymob                   | Payments | 4.0 | none (HMAC)          | —        | PaymobWebhookRequest | { ok: true } | ❌ todo |
| POST | /api/v1/admin/bookings/{ulid}/refunds     | Payments | 4.1 | sanctum-cookie+token | admin (payment.refund) | InitiateRefundRequest | RefundResource | ❌ todo |
| GET  | /api/v1/admin/refunds/{ulid}              | Payments | 4.1 | sanctum-cookie+token | admin    | — | RefundResource | ❌ todo |
```

`Documented` flag flips to `✅ scribe` once the Scribe doc is generated and the Bruno collection lands.

### 8.5 Bruno collection

A new Bruno collection folder `docs/api/collections/payments/` will be created with five `.bru` files:
- `01_initiate_payment.bru`
- `02_show_payment.bru`
- `03_paymob_webhook.bru` (with sandbox HMAC signature for happy + tampered cases)
- `04_initiate_refund.bru` (admin)
- `05_show_refund.bru` (admin)

Each request uses `{{baseUrl}}` and `{{authToken}}` Bruno variables and includes the `Idempotency-Key` header on the two state-changing requests. EN + AR sample request bodies for the refund call.

### 8.6 Postman collection

The Bruno collection is exported as Postman v2.1 JSON to `docs/api/collections/payments.postman_collection.json` for the mobile/web teams.

---

## 9. Packages Used

All Composer packages used by this module are already in `docs/specs/10_Package_List.md`. **No new packages introduced.**

| Package | Version | Listed in `10_Package_List.md` | Usage in this feature |
|---|---|---|---|
| `laravel/framework` | `^12.0` | §1 Foundation | HTTP, validation, scheduler, queue |
| `laravel/sanctum` | `^4.0` | §1 Foundation | Auth on customer/admin endpoints |
| `predis/predis` | `^2.2` | §1 Foundation | Queue backing (events) |
| `spatie/laravel-translatable` | `^6.8` | §2 Catalog & Translatable | `failure_message`, `reason_notes` JSON columns |
| `spatie/laravel-permission` | `^6.10` | §2 Identity & Authorization | `payment.refund` admin permission |
| `brick/money` | `^0.10` | §2 Money | `MoneyCast` on `payments.amount`, `refunds.amount` |
| `spatie/laravel-data` | `^4.13` | §2 Booking & State Machines | DTOs (`InitiatePaymentDto`, `PaymobWebhookDto`, `InitiateRefundDto`) |
| `spatie/laravel-activitylog` | (already on the list) | §2 Audit | Audit trail on refund admin actions |
| `filament/filament` | `^3.x` | §3 Filament | `RefundResource` admin read-only view + Booking refund Action |
| `filament/spatie-laravel-translatable-plugin` | (curated) | §3 Filament plugins | EN/AR tabs on refund reason |
| `bezhansalleh/filament-shield` | (curated) | §3 Filament plugins | Auto-generates `view_refund`, `view_any_refund`, `payment.refund` |
| `pestphp/pest` | (dev) | §4 Dev / Quality | All Feature + Unit + Architecture tests |
| `pestphp/pest-plugin-laravel` | (dev) | §4 Dev / Quality | HTTP test helpers |
| `larastan/larastan` | (dev) | §4 Dev / Quality | Static analysis |
| `laravel/pint` | (dev) | §4 Dev / Quality | Style |

**Native PHP only (no package):**
- `hash_hmac('sha512', $payload, $secret)` for Paymob signature
- `hash_equals($expected, $received)` for constant-time comparison
- `Http::client()` (Laravel built-in) for outbound Paymob HTTP calls — no `guzzlehttp/guzzle` direct usage

**Architecture-test package:** Pest's built-in `tests/Architecture/` directory, no extra package.

> If during implementation the developer is tempted to install something else (e.g., a Paymob SDK), the rule is: stop, push back to Ibrahim, justify in one paragraph, update `10_Package_List.md` in the same commit. ADR-0005 §6.6 explicitly rejects external Paymob SDKs.

---

## 10. Architecture Tests (Added or Updated)

Lives under `tests/Architecture/`. All tests run on every commit via Pint+PHPStan+Pest pre-push hook.

| Test | Purpose | Existing or New |
|---|---|---|
| `tests/Architecture/PaymentsModuleNoCrossImportTest.php` | Asserts `App\Modules\Payments` does not `use` any Eloquent model from `Booking`, `Catalog`, `Identity`, `Settlement`, `Reviews`, `Communication` | **New** |
| `tests/Architecture/PaymentLoggingRedactionTest.php` | Asserts every write to `payment_attempts.request_payload`, `payment_attempts.response_payload`, `gateway_webhook_logs.payload` passes through the `RedactPciFields` helper. Walks the AST of `PaymobGateway` and `ProcessPaymobWebhookAction` for raw insert calls. | **New** |
| `tests/Architecture/EventsFireAfterCommitTest.php` | Asserts no `event(` or `dispatch(` calls appear inside `DB::transaction` closures across the Payments module | **New** (extension of existing pattern) |
| `tests/Architecture/NoFloatForMoneyTest.php` | Existing repo-wide test; auto-covers Payments | Existing — **no change** |
| `tests/Architecture/NoIfElseOnProductTypeStringTest.php` | Existing repo-wide test; covers `RefundPolicyService` automatically | Existing — **no change** |
| `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` | Existing — verifies `payments`, `payment_attempts`, `gateway_webhook_logs` (the three append-only tables added here) have no `SoftDeletes` trait and no `deleted_at` column | Existing — **assertions extended** to include new tables |
| `tests/Architecture/IdempotencyMiddlewareCoverageTest.php` | Asserts every route matching `POST /api/v1/customer/bookings/{ulid}/payments`, `POST /api/v1/admin/bookings/{ulid}/refunds` has the `idempotency` middleware applied | **New** |
| `tests/Architecture/LocaleParityTest.php` | Existing — auto-covers new `Resources/lang/en/{payments,refunds}.php` and AR mirrors | Existing — **no change** |

---

## 11. Cut-List (inherited from `09_Phasing_Plan.md` Phases 4.0 + 4.1)

Defer in this priority order if budget slips:

1. **Automatic gateway-failure retry job for failed refunds** — Phase 1.5. Phase 1 admin manually retries via Filament action.
2. **Filament `RefundResource` polish** (advanced filters, exports, charts) — Phase 1.5. Ship basic table + view page only.
3. **Digital `is_refundable_after_delivery` flag precision** — fall back to "all digital refunds blocked after `delivered`" if Catalog can't implement `PaymentsCatalogReader` in time. Documented as a known gap; full fidelity in Phase 1.5.
4. **Customer-facing payment page polish** (the `redirect_url` we return is currently a raw Paymob hosted-checkout URL — UX team may want a wrapper page in Phase 1.5).

Permanently excluded from Phase 1 (Constitution §"Phase 1 Forbidden Features"):

- ❌ Split payments (one `payments` row per booking, enforced in `InitiatePaymentAction`)
- ❌ Partial refunds (full only — `amount_minor` MUST equal `payments.amount_minor`)
- ❌ GCC adapters (Tabby, Tamara, HyperPay) — Phase 2 only; `PaymentGateway` interface keeps the slot open
- ❌ Multi-currency activation — runtime rejects non-EGP `amount_currency`; schema columns stay
- ❌ Customer self-serve refund / cancel-and-refund — admin-only per spec clarification

---

## Project Structure

### Documentation (this feature)

```
specs/007-payments-paymob-refunds/
├── spec.md              ✅ created — /speckit.specify
├── plan.md              ✅ this file — /speckit.plan
├── research.md          ➡ Phase 0 (next)
├── data-model.md        ➡ Phase 1
├── quickstart.md        ➡ Phase 1
├── contracts/           ➡ Phase 1 — OpenAPI fragments + Bruno collection skeleton
├── tasks.md             ⏳ /speckit.tasks (after ADR-0005 acceptance)
└── checklists/
    └── requirements.md  ✅ created — /speckit.specify
```

### Source code (repository root — module layout per ADR-0005 §5)

```
app/Modules/Payments/
├── Domain/
│   ├── Models/                    # Payment, PaymentAttempt, Refund, IdempotencyKey, GatewayWebhookLog
│   ├── Enums/                     # PaymentStatus, PaymentMethod, RefundStatus, RefundReasonCode
│   ├── Events/                    # PaymentInitiated, PaymentCaptured, PaymentFailed, RefundCompleted, RefundFailed
│   ├── ValueObjects/              # RefundPolicy
│   └── Contracts/                 # PaymentGateway
├── Application/
│   ├── Actions/                   # 6 Action classes (see ADR-0005 §5)
│   ├── Services/                  # RefundPolicyService
│   ├── DTOs/                      # InitiatePaymentDto, PaymobWebhookDto, InitiateRefundDto
│   └── Listeners/                 # placeholders; Booking module owns listeners that mutate booking.payment_status
├── Infrastructure/
│   ├── Repositories/              # Eloquent{Payment,Refund,IdempotencyKey}Repository
│   └── Gateways/                  # PaymobGateway
├── Http/
│   ├── Controllers/               # Customer/Webhook/Admin controllers, 3-line bodies
│   ├── Requests/                  # 3 Form Requests
│   ├── Resources/                 # PaymentResource, RefundResource
│   └── Middleware/                # IdempotencyKeyMiddleware
├── Filament/
│   └── Resources/                 # RefundResource (read-only)
├── Routes/
│   ├── customer.php
│   ├── admin.php
│   └── webhook.php
├── Database/
│   └── Migrations/                # 5 migrations in order above
├── Resources/
│   └── lang/
│       ├── en/{payments,refunds}.php
│       └── ar/{payments,refunds}.php
└── Providers/
    └── PaymentsServiceProvider.php
```

**Structure Decision:** Modular monolith per ADR-0001. Payments module follows the canonical layer layout. Booking and Catalog references go through their respective module Contracts.

---

## Phase 0 — Outline & Research

All "NEEDS CLARIFICATION" items were resolved during `/speckit.clarify` (2026-05-02). No further unknowns remain. The five Q&A bullets are recorded in `spec.md` § Clarifications and reflected in ADR-0005 §6 internal decisions.

A separate **`research.md`** will document the implementation-level investigations (Phase 0 output):

- **Paymob HMAC field ordering** — exact list of fields concatenated for the SHA-512 HMAC, with sandbox vs production differences.
- **Paymob sandbox test cards** — set of card numbers for happy / declined / 3DS-required scenarios.
- **Paymob webhook retry behaviour** — backoff schedule and how InstaParty's idempotency interacts.
- **Idempotency middleware concurrency proof technique** — how to write a Pest test for 10 concurrent requests on Windows without `pcntl_fork` (use `\React\Async\parallel` or HTTP test client with `Http::pool`).
- **Sweep job scheduling** — Laravel scheduler vs queue cron worker on DigitalOcean App Platform.

`research.md` is generated next as part of this `/speckit.plan` invocation.

---

## Phase 1 — Design & Contracts

Will produce:

1. **`data-model.md`** — entity-relationship view of all 5 tables + cross-module FKs + the `RefundPolicy` value object shape.
2. **`contracts/openapi-payments.yaml`** — OpenAPI 3.1 fragment for the 5 endpoints, importable into Postman / Bruno.
3. **`contracts/paymob-webhook-schema.json`** — JSON-schema of the canonical Paymob webhook payload InstaParty validates against.
4. **`quickstart.md`** — step-by-step "happy path" runbook: `composer install` → migrations → seed admin → Paymob sandbox `.env` → run `php artisan serve` → exercise the 5 endpoints with Bruno.
5. **Agent context update** via `.specify/scripts/powershell/update-agent-context.ps1 -AgentType claude` so future sessions know Payments is in flight.

Re-evaluation of Constitution Check after Phase 1: PASS expected. No design choice violates principles I-XI given the constraints captured in §2 above.

---

## Complexity Tracking

> No constitution violations to justify — every gate passes.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| _(none)_ | — | — |

The single soft-deviation worth naming is **§4: cross-type infrastructure with per-type policy** — i.e., we use one `RefundPolicyService` with `match($enum)` instead of three `Initiate{Type}RefundAction` classes. ADR-0005 §6.5 records the rationale (uniform `refunds` data shape, single state machine, triplication would add no signal). This is consistent with constitution principle II (`match($enum)` is the prescribed pattern for cross-type code), not a violation.
