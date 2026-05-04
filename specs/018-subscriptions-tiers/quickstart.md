# Quickstart — Subscriptions Phase 1.7

**Feature**: 018-subscriptions-tiers
**Prerequisite**: existing InstaParty dev environment running (`php artisan serve`, `php artisan queue:work`, MySQL/Redis up).

---

## 1. Switch to the feature branch

```bash
git checkout 018-subscriptions-tiers
composer install
```

No new Composer packages — `composer install` is just to confirm parity with the lockfile. If `composer.lock` shows pending writes, that's a violation; back out before continuing.

---

## 2. Run migrations + seeder

```bash
php artisan migrate
php artisan db:seed --class="App\Modules\Subscriptions\Database\Seeders\SubscriptionPlansSeeder"
```

Expected outcome:
- 6 new tables created + `commission_rates` altered with `subscription_plan_id` FK.
- 4 plans (`free`, `silver`, `gold`, `premium`) seeded with EN+AR `name` / `description`.
- ~36 `plan_features` rows.
- 4 tier-only commission_rates rows.
- Re-running the seeder is a no-op (idempotent on `plan_code`).

---

## 3. Verify auto-enrolment

```bash
php artisan tinker
> $vendor = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create();
> $vendor->subscriptions()->latest()->first()
# → status = active, plan = free, billing_cycle = none
```

---

## 4. Walk the upgrade flow against the local API

```bash
# 1) List plans (English)
curl -H "Authorization: Bearer $VENDOR_TOKEN" \
     -H "Accept-Language: en" \
     http://localhost/api/vendor/plans

# 2) Subscribe to Silver yearly with idempotency
curl -X POST http://localhost/api/vendor/subscribe \
     -H "Authorization: Bearer $VENDOR_TOKEN" \
     -H "Idempotency-Key: $(uuidgen)" \
     -H "Content-Type: application/json" \
     -d '{"plan_code":"silver","billing_cycle":"yearly","save_payment_token":true}'
# → 202 with checkout_url + invoice_public_id

# 3) Simulate Paymob webhook (test webhook helper)
php artisan paymob:simulate-capture --invoice=<invoice_public_id>

# 4) Confirm activation
curl -H "Authorization: Bearer $VENDOR_TOKEN" http://localhost/api/vendor/subscription
# → status active, plan_code silver, current_period_end ~ +1 year
```

---

## 5. Walk the renewal-failure → grace → expiry path

Set the recurring-token feature flag on, then force a failure:

```bash
php artisan tinker
> \App\Modules\Shared\Models\FeatureFlag::set('subscriptions.recurring_tokens_enabled', true);

# Move the subscription's current_period_end into the past
> $sub = \App\Modules\Subscriptions\Domain\Models\VendorSubscription::find(...);
> $sub->update(['current_period_end' => now()->subMinute()]);

# Run the renewal job (force a gateway failure via test mode)
php artisan subscriptions:renew --simulate-failure
# → status moves to past_due, grace_period_ends_at = now + 7 days

# Skip ahead and run the expiration job
php artisan subscriptions:expire-grace --simulate-clock-advance="+8 days"
# → status moves to expired, vendor re-enrolled on free, services > 5 are paused (oldest first)
```

The two artisan commands above are thin wrappers around the queued cron Actions and are intended for development only — production scheduling lives in `app/Console/Kernel.php`.

---

## 6. Verify commission tier fallback

```bash
./vendor/bin/pest --filter=CommissionTierFallbackTest
```

Expects 24 cases (4 tiers × 3 product types × 2 category-override scenarios) — all passing, with assertions on both the resolved bps and the snapshotted value on `booking_items.commission_bps`.

---

## 7. Filament admin walkthrough

1. `php artisan shield:generate --all`
2. Visit `/admin` → "Services" navigation → no change. Visit "Vendors" → "Subscriptions" — list page.
3. As a super-admin, click a vendor row → "Override tier" action.
4. Pick `premium`, reason "Beta partner", expires `+30 days`.
5. Confirm: an audit row appears on `subscription_audit`, the vendor's effective tier flips to Premium, and a `subscription.tier_changed` event lands on `event_outbox`.

---

## 8. Run the full feature test suite

```bash
./vendor/bin/pest --group=subscriptions
./vendor/bin/pest --group=rental --group=sale --group=digital
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

All must be green before opening a PR back to `002-identity-vendor-onboarding` (the configured base branch).

---

## 9. Update Phasing Plan + ADR

1. Edit `docs/specs/09_Phasing_Plan.md` to add Phase 1.7 row (Subscriptions, between Phase 5 and Phase 6).
2. Edit `docs/specs/01_PRD.md` §5.2 / §11 to remove vendor subscription tiers from the Phase-2-only list.
3. Author `docs/adr/ADR-0013-subscription-tiers-module.md` from the new-module ADR template.
4. Add the 6 endpoints to `.specify/memory/api-registry.md`.
5. Generate Bruno collection entries in `docs/api/collections/subscriptions/`.

These are all **part of the implementation plan** — they're not optional cleanup.
