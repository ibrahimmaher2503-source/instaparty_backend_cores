# Phase 1 — Data Model

> This feature introduces **no new tables**. It reuses existing tables and adds **one new Domain Exception** and a **deadline guard** to three existing Application Actions.

---

## Entities Referenced (read-only or via existing Actions)

### `booking_vendors` *(primary entity of this page)*

| Column | Type | Role on this page |
|---|---|---|
| `id` | BIGINT PK | internal lookup |
| `public_id` | CHAR(26) ULID | URL route parameter |
| `booking_id` | FK → bookings | join to parent |
| `vendor_profile_id` | FK → vendor_profiles | ownership check |
| `sub_status` | ENUM(`Pending`,`Accepted`,`Rejected`,`Modified`,`InProgress`,`Completed`) | gates actions + read-only mode |
| `response_deadline` | TIMESTAMP NULL | drives countdown + deadline guard |
| `responded_at` | TIMESTAMP NULL | displayed in read-only mode |
| `rejection_reason` | JSON `{en,ar}` NULL | captured from reject modal |
| `subtotal_minor` | BIGINT UNSIGNED | displayed |
| `commission_minor` | BIGINT UNSIGNED | displayed |
| `vendor_payout_minor` | BIGINT UNSIGNED | displayed |

### `bookings`

| Column | Role |
|---|---|
| `reference_no` | header display |
| `event_starts_at` | header display |
| `lifecycle_status` | read-only mode trigger |
| `payment_status` | badge display |
| `customer_notes` | JSON translatable display |
| `customer_id` | join to customer name |

### `booking_items` *(filtered to this vendor)*

| Column | Role |
|---|---|
| `booking_vendor_id` | filter |
| `product_type` | badge + per-type render |
| `name_snapshot` | JSON translatable display |
| `type_snapshot` | per-type details (slot, qty, theme) |
| `quantity` | display |
| `unit_price_minor` | money display |
| `line_total_minor` | money display |
| `commission_bps` | (already shown on detail page; optional here) |

### `booking_addresses`

| Column | Role |
|---|---|
| `address_line` | JSON translatable display |
| `city_id` | coverage lookup |
| `governorate_id` | coverage fallback |

### `booking_modifications`

| Column | Role |
|---|---|
| `created_at` | display |
| `proposed_by_user_id` | display proposer label |
| `summary` | display in "Previous modifications" section |

### `booking_locks`

| Column | Role |
|---|---|
| `released_at` | NULL → read-only mode |

### `vendor_coverage_areas`

| Column | Role |
|---|---|
| `vendor_profile_id` | filter |
| `city_id` | coverage match |
| `governorate_id` | coverage match (when city_id NULL) |

### `service_inventory_reservations`

| Column | Role |
|---|---|
| `service_id` | match per item |
| `reserved_starts_at` / `reserved_ends_at` | overlap window |
| `status` | filter to `held`,`confirmed` |
| `booking_id` | exclude self |

### `audit_logs`, `booking_state_transitions`

Written by existing Actions / listeners. Page does NOT touch these.

### `idempotency_keys`

Optional, only used by Actions when DTO carries a key. Page does NOT mint keys.

---

## State Transitions Triggered

| Trigger | Action invoked | Resulting state |
|---|---|---|
| Vendor clicks **Accept** | `VendorAcceptBookingAction::execute(VendorAcceptDTO)` | `booking_vendors.sub_status: Pending → Accepted`; if all-accepted, `bookings.lifecycle_status: VendorReview → Confirmed` |
| Vendor clicks **Reject** + supplies reason | `VendorRejectBookingAction::execute(VendorRejectDTO)` | `booking_vendors.sub_status: Pending → Rejected`; if all-rejected, `bookings.lifecycle_status: VendorReview → Cancelled`; otherwise → `CustomerReview` |
| Vendor clicks **Modify** | (a) when `VendorBookingModificationBuilder` exists → redirect; (b) else → no-op notification | none (mutation happens inside the modification builder) |

All three transitions:
- Wrap in `DB::transaction`
- Write a `booking_state_transitions` row via existing listener
- Fire domain event after commit (`VendorAccepted`, `VendorRejected`)
- Write `audit_logs` via existing audit listener

---

## New Domain Exception

```php
namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class ResponseDeadlineExpiredException extends RuntimeException
{
    public function __construct(
        public readonly int $bookingVendorId,
    ) {
        parent::__construct(
            __('booking.errors.response_deadline_expired'),
            409,
        );
    }
}
```

Rendered as HTTP 409 via Laravel's exception handler. Filament catches it and surfaces a `danger` notification on the page.

---

## DTOs (unchanged, referenced for completeness)

- `App\Modules\Booking\Application\DTOs\VendorAcceptDTO` — `bookingVendorId`, `vendorProfileId`, `proposedByUserId`, `idempotencyKey?`
- `App\Modules\Booking\Application\DTOs\VendorRejectDTO` — `bookingVendorId`, `vendorProfileId`, `proposedByUserId`, `rejectionReason?: array{en,ar}`, `idempotencyKey?`
- `App\Modules\Booking\Application\DTOs\VendorModifyDTO` — (existing shape; not invoked from this page in Phase 3.2)

---

## Validation Rules

| Surface | Rule | Source |
|---|---|---|
| Reject modal | `reason_en`, `reason_ar` both optional; if either present, the JSON `{en, ar}` is stored | Spec User Story 2 / FR-EXT-030-032 |
| Reject modal | If both blank, payload is `null` (matches existing inline reject behavior) | Spec FR-EXT-030-032 |
| Page mount | `bookingVendor` URL parameter must be a valid 26-char ULID; mismatched length → 404 | Route param constraint |
| Page mount | Booked vendor must equal `auth()->user()->vendorProfile->id` → else 403 | FR-EXT-030-041 |
| Action invocation | `booking_vendors.sub_status === Pending` → else 409 | Existing Action guard |
| Action invocation | `booking_vendors.response_deadline === null \|\| !response_deadline->isPast()` → else 409 (`ResponseDeadlineExpiredException`) | NEW guard added by this plan |
