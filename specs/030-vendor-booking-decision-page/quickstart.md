# Quickstart — Vendor Booking Decision Page

> Local recipe for landing on a freshly-rendered decision page and exercising the three actions end-to-end.

---

## Prerequisites

- `php artisan migrate:fresh --seed` (or your usual reset)
- Booking dev seeder run: `php artisan db:seed --class="App\\Modules\\Booking\\Database\\Seeders\\BookingDevelopmentSeeder"`
- At least one `vendor_profiles` row is approved
- At least one `booking_vendors` row exists with `sub_status = pending` and `response_deadline` in the future, belonging to that vendor

The dev seeder already creates this — verify with:

```bash
php artisan tinker --execute="echo \App\Modules\Booking\Domain\Models\BookingVendor::query()->where('sub_status', 'pending')->whereNotNull('response_deadline')->count();"
```

---

## Run locally

```bash
php artisan serve
php artisan queue:work    # post-commit listeners
```

Sign in as the vendor user at `/vendor/login`. From the incoming bookings list, click **Decide** on any pending row. Or paste the URL directly:

```text
http://localhost:8000/vendor/booking-decisions/{public_id}
```

(Replace `{public_id}` with the `booking_vendors.public_id` from your seed data.)

---

## Smoke test — Happy path Accept

1. Open the decision page for a pending booking.
2. Verify all panels render (header info, coverage badge, items, customer notes, deadline countdown, payment status).
3. Click **Accept** → confirm in modal.
4. Expect: redirect to incoming list, green success toast.
5. Verify:

```bash
php artisan tinker --execute="\$bv = \App\Modules\Booking\Domain\Models\BookingVendor::where('public_id','{public_id}')->first(); dump(\$bv->sub_status, \$bv->responded_at);"
```

   `sub_status` should now be `accepted` and `responded_at` populated.

6. Check `booking_state_transitions` has one new row for this BookingVendor with `to_state=accepted`.
7. Check `audit_logs` has the corresponding entry.

---

## Smoke test — Happy path Reject (bilingual reason)

1. Open the decision page for another pending booking.
2. Click **Reject** → fill `Reason (EN)` = "Not available that weekend" and `Reason (AR)` = "غير متاح في هذا الأسبوع" → submit.
3. Expect: redirect to incoming list, warning toast.
4. Verify `booking_vendors.rejection_reason = {"en": "...", "ar": "..."}` (JSON).

---

## Smoke test — Modify handoff

If `VendorBookingModificationBuilder` is wired:

1. Click **Modify** → expect navigation to the builder page with the booking_vendor public_id pre-loaded.

If the builder is not yet shipped:

1. Click **Modify** → expect an info notification "Modification builder coming soon" and no navigation.

---

## Smoke test — Deadline expired guard

Force-expire the deadline:

```bash
php artisan tinker --execute="\App\Modules\Booking\Domain\Models\BookingVendor::where('public_id','{public_id}')->update(['response_deadline' => now()->subHours(1)]);"
```

1. Reload the page → deadline panel shows **Expired** in red strike-through.
2. Header actions are hidden; banner explains admin intervention is needed.
3. Attempt to submit Accept via direct Livewire round-trip → server responds with 409 (`ResponseDeadlineExpiredException`); page shows a danger toast.

---

## Smoke test — Wrong-vendor 403

1. Sign in as a **different** approved vendor (use a second seeded user).
2. Paste the original booking's decision URL.
3. Expect HTTP 403, no data leakage.

---

## Smoke test — Unauthenticated redirect

1. Sign out.
2. Hit the decision URL.
3. Expect redirect to `/vendor/login`.

---

## Smoke test — Bilingual rendering

1. Switch admin locale to AR via the language switcher.
2. Reload the decision page.
3. Verify: all labels render in Arabic, layout flips to RTL, money badges still read left-to-right (per Filament default), product-type badges keep their EN labels (or AR labels if `ProductType::label()` is translated — check current implementation).

---

## Pest test invocation

```bash
./vendor/bin/pest tests/Feature/Modules/Booking/VendorPortal/VendorBookingDecisionPageTest.php
```

All test cases must pass before this feature is considered done:

- `it renders for an authorised vendor with a pending booking`
- `it accepts a pending booking and transitions sub_status to accepted`
- `it rejects a pending booking with bilingual reason`
- `it routes modify to the builder when present`
- `it shows a coming-soon notification when builder absent`
- `it returns 403 for a different vendor`
- `it returns 403 when user has no vendor profile`
- `it redirects unauthenticated users to login`
- `it hides actions when sub_status is no longer pending`
- `it hides actions when response_deadline has lapsed`
- `it surfaces 409 when Accept is attempted past the deadline`
- `it hides actions when booking has an active lock`
- `it renders in EN and AR without layout errors`
