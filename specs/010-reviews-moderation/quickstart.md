# Quickstart: Reviews + Moderation (Phase 5.1)

**Audience**: Developer (likely Ibrahim) implementing this phase from the plan/tasks.
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **ADR**: [ADR-0011](../../docs/adr/0011-reviews-module.md)

This is a step-by-step kick-off guide for the 1-day Phase 5.1 implementation. Follow the day-plan in `plan.md §Implementation Order` for the full task list.

---

## 0. Prerequisites

```powershell
# Confirm you're on a Reviews working branch (recommended fresh branch off main)
git switch -c 010-reviews-moderation   # if not already there

# Confirm migrations from earlier phases are applied
php artisan migrate:status

# Confirm dependent modules' relevant tables exist:
#   - users, vendor_profiles, customer_profiles
#   - services (with rating_avg, rating_count)
#   - booking_items (with item_status)
#   - booking_vendors
```

If any are missing, do not proceed — earlier phases (1.x Identity, 2.x Catalog, 3.x Booking) must be complete first.

---

## 1. Scaffold the module skeleton

```powershell
# Create the Reviews module skeleton
$base = "app/Modules/Reviews"
New-Item -ItemType Directory -Force -Path "$base/Domain/Models",
                                          "$base/Domain/Enums",
                                          "$base/Domain/Events",
                                          "$base/Domain/States",
                                          "$base/Domain/Contracts",
                                          "$base/Application/Actions",
                                          "$base/Application/Services",
                                          "$base/Application/DTOs",
                                          "$base/Application/Listeners",
                                          "$base/Infrastructure/Repositories",
                                          "$base/Http/Controllers/Customer",
                                          "$base/Http/Controllers/Public",
                                          "$base/Http/Requests",
                                          "$base/Http/Resources",
                                          "$base/Filament/Pages",
                                          "$base/Routes",
                                          "$base/Database/Migrations",
                                          "$base/Database/Factories",
                                          "$base/Resources/lang/en",
                                          "$base/Resources/lang/ar",
                                          "$base/Providers"

# Stub the ServiceProvider (T001)
# (template content from plan.md §Implementation Order step T001)
```

Then register in `bootstrap/providers.php`:

```php
return [
    // ...
    App\Modules\Reviews\Providers\ReviewsServiceProvider::class,
];
```

---

## 2. Run migrations

```powershell
# Tasks T002–T005 — generate migrations under app/Modules/Reviews/Database/Migrations/
# (Use plain hand-written migrations following the SQL in data-model.md)

php artisan migrate
```

Verify in MySQL:

```sql
SHOW TABLES LIKE '%review%';
-- Expect: service_reviews, vendor_reviews, review_responses, review_moderation_log

-- Confirm append-only invariant on review_moderation_log:
DESCRIBE review_moderation_log;
-- Expect: NO updated_at, NO deleted_at columns.
```

---

## 3. Smoke-test the eligibility contract

```powershell
php artisan tinker
```

```php
// Inside tinker
use App\Modules\Reviews\Domain\Contracts\BookingItemReviewabilityReader;

$reader = app(BookingItemReviewabilityReader::class);

// Pick a known completed booking_item from your seeded data
$reader->isReviewable('01J9KM3Z1A8BCDR7ER77M2X8YE', userId: 1);
// → true if the item is completed and owned by user 1
// → false otherwise
```

If the contract is not yet bound, you'll see:

```
BindingResolutionException: Target class [...] does not exist
```

→ T021 not done. Bind the implementation in `BookingServiceProvider::register()`.

---

## 4. Submit a review (manual API smoke test)

After T024 (controllers + routes wired):

```powershell
# Get a customer token first (Phase 1.0 auth flow); export to env
$token = "<paste sanctum token here>"
$bookingItemPublicId = "<paste a completed booking_item public_id>"

curl -X POST "http://localhost:8000/api/v1/customer/booking-items/$bookingItemPublicId/review" `
     -H "Authorization: Bearer $token" `
     -H "Accept-Language: ar" `
     -H "Content-Type: application/json" `
     -d '{"rating": 5, "body": "خدمة ممتازة"}'
```

Expected response:

```json
{
  "data": {
    "public_id": "...",
    "review_type": "service",
    "rating": 5,
    "body": "خدمة ممتازة",
    "locale": "ar",
    "moderation_status": "pending",
    "submitted_at": "2026-05-04T..."
  },
  "meta": {},
  "errors": []
}
```

