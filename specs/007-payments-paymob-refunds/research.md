# Research — Payments Module (Phase 4.0 + 4.1)

**Feature**: `specs/007-payments-paymob-refunds`
**Date**: 2026-05-02
**Phase**: 0 (Outline & Research)
**Status**: Complete — all `NEEDS CLARIFICATION` resolved

All open questions from `/speckit.clarify` are answered in `spec.md § Clarifications`. This file documents the **implementation-level** investigations needed before writing code: the things we'd otherwise have to discover mid-build by trial and error.

---

## R-1. Paymob HMAC field ordering

**Decision:** Use Paymob's documented HMAC-SHA512 over a concatenation of these fields **in this exact order**, joined with no separator:

```
amount_cents + created_at + currency + error_occured + has_parent_transaction
+ id + integration_id + is_3d_secure + is_auth + is_capture + is_refunded
+ is_standalone_payment + is_voided + order.id + owner + pending
+ source_data.pan + source_data.sub_type + source_data.type + success
```

The HMAC secret used for verification is `config('services.paymob.hmac_secret')` (loaded from `PAYMOB_HMAC_SECRET` env var — sandbox vs production secrets are separate keys).

**Rationale:** This is Paymob's official HMAC scheme as of their MENA-region API docs (`https://docs.paymob.com/docs/hmac-calculation`). Any deviation in field set or ordering produces a different hash and rejects valid callbacks.

**Alternatives considered:**
- **Subset of fields (e.g., only `id + amount_cents + success`)** — rejected because Paymob's signature is computed over the full set; partial verification would accept tampered payloads.
- **Sorting fields alphabetically** — rejected; Paymob uses the documented order, not alphabetic.

**Implementation note:** `PaymobGateway::verifyWebhookSignature($payload, $hmacHeader)` builds the concatenation via a private constant array of field accessors, runs `hash_hmac('sha512', $concat, $secret)`, then `hash_equals($expected, $hmacHeader)`. Constant-time comparison is non-negotiable.

---

## R-2. Paymob sandbox test cards

**Decision:** Use Paymob's published sandbox card matrix:

| Scenario | Card | CVV | Expiry | Outcome |
|---|---|---|---|---|
| Successful capture | `5123 4567 8901 2346` | `123` | `12/2030` | `success: true` callback |
| Insufficient funds | `4000 0000 0000 0002` | `123` | `12/2030` | `success: false`, `data.message: "Insufficient funds"` |
| 3DS required | `4111 1111 1111 1111` | `123` | `12/2030` | OTP challenge, then success |
| Declined by issuer | `4000 0000 0000 0119` | `123` | `12/2030` | `success: false`, declined |
| Network error simulation | (timeout — close browser tab during 3DS) | — | — | No callback fires; sweep job ages row to `failed` |

**Rationale:** Pest tests for the happy path use a deterministic mocked `PaymobGateway` (no real HTTP calls). The integration-level smoke test in `quickstart.md` uses these sandbox cards to prove end-to-end behavior in CI's nightly job.

**Alternatives considered:**
- **Stub-only testing (no sandbox)** — rejected; spec exit criterion SC-001 explicitly requires "Test card succeeds in Paymob sandbox end-to-end."
- **Hitting production with a real card and refunding immediately** — obviously rejected.

---

## R-3. Paymob webhook retry behaviour

**Decision:** Paymob retries failed webhook deliveries (non-2xx response) with exponential backoff: `0s → 30s → 5min → 30min → 2h → 6h → 24h` (7 attempts, ~32h total). Our endpoint must:

