# Quickstart — Admin Booking Intervention Page

> How to verify the feature end-to-end locally once `/speckit.implement` finishes.

---

## 1. Preflight (skip if already done by `/speckit.implement`)

```powershell
# 1. Confirm chat_threads table state — gates the freeze action
Get-ChildItem app/Modules/Communication/Database/Migrations -Filter "*chat_threads*"

# 2. Confirm booking_admin_interventions.intervention_type is VARCHAR (not ENUM)
php artisan tinker --execute="echo DB::select('SHOW CREATE TABLE booking_admin_interventions')[0]->{'Create Table'};"
```

If `chat_threads` is absent, expect `FreezeBookingChatAction` to be deferred (cut-list item 1 in `plan.md`).

## 2. Migrate, seed, and start the stack

```powershell
php artisan migrate
php artisan db:seed --class="Database\Seeders\BookingDevelopmentSeeder"
php artisan db:seed --class="App\Modules\Booking\Database\Seeders\BookingPermissionsSeeder"

# Generate Filament Shield permissions for the new resource
php artisan shield:generate --all

# Make sure your admin user has the new abilities
php artisan tinker --execute="App\Modules\Identity\Domain\Models\User::where('email','admin@instaparty.test')->first()->assignRole('admin');"

# Start the dev stack
php artisan serve
php artisan queue:work --queue=default,notifications
```

## 3. Seed trouble bookings

The development seeder creates four bookings — one per trouble bucket — when run with `--with-intervention-fixtures`:

```powershell
php artisan db:seed --class="App\Modules\Booking\Database\Seeders\BookingDevelopmentSeeder" -- --with-intervention-fixtures
```

(If the flag is not yet wired, create them inline:)

```powershell
php artisan tinker
> $factory = App\Modules\Booking\Database\Factories\BookingFactory::new();
> $factory->stalledWithLateVendor()->create();
> $factory->withAllVendorsRejected()->create();
> $factory->withCustomerReviewPending()->create();
> $factory->stalled()->create();
```

## 4. Open the page

1. Navigate to `http://localhost:8000/admin`.
2. Sign in as `admin@instaparty.test` (password: `password` per `SeedE2eAdminCommand`).
3. Open **Booking → Booking Intervention** from the sidebar.
4. Verify four rows appear, one per trouble bucket, with the correct badge.

## 5. Walk through each Action

### a. Send vendor reminder (P1)
- Click **Send reminder** on the "late vendor response" row.
- Confirm the success toast.
- Verify: `php artisan tinker --execute="echo App\Modules\Communication\Domain\Models\NotificationDispatch::latest()->first()->context | json_encode();"` includes `booking_vendor_id` and event key `booking.vendor.reminder`.
- Click **Send reminder** again immediately → throttled error (5-min cooldown).

### b. Escalate late vendor (P1)
- Click **Escalate** on the same row (after waiting for the deadline or seeding `response_deadline = NOW() - 2h`).
- Confirm vendor row `sub_status` is `timed_out` (visible in the detail view).
- Verify `state_transitions` table has a new row with `transitionable_type = App\Modules\Booking\Domain\Models\BookingVendor`, `trigger_kind = 'admin'`.
- Verify `admin_inbox_items` has a new row assigned to your admin.

### c. Suggest alternative vendors (P2)
- Click **Suggest alternatives** on a "vendor rejection" row.
- Pick 2 candidates from the filtered list (only approved + governorate-covered + product-type-approved vendors appear).
- Submit.
- Verify `booking_admin_interventions` has a row with `intervention_type = 'vendor_proposal'`, `proposed_vendor_id = NULL`, `after_state.suggested_vendor_ids = [id1, id2]`.
- Verify no new `booking_vendors` row was created — this is the hard boundary.

### d. Freeze / resume chat (P2, gated)
- Click **Freeze chat** on any row.
- Verify `chat_threads.frozen_at` is set; Firestore mirror push job appears in `queue:work` output.
- Click **Resume chat** → verify `frozen_at = NULL`.

### e. Resume customer review (P2)
- Click **Resume customer review** on the "customer review pending" row.
- Verify a `notification_dispatches` row keyed to `booking.customer_review.reminder` appears.
- Click again → throttled error (4-hour cooldown).

### f. Add intervention note (P2)
- Click **Add note**; enter "Customer called via WhatsApp; awaiting response by EOD."
- Verify a `booking_admin_interventions` row with `intervention_type = 'admin_note'` and the note in `reason`.
- Verify NO `notification_dispatches` row was created for this action.

## 6. Locale check

1. Switch the Filament admin locale to العربية (top-right language switcher).
2. Refresh the page — column labels, action buttons, badges all in Arabic, layout in RTL.
3. Open a booking detail; vendor business name and customer notes appear in Arabic.

## 7. Run the test suite

```powershell
.\vendor\bin\pest --group=booking --group=intervention --parallel
.\vendor\bin\pest --filter=AdminCannotAssignReplacementVendor
.\vendor\bin\pest --filter=VendorProposalInterventionHasNullProposedVendor
.\vendor\bin\pest --filter=EventsFireAfterCommit
```

Every test green; the architecture tests must pass on the first run.

## 8. What "done" looks like

- All six new Actions ship; FreezeBookingChat is either present or explicitly deferred via the cut-list with a follow-up issue filed.
- `php artisan pint` clean.
- `./vendor/bin/phpstan analyse` level baseline maintained.
- Architecture tests green.
- Pest group `booking,intervention` green, including per-product-type triplet for the listing query.
- Admin role seeder grants all 7 new permissions.
- `notification_templates` rows seeded (EN + AR) for all 7 event keys used.
- `docs/adr/ADR-0029-admin-booking-intervention-page.md` accepted.
- Backfill PRs filed against `01_PRD.md` §11 and `09_Phasing_Plan.md` Phase 6/7 for traceability — flagged in `spec.md` and `plan.md`.
