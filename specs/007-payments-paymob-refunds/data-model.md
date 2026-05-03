# Data Model — Payments Module

**Feature**: `specs/007-payments-paymob-refunds`
**Phase**: 1 (Design & Contracts)
**Source schemas:** `docs/specs/11_DB_Schema.md` §6 (Payments — 5 tables) — exactly mirrored here.

---

## Entity-Relationship Overview

```
                        ┌──────────────┐
                        │    users     │  (Identity module)
                        └──────┬───────┘
                               │
                ┌──────────────┼──────────────────────────────┐
                │              │                              │
                ▼              ▼                              ▼
        ┌────────────┐  ┌─────────────┐              ┌────────────────┐
        │  payments  │  │   refunds   │              │ idempotency_   │
        │  (5.3.1)   │  │   (5.3.3)   │              │ keys (5.3.4)   │
        └─────┬──────┘  └──────┬──────┘              └────────────────┘
              │                │
              │ ┌──────────────┘
              │ │
              ▼ ▼
        ┌─────────────┐                 ┌──────────────────┐
        │  bookings   │                 │ payment_attempts │
        │ (Booking)   │                 │     (5.3.2)      │
        └─────────────┘                 └──────────────────┘

                                        ┌────────────────────┐
                                        │ gateway_webhook_   │
                                        │   logs (5.3.5)     │
                                        └────────────────────┘
                                        (no FK — standalone audit)
```

---

## 1. Entities

### 1.1 `Payment`

Eloquent model: `App\Modules\Payments\Domain\Models\Payment`

**Attributes:**

| Field | Type | Source column | Notes |
|---|---|---|---|
| `id` | `int` | `id` BIGINT | Internal, never exposed |
| `publicId` | `string` (ULID, 26) | `public_id` | API-facing |
| `bookingId` | `int` | `booking_id` | FK→Booking |
| `userId` | `int` | `user_id` | Payer |
| `gateway` | `string` | `gateway` | `paymob` in Phase 1 |
| `gatewayRef` | `string` | `gateway_ref` | Paymob transaction id |
| `amount` | `Brick\Money\Money` | `amount_minor` + `amount_currency` | Cast via `MoneyCast` |
| `method` | `PaymentMethod` (enum) | `method` | `card` / `wallet` / `installment` / `cash_on_delivery` / `transfer` |
| `status` | `PaymentStatus` (enum) | `status` | State machine — see §3.1 |
| `capturedAt` | `?Carbon` | `captured_at` | Null until webhook flips to `captured` |
| `failureCode` | `?string` | `failure_code` | e.g., `expired_payment_hold`, `insufficient_funds` |
| `failureMessage` | `?array` | `failure_message` JSON | Translatable EN+AR |
| `metadata` | `?array` | `metadata` JSON | Free-form gateway echo |

**Relationships:**
- **No** `belongsTo(Booking::class)` Eloquent relationship — Constitution I forbids the model import. Booking lookups go through the injected `PaymentsBookingReader` contract (returns `PaymentBookingReadDto`). The DB-level FK is preserved.
- `belongsTo(User::class)` — via Identity contract
- `hasMany(PaymentAttempt::class)`
- `hasOne(Refund::class)` — Phase 1 enforces 1:0..1

**Casts:**
```php
protected $casts = [
    'amount'           => MoneyCast::class,
    'method'           => PaymentMethod::class,
    'status'           => PaymentStatus::class,
    'failure_message'  => 'array',
    'metadata'         => 'array',
    'captured_at'      => 'datetime',
];

protected $translatable = ['failure_message'];
```

**Append-only invariants:** Only `status`, `failure_code`, `failure_message`, `captured_at` are mutable post-insert. Architecture test enforces.

---

### 1.2 `PaymentAttempt`

Eloquent model: `App\Modules\Payments\Domain\Models\PaymentAttempt`

**Attributes:**

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | `int` | `id` | |
| `paymentId` | `int` | `payment_id` | FK→Payment |
| `attemptNo` | `int` | `attempt_no` | Monotonic per payment |
| `requestPayload` | `array` | `request_payload` JSON | Redacted via PCI allowlist |
| `responsePayload` | `?array` | `response_payload` JSON | Redacted |
| `httpStatus` | `?int` | `http_status` | |
| `createdAt` | `Carbon` | `created_at` | `useCurrent()` |

**No `updated_at`. Fully immutable. No `deleted_at`.**

---

### 1.3 `Refund`

Eloquent model: `App\Modules\Payments\Domain\Models\Refund`

