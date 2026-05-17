# Phase 0 — Research: Communication Provider Health

**Date**: 2026-05-17
**Spec**: [./spec.md](./spec.md)
**Plan**: [./plan.md](./plan.md)

This document resolves all open questions before Phase 1 design. Six decisions are recorded.

---

## Decision 1 — Queue refactor strategy

**Question**: The existing `DispatchNotificationAction` calls `$adapter->send($dispatch)` inside `DB::afterCommit(...)` synchronously. Test sends and retries must be queue-based per FR-EXT-029. Should we refactor the existing flow, or introduce a parallel queued path?

**Decision**: Introduce a new `DispatchNotificationJob` (queued). New code paths (test sends, retries, scheduled retries) enqueue this job. The existing `DispatchNotificationAction` flow is **not** modified in this phase.

**Rationale**:
- 30+ listeners (`OnBookingSubmitted`, `OnPaymentCaptured`, `DispatchVendorChangesRequestedNotificationListener`, etc.) depend on the current synchronous-after-commit behaviour. Refactoring all of them is out of scope for Phase 5.3.
- The new job can be enqueued from the new actions without touching legacy paths.
- A future phase (5.4 or later) can migrate the legacy flow to the queued job once observability is in place.

**Alternatives considered**:
- *Refactor `DispatchNotificationAction` to enqueue the job* — large blast radius across 30+ listener tests. Rejected.
- *Use Laravel sync queue for tests* — defeats the purpose of FR-EXT-029 (queue-based dispatch is required so the UI is not blocked, and retries can be deferred).
- *Use `dispatch_sync($job)` from tests* — works, but the spec wants observability via the queue (admins can see the job pending), so we use real `dispatch($job)` with `Queue::fake()` in tests.

---

## Decision 2 — Health check implementation per provider

**Question**: What does `healthCheck()` do for each of the four production adapters and the null adapter?

**Decision**:

| Adapter | `name()` | `healthCheck()` implementation |
|---|---|---|
| `FcmPushAdapter` | `"fcm"` | Resolve `Kreait\Firebase\Contract\Messaging` from container; call `getAppInstance()->getName()` to confirm the SDK is bootstrapped. Latency = micros from start. No external HTTP call (FCM has no lightweight ping endpoint and sending a dummy push would cost a token). `isReachable=true` if container resolution succeeds. `note` includes the Firebase project ID. |
| `VonageSmsAdapter` | `"vonage_sms"` | Resolve `Vonage\Client` from container; call `$client->account()->getBalance()` (lightweight authenticated API call that costs nothing). `isReachable=true` if call succeeds. `note` includes the EUR balance returned. Catches `Throwable` → `isReachable=false` with the exception class in `note`. |
| `MailchimpEmailAdapter` | `"mailchimp_email"` | Resolve `MailchimpMarketing\ApiClient`; call `$client->ping->get()`. `isReachable=true` if returns HTTP 200. `note` includes the API response health string. Catches `Throwable` → `isReachable=false`. |
| `WhatsAppStubAdapter` | `"whatsapp_stub"` | Always return `isReachable=true`, `note="stub adapter — Phase 1 placeholder, no live API"`. No external call. |
| `NullProviderAdapter` | `"null"` | Always return `isReachable=true`, `note="null adapter — used in {env}"`. |

**Rationale**:
- Each provider has a well-documented lightweight health endpoint (or a deterministic local check) that does not consume per-message quota.
- FCM is the exception — its only "health" signal is SDK bootstrap success. This is intentional: a deeper check would require sending a real test message, which is exactly what the explicit Send Test button is for.

**Alternatives considered**:
- *Cache health-check results for 60s* — adds complexity and could mask a fast-recovering provider failure. Defer to Phase 2.
- *Async health checks via a background job* — health page would then show stale data; admins explicitly want a "now" snapshot.

---

## Decision 3 — Retry back-off schedule

**Question**: What back-off function calculates `next_retry_at` on each failure?

**Decision**: Linear with multiplier 2, base 5 minutes, max 5 attempts.

| attempt_count after failure | next_retry_at = now() + |
|---|---|
| 1 | 5 min |
| 2 | 10 min |
| 3 | 20 min |
| 4 | 40 min |
| 5 | (no further retry; `next_retry_at` set to NULL) |

Formula: `next_retry_at = now() + 5 * 2^(attempt_count - 1) minutes` capped at attempt 4.

**Rationale**:
- Linear-doubling is the standard exponential back-off pattern. Capping at 5 attempts is consistent with PostgreSQL/Sidekiq defaults and avoids unbounded retry queues.
- Total window before final failure: 5+10+20+40 = 75 minutes — fits within the 24h payment hold window and the vendor 24h SLA, so a transient FCM/Vonage outage does not cause an alert escalation to drop.

**Alternatives considered**:
- *Fixed 5-min interval* — too aggressive for sustained outages; would generate 12 retries/hour per failed dispatch.
- *Jitter (random +/- 30%)* — Phase 2 optimisation; not required at current scale.
- *Per-provider back-off* — not justified by current data; can be added if specific providers show pattern of slow recovery.

