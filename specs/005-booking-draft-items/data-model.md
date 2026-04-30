# Data Model: Booking — Draft Creation & Item Management

**Date**: 2026-04-30
**Status**: Schema locked from `docs/specs/11_DB_Schema.md`. State machines designed in this plan.

---

## Entity Map

### Booking

**Table**: `bookings`
**Traits**: `HasPublicId`, `SoftDeletes`, `HasFactory`
**Translatable**: `['theme', 'customer_notes']`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | internal |
| public_id | CHAR(26) UNIQUE | ULID, exposed in API |
| reference_no | VARCHAR(20) UNIQUE | `IP-2026-000001` format |
| customer_id | BIGINT FK→users | |
| occasion_id | BIGINT FK→occasions | |
| lifecycle_status | ENUM | `draft`→`submitted`→`vendor_review`→`confirmed`→… |
| payment_status | ENUM | Default `unpaid` |
| fulfillment_status | ENUM | Default `not_started` |
| event_starts_at | TIMESTAMP | UTC |
| event_ends_at | TIMESTAMP | UTC |
| subtotal_minor | BIGINT UNSIGNED | computed |
| delivery_total_minor | BIGINT UNSIGNED | computed |
| discount_total_minor | BIGINT UNSIGNED | default 0 |
| total_minor | BIGINT UNSIGNED | computed |
| created_at, updated_at, deleted_at | | Soft delete |

**Relationships**:
- `hasOne(BookingAddress)`
- `hasMany(BookingVendor)`
- `hasManyThrough(BookingItem, BookingVendor)`
- `hasMany(BookingSnapshot)`
- `hasMany(BookingStateTransition)`

**Casts**:
```php
protected $casts = [
    'lifecycle_status'   => LifecycleStatus::class,
    'payment_status'     => PaymentStatus::class,
    'fulfillment_status' => FulfillmentStatus::class,
    'event_starts_at'    => 'datetime',
    'event_ends_at'      => 'datetime',
];
```

---

### BookingAddress

**Table**: `booking_addresses`
**Traits**: none (no public_id — internal read model)

Snapshot copied from `customer_addresses` at booking creation. Fields: `city_id`, `address_line`, `building`, `floor`, `apartment`, `landmark`, `latitude`, `longitude`, `recipient_name`, `recipient_phone_e164`.

**Relationships**: `belongsTo(Booking)`, `belongsTo(City)` (read-only FK, not cross-module write)

---

### BookingVendor

**Table**: `booking_vendors`
**Traits**: `HasPublicId`

| Field | Type | Notes |
|---|---|---|
| booking_id | BIGINT FK | |
| vendor_profile_id | BIGINT FK | |
| sub_status | ENUM | Default `pending` |
| response_deadline | TIMESTAMP | `now() + 24h` when booking submitted |
| subtotal_minor | BIGINT UNSIGNED | SUM of item line_totals |
| delivery_fee_minor | BIGINT UNSIGNED | from vendor_coverage_areas |

**Unique constraint**: `(booking_id, vendor_profile_id)` — one row per vendor per booking.

**Relationships**: `belongsTo(Booking)`, `hasMany(BookingItem)`

---

### BookingItem

**Table**: `booking_items`
**Traits**: `HasPublicId`

| Field | Type | Notes |
|---|---|---|
| booking_vendor_id | BIGINT FK | |
| service_id | BIGINT FK | |
| product_type | ENUM | Denormalized discriminator |
| name_snapshot | JSON | Frozen translatable name at booking time |
| unit_price_minor | BIGINT UNSIGNED | Snapshot of base_price_minor |
| quantity | INT UNSIGNED | |
| line_total_minor | BIGINT UNSIGNED | `unit_price_minor × quantity` |
| effective_starts_at | TIMESTAMP | Defaults to `booking.event_starts_at` |
| effective_ends_at | TIMESTAMP | |
| item_status | VARCHAR(40) | Per-type state machine value |
| type_snapshot | JSON | Frozen type-specific rules at booking time |
| commission_bps | INT UNSIGNED | Locked commission rate at booking time |

**Relationships**: `belongsTo(BookingVendor)`, `belongsTo(Service)`, `hasOne(ServiceInventoryReservation)`

**State machine resolution**:
```php
public function resolveItemState(): State
{
    return match ($this->product_type) {
        ProductType::Rental  => app(RentalItemStatus::class, ['model' => $this]),
        ProductType::Sale    => app(SaleItemStatus::class,   ['model' => $this]),
        ProductType::Digital => app(DigitalItemStatus::class, ['model' => $this]),
    };
}
```

---

### BookingLock

**Table**: `booking_locks`

Used for application-level pessimistic coordination across worker processes. `UNIQUE (resource_type, resource_id, released_at)` ensures one active lock per resource.

