# Admin UI Contract: Booking View

This feature adds no HTTP API contract. This document defines the admin UI contract that `/admin/bookings` must satisfy.

## Booking List Contract

### Entry point

- Admin route: `/admin/bookings`
- Primary action: open a booking row or invoke the row view action.

### Required columns

| Column | Required display |
|---|---|
| Booking reference | `reference_no` and/or `public_id` |
| Customer | Customer `name`; phone available as tooltip, description, or secondary text |
| Occasion | Localized occasion name for current admin locale |
| Lifecycle status | Localized badge |
| Payment status | Localized badge |
| Fulfillment status | Localized badge |
| Total | Minor units displayed as EGP money |
| Submitted at | Localized date/time |

### Prohibited display

- Raw `customer_id` as the main customer label when customer record exists.
- Raw `occasion_id` as the main occasion label when occasion record exists.
- Raw vendor profile IDs in booking-vendor displays when vendor record exists.

## Booking View Contract

### Entry point

- Admin route pattern: `/admin/bookings/{record}`
- Page purpose: everyday booking operations drill-down, not forensics reconstruction.

### Header actions

| Action | Contract |
|---|---|
| Edit | Available only if existing Booking policy/action allows it |
| Force Cancel | Reuses existing Force Cancel behavior and permission gating |
| Add Admin Note | Reuses existing Admin Note behavior and permission gating |

No line-item editing, payment mutation, replacement-vendor selection, print, or export action belongs to this page.

## Relation Manager Contracts

### Booking Vendors

Required columns:
- Vendor business name
- Vendor status
- Response deadline
- Vendor total/payout amount

Required behavior:
- Read-only.
- Vendor name uses active locale with fallback.

### Booking Items

Required columns:
- Service label from service relation or item snapshot
- Product type
- Quantity
- Line total
- Item status with clear state color

Required behavior:
- Read-only.
- Product type coverage includes rental, sale, and digital.

### Booking Addresses

Required columns:
- Address line
- Building/floor/apartment when available
- Landmark
- Recipient name
- Recipient phone
- Coordinates when available

Required behavior:
- Read-only snapshot; never points admins to edit customer saved addresses as booking history.

### Payments

Required columns:
- Payment public ID
- Gateway
- Gateway reference
- Amount
- Method
- Status
- Captured at
- Created at

Required behavior:
- Read-only from booking view.
- Link to Payment resource detail where the Payment resource supports detail view.

### Booking Snapshots

Required columns:
- Version
- Trigger kind
- Trigger reference
- Triggered by
- Created at
- JSON payload viewer

Required behavior:
- Read-only.
- Payload displayed in readable JSON form.

### Booking State Transitions

Required columns:
- Transition target
- From state
- To state
- Trigger kind
- Triggered by
- Created at
- Context/notes

Required behavior:
- Read-only.
- Ordered consistently as a timeline.

## Empty State Contract

Each relation manager must show a normal empty state when no related records exist. Missing related data must not break the booking detail page.

## Performance Contract

The list and detail pages must eager-load display relationships so that representative bookings with multiple vendors/items/payments/snapshots/transitions do not trigger repeated per-row lookups.