The back-off is implemented in `DispatchNotificationJob` — when `send()` fails, the job sets `next_retry_at` on the dispatch (the scheduler picks it up later).

---

## Decision 4 — Migration approach (rename vs. add)

**Question**: The current `notification_dispatches` has columns `provider`, `provider_ref`, `error_message`. The spec calls for `provider_name`, `provider_message_id`, `provider_error_message`. Rename or add?

**Decision**: **Add** new columns; **keep** legacy columns; **dual-write** in adapters during this phase. A future phase will drop the legacy columns in a separate migration after confirming no readers remain.

**Rationale**:
- A column rename in MySQL is a metadata change that requires holding all writers/readers in sync at deploy time. With four production adapters writing to the existing columns and a Filament resource reading from them, a single-shot rename increases deploy risk.
- Dual-writing is a one-line change in each adapter (`$dispatch->provider = ...; $dispatch->provider_name = ...;`) and costs effectively nothing.
- Legacy columns can be safely dropped in a later cleanup migration once we confirm via PHPStan / grep that nothing reads them.

**Alternatives considered**:
- *Rename via Doctrine DBAL* — requires `doctrine/dbal` to be added to Composer. Not on the package list. Rejected.
- *Drop legacy columns in same migration* — too risky for zero-downtime deploy.

---

## Decision 5 — `NullProviderAdapter` binding strategy

**Question**: When should `NullProviderAdapter` replace the real adapters?

**Decision**: Bind `NullProviderAdapter` for **all four channels** when `app()->environment(['local', 'testing'])`. In `production`, the real adapters are bound. In `staging`, the real adapters are bound (so staging exercises real providers against test API keys).

In `CommunicationServiceProvider::register()`:

```php
if ($this->app->environment(['local', 'testing'])) {
    foreach (NotificationChannel::cases() as $channel) {
        if ($channel === NotificationChannel::InApp) {
            continue;  // in_app uses Reverb, no adapter
        }
        $this->app->bind($channel->value.'_adapter', NullProviderAdapter::class);
    }
} else {
    $this->app->bind(NotificationChannel::Push->value.'_adapter', FcmPushAdapter::class);
    $this->app->bind(NotificationChannel::Sms->value.'_adapter', VonageSmsAdapter::class);
    $this->app->bind(NotificationChannel::Whatsapp->value.'_adapter', WhatsAppStubAdapter::class);
    $this->app->bind(NotificationChannel::Email->value.'_adapter', MailchimpEmailAdapter::class);
}
```

Additionally, `NullProviderAdapter::send()` throws `NullAdapterInProductionException` if `app()->environment('production')` — defense in depth in case the binding is misconfigured.

**Rationale**:
- `local` developer machines should never hit FCM, Vonage, or Mailchimp.
- `testing` Pest runs must be deterministic and offline.
- `staging` deliberately exercises real providers against real (test-mode) credentials so we catch credential rotation bugs before production.
- The double-guard (binding + runtime check) is consistent with how `kreait/firebase-php` and other SDKs guard against accidental production credentials in tests.

**Alternatives considered**:
- *Use `.env.local` to swap config* — relies on the developer remembering to copy the file. Rejected.
- *Bind null adapter only in `testing`* — local developers running `php artisan tinker` or `php artisan serve` would hit real APIs.

---

## Decision 6 — Audit log action keys

**Question**: What `audit_logs.action` strings are written by the new flows?

**Decision**: Add the following entries to the "Audit-Log Action Catalogue" in `.specify/memory/api-registry.md`:

| Action key | When fired | Auditable type | Notes |
|---|---|---|---|
| `notification.test_sent` | Any `SendTestXxxAction::execute()` succeeds | `NotificationDispatch` | `properties` includes `channel`, `recipient` (masked: only last 4 chars), `admin_user_id` |
| `notification.retry_queued` | `RetryFailedDispatchAction::execute()` re-queues a dispatch | `NotificationDispatch` | `properties` includes `attempt_count`, `provider_name`, `previous_error_code` |
| `notification.retry_cap_reached` | `RetryFailedDispatchAction` throws `DispatchNotRetryableException` | `NotificationDispatch` | Recorded for monitoring; UI surfaces this as a warning |
| `notification.auto_retry_queued` | `RetryFailedDispatchesCommand` picks up a due failure | `NotificationDispatch` | `properties` includes `attempt_count`, `next_retry_at` |

**Rationale**:
- Every admin-triggered side-effect must be audit-traceable per Constitution §V (append-only audit) and the existing audit log conventions.
- Recipient is masked because the audit log is reviewed by support staff who do not have a need to know the full phone number / email of a notification target.

**Alternatives considered**:
- *Use `activity_log` (Spatie) instead of `audit_logs`* — `audit_logs` is the established InstaParty pattern for compliance-relevant events; `activitylog` is for general model change tracking.

---

## Open Items (deferred)

None. All Phase 0 questions resolved. Proceed to Phase 1.