**Attributes:**

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | `int` | `id` | |
| `publicId` | `string` (ULID) | `public_id` | API-facing |
| `paymentId` | `int` | `payment_id` | FK→Payment |
| `bookingId` | `int` | `booking_id` | FK→Booking (denormalized for reporting) |
| `amount` | `Brick\Money\Money` | `amount_minor` + `amount_currency` | Phase 1: equals `payment.amount` |
| `reasonCode` | `RefundReasonCode` (enum) | `reason_code` | 5 fixed values |
| `reasonNotes` | `?array` | `reason_notes` JSON | Translatable EN+AR |
| `gatewayRef` | `?string` | `gateway_ref` | Set when gateway accepts |
| `status` | `RefundStatus` (enum) | `status` | State machine — see §3.2 |
| `initiatedBy` | `int` | `initiated_by` | Admin user id |
| `processedAt` | `?Carbon` | `processed_at` | |

**Relationships:**
- `belongsTo(Payment::class)`
- `belongsTo(Booking::class)` — via contract
- `belongsTo(User::class, 'initiated_by')` — via Identity contract

**Casts:**
```php
protected $casts = [
    'amount'        => MoneyCast::class,
    'reason_code'   => RefundReasonCode::class,
    'status'        => RefundStatus::class,
    'reason_notes'  => 'array',
    'processed_at'  => 'datetime',
];

protected $translatable = ['reason_notes'];
```

---

### 1.4 `IdempotencyKey`

Eloquent model: `App\Modules\Payments\Domain\Models\IdempotencyKey`

**Attributes:**

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | `int` | | |
| `key` | `string` | `key` | Header value, max 190 |
| `userId` | `?int` | `user_id` | Null for unauthenticated routes (none in Phase 1) |
| `route` | `string` | `route` | `\Route::currentRouteName()` |
| `requestHash` | `string` (64) | `request_hash` | SHA-256 of body |
| `responseStatus` | `?int` | `response_status` | |
| `responseBody` | `?array` | `response_body` JSON | Replayed verbatim |
| `expiresAt` | `Carbon` | `expires_at` | `now + 24h` |
| `createdAt` | `Carbon` | `created_at` | |

**No `updated_at`, no `deleted_at`.** Daily cleanup job purges where `expires_at < now()`.

---

### 1.5 `GatewayWebhookLog`

Eloquent model: `App\Modules\Payments\Domain\Models\GatewayWebhookLog`

**Attributes:**

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | `int` | | |
| `gateway` | `string` | `gateway` | `paymob` |
| `eventType` | `string` | `event_type` | e.g., `transaction_processed` |
| `signatureValid` | `bool` | `signature_valid` | |
| `payload` | `array` | `payload` JSON | Redacted via PCI allowlist |
| `processedAt` | `?Carbon` | `processed_at` | Null if processing errored |
| `processingError` | `?string` | `processing_error` | TEXT |
| `createdAt` | `Carbon` | `created_at` | |

**Fully immutable.** No `updated_at`, no `deleted_at`.

---

## 2. Value Objects (no DB persistence)

### 2.1 `RefundPolicy`

```php
final readonly class RefundPolicy {
    public function __construct(
        public bool   $allowed,
        public string $reasonCode,
        public string $reasonMessageKey,
    ) {}

    public static function allowed(): self;
    public static function denied(string $code, string $key): self;
}
```

Returned by `RefundPolicyService::policyFor(ProductType, BookingItem)`. Phase 1 reason codes:

| Reason code | Translation key | When |
|---|---|---|
| `rental_window_closed` | `refunds.policy.rental_window_closed` | Rental item `event_starts_at < now + 24h` |
| `rental_in_setup` | `refunds.policy.rental_in_setup` | Rental item `item_status = setup` |
| `sale_in_preparation` | `refunds.policy.sale_in_preparation` | Sale item `item_status >= in_preparation` |
| `digital_post_delivery` | `refunds.policy.digital_post_delivery` | Digital item `item_status = delivered` AND `service.is_refundable_after_delivery = false` |

### 2.2 `PaymentIntentDto`

```php
final readonly class PaymentIntentDto {
    public function __construct(
        public string $gatewayRef,
        public string $redirectUrl,
        public ?array $rawResponse = null,  // for payment_attempts logging (redacted)
    ) {}
}
```

### 2.3 `PaymobWebhookDto`

```php
final readonly class PaymobWebhookDto {
    public function __construct(
        public string $gatewayRef,
        public bool   $success,
        public ?string $failureCode = null,
        public ?Money $capturedAmount = null,
        public array  $rawPayload = [],  // for gateway_webhook_logs (redacted)
    ) {}
}
```

### 2.4 `RefundResultDto`

```php
final readonly class RefundResultDto {
    public function __construct(
        public bool   $success,
        public ?string $gatewayRef = null,
        public ?string $failureMessage = null,
    ) {}
}
```

---

## 3. State Machines

### 3.1 `PaymentStatus`