| Field | Notes |
|---|---|
| resource_type | `service`, `booking`, `booking_vendor` |
| resource_id | FK to the resource's `id` |
| lock_token | UUID, used by lock holder |
| lock_purpose | `payment`, `modification`, `admin_action` |
| expires_at | Lock TTL |
| released_at | NULL while active |

---

### ServiceInventoryReservation (owned by Catalog, used here)

Phase 3.1 creates `service_inventory_reservations` rows when adding rental/sale items. The Booking module writes to this table via `BookingItemRepository::createReservation()`. It does NOT import the Catalog model directly; it uses a DTO + raw query within a transaction.

**TTL**: `expires_at = now()->addMinutes(15)` for cart holds.
**Cleanup**: `ReleaseExpiredReservationsCommand` (Booking module) updates `status = 'expired'` where `status = 'held' AND expires_at < now()`.

---

### BookingSnapshot (append-only)

**Table**: `booking_snapshots`

One snapshot written at draft creation (`version = 1`, `trigger_kind = 'booking_created'`). Additional snapshots deferred to Phase 3.2.

No `updated_at`. No soft delete.

---

### BookingStateTransition (append-only)

**Table**: `booking_state_transitions`

Written by `WriteBookingStateTransitionListener` on any `lifecycle_status`, `payment_status`, or `fulfillment_status` change. Polymorphic: covers `Booking`, `BookingVendor`, `BookingItem`.

No `updated_at`. No soft delete.

---

## Per-Type Fulfillment State Machines

### Rental Item States

```
pending_delivery
    → out_for_delivery   (triggered by vendor: item dispatched)
    → delivered          (triggered by vendor: item at venue)
    → setup_complete     (triggered by vendor: setup done)
    → picked_up          (triggered by vendor: item retrieved)
```

### Sale Item States

```
pending
    → in_preparation     (triggered by vendor: order accepted and started)
    → ready              (triggered by vendor: ready for pickup/delivery)
    → delivered          (triggered by vendor: delivered to customer)
```

### Digital Item States

```
pending
    → sent               (triggered by system: delivery dispatched via email/SMS/in_app)
    → redeemed           (triggered by customer: code used)
```

**Phase 3.1 scope**: All items created in their initial state. State transitions are defined but not invoked until Phase 3.2 (vendor response flow).

---

## Enums

### LifecycleStatus

```php
enum LifecycleStatus: string {
    case Draft          = 'draft';
    case Submitted      = 'submitted';
    case VendorReview   = 'vendor_review';
    case CustomerReview = 'customer_review';
    case Confirmed      = 'confirmed';
    case Active         = 'active';
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';
}
```

### PaymentStatus

```php
enum PaymentStatus: string {
    case Unpaid             = 'unpaid';
    case Partial            = 'partial';
    case Paid               = 'paid';
    case RefundPending      = 'refund_pending';
    case PartiallyRefunded  = 'partially_refunded';
    case Refunded           = 'refunded';
}
```

### FulfillmentStatus

```php
enum FulfillmentStatus: string {
    case NotStarted          = 'not_started';
    case InProgress          = 'in_progress';
    case PartiallyCompleted  = 'partially_completed';
    case Completed           = 'completed';
    case Failed              = 'failed';
}
```

### VendorSubStatus

```php
enum VendorSubStatus: string {
    case Pending    = 'pending';
    case Accepted   = 'accepted';
    case Modified   = 'modified';
    case Rejected   = 'rejected';
    case Cancelled  = 'cancelled';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
}
```

---

## Test Plan Summary

### `CreateBookingDraftTest`

| Case | Expected |
|---|---|
| Authenticated customer + valid payload | 201, booking `public_id` returned, `lifecycle_status = draft` |
| Unauthenticated request | 401 |
| Missing `event_starts_at` | 422 with field errors |
| Missing `occasion_id` | 422 |

### `AddItemToBookingTest`

| Case | Expected |
|---|---|
| Add rental item | 201, `booking_vendor` row created, reservation `held` for 15 min |
| Add sale item | 201, stock reservation created |
| Add digital item | 201, no reservation row |
| Add second item from same vendor | Only one `booking_vendor` row, subtotal updated |
| Add item from second vendor | Two `booking_vendor` rows |
| Add out-of-stock rental | 409 |
| Add to non-draft booking | 409 |
| Add item belonging to another user's booking | 403 |

### `RemoveItemFromBookingTest`

| Case | Expected |
|---|---|
| Remove item (vendor still has other items) | 200, reservation released, subtotal updated |
| Remove last item for a vendor | 200, `booking_vendor` row deleted |
| Remove item from non-draft booking | 409 |
| Remove item from another user's booking | 403 |

### `BookingTotalsTest`

| Case | Expected |
|---|---|
| Add item with quantity 2 | `line_total_minor = unit_price_minor × 2` |
| Two vendors, compute grand total | `total_minor = vendor_A_subtotal + vendor_B_subtotal + delivery_fees` |
| Remove item, totals recalculate | Total decreases by removed item's line total |
