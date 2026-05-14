# Data Model: Admin Booking View

No new tables are introduced. This feature reads existing data and may add relationship-only methods to domain models.

## Booking

**Source table**: `bookings`

**Purpose**: Primary admin record under review.

**Displayed fields**:
- `public_id`
- `reference_no`
- `customer_id` resolved through Customer
- `occasion_id` resolved through Occasion
- `lifecycle_status`
- `payment_status`
- `fulfillment_status`
- `event_starts_at`
- `event_ends_at`
- `guest_count`
- `subtotal_minor`, `subtotal_currency`
- `delivery_total_minor`, `delivery_total_currency`
- `discount_total_minor`, `discount_total_currency`
- `total_minor`, `total_currency`
- `amount_paid_minor`, `amount_paid_currency`
- `submitted_at`, `confirmed_at`, `cancelled_at`

**Relationships used**:
- `customer`
- `occasion`
- `address`
- `vendors`
- `items`
- `snapshots`
- `stateTransitions`
- `payments` (planned relationship if missing)

**Validation/constraints**:
- Display only from this feature, except existing header actions.
- Must not expose internal `id` in primary UI labels when a human label exists.

## Booking Vendor

**Source table**: `booking_vendors`

**Purpose**: Vendor-specific booking split.

**Displayed fields**:
- vendor business name
- `sub_status`
- `response_deadline`
- `responded_at`
- `subtotal_minor`, `subtotal_currency`
- `delivery_fee_minor`, `delivery_fee_currency`
- `commission_minor`, `commission_currency`
- `vendor_payout_minor`, `vendor_payout_currency`
- `vendor_notes`
- `rejection_reason`

**Relationships used**:
- `booking`
- `items`
- `vendor` to `VendorProfile` (planned relationship if missing)

**Validation/constraints**:
- Read-only relation manager.
- Vendor label must prefer translated `business_name` in active locale.

## Booking Item

**Source table**: `booking_items`

**Purpose**: Service line under a vendor booking split.

**Displayed fields**:
- service label from `service` or `name_snapshot`
- `product_type`
- `quantity`
- `unit_price_minor`, `unit_price_currency`
- `line_total_minor`, `line_total_currency`
- `commission_minor`, `commission_currency`
- `effective_starts_at`
- `effective_ends_at`
- `item_status`
- `commission_bps`

**Relationships used**:
- `bookingVendor`
- `service` to Catalog Service (planned relationship if missing)

**Validation/constraints**:
- Read-only relation manager.
- Status colors must cover rental, sale, and digital cases.
- Product-type logic uses the canonical enum and `match`.

## Booking Address

**Source table**: `booking_addresses`

**Purpose**: Immutable snapshot of event location and recipient details.

**Displayed fields**:
- `address_line`
- `building`
- `floor`
- `apartment`
- `landmark`
- `latitude`
- `longitude`
- `recipient_name`
- `recipient_phone_e164`
- `city_id` if city relationship is unavailable

**Relationships used**:
- `booking`

**Validation/constraints**:
- Read-only.
- Do not replace it with `customer_addresses`; booking address is a historical snapshot.

## Payment

**Source table**: `payments`

**Purpose**: Booking payment records shown in context.

**Displayed fields**:
- `public_id`
- `gateway`
- `gateway_ref`
- `amount_minor`, `amount_currency`
- `method`
- `status`
- `captured_at`
- `failure_code`
- `failure_message`
- `created_at`

**Relationships used**:
- `booking`
- optional relation from `Booking::payments()`

**Validation/constraints**:
- Read-only from Booking view.
- Payment detail navigation should use existing Payment resource pages.

## Booking Snapshot

**Source table**: `booking_snapshots`

**Purpose**: Versioned read model for booking investigation.

**Displayed fields**:
- `public_id`
- `version`
- `trigger_kind`
- `trigger_reference_type`
- `trigger_reference_id`
- `triggered_by`
- `snapshot`
- `created_at`

**Relationships used**:
- `booking`
- optional `triggeredBy` user relationship for actor display

**Validation/constraints**:
- Append-only and read-only.
- JSON payload viewer must not mutate payload.

## Booking State Transition

**Source table**: `booking_state_transitions`

**Purpose**: Chronological state history for Booking, BookingVendor, or BookingItem.

**Displayed fields**:
- `transitionable_type`
- `transitionable_id`
- `from_state`
- `to_state`
- `trigger_kind`
- `triggered_by`
- `context`
- `created_at`

**Relationships used**:
- `transitionable`
- optional `triggeredByUser` relationship for actor display

**Validation/constraints**:
- Append-only and read-only.
- Timeline order must be consistent.

## Customer

**Source table**: `users`

**Purpose**: Booking owner display in list and detail.

**Displayed fields**:
- `name`
- `phone_e164`
- `public_id` only as secondary context if needed

**Relationships used**:
- `Booking::customer()`

**Validation/constraints**:
- Customer column must not show raw internal ID when user exists.

## Occasion

**Source table**: `occasions`

**Purpose**: Localized event occasion display.

**Displayed fields**:
- translated `name`

**Relationships used**:
- `Booking::occasion()`

**Validation/constraints**:
- Prefer active admin locale, then English, then first available non-empty translation.

## Vendor Profile

**Source table**: `vendor_profiles`

**Purpose**: Vendor business name display for booking vendor rows.

**Displayed fields**:
- translated `business_name`
- `public_id` as fallback secondary context

**Relationships used**:
- planned `BookingVendor::vendor()`

**Validation/constraints**:
- Do not mutate approval or profile data.
