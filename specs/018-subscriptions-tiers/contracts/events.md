# Domain Events & Outbox Contracts — Subscriptions

**Feature**: 018-subscriptions-tiers
**Date**: 2026-05-03

All events fire **after** `DB::transaction` commit via `DB::afterCommit()`. Each event is also written to the existing `event_outbox` table (transactional outbox pattern, append-only) with the payload below.

---

## SubscriptionEventType enum (canonical 14 values)

Stored in `subscription_audit.event_type` and as the `event_key` on `event_outbox`.

| Value | Fired when | Actor |
|---|---|---|
| `subscription.created` | Free auto-enrolment after `VendorRegistered` | `system` |
| `subscription.activated` | Paid invoice captured → subscription becomes `active` | `system` |
| `subscription.upgraded` | Vendor moves from a lower paid tier to a higher paid tier | `vendor` |
| `subscription.downgraded` | Vendor moves to a lower paid tier (effective at period end) | `vendor` |
| `subscription.cancel_requested` | Vendor sets `cancel_at_period_end = true` | `vendor` |
| `subscription.cancelled` | Subscription reaches terminal `cancelled` state | `system` or `admin` |
| `subscription.renewal_attempt_failed` | Renewal payment attempt failed (per-attempt) | `system` |
| `subscription.past_due` | First failed renewal — entered grace window | `system` |
| `subscription.renewed` | Renewal succeeded → period rolled forward | `system` |
| `subscription.expired` | Grace window elapsed → moved to terminal `expired`; Free auto-attached | `system` |
| `subscription.superseded` | Replaced by a new subscription (upgrade/downgrade switchover) | `system` |
| `subscription.tier_changed` | Effective tier changed (covers upgrade, downgrade, override apply, override end). Carries `from_tier`, `to_tier`, `source`. | varies |
| `subscription.admin_override_applied` | Admin applied or replaced an override | `admin` |
| `subscription.admin_override_ended` | Admin ended an override OR override expiry sweep ran | `admin` or `system` |

---

## Event payload schema (common envelope)

```json
{
  "event_id": "01J9P3RX...ULID",
  "event_key": "subscription.activated",
  "occurred_at": "2026-05-03T14:21:09Z",
  "vendor_subscription_id": 12345,
  "vendor_subscription_public_id": "01J9P3R7XY4Q5GH7B8N9C0VWQE",
  "vendor_profile_id": 8821,
  "subscription_plan_id": 3,
  "plan_code": "silver",
  "actor": { "type": "system", "id": null },
  "data": { /* event-specific fields */ },
  "version": 1
}
```

---

## Event-specific `data` fields

### `subscription.activated`
```json
{ "billing_cycle": "yearly", "current_period_start": "...", "current_period_end": "...", "invoice_public_id": "..." }
```

### `subscription.renewed`
```json
{ "previous_period_end": "...", "new_period_end": "...", "invoice_public_id": "...", "mode": "recurring_token" }
```

### `subscription.renewal_attempt_failed`
```json
{ "attempt_no": 2, "failure_code": "card_declined", "failure_reason": "Insufficient funds", "next_retry_at": "..." }
```

### `subscription.past_due`
```json
{ "grace_period_ends_at": "..." }
```

### `subscription.expired`
```json
{ "downgraded_to_plan_code": "free", "auto_paused_service_count": 7 }
```

### `subscription.cancelled`
```json
{ "reason": "...", "effective_at": "current_period_end" | "immediate" }
```

### `subscription.tier_changed`
```json
{ "from_plan_code": "silver", "to_plan_code": "premium", "source": "admin_override" | "vendor_upgrade" | "vendor_downgrade" | "expiry" }
```

### `subscription.admin_override_applied`
```json
{ "to_plan_code": "premium", "reason": "Beta partner", "expires_at": "2026-08-01T00:00:00Z" }
```

### `subscription.admin_override_ended`
```json
{ "from_plan_code": "premium", "reason": "expiry_sweep" | "manual_admin", "underlying_plan_code": "silver" }
```

---

## Listeners (subscribers)

| Listener (this module) | Reacts to | Effect |
|---|---|---|
| `Subscriptions\Application\Listeners\OnVendorRegistered` | `Identity\Domain\Events\VendorRegistered` | calls `AutoEnrolFreeTierAction` |
| `Subscriptions\Application\Listeners\OnPaymentCaptured` | `Payments\Domain\Events\PaymentCaptured` | when payable_type = subscription_invoice → calls `HandleSubscriptionPaymentCapturedAction` |
| `Subscriptions\Application\Listeners\OnSubscriptionExpired` | `Subscriptions\Domain\Events\SubscriptionExpired` | calls `PauseExcessServicesAction` |

| Listener (other modules) | Reacts to | Effect |
|---|---|---|
| `Communication\Application\Listeners\DispatchSubscriptionNotification` | every `subscription.*` event from outbox | dispatches the matching `notification_templates.event_key` |
| `Reporting\Application\Listeners\TrackSubscriptionAnalytics` (deferred to Phase 6) | every `subscription.*` event | append to `analytics_events` |

---

## Outbox guarantees

- Every event listed above lands in `event_outbox` within the same transaction as the state change.
- The outbox dispatcher uses the existing `(status, next_retry_at)` poll index (per `schema-cheatsheet.md`).
- `subscription_audit` is written **inside** the transaction; the outbox row is also written **inside** the transaction; the queued listener runs **after** commit. This keeps audit and outbox consistent regardless of post-commit failures.

---

## Idempotency between renewal-job and webhook

When the renewal job initiates a saved-token charge, the webhook for the captured payment may arrive before or after the job's own success path returns. Guards:

1. `subscription_payments` UNIQUE on `(subscription_invoice_id, attempt_no)` — duplicate insert prevented.
2. `subscription_invoices.status` transition `pending → paid` is gated by an optimistic version check — only the first writer wins; the second silently no-ops.
3. `event_outbox` event_id is a ULID generated once per logical event — if the same logical event is enqueued twice, the second insert violates a UNIQUE on `(event_key, source_id, source_type, dedupe_hash)` and is ignored.
