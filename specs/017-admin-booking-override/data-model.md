# Data Model: Admin Booking Override

**Feature**: Phase 6.5 — Admin Booking Override
**Date**: 2026-05-03

---

## New Table: `booking_admin_interventions`

This table is the primary audit surface for all admin interventions. It is append-only except for the `customer_consent_status` column (status-only update, per Constitution Principle V).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | NO | Internal PK |
| `public_id` | CHAR(26) UNIQUE | NO | ULID — exposed in API URLs and notifications |
| `booking_id` | BIGINT UNSIGNED FK→bookings.id | NO | restrictOnDelete |
| `admin_id` | BIGINT UNSIGNED FK→users.id | NO | restrictOnDelete (admin who acted) |
| `intervention_type` | ENUM('force_cancel','vendor_timeout','vendor_proposal','admin_note') | NO | Governs side effects |
| `reason` | TEXT | NO | Admin-provided rationale (EN, internal) |
| `before_state` | JSON | NO | Snapshot: `{lifecycle_status, payment_status, fulfillment_status}` |
| `after_state` | JSON | NO | Snapshot after any transition (same as before for admin_note) |
| `customer_consent_status` | ENUM('pending','accepted','rejected','consent_expired') | YES | NULL for non-proposal types |
| `proposed_vendor_id` | BIGINT UNSIGNED FK→vendor_profiles.id | YES | NULL for non-proposal types; restrictOnDelete |
| `consent_expires_at` | TIMESTAMP | YES | NULL for non-proposal types; proposal TTL deadline |
| `created_at` | TIMESTAMP | NO | Immutable after insert |
| `updated_at` | TIMESTAMP | NO | Updated only when `customer_consent_status` changes |

**Indexes**:
- `(booking_id, intervention_type)` — intervention history per booking per type
- `(customer_consent_status, consent_expires_at)` — expiry job query
- `(admin_id, created_at)` — admin activity audit

**Migration file**: `app/Modules/Booking/Database/Migrations/2026_05_03_000012_create_booking_admin_interventions_table.php`

---

## Modified Table: `booking_vendors`

No new columns. The existing `sub_status` column gets a new enum value `'timed_out'` to represent a vendor that was force-timed-out by admin.

**Change**: The `sub_status` column's ENUM must be extended to include `'timed_out'`.

> Implementation note: Extend `VendorSubStatus` enum with `TimedOut = 'timed_out'` case. The DB migration alters the column definition to add `'timed_out'` to the enum.

---

## Modified Table: `bookings`

No new columns. The existing `lifecycle_status` is mutated by `ForceCancelBookingAction` and `TimeoutVendorResponseAction`. No DDL change required.

---

## New Domain Enums

### `InterventionType`

```
App\Modules\Booking\Domain\Enums\InterventionType
Cases: ForceCanel, VendorTimeout, VendorProposal, AdminNote
```

### `CustomerConsentStatus`

```
App\Modules\Booking\Domain\Enums\CustomerConsentStatus
Cases: Pending, Accepted, Rejected, ConsentExpired
```

### Extended `VendorSubStatus`

Add `TimedOut = 'timed_out'` to existing enum.

---

## New Domain Events

| Event | Fired by | Carries |
|---|---|---|
| `BookingForceCancelled` | `ForceCancelBookingAction` (DB::afterCommit) | `Booking $booking`, `BookingAdminIntervention $intervention` |
| `VendorResponseTimedOut` | `TimeoutVendorResponseAction` (DB::afterCommit) | `Booking $booking`, `BookingAdminIntervention $intervention` |
| `AlternativeVendorProposed` | `ProposeAlternativeVendorAction` (DB::afterCommit) | `Booking $booking`, `BookingAdminIntervention $intervention` |
| `VendorProposalDecided` | `RespondToVendorProposalAction` (DB::afterCommit) | `Booking $booking`, `BookingAdminIntervention $intervention`, `bool $accepted` |

---

## New Application DTOs

### `AdminInterventionDTO`

```
bookingId: int
adminId: int
interventionType: InterventionType
reason: string
proposedVendorId: ?int         // only for VendorProposal
consentExpiresAt: ?Carbon      // only for VendorProposal (default: now() + 48h)
```

### `VendorProposalResponseDTO`

```
interventionPublicId: string   // ULID
customerId: int
decision: CustomerConsentStatus // Accepted or Rejected only
```

---

## New Models

### `BookingAdminIntervention`

- Relationships: `belongsTo(Booking)`, `belongsTo(User, 'admin_id')`, `belongsTo(VendorProfile, 'proposed_vendor_id')`
- Casts: `before_state → array`, `after_state → array`, `intervention_type → InterventionType::class`, `customer_consent_status → CustomerConsentStatus::class`, `consent_expires_at → datetime`
- No soft deletes (append-only)

---

## New API Resources

### `BookingAdminInterventionResource` (admin-facing)

Returns: `public_id`, `intervention_type` (label), `reason`, `before_state`, `after_state`, `customer_consent_status`, `proposed_vendor` (name only), `consent_expires_at`, `created_at`

### `VendorProposalResource` (customer-facing, embedded in booking response)

Returns: `public_id`, `proposed_vendor_name`, `customer_consent_status`, `consent_expires_at` — minimal, no internal admin data

---

## State Transition Map for Admin Interventions

| Intervention | Applicable Lifecycle States | Resulting Lifecycle State | booking_vendors sub_status |
|---|---|---|---|
| `force_cancel` | Any except `completed` | `cancelled` | `cancelled` (all vendors) |
| `vendor_timeout` | `vendor_review` only | `cancelled` | `timed_out` (timed-out vendor) |
| `vendor_proposal` | `vendor_review`, `customer_review`, `confirmed` | unchanged | unchanged (pending customer decision) |
| `admin_note` | Any | unchanged | unchanged |

---

## Notification Templates Required (new event keys)

| Event Key | Audience | Channel | EN Subject (example) | AR Subject (example) |
|---|---|---|---|---|
| `booking.admin_force_cancelled` | customer | push + email | "Your booking has been cancelled" | "تم إلغاء حجزك" |
| `booking.vendor_timed_out` | customer | push + email | "Your vendor didn't respond — we've cancelled the booking" | "لم يستجب البائع — تم إلغاء الحجز" |
| `booking.vendor_proposal_received` | customer | push + email | "An alternative vendor has been proposed for your booking" | "تم اقتراح بائع بديل لحجزك" |
| `booking.vendor_proposal_expiry_reminder` | customer | push | "Your vendor proposal expires soon — please decide" | "انتهاء صلاحية اقتراح البائع قريباً" |
