# Quickstart — Communication Provider Health (Manual QA)

**Audience**: Ibrahim, post-implementation manual verification before merging.
**Time**: ~10 minutes end-to-end.
**Environment**: local (`.env` with `APP_ENV=local`).

---

## 0. Prerequisites

```powershell
# 1) Pull latest, install, and migrate
composer install
php artisan migrate
php artisan shield:generate --all          # picks up the 3 new permissions
php artisan db:seed --class=CommunicationPermissionsSeeder

# 2) Confirm null adapter is bound (only fires in local/testing)
php artisan tinker
> app(\App\Modules\Communication\Domain\Enums\NotificationChannel::Push->value . '_adapter')
# Expected: App\Modules\Communication\Infrastructure\Gateways\NullProviderAdapter

# 3) Start the panel
php artisan serve
php artisan queue:work --queue=default
```

Log into `/admin` as an admin user that has the `admin` role.

---

## 1. View the provider health page

1. Navigate to **Communication → Provider Health** in the left sidebar.
2. ✅ Four channel cards render: Push (FCM), SMS (Vonage), WhatsApp, Email (Mailchimp).
3. ✅ Each card shows: adapter name, "Reachable" badge (green), `latency_ms` (likely 0 for null adapter), 24h sent / failed counts (likely 0 / 0 on fresh data).
4. ✅ **Inspect the rendered HTML** (F12) — search for `vonage`, `mailchimp`, `fcm` server key, FCM private key snippet. None should appear in any value or attribute.

If any card shows a red "Unreachable" badge, that's expected on a fresh local install (provider credentials may be blank). The badge will turn green in staging once real credentials are loaded.

---

## 2. Send a test message (push channel)

1. On the Push card, click **Send Test**.
2. Fill in the modal:
   - Device token: `test-token-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa` (32+ chars)
   - Body (optional): `Hello from health page test`
3. Click **Submit**.
4. ✅ A Filament success notification appears: "Test queued — dispatch public_id: 01H…".
5. ✅ Check the queue worker output — `DispatchNotificationJob` ran.
6. ✅ Navigate to **Communication → Notification Dispatches**, filter `is_test = true`.
   - One row exists.
   - `status = sent` (because null adapter is bound locally).
   - `provider_name = null`.
   - `provider_message_id = null-01H…` (null adapter stamp).
   - `attempt_count = 1`.
   - `last_attempt_at` is within the last few seconds.

Repeat for SMS (`+201234567890`), WhatsApp (`+201234567890`), and Email (`test@example.com`). All four should produce the same shape of test dispatch row.

---

## 3. Simulate a failure and retry

3a. **Inject a failure manually** (no real provider needed):

```powershell
php artisan tinker
> $d = \App\Modules\Communication\Domain\Models\NotificationDispatch::query()
        ->where('is_test', true)
        ->first();
> $d->update([
    'status' => 'failed',
    'provider_error_code' => 'TEST_INJECTED',
    'provider_error_message' => 'Manually injected failure for QA',
    'attempt_count' => 1,
]);
```

3b. **Retry via UI**:

1. Navigate to **Communication → Notification Dispatches**.
2. Filter `status = failed`.
3. ✅ The row has a **Retry** action button (only visible when `status=failed` and `attempt_count < 5`).
4. Click **Retry**, confirm.
5. ✅ Filament success notification: "Dispatch re-queued — attempt 2".
6. ✅ Row updates: `status = sent` (null adapter), `attempt_count = 2`, `last_attempt_at` refreshed.
7. ✅ Audit log entry written: open **Activity Logs → audit_logs** (or query `audit_logs` table) and find an entry with `action = notification.retry_queued`.

3c. **Test the retry cap**:

```powershell
> $d = \App\Modules\Communication\Domain\Models\NotificationDispatch::query()->first();
> $d->update(['status' => 'failed', 'attempt_count' => 5]);
```

1. Navigate to the dispatch list, filter `status=failed`.
2. ✅ The Retry button is **not** visible for this row (or is disabled with a tooltip "max attempts reached").

---

## 4. Test the scheduled auto-retry

4a. **Seed a due failure**:

```powershell
> \App\Modules\Communication\Domain\Models\NotificationDispatch::factory()->create([
    'status' => 'failed',
    'attempt_count' => 2,
    'next_retry_at' => now()->subMinutes(1),
    'is_test' => true,
    'channel' => 'push',
    'context' => ['device_token' => 'test-token-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
]);
```

4b. **Run the scheduler command manually**:

```powershell
php artisan communication:retry-failed-dispatches
```

✅ Output: `Re-queued N dispatch(es) for retry.`
✅ The queue worker processes the re-queued job, `status` becomes `sent`, `attempt_count = 3`.

4c. **Verify schedule registration**:

```powershell
php artisan schedule:list
# Expected: "communication:retry-failed-dispatches" with "*/5 * * * *" expression and "withoutOverlapping".
```

---

## 5. Production safety check

Confirm `NullProviderAdapter` cannot fire in production:

```powershell
php artisan tinker
> app()->detectEnvironment(fn () => 'production');
> $adapter = new \App\Modules\Communication\Infrastructure\Gateways\NullProviderAdapter();
> $dispatch = \App\Modules\Communication\Domain\Models\NotificationDispatch::first();
> $adapter->send($dispatch);
# Expected: NullAdapterInProductionException thrown.
```

(Restart tinker afterwards to reset the environment.)

---

## 6. Run the test suite

```powershell
./vendor/bin/pest tests/Feature/Modules/Communication/ProviderHealth/ --parallel
```

✅ All tests green. Expected count: ~30+ tests across 10 files (contract test multiplies by 4 adapters via dataset).

---

## 7. Lint + static analysis

```powershell
./vendor/bin/pint app/Modules/Communication/
./vendor/bin/phpstan analyse app/Modules/Communication/ --memory-limit=1G
```

✅ Pint clean. PHPStan clean (level matches existing config).

---

## Smoke-test summary

| Capability | Expected | Verified? |
|---|---|---|
| Health page renders, no secrets exposed | Cards for 4 channels, no API keys visible | ☐ |
| Send test push / SMS / WhatsApp / email succeeds locally | 4 test dispatches with `is_test=true`, `status=sent` | ☐ |
| Manual retry of failed dispatch | `attempt_count` increments; audit log entry | ☐ |
| Retry cap enforced at 5 attempts | Button hidden / disabled; action throws | ☐ |
| Scheduled auto-retry picks up due failures | Command output reports re-queued count | ☐ |
| Null adapter throws in production | `NullAdapterInProductionException` | ☐ |
| Pest + Pint + PHPStan clean | All commands return 0 | ☐ |

When all rows are ☑, the feature is ready for commit.