1. Return `200 OK` quickly (< 5s) once HMAC is verified — even if downstream listener queue is backlogged.
2. Be idempotent on `(gateway, gateway_ref)` — replays must not double-mutate `payments.status`.
3. Return `401 Unauthorized` for invalid signatures (Paymob will NOT retry these — they're treated as configuration errors).
4. Return `200 OK` even when `gateway_ref` is unknown (logged with `processing_error`); returning 5xx would trap Paymob in an infinite retry storm against a payment that doesn't exist on our side.

**Rationale:** The `(gateway, gateway_ref)` UNIQUE index plus the status-machine guard (`captured` cannot transition back to `captured`) makes webhook processing exactly-once at the database level. Spec User Story 2 acceptance scenario 3 codifies the unknown-ref handling.

**Alternatives considered:**
- **Returning 5xx on unknown ref to "trigger retry"** — rejected; creates retry storms when Paymob misroutes a webhook for a different merchant. Better to log and 200.
- **Storing the full HMAC-verified payload BEFORE checking `gateway_ref`** — accepted; `gateway_webhook_logs` is appended unconditionally so we have a forensic trail even for unmatched callbacks.

---

## R-4. Idempotency concurrency-proof technique on Windows

**Decision:** Use Laravel's `Http::pool()` to fire 10 simulated concurrent requests against the in-memory test app:

```php
it('serializes 10 concurrent requests with the same idempotency key', function () {
    $key = 'test-key-' . Str::random(8);
    $responses = Http::pool(fn (Pool $pool) => collect(range(1, 10))->map(
        fn () => $pool->withHeaders(['Idempotency-Key' => $key, ...])
            ->post(route('customer.payments.initiate', $booking))
    ));

    expect($responses)->toHaveCount(10);
    expect(collect($responses)->pluck('json.data.payment.public_id')->unique())->toHaveCount(1);
    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
});
```

**Rationale:** The development environment is Windows (per session context), where `pcntl_fork` is not available. `Http::pool()` uses Guzzle's parallel execution and is sufficient for proving the middleware's lock semantics in a deterministic test run.

**Alternatives considered:**
- **`pcntl_fork`** — rejected; not available on Windows (the active dev environment).
- **`\React\Async\parallel`** — rejected; adds a runtime dependency that's not in `10_Package_List.md`.
- **Separate process spawning via `Symfony\Process\Process`** — works but slow and brittle in CI; only fall back to this if `Http::pool()` proves insufficient.

---

## R-5. Sweep job scheduling

**Decision:** `ExpirePendingPaymentsAction` is invoked by Laravel's scheduler every 15 minutes:

```php
// app/Console/Kernel.php
$schedule->call(fn () => app(ExpirePendingPaymentsAction::class)->execute())
    ->everyFifteenMinutes()
    ->onOneServer()        // requires lock store (Redis is configured)
    ->withoutOverlapping();
```

In production on DigitalOcean App Platform: a single dedicated worker process runs `php artisan schedule:work` continuously; the scheduler's `onOneServer()` lock prevents duplicate execution if the worker is ever scaled to >1 replica.

**Rationale:** Constitution doesn't dictate a queue/scheduler split. Laravel's scheduler is the simplest path for cron-like recurring work, and the existing Redis lock store handles the distributed-lock concern. Daily `idempotency_keys` cleanup uses the same scheduler.

**Alternatives considered:**
- **OS-level cron calling `php artisan ...`** — rejected; couples deployment to host config and breaks on App Platform's ephemeral filesystem semantics.
- **A queue job that re-enqueues itself with `delay(15 * 60)`** — works but harder to reason about and more failure modes (orphaned chains).

---

## R-6. Webhook payload redaction allowlist

**Decision:** A `RedactPciFields` helper applies an allowlist before any `payment_attempts.request_payload`, `payment_attempts.response_payload`, or `gateway_webhook_logs.payload` insert. Only these top-level keys (and their nested children) survive:

```
order_id, amount_cents, currency, merchant_order_id, integration_id,
payment_key_token, transaction_id, success, error_occured, captured_amount,
created_at, has_parent_transaction, is_3d_secure, is_auth, is_capture,
is_refunded, is_standalone_payment, is_voided, owner, pending,
hmac, source_data.sub_type, source_data.type
```

**Explicitly redacted (replaced with `"[REDACTED]"`):** `source_data.pan`, `source_data.cvv`, `source_data.expiry_month`, `source_data.expiry_year`, `email`, `phone_number`, `first_name`, `last_name`, anything containing `card_number`, `cardholder`, `cvv`.

**Rationale:** Keeps PCI-DSS scope at SAQ-A (ADR-0005 §6.1). Even though Paymob's hosted iframe means they shouldn't send us PAN, a future Paymob API change or a misconfiguration could leak data into a webhook payload. Belt-and-braces redaction is the architectural-test-enforced safety net.

**Alternatives considered:**
- **Denylist (redact specific keys)** — rejected; defaults to leaking unknown new fields. Allowlist defaults to safe.
- **Storing nothing at all in `payload`** — rejected; we lose forensic capability when Paymob disputes a transaction.

---

## R-7. Locale strategy for `failure_message`

**Decision:** When the gateway returns a failure, the listener (or Action) populates `payments.failure_message` with both EN and AR translations sourced from a static mapping in `app/Modules/Payments/Resources/lang/{locale}/failures.php`. Paymob's raw `data.message` field is NOT shown to end users — it's logged in `payment_attempts.response_payload` for engineers only.

Mapping example:
```php
// en/failures.php
return [
    'insufficient_funds'  => 'Your card was declined for insufficient funds.',
    'declined_by_issuer'  => 'Your bank declined the transaction. Please contact them.',
    'expired_card'        => 'The card you used has expired.',
    'fraud_suspected'     => 'The transaction was blocked. Please try a different card.',
    'expired_payment_hold'=> 'The payment window expired. Please start a new payment.',
    'unknown'             => 'The payment could not be completed. Please try again.',
];
```

Mapping logic in `MapPaymobFailureCode`: pattern-match on Paymob's `data.message` (case-insensitive substring match) → InstaParty `failure_code` → translation key.

**Rationale:** Constitution IV requires both locales for any user-facing field. Paymob's messages are English-only (occasionally Arabic transliteration). Maintaining our own mapping ensures consistent EN+AR error UX and keeps PII / gateway-internals out of customer responses.

**Alternatives considered:**
- **Storing Paymob's raw message** — rejected; violates Constitution IV (no AR), leaks gateway internals.
- **Using Google Translate at runtime** — rejected; latency, cost, and quality unacceptable for transactional UX.

---

## R-8. PaymentGateway interface shape

**Decision:** The interface in `app/Modules/Payments/Domain/Contracts/PaymentGateway.php`:

```php
interface PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto;

    public function verifyWebhookSignature(array $payload, string $signature): bool;

    public function parseWebhook(array $payload): PaymobWebhookDto;

    public function refund(Payment $payment, Money $amount): RefundResultDto;
}
```

**Rationale:** Four methods cover all gateway lifecycle touchpoints: outbound initiate, inbound verify, inbound parse, outbound refund. `PaymentIntentDto` carries `redirect_url` + `gateway_ref` so the Action can persist them. `PaymobWebhookDto` is the canonical shape regardless of gateway. `RefundResultDto` carries `success`, `gateway_ref`, optional `failure_message`. Phase 2 Tabby/Tamara adapters implement the same four methods — ditto for any future gateway.

**Alternatives considered:**
- **Smaller interface (just `initiate` + `verifyWebhookSignature`)** — rejected; refund logic would have to know the gateway's HTTP shape, undermining the seam.
- **Larger interface with `void`, `authorize`, `capture` separate** — rejected; Paymob's hosted-checkout flow is auth-and-capture in one step; splitting adds null-method noise.

---

## R-9. RefundPolicy value object shape

**Decision:** Immutable value object in `app/Modules/Payments/Domain/ValueObjects/RefundPolicy.php`:

```php
final readonly class RefundPolicy
{
    public function __construct(
        public bool   $allowed,
        public string $reasonCode,        // e.g., 'rental_window_closed', 'sale_in_preparation'
        public string $reasonMessageKey,  // translation key, e.g., 'refunds.policy.rental_window_closed'
    ) {}

    public static function allowed(): self    { return new self(true, '', ''); }
    public static function denied(string $code, string $key): self { return new self(false, $code, $key); }
}
```

`InitiateRefundAction` calls `RefundPolicyService::policyFor($type, $bookingItem)`, then if `!$policy->allowed` throws `RefundPolicyViolationException` carrying `$policy->reasonMessageKey` for the `422` response (translated at the API Resource layer).

**Rationale:** Immutable, no setters, two static constructors for the only two states. Pest tests assert against the value object directly without touching HTTP — clean unit boundary.

**Alternatives considered:**
- **Boolean return from `policyFor`** — rejected; loses the reason-code information needed for translatable error messages.
- **Throwing inside `policyFor`** — rejected; coupling exception flow to policy logic makes unit testing awkward.

---

## R-10. Schedule for the 3-day work

| Day | Focus | Artifacts |
|---|---|---|
| **Day 1 (Phase 4.0 — gateway + webhook)** | Migrations, `PaymentGateway` interface, `PaymobGateway`, `IdempotencyKeyMiddleware`, webhook controller + HMAC verification | 5 migrations, `PaymobGateway`, middleware, webhook route + controller, `PaymentInitiated` / `PaymentCaptured` / `PaymentFailed` events, `gateway_webhook_logs` insert path, ADR-0005 moved to `Accepted` (Ibrahim gate) |
| **Day 2 (Phase 4.0 — Actions + tests)** | Actions, listeners, full Pest coverage | `InitiatePaymentAction`, `CapturePaymentAction`, `ProcessPaymobWebhookAction`, `ExpirePendingPaymentsAction`, listener wiring, `InitiatePaymentTest`, `PaymobWebhookTest`, `IdempotencyMiddlewareTest`, `ExpirePendingPaymentsJobTest`, architecture tests |
| **Day 3 (Phase 4.1 — refunds)** | Refund flow + 3 per-type test files + Filament action | `Refund` migration (already on Day 1), `RefundPolicyService`, `RefundPolicy` VO, `InitiateRefundAction`, `ProcessRefundAction`, `RefundResource` (Filament read-only), Booking refund Action wiring, `InitiateRefundRentalTest`, `InitiateRefundSaleTest`, `InitiateRefundDigitalTest`, `RefundPolicyServiceTest` (unit) |

Test-with-code rule (Constitution VII) is honored — no test deferral to a separate "test day."

---

## Summary

All Phase 0 unknowns resolved. No `NEEDS CLARIFICATION` items remain. Proceed to Phase 1 (data-model + contracts + quickstart).
