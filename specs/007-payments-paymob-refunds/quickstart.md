# Quickstart — Payments Module (Phase 4.0 + 4.1)

**Feature**: `specs/007-payments-paymob-refunds`
**Phase**: 1 (Design & Contracts)
**Audience**: Anyone running this feature for the first time after implementation lands.

This is the runbook for taking a brand-new clone and exercising the full happy path: customer pays a confirmed booking → webhook captures → admin issues refund.

---

## 1. Prerequisites

- PHP 8.3+, Composer, Node 20+
- MySQL 8 / MariaDB 11 running locally (or Docker)
- Redis running locally
- A Paymob sandbox account with:
  - `API_KEY`
  - `INTEGRATION_ID` (the card-payment integration)
  - `IFRAME_ID`
  - `HMAC_SECRET`
- A tunneling tool for the webhook (cloudflared, ngrok, or a public dev server) — Paymob can't reach `localhost`.

---

## 2. Environment

Add to `.env`:

```env
PAYMOB_API_KEY=eyJ0eXAiOiJKV1Qi...   # from Paymob dashboard
PAYMOB_INTEGRATION_ID=12345
PAYMOB_IFRAME_ID=67890
PAYMOB_HMAC_SECRET=abc123...
PAYMOB_BASE_URL=https://accept.paymob.com/api
PAYMOB_WEBHOOK_URL=https://<your-tunnel>.trycloudflare.com/api/v1/webhooks/paymob

QUEUE_CONNECTION=redis
SCHEDULE_TIMEZONE=UTC
```

Configure the same `PAYMOB_WEBHOOK_URL` in the Paymob dashboard under **Developers → Transaction Processed Callback**.

---

## 3. Bootstrap

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DatabaseSeeder      # seeds an admin + a sample confirmed booking
php artisan shield:generate --all               # registers payment.refund permission

# Background processes (run in separate terminals)
php artisan serve                               # API on :8000
php artisan queue:work redis --queue=default    # event listeners
php artisan schedule:work                       # sweep job + idempotency cleanup
cloudflared tunnel --url http://localhost:8000  # public webhook URL
```

---

## 4. Happy path — customer payment

Pre-condition: `php artisan db:seed` has created a confirmed booking with `payment_status = pending`.

### 4.1 Find the booking ULID

```bash
php artisan tinker
> \App\Modules\Booking\Domain\Models\Booking::where('lifecycle_status', 'confirmed')->first()->public_id;
# → "01HE5T7G9N5K2P7M9R4Q6V8Y0X"
```

### 4.2 Initiate payment (as customer)

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"phone_e164":"+201234567890","password":"password"}' | jq -r .data.token)

curl -s -X POST http://localhost:8000/api/v1/customer/bookings/01HE5T7G9N5K2P7M9R4Q6V8Y0X/payments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"method":"card"}' | jq
```

Expected response (201):

```json
{
  "data": {
    "payment": {
      "public_id": "01HE5T8K2P7M9R4Q6V8Y0X3Z2A",
      "booking_public_id": "01HE5T7G9N5K2P7M9R4Q6V8Y0X",
      "gateway": "paymob",
      "amount_minor": 125000,
      "amount_currency": "EGP",
      "method": "card",
      "status": "pending",
      "captured_at": null,
      "failure_message": null,
      "created_at": "2026-05-02T14:30:00Z"
    },
    "redirect_url": "https://accept.paymob.com/api/acceptance/iframes/67890?payment_token=eyJ0eXAi..."
  },
  "meta": {},
  "errors": []
}
```

### 4.3 Complete payment in browser

Open `redirect_url`, enter sandbox card `5123 4567 8901 2346`, CVV `123`, expiry `12/2030`. Submit.

Paymob returns to its hosted result page. **Do NOT rely on this redirect for state** — the webhook is the source of truth.

### 4.4 Watch the webhook fire

Tail the Laravel log:

```bash
tail -f storage/logs/laravel.log
```

You'll see (approximately):
```
[2026-05-02 14:32:12] local.INFO: Paymob webhook received {"event_type":"TRANSACTION","gateway_ref":"123456789","signature_valid":true}
[2026-05-02 14:32:12] local.INFO: Payment captured {"payment_public_id":"01HE5T8K..."}
[2026-05-02 14:32:13] local.INFO: Booking payment_status updated {"booking_public_id":"01HE5T7G...","payment_status":"paid"}
```

