# Quickstart — Replacement Vendor Guard

**Feature**: `032-replacement-vendor-guard`
**Audience**: engineer or auditor verifying the guard end-to-end on a freshly migrated dev DB.
**Time required**: ~5 minutes.

---

## Pre-requisites

```powershell
git checkout 032-replacement-vendor-guard
composer install
php artisan migrate:fresh --seed
php artisan shield:generate --all
```

You need at minimum:
- A `super_admin` user
- An `admin` user (any operational variant)
- A `customer` user
- One `Booking` row in any `lifecycle_status`

The development seeder (`SeedE2eAdminCommand`) provides these — run `php artisan db:seed` if not already done.

---

## Step 1 — Verify the named policy method exists

```powershell
php artisan tinker --execute="echo (new \App\Modules\Booking\Domain\Policies\BookingPolicy())->assignReplacementVendor(\App\Modules\Identity\Domain\Models\User::role('super_admin')->first(), \App\Modules\Booking\Domain\Models\Booking::first()) ? 'allowed' : 'refused';"
```

**Expected output**: `refused`

---

## Step 2 — Verify the Gate facade returns `false` for super_admin

```powershell
php artisan tinker --execute="$u = \App\Modules\Identity\Domain\Models\User::role('super_admin')->first(); $b = \App\Modules\Booking\Domain\Models\Booking::first(); echo \Illuminate\Support\Facades\Gate::forUser($u)->allows('assignReplacementVendor', $b) ? 'allowed' : 'refused';"
```

**Expected output**: `refused`

---

## Step 3 — Verify the audit-log row was written

```powershell
php artisan tinker --execute="echo \DB::table('audit_logs')->where('action', 'booking.replacement_vendor_assignment_blocked')->latest('id')->first()?->changes;"
```

**Expected output**: a JSON string containing `attempted_at`, `role`, `source` (likely `"cli"` for these tinker calls), `ip`, `user_agent`.

---

## Step 4 — Verify admin can still suggest alternatives (regression check)

```powershell
php artisan tinker --execute="$admin = \App\Modules\Identity\Domain\Models\User::role('admin')->first(); $booking = \App\Modules\Booking\Domain\Models\Booking::first(); $vendorIds = \App\Modules\Identity\Domain\Models\VendorProfile::limit(2)->pluck('id')->all(); $dto = new \App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO(adminId: $admin->id, vendorProfileIds: $vendorIds, reason: 'quickstart smoke'); echo app(\App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction::class)->execute($booking, $dto)->intervention_type->value;"
```

**Expected output**: `vendor_proposal` (the intervention was created with `proposed_vendor_id = NULL`).

Verify the suggestion record was written without assignment:

```powershell
php artisan tinker --execute="echo \App\Modules\Booking\Domain\Models\BookingAdminIntervention::where('intervention_type', 'vendor_proposal')->latest('id')->first()?->proposed_vendor_id ?? 'NULL';"
```

**Expected output**: `NULL`

---

## Step 5 — Run the feature's Pest tests

```powershell
./vendor/bin/pest --filter=AssignReplacementVendor
./vendor/bin/pest --filter=AdminCanStillSuggestAlternatives
./vendor/bin/pest --group=architecture
```

**Expected**: all green.

---

## Step 6 — Confirm zero replacement-style permissions exist

```powershell
php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::where('name', 'like', '%assign_replacement%')->orWhere('name', 'like', '%replacement_vendor%')->orWhere('name', 'like', '%swap_vendor%')->count();"
```

**Expected output**: `0`

---

## What "passing" looks like

- Steps 1, 2 print `refused`.
- Step 3 returns a JSON payload (the tripwire fired).
- Step 4 prints `vendor_proposal` then `NULL`.
- Step 5 reports all tests passing.
- Step 6 prints `0`.

If any step deviates, the guard is broken — file a P1 issue.

---

## Rollback procedure

If for any reason this feature must be reverted:

```powershell
git revert <merge-commit-sha>
```

No data migration is required because no schema changed and the `audit_logs` rows already written remain valid append-only history.
