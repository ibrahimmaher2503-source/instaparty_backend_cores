# ADR-0013 — Admin Booking Override & Intervention Capability

- **Status:** Accepted
- **Date:** 2026-05-03
- **Decision-makers:** Ibrahim
- **Tags:** booking, admin-intervention, phase-6.5
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent architecture pattern
  - [`docs/adr/0003-identity-booking-states.md`](0003-identity-booking-states.md) — booking state machines
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §FR-16, §FR-17, §FR-18
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) — Booking module tables
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) — Phase 6.5, Week 7

---

## 1. Context

**EN:** Admins need the ability to intervene in bookings that are stuck, disputed, or require manual resolution — without bypassing the audit trail or breaking module boundaries. Three concrete scenarios drive this ADR: (1) force-cancelling a booking when a vendor becomes non-responsive (FR-16), (2) timing out a vendor who has not responded within the SLA window (FR-17), and (3) proposing an alternative vendor to the customer when the original vendor cannot fulfill the booking (FR-18). All three interventions must be traceable, reversible only through the normal booking flow, and must not allow admins to silently mutate state.

**Phase:** 6.5 (Week 7 — Hardening + Admin Tools)

---

## 2. Responsibilities

### This feature owns:

- The `booking_admin_interventions` table (new) and its insert-only lifecycle
- `ForceCancelBookingAction`, `TimeoutVendorResponseAction`, `ProposeAlternativeVendorAction`, `AddBookingNoteAction`
- Triple-log enforcement: every intervention writes to `booking_admin_interventions` + `booking_state_transitions` (state mutations only) + `audit_logs` (all)
- The `VendorSubStatus::TimedOut` enum case added to the existing vendor sub-status machine
- Admin-facing Filament actions on `BookingsMonitorResource` that delegate to the Actions above

### This feature does NOT own:

- Refund processing — triggered via `BookingForceCancelled` domain event consumed by the Payments module
- Customer notification delivery — triggered via domain events consumed by the Communication module
- The core booking state machine itself (owned by the Booking module's `BookingLifecycleState`)
- `booking_vendors` row creation or vendor matching — owned by the Booking application layer

---

## 3. Tables Affected

| Table | Purpose | Soft-delete? | Append-only? | Change |
|---|---|---|---|---|
| `booking_admin_interventions` | Immutable record of every admin intervention | No | Yes (insert-only) | **NEW** |
| `bookings` | Core booking record | Yes | No | `lifecycle_status` column updated on force-cancel |
| `booking_vendors` | Per-vendor sub-booking record | No | No | `sub_status` updated on vendor timeout |
| `booking_state_transitions` | Append-only state change log | No | Yes | New rows appended on state mutations |
| `audit_logs` | Append-only admin action log | No | Yes | New rows appended on all interventions |

### `booking_admin_interventions` schema

```sql
CREATE TABLE booking_admin_interventions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id               CHAR(26) NOT NULL UNIQUE,          -- ULID
    booking_id              BIGINT UNSIGNED NOT NULL,
    admin_user_id           BIGINT UNSIGNED NOT NULL,
    intervention_type       ENUM('force_cancel','timeout_vendor','propose_alternative','add_note') NOT NULL,
    target_booking_vendor_id BIGINT UNSIGNED NULL,             -- set for vendor-scoped interventions
    reason                  JSON NOT NULL,                     -- {"en": "...", "ar": "..."}
    metadata                JSON NULL,                         -- type-specific payload
    customer_consent_status ENUM('not_required','pending','accepted','declined') NOT NULL DEFAULT 'not_required',
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- No updated_at (append-only); customer_consent_status is the ONLY mutable column

    CONSTRAINT fk_bai_booking    FOREIGN KEY (booking_id)               REFERENCES bookings(id),
    CONSTRAINT fk_bai_admin_user FOREIGN KEY (admin_user_id)            REFERENCES users(id),
    CONSTRAINT fk_bai_bv         FOREIGN KEY (target_booking_vendor_id) REFERENCES booking_vendors(id),
    INDEX idx_bai_booking        (booking_id),
    INDEX idx_bai_admin          (admin_user_id, created_at)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Mutability rule:** `customer_consent_status` is the **only** column that may be UPDATEd after insert. All other columns are effectively immutable. Any code that attempts to UPDATE `reason`, `metadata`, `intervention_type`, or `created_at` is a bug.

### Foreign key dependencies

| FK | References | Module |
|---|---|---|
| `booking_admin_interventions.booking_id` | `bookings.id` | Booking |
| `booking_admin_interventions.admin_user_id` | `users.id` | Identity |
| `booking_admin_interventions.target_booking_vendor_id` | `booking_vendors.id` | Booking |

---

## 4. Type-awareness

**Cross-type.** Admin interventions apply uniformly across all three product types (rental, sale, digital). No per-type branching is needed in the intervention Actions themselves. The downstream effects (refund policy, fulfillment state) remain type-aware within their own modules.

---

## 5. Internal Decisions

### 5.1 Refund trigger via domain event, not direct Action call

**Decision:** `ForceCancelBookingAction` fires `BookingForceCancelled` event after the transaction commits. The Payments module listens and calls its own `InitiateRefundAction`.

**Alternative rejected:** Directly calling `InitiateRefundAction` from within `ForceCancelBookingAction`.

**Why:** Direct cross-module Action calls violate the modular monolith boundary defined in ADR-0001. Using a domain event preserves module independence — the Booking module does not need to know about Payments internals, and the Payments module can evolve its refund logic independently.

### 5.2 `customer_consent_status` as the sole mutable column

**Decision:** `booking_admin_interventions` is append-only except for `customer_consent_status`, which tracks whether the customer has accepted a proposed alternative vendor.

**Alternative rejected:** Creating a separate `booking_customer_consents` table.

**Why:** The consent status is inseparable from the intervention record — it answers "did the customer agree to this specific proposal?" Separating it would require a join on every read and adds no semantic value. Keeping it on the intervention row while making everything else immutable satisfies both the audit requirement and the data model simplicity goal.

### 5.3 `VendorSubStatus::TimedOut` distinguishes admin-triggered timeout

**Decision:** A new `TimedOut` case is added to the `VendorSubStatus` enum. This is set by `TimeoutVendorResponseAction` and is distinct from `Cancelled` (which is set by normal cancellation flow).

**Alternative rejected:** Reusing `VendorSubStatus::Cancelled` with a metadata flag.

**Why:** Downstream processes (SLA reporting, vendor penalty scoring, dispute resolution) need to distinguish between a vendor who was cancelled normally and one who was timed out by admin intervention. Conflating these into a single status with metadata would require parsing JSON in every query that needs this distinction.

### 5.4 FR-17: `ProposeAlternativeVendorAction` is read-only with respect to `booking_vendors`

**Decision:** `ProposeAlternativeVendorAction` records the proposal in `booking_admin_interventions` and fires a notification, but does NOT insert or mutate any row in `booking_vendors`. Only `RespondToVendorProposalAction` (called in the customer context after consent) may insert a new `booking_vendors` row.

**Alternative rejected:** Having `ProposeAlternativeVendorAction` pre-insert a `booking_vendors` row in `pending` status.

**Why:** Pre-inserting a vendor row before customer consent creates a race condition — the customer may decline, leaving a dangling `booking_vendors` row. It also means the customer response Action must "activate" a row rather than create one, which is an unintuitive state machine. The chosen approach keeps `booking_vendors` as a record of confirmed vendor relationships only.

### 5.5 Triple-logging requirement

**Decision:** Every admin intervention MUST write to all three of the following:
1. `booking_admin_interventions` — always (all intervention types)
2. `booking_state_transitions` — only when booking or vendor sub-status is mutated
3. `audit_logs` — always (all intervention types), with `actor_id = admin_user_id`

**Why:** These three logs serve different consumers. `booking_admin_interventions` is the operational record for customer support and dispute resolution. `booking_state_transitions` is the state machine audit trail used by the booking history reader. `audit_logs` is the compliance record for all admin actions. Omitting any one of these breaks a downstream consumer.

Implementation: All three writes happen inside a single `DB::transaction()`. The `BookingForceCancelled` event fires after commit via `DB::afterCommit()`.

---

## 6. Inter-Module Communication

### Events we publish:

| Event | When | Payload |
|---|---|---|
| `Booking\Domain\Events\BookingForceCancelled` | After force-cancel transaction commits | `booking_id`, `admin_user_id`, `reason`, `intervention_id` |
| `Booking\Domain\Events\VendorResponseTimedOut` | After vendor timeout transaction commits | `booking_vendor_id`, `booking_id`, `admin_user_id` |
| `Booking\Domain\Events\AlternativeVendorProposed` | After proposal recorded | `intervention_id`, `booking_id`, `proposed_vendor_id`, `admin_user_id` |

### Events we consume:

None for this feature. Admin interventions are initiated by admin action, not by external events.

### Public Contracts we expose:

None new. The four Actions are internal to the Booking module and called from Filament actions only.

---

## 7. Permissions

Four new admin permissions gate these interventions:

| Permission | Action |
|---|---|
| `force_cancel_booking` | `ForceCancelBookingAction` |
| `timeout_vendor_response` | `TimeoutVendorResponseAction` |
| `propose_alternative_vendor` | `ProposeAlternativeVendorAction` |
| `add_booking_note` | `AddBookingNoteAction` |

Assigned to: `super_admin` (all), `booking_manager` (all, role created if absent), `admin` (graceful — skip if role not found).

Seeded by: `App\Modules\Booking\Database\Seeders\BookingPermissionsSeeder`.

---

## 8. Filament Footprint

| Location | Purpose |
|---|---|
| `Booking/Filament/Resources/BookingsMonitorResource.php` | Existing resource — new row Actions added for each intervention type |

No new Filament Resource is introduced. The four intervention Actions are surfaced as row-level `Action::make(...)` entries on the existing `BookingsMonitorResource` table. Each action opens a confirmation modal with a `reason` textarea before executing.

---

## 9. Implementation Checklist

- [ ] Migration: `create_booking_admin_interventions_table`
- [ ] `VendorSubStatus::TimedOut` case added to enum
- [ ] `BookingAdminIntervention` model (`Domain/Models/`) — no business logic
- [ ] `ForceCancelBookingAction` with triple-log + event
- [ ] `TimeoutVendorResponseAction` with triple-log + event
- [ ] `ProposeAlternativeVendorAction` — read-only w.r.t. `booking_vendors`
- [ ] `AddBookingNoteAction` with audit_log write
- [ ] `BookingForceCancelled`, `VendorResponseTimedOut`, `AlternativeVendorProposed` events
- [ ] Payments listener: `HandleBookingForceCancelled` → calls `InitiateRefundAction`
- [ ] Communication listener: notifications to customer/vendor on each event
- [ ] `BookingPermissionsSeeder` seeding 4 permissions
- [ ] Filament row actions on `BookingsMonitorResource`
- [ ] Pest tests: happy path, 401, 403, validation, triple-log assertion
- [ ] This ADR listed in `docs/adr/README.md`

---

## 10. Open Questions

None. All decisions above are accepted and unblocked.