### 4.5 Verify

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/customer/payments/01HE5T8K2P7M9R4Q6V8Y0X3Z2A | jq .data.status
# "captured"
```

---

## 5. Idempotency proof

Re-run the initiate call with the **same** `Idempotency-Key`:

```bash
KEY=$(uuidgen)
for i in 1 2 3; do
  curl -s -X POST http://localhost:8000/api/v1/customer/bookings/01HE5T7G.../payments \
    -H "Authorization: Bearer $TOKEN" \
    -H "Idempotency-Key: $KEY" \
    -H "Content-Type: application/json" \
    -d '{"method":"card"}' | jq -r .data.payment.public_id
done
# Same public_id printed three times. Only one row in `payments`.
```

---

## 6. Refund (admin)

### 6.1 Log in as admin to Filament

Navigate to `http://localhost:8000/admin`, log in with the seeded admin credentials.

### 6.2 Open the paid booking

`Bookings` → click the row → `Refund` action (only visible if the admin has `payment.refund` permission).

A modal appears with:
- `reason_code` (Select): customer_request / vendor_cancellation / service_unavailable / duplicate_charge / admin_discretion
- `reason_notes` (translatable, EN tab + AR tab)

Submit. Behind the scenes:

```
POST /api/v1/admin/bookings/01HE5T7G.../refunds
  Idempotency-Key: <auto-generated>
  Content-Type: application/json

  { "reason_code": "customer_request",
    "reason_notes": { "en": "...", "ar": "..." } }
```

### 6.3 Verify

- Filament: booking `payment_status` flips to `refunded`.
- DB: a `refunds` row exists with `status = completed`.
- DB: `wallet_ledger` row will be created in Phase 4.2 (Settlement) — Phase 1 stops at the refund itself.

---

## 7. Per-type refund policy boundaries

Test each policy boundary with curl + a booking constructed to sit on the boundary:

| Booking shape | Expected outcome |
|---|---|
| 1 rental item, `event_starts_at = now + 25h`, `item_status = confirmed` | 201 — refund completes |
| 1 rental item, `event_starts_at = now + 24h`, `item_status = confirmed` | 201 — refund completes (≥24h is inclusive) |
| 1 rental item, `event_starts_at = now + 23h`, `item_status = confirmed` | 422 — `code: rental_window_closed` |
| 1 rental item, `item_status = setup` | 422 — `code: rental_in_setup` |
| 1 sale item, `item_status = confirmed` | 201 — refund completes |
| 1 sale item, `item_status = in_preparation` | 422 — `code: sale_in_preparation` |
| 1 digital item, `service.is_refundable_after_delivery=true`, `item_status=delivered` | 201 — refund completes |
| 1 digital item, `service.is_refundable_after_delivery=false`, `item_status=delivered` | 422 — `code: digital_post_delivery` |

Each `422` returns a translated error message in the `Accept-Language` locale.

---

## 8. Sweep job

A dev shortcut to age out a `pending` payment immediately:

```bash
php artisan tinker
> $p = \App\Modules\Payments\Domain\Models\Payment::where('status', 'pending')->first();
> $p->created_at = now()->subHours(25);
> $p->save();
> exit
```

Then trigger the sweep:

```bash
php artisan schedule:run
```

The payment moves to `failed` with `failure_code = expired_payment_hold`.

---

## 9. Run tests

```bash
./vendor/bin/pest --group=payments
./vendor/bin/pest --group=rental         # refund-policy rental boundaries
./vendor/bin/pest --group=sale
./vendor/bin/pest --group=digital
./vendor/bin/pest tests/Architecture/    # arch tests
```

Expected: all green.

---

## 10. Webhook signature debugging

If a webhook is rejected with `401 invalid_signature`:

1. Check `gateway_webhook_logs` — the row is appended even on failure.
2. Compare the computed HMAC vs the received `HMAC` header (both in the log — never card data).
3. Common causes: wrong `PAYMOB_HMAC_SECRET` env var, mismatched sandbox vs production secrets, payload field tamper (especially `amount_cents`).

To re-deliver a webhook for testing, use the Paymob dashboard's "Re-send" action on the transaction detail page. This proves R-3 (idempotency on `(gateway, gateway_ref)` UNIQUE).

---

## 11. Known gaps (Phase 1)

- No customer-facing payment-status polling endpoint — clients are expected to poll `GET /customer/payments/{ulid}` themselves until status flips.
- No real-time push to the customer when payment captures (Reverb broadcast comes in Phase 5.0).
- No retry job for `refunds.status = failed` — admin manually retries via Filament.
- No partial refunds — full refund only.
- No customer-initiated cancel-and-refund — admin only.

These are documented in `spec.md` cut-list and `plan.md` §11.
