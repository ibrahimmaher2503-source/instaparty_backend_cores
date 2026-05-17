# Contract: `NotificationChannelAdapter` (extended)

**Status**: Extension of existing contract at `app/Modules/Communication/Domain/Contracts/NotificationChannelAdapter.php`
**Backwards compatibility**: All existing implementations (`FcmPushAdapter`, `VonageSmsAdapter`, `WhatsAppStubAdapter`, `MailchimpEmailAdapter`) must add the two new methods; no existing method signatures change.

---

## Interface

```php
<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;

interface NotificationChannelAdapter
{
    /**
     * Send a single notification dispatch. The adapter is responsible for:
     *  - Setting $dispatch->status (Sent / Failed)
     *  - Writing $dispatch->provider_name, provider_message_id, provider_status, sent_at on success
     *  - Writing $dispatch->provider_error_code, provider_error_message, next_retry_at on failure
     *  - Calling $dispatch->save() — the caller does NOT save the dispatch after this returns
     *
     * Adapters MUST also dual-write the legacy columns (provider, provider_ref, error_message)
     * for the duration of the legacy-column transition phase.
     *
     * Adapters MUST NOT throw. All failures should be captured into the dispatch row.
     */
    public function send(NotificationDispatch $dispatch): void;

    /**
     * Machine-readable adapter name. Lowercase, underscore-separated, stable.
     * Used as the canonical value for $dispatch->provider_name.
     */
    public function name(): string;

    /**
     * Lightweight reachability + credentials check. Synchronous. SHOULD complete in <2s.
     * MUST NOT consume per-message provider quota (no test sends from this method).
     * MUST NOT include any provider secret in the returned ProviderHealthResult.
     */
    public function healthCheck(): ProviderHealthResult;
}
```

---

## Contract test (must pass for every implementation)

`tests/Feature/Modules/Communication/ProviderHealth/ContractTest.php` exercises every binding registered in `CommunicationServiceProvider` against this contract:

```php
dataset('adapters', [
    'push'     => fn () => app(NotificationChannel::Push->value . '_adapter'),
    'sms'      => fn () => app(NotificationChannel::Sms->value . '_adapter'),
    'whatsapp' => fn () => app(NotificationChannel::Whatsapp->value . '_adapter'),
    'email'    => fn () => app(NotificationChannel::Email->value . '_adapter'),
]);

it('returns a non-empty name', function (NotificationChannelAdapter $adapter) {
    expect($adapter->name())->toBeString()->not->toBeEmpty();
})->with('adapters');

it('returns a ProviderHealthResult', function (NotificationChannelAdapter $adapter) {
    $result = $adapter->healthCheck();
    expect($result)->toBeInstanceOf(ProviderHealthResult::class);
    expect($result->providerName)->toBe($adapter->name());
})->with('adapters');

it('healthCheck does not throw', function (NotificationChannelAdapter $adapter) {
    // Even when the provider API is unreachable, healthCheck() must catch
    // and return ProviderHealthResult::unreachable(...).
    expect(fn () => $adapter->healthCheck())->not->toThrow(Throwable::class);
})->with('adapters');

it('healthCheck note never contains a secret', function (NotificationChannelAdapter $adapter) {
    $result = $adapter->healthCheck();
    $forbiddenSubstrings = [
        config('services.fcm.server_key', 'INTENTIONALLY_UNMATCHABLE_VALUE_1'),
        config('services.vonage.api_secret', 'INTENTIONALLY_UNMATCHABLE_VALUE_2'),
        config('services.mailchimp.api_key', 'INTENTIONALLY_UNMATCHABLE_VALUE_3'),
    ];
    foreach ($forbiddenSubstrings as $secret) {
        if (! empty($secret) && $secret !== 'INTENTIONALLY_UNMATCHABLE_VALUE_1') {
            expect($result->note ?? '')->not->toContain($secret);
        }
    }
})->with('adapters');
```

---

## Adapter responsibilities — `send()` post-conditions

On success:

| Field | Value |
|---|---|
| `status` | `DispatchStatus::Sent` |
| `provider_name` | `$this->name()` |
| `provider_message_id` | Provider's message ID (truncated to 255 chars) |
| `provider_status` | Provider's raw status code/string (truncated to 60 chars) |
| `sent_at` | `now()` |
| `provider` *(legacy)* | `$this->name()` |
| `provider_ref` *(legacy)* | same as `provider_message_id` |

On failure (no throw):

| Field | Value |
|---|---|
| `status` | `DispatchStatus::Failed` |
| `provider_name` | `$this->name()` |
| `provider_error_code` | Provider's error code (truncated to 60 chars) |
| `provider_error_message` | Provider's human-readable error (full text) |
| `next_retry_at` | `now() + (5 * 2^(attempt_count - 1)) minutes` if `attempt_count < 5`, else NULL |
| `error_message` *(legacy)* | same as `provider_error_message` |

The adapter MUST `$dispatch->save()` before returning.

---

## Implementation notes — per adapter

### `FcmPushAdapter::healthCheck()`

```php
public function healthCheck(): ProviderHealthResult
{
    $start = hrtime(true);
    try {
        $projectId = $this->messaging->getAppInstance()->getName();
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        return ProviderHealthResult::reachable('fcm', $latencyMs, "Firebase project: {$projectId}");
    } catch (Throwable $e) {
        return ProviderHealthResult::unreachable('fcm', 'SDK bootstrap failed: ' . class_basename($e));
    }
}
```

### `VonageSmsAdapter::healthCheck()`

```php
public function healthCheck(): ProviderHealthResult
{
    $start = hrtime(true);
    try {
        $balance = $this->vonageClient->account()->getBalance();
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        return ProviderHealthResult::reachable('vonage_sms', $latencyMs, "Balance: EUR " . number_format((float) $balance->getBalance(), 2));
    } catch (Throwable $e) {
        return ProviderHealthResult::unreachable('vonage_sms', class_basename($e) . ': ' . $e->getCode());
    }
}
```

### `MailchimpEmailAdapter::healthCheck()`

```php
public function healthCheck(): ProviderHealthResult
{
    $start = hrtime(true);
    try {
        $response = $this->mailchimp->ping->get();
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $health = $response->health_status ?? 'unknown';
        return ProviderHealthResult::reachable('mailchimp_email', $latencyMs, "Health: {$health}");
    } catch (Throwable $e) {
        return ProviderHealthResult::unreachable('mailchimp_email', class_basename($e));
    }
}
```

### `WhatsAppStubAdapter::healthCheck()`

```php
public function healthCheck(): ProviderHealthResult
{
    return ProviderHealthResult::reachable('whatsapp_stub', 0, 'Stub adapter — Phase 1 placeholder');
}
```

### `NullProviderAdapter::healthCheck()`

```php
public function healthCheck(): ProviderHealthResult
{
    return ProviderHealthResult::reachable('null', 0, 'Null adapter active in ' . app()->environment());
}
```

### `NullProviderAdapter::send()`

```php
public function send(NotificationDispatch $dispatch): void
{
    if (app()->environment('production')) {
        throw new NullAdapterInProductionException();
    }

    $dispatch->update([
        'status' => DispatchStatus::Sent,
        'provider_name' => 'null',
        'provider' => 'null',  // legacy
        'provider_message_id' => 'null-' . Str::ulid()->toBase32(),
        'provider_ref' => null,  // legacy — keep NULL so dev data is distinguishable
        'provider_status' => 'ok',
        'sent_at' => now(),
    ]);
}
```

---

## What this contract does NOT cover (intentional)

- **Bulk send** — `send()` is single-dispatch. Campaign bulk sends already have their own job (`DispatchCampaignRecipientJob`) and do not use this contract path.
- **Webhook ingestion** — provider delivery/bounce webhooks are out of scope for this phase; the `delivered_at` / `DispatchStatus::Delivered` transition is set by Phase-2 webhook handlers.
- **Provider config validation** — `healthCheck()` reports reachability, not a deep config audit; e.g., it does not verify that the FCM project has the correct API restrictions.
