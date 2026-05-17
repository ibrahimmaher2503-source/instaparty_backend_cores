# Contracts — Permissions, Policies, and Authorization

> Every new ability is registered as a Spatie `Permission` row via `php artisan shield:generate --all` after the Filament resource is added. Granted to the `admin` role in `app/Modules/Identity/Database/Seeders/IdentityPermissionsSeeder.php` (or the Booking module's permissions seeder).

---

## Shield permission strings (NEW)

| Permission | Action(s) gated | Notes |
|---|---|---|
| `booking.intervene.access` | the entire `AdminBookingInterventionResource` (page-level `canAccess()`) | Master gate — without this, the user does not see the nav entry. |
| `booking.intervene.send_vendor_reminder` | `SendVendorReminderAction` invocation; `Action::make('sendVendorReminder')->visible(...)` | |
| `booking.intervene.escalate_vendor_timeout` | `EscalateLateVendorResponseAction` invocation; `Action::make('escalateLateVendorResponse')->visible(...)` | |
| `booking.intervene.suggest_alternative_vendors` | `SuggestAlternativeVendorsAction` invocation; `Action::make('suggestAlternativeVendors')->visible(...)` | |
| `booking.intervene.freeze_chat` | `FreezeBookingChatAction` / `ResumeBookingChatAction` invocations | One permission covers both freeze + resume. |
| `booking.intervene.resume_customer_review` | `ResumeBookingReviewAction` invocation | |
| `booking.intervene.create_note` | `CreateAdminInterventionNoteAction` invocation | |

The existing `force_cancel_booking` permission (used by `BookingsMonitorResource`) is **unchanged**. The new resource may expose Force-Cancel as well if the acting user has both `booking.intervene.access` AND `force_cancel_booking`.

## Policy: `BookingAdminInterventionPolicy` (NEW)

**Path**: `app/Modules/Booking/Domain/Policies/BookingAdminInterventionPolicy.php`

```php
final class BookingAdminInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('booking.intervene.access');
    }

    public function view(User $user, BookingAdminIntervention $i): bool
    {
        return $user->can('booking.intervene.access');
    }

    /**
     * Hard boundary — admin cannot create a `vendor_proposal` intervention with a non-null
     * `proposed_vendor_id`. This is the customer's choice.
     */
    public function create(User $user, BookingAdminIntervention $i): bool
    {
        if ($i->intervention_type === InterventionType::VendorProposal && $i->proposed_vendor_id !== null) {
            return false;   // FR-EXT-012
        }
        return match ($i->intervention_type) {
            InterventionType::VendorReminder         => $user->can('booking.intervene.send_vendor_reminder'),
            InterventionType::VendorTimeout          => $user->can('booking.intervene.escalate_vendor_timeout'),
            InterventionType::VendorProposal         => $user->can('booking.intervene.suggest_alternative_vendors'),
            InterventionType::ChatFrozen,
            InterventionType::ChatResumed            => $user->can('booking.intervene.freeze_chat'),
            InterventionType::CustomerReviewReminder => $user->can('booking.intervene.resume_customer_review'),
            InterventionType::AdminNote              => $user->can('booking.intervene.create_note'),
            InterventionType::ForceCancel            => $user->can('force_cancel_booking'),
        };
    }
}
```

Registered in `BookingServiceProvider::boot()` via `Gate::policy(BookingAdminIntervention::class, BookingAdminInterventionPolicy::class)`.

## Filament resource gate

`AdminBookingInterventionResource` implements:

```php
public static function canViewAny(): bool
{
    return auth()->user()?->can('booking.intervene.access') === true;
}
```

Each table Action sets `->visible(fn () => auth()->user()?->can('booking.intervene.{...}'))`.

## Forbidden permission

No permission named `booking.intervene.assign_replacement_vendor` (or any variant) is ever registered. The architecture test `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` asserts this via Spatie's `Permission::query()->where('name', 'LIKE', '%assign_replacement%')->doesntExist()`.

## Seeding plan

`app/Modules/Booking/Database/Seeders/BookingPermissionsSeeder.php` is updated (the existing seeder, not a new one) to insert the 7 new permission rows and to grant them to the `admin` role. The migration that runs `shield:generate --all` is part of CI, not part of a Laravel migration; the seeder handles role-grant.

## Multi-admin race conditions

- `EscalateLateVendorResponseAction` uses `lockForUpdate()` on the `BookingVendor` row inside its transaction; concurrent escalations see `sub_status = timed_out` on the second read and throw `\DomainException`.
- `SendVendorReminderAction` and `ResumeBookingReviewAction` use `idempotency_keys` to coalesce duplicate clicks across admins within the throttle window.
- `FreezeBookingChatAction` uses `lockForUpdate()` on the `chat_threads` row to prevent simultaneous freezes.