```
                ┌─────────────┐
                │   pending   │ ← initial state on row insert
                └──────┬──────┘
                       │
       ┌───────────────┼───────────────┐
       │               │               │
       ▼               ▼               ▼
  ┌──────────┐   ┌──────────┐   ┌─────────────┐
  │ captured │   │  failed  │   │  authorized │   ← (auth-only — unused in Phase 1)
  └─────┬────┘   └──────────┘   └──────┬──────┘
        │                              │
        ▼                              ▼
  ┌──────────────┐                ┌──────────┐
  │   refunded   │ ←──── gateway  │ captured │
  │ partially_   │       refund   └──────────┘
  │   refunded   │
  └──────────────┘

  voided → only on auth-then-void path (unused in Phase 1)
```

Transitions used in Phase 1:
- `pending → captured` (successful webhook)
- `pending → failed` (failed webhook OR sweep job — `failure_code = 'expired_payment_hold'`)
- `captured → refunded` (refund completed; full only)

### 3.2 `RefundStatus`

```
  ┌─────────┐    ┌────────────┐    ┌───────────┐
  │ pending │ →  │ processing │ →  │ completed │
  └─────────┘    └─────┬──────┘    └───────────┘
                       │
                       ▼
                 ┌──────────┐
                 │  failed  │
                 └──────────┘
```

`pending → processing` happens immediately when `ProcessRefundAction` calls the gateway. Phase 1 has no manual approval step (admin clicks Refund → gateway called).

---

## 4. Cross-module read-only references

The Payments module reads (never writes) these tables via Contract interfaces (no model imports):

| Table | Field(s) read | Contract method |
|---|---|---|
| `bookings` | `id`, `public_id`, `lifecycle_status`, `payment_status`, `total_minor`, `total_currency`, `customer_id`, `payment_hold_expires_at` | `PaymentsBookingReader::findByPublicId(string $ulid): ?PaymentBookingReadDto` (Payments-owned contract; Booking implements) |
| `booking_items` | `id`, `booking_id`, `product_type`, `event_starts_at`, `item_status`, `service_id` | `PaymentsBookingReader::itemsFor(int $bookingId): array<int, PaymentBookingItemReadDto>` |
| `bookings.payment_hold_expires_at` | timestamp used by sweep job | `PaymentsBookingReader::staleHoldBookingIds(): array<int>` |
| `services` (digital details) | `service_digital_details.is_refundable_after_delivery` | `PaymentsCatalogReader::isDigitalRefundableAfterDelivery(int $serviceId): bool` (Payments-owned contract; Catalog implements) |

Booking and Catalog modules expose these via their `Domain\Contracts\` interfaces. If those contracts don't exist yet at implementation time, the cut-list (plan §11) defers the most-affected feature (digital refund flag precision).

---

## 5. Validation rules (per Form Request)

### `InitiatePaymentRequest`
| Field | Rule |
|---|---|
| `method` | `required`, `string`, `Rule::in(['card'])` (Phase 1 — only `card`; other methods deferred) |

Header: `Idempotency-Key` (required), `Accept-Language` (`en` or `ar`).

### `PaymobWebhookRequest`
- No body validation (Paymob's payload shape varies); HMAC verification is the gate.
- The Form Request only verifies `Content-Type: application/json` and the presence of an HMAC header.

### `InitiateRefundRequest`
| Field | Rule |
|---|---|
| `reason_code` | `required`, `string`, `Rule::in(RefundReasonCode::values())` |
| `reason_notes` | `required`, `array` |
| `reason_notes.en` | `required`, `string`, `max:500` |
| `reason_notes.ar` | `required`, `string`, `max:500` |

Header: `Idempotency-Key` (required).

---

## 6. Indexes (recap from Schema §6)

| Table | Index | Purpose |
|---|---|---|
| `payments` | UNIQUE `(gateway, gateway_ref)` | DB-level idempotency on captures |
| `payments` | `(booking_id, status)` | Lookup payment for a booking |
| `payments` | `(status, created_at)` | Sweep job query |
| `payment_attempts` | `(payment_id, attempt_no)` | Read attempt history per payment |
| `refunds` | `(payment_id)` | One refund per payment (Phase 1) |
| `refunds` | `(booking_id, status)` | Reporting |
| `refunds` | `(status, created_at)` | Admin queue |
| `idempotency_keys` | UNIQUE `(key)` | Lookup |
| `idempotency_keys` | `(expires_at)` | Cleanup sweep |
| `idempotency_keys` | `(user_id, route)` | Forensics |
| `gateway_webhook_logs` | `(gateway, signature_valid, created_at)` | Admin audit |
| `gateway_webhook_logs` | `(event_type, created_at)` | Trend analysis |
