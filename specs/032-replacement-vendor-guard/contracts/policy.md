# Contract — `BookingPolicy::assignReplacementVendor`

**Feature**: `032-replacement-vendor-guard`
**Date**: 2026-05-16
**Type**: Internal policy contract (not an HTTP API)

---

## Signature

```php
namespace App\Modules\Booking\Domain\Policies;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;

final class BookingPolicy
{
    /**
     * Hard refusal: admin cannot assign a replacement vendor on behalf of the customer.
     * Enforces FR-17 / FR-18 / BR-4 / FR-EXT-021. The customer remains the sole decider
     * of vendor alternatives.
     *
     * @return false  Always returns false. The return type is the literal `false` to
     *                make accidental `return true;` a fatal type error.
     */
    public function assignReplacementVendor(User $user, Booking $booking): false
    {
        $this->logBlockedReplacementAttempt($user, $booking);
        return false;
    }
}
```

---

## Behavioural contract (role matrix)

| Caller | `$user->can('assignReplacementVendor', $booking)` returns | `audit_logs` row inserted? |
|---|---|---|
| `super_admin` | `false` | yes |
| `admin` | `false` | yes |
| `ops_admin` | `false` | yes |
| `support_admin` | `false` | yes |
| `vendor` | `false` | yes |
| `customer` | `false` | yes |
| Unauthenticated / guest (`$user` is a `GenericUser` shim) | `false` | yes (with `user_id = NULL`) |

The result is **invariant** with respect to:
- Booking `lifecycle_status` (`draft`, `vendor_review`, `customer_review`, `confirmed`, `cancelled`, ...)
- Booking `product_type` (`rental`, `sale`, `digital`)
- Whether the original vendor has timed out / withdrawn / been suspended
- Permission table contents
- `app/Modules/*` package additions

---

## Audit-log row shape

```php
[
    'public_id'      => '<26-char Crockford ULID>',
    'user_id'        => 42,                                    // or NULL
    'auditable_type' => 'App\\Modules\\Booking\\Domain\\Models\\Booking',
    'auditable_id'   => 1234,
    'action'         => 'booking.replacement_vendor_assignment_blocked',
    'changes'        => '{"attempted_at":"2026-05-16T10:21:33+00:00","role":["super_admin"],"source":"admin.bookings.intervene","ip":"10.0.0.7","user_agent":"Mozilla/5.0 ..."}',
    'created_at'     => '2026-05-16 10:21:33',
]
```

`changes.source` falls back to the literal `"cli"` when no HTTP request is present (artisan invocations, queue worker, scheduled task).

---

## HTTP behaviour

If a controller calls `$this->authorize('assignReplacementVendor', $booking)`:

- Laravel throws `Illuminate\Auth\Access\AuthorizationException`.
- Laravel's default exception handler renders **HTTP 403** with the standard error envelope.
- The audit-log row is inserted **before** the exception is thrown (inside the policy method).

Per spec FR-EXT-021c and SC-021-06: response time budget < 50 ms p95 for the 403 path.

---

## What this contract does NOT do

- It does not block customer-driven vendor selection from an admin-suggested list (that flow uses a separate, customer-facing endpoint).
- It does not modify the existing `BookingAdminInterventionPolicy::create()` refusal for non-null `proposed_vendor_id`.
- It does not block `BookingVendor.sub_status` updates (vendor lifecycle), only `vendor_profile_id` changes — which no code path currently performs.

---

## Test obligations

Pest tests under `tests/Feature/Modules/Booking/Policies/`:

1. **Role matrix test** — assert `false` for every role in the matrix above; assert one audit-log row per attempt.
2. **Unauthenticated test** — assert `false` and audit row with `user_id = NULL`.
3. **Booking state invariance** — loop over `lifecycle_status` values; assert refusal for each.
4. **Product type invariance** — loop over `rental / sale / digital`; assert refusal for each.
5. **Regression test** — assert `SuggestAlternativeVendorsAction::execute` still succeeds for an admin with `booking.intervene.suggest_alternative_vendors` permission, and that no `audit_logs` row with the blocked-action key is inserted.

Architecture test under `tests/Architecture/`:

6. **Reflection test** — assert the `BookingPolicy` class has a public method `assignReplacementVendor` with the exact signature and that calling it returns `false`. Catches accidental rename / removal.