Then submit again with the same body — expect `409 Conflict`:

```json
{
  "data": null,
  "errors": [{
    "code": "review_already_exists",
    "message": "...",
    "existing_review_public_id": "..."
  }]
}
```

---

## 5. Moderate via Filament

After T026 + T027:

1. Visit `http://localhost:8000/admin` and log in as an admin user.
2. Navigate to **Moderation → Review Moderation**.
3. The pending review submitted in step 4 should appear at the top.
4. Click **Approve** → confirm modal.
5. Watch the queue worker:

   ```powershell
   php artisan queue:work --queue=default --once
   ```

   Expect a `RecomputeRatingOnApproval` job to fire and complete.
6. Verify rating column updated:

   ```sql
   SELECT id, rating_avg, rating_count
   FROM services
   WHERE id = (SELECT service_id FROM service_reviews WHERE public_id = '<the review>');
   -- Expect rating_avg=5.00, rating_count=1
   ```

---

## 6. Verify public listing

No auth needed:

```powershell
curl "http://localhost:8000/api/v1/public/services/$servicePublicId/reviews" `
     -H "Accept-Language: en"
```

Expected: the approved review appears with `reviewer_first_name` set to the customer's first name (or "Verified Customer" fallback).

Pending / rejected / hidden / soft-deleted reviews should NEVER appear here.

---

## 7. Run the test suite

```powershell
# Reviews-specific group
./vendor/bin/pest --group=reviews

# Per-product-type eligibility coverage
./vendor/bin/pest --group=reviews,rental
./vendor/bin/pest --group=reviews,sale
./vendor/bin/pest --group=reviews,digital

# Architecture tests (must pass)
./vendor/bin/pest --filter=ReviewsModuleNoCrossImport
./vendor/bin/pest --filter=AppendOnlyTablesHaveNoSoftDeletes
./vendor/bin/pest --filter=NoIfElseOnProductTypeStringTest

# Full suite — green before commit
./vendor/bin/pest --bail
```

---

## 8. Lint + static analysis

```powershell
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

Both must run clean.

---

## 9. Update API artifacts (T034 + T035)

```powershell
# Append the 8 new endpoints to .specify/memory/api-registry.md per the plan's table.

# Create Bruno + Postman collections under docs/api/collections/reviews*
```

---

## 10. Final commit checklist

- [ ] All migrations applied locally and on staging
- [ ] All 30+ Pest tests pass (`./vendor/bin/pest --bail`)
- [ ] Architecture tests pass
- [ ] `pint` + `phpstan` clean
- [ ] `php artisan shield:generate --all` ran after Filament Page added
- [ ] `.specify/memory/api-registry.md` updated with 8 new rows
- [ ] Bruno + Postman collections committed under `docs/api/collections/`
- [ ] EN + AR translations complete in `Reviews/Resources/lang/`
- [ ] All 5 Exit Criteria from spec demonstrably met
- [ ] Conventional commit: `feat(reviews): phase 5.1 — service + vendor reviews + moderation`

---

## Troubleshooting

**Q: My review submission returns 422 with `booking_item_not_completed`, but I'm sure the item is completed.**
A: Confirm the `booking_items.item_status` column literal is `'completed'` (lowercase) — not `'Completed'`. The contract does a strict string match.

**Q: The aggregation listener fires but `services.rating_avg` doesn't update.**
A: Check that `EloquentServiceRatingWriter` is bound in `CatalogServiceProvider`. Run `php artisan optimize:clear` and restart the queue worker.

**Q: Pest reports `Class "App\Modules\Reviews\..." not found` even after creating the file.**
A: Run `composer dump-autoload`. The `app/Modules/Reviews/` PSR-4 mapping needs to be picked up.

**Q: Architecture test fails with `Reviews module imports App\Modules\Booking\Domain\Models\BookingItem`.**
A: Find the offending `use` statement. Replace it with the corresponding contract from `Reviews\Domain\Contracts\`.

**Q: Filament's `ReviewModerationPage` shows no rows even when there are pending reviews.**
A: Confirm the page's query scope is `whereModerationStatus('pending')` against BOTH `service_reviews` AND `vendor_reviews` (UNION). Also check Shield permissions — the logged-in admin needs `page_ReviewModerationPage`.
