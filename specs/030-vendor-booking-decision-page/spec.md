---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

TRACEABILITY:
- FR coverage: PRD §7 (Booking flow) — customer submit → vendor decision loop. Locally extends with FR-EXT-030-NNN for UI-specific behaviors not enumerated in the PRD.
- Schema traceability: uses existing tables `bookings`, `booking_vendors`, `booking_items`, `booking_addresses`, `booking_modifications`, `booking_state_transitions`, `service_inventory_reservations`, `vendor_coverage_areas`, `audit_logs`. No new tables.
- Phase alignment: **Phase 3.2 — Booking: Negotiation Loop** (09_Phasing_Plan.md L95, L623) plus vendor portal item L246 ("9-12 Booking decisions").
- Package use: no new packages. Reuses Filament v3 (Pages, Infolists, Forms, Notifications) and existing Booking Application Actions.
---

# Feature Specification: Vendor Booking Decision Page

**Feature Branch**: `030-vendor-booking-decision-page`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Build VendorBookingDecisionPage in /vendor. Vendor should have one clear screen to review a new booking request and accept, modify, or reject it."

---

## Overview

A focused, single-screen decision surface inside the **Vendor Filament panel** (`/vendor`). It consolidates everything a vendor needs to act on a pending `booking_vendors` record: identifying info, schedule, location coverage, this-vendor's line items, customer notes, response deadline countdown, inventory/availability warnings, payment status of the parent booking, prior modifications, and three commit-or-walk actions (Accept / Modify / Reject).

This page **does not replace** the existing `VendorBookingDetailPage` (which surfaces commission breakdown and is reachable post-decision); it is a **decision-optimised** view used during the response window. Both `VendorIncomingBookingsPage` and `VendorBookingDetailPage` add a "Decide" entry-point that links here.

The page reuses three already-implemented Application Actions:

- `App\Modules\Booking\Application\Actions\VendorAcceptBookingAction`
- `App\Modules\Booking\Application\Actions\VendorRejectBookingAction`
- `App\Modules\Booking\Application\Actions\VendorModifyBookingAction`

(No new Actions are introduced; the page is a thin Filament Page that wires these Actions and orchestrates a confirm-modal flow. The future `VendorBookingModificationBuilder` page receives a navigation handoff from here.)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Vendor accepts a pending booking from the decision page (Priority: P1)

A vendor receives a notification that a new booking is awaiting their response. They open the decision page, scan the event date, address & coverage, customer notes, and the line items requested from them. Inventory is clear, the deadline is comfortably in the future, so they tap **Accept**, confirm in a modal, and the page advances them back to the incoming queue with the booking removed.

**Why this priority**: This is the dominant happy path of the Phase 3.2 negotiation loop. Without it, the vendor cannot fulfill the most basic Vendor Journey requirement (respond to a request before the deadline).

**Independent Test**: Seed one pending `booking_vendor` for the authenticated vendor, navigate to its decision page, click Accept. Assert: `sub_status` transitions to `accepted`, `booking_state_transitions` row written, `audit_logs` entry recorded, the row disappears from `VendorIncomingBookingsPage`, an in-app success notification is shown.

**Acceptance Scenarios**:

1. **Given** the vendor has a `booking_vendor` with `sub_status = pending` and `response_deadline` in the future, **When** they click **Accept** and confirm the modal, **Then** `VendorAcceptBookingAction::execute()` is invoked, `sub_status` becomes `accepted`, an audit log entry is written, and the user lands on the incoming-bookings list with a green "Accepted" toast.
2. **Given** all of this vendor's items in the booking are inventory-clear and the address falls inside one of the vendor's `vendor_coverage_areas`, **When** the page renders, **Then** no warning banner is shown and the Accept button is enabled.
3. **Given** the booking has prior `booking_modifications` rows, **When** the decision page renders, **Then** the latest modification's diff summary and date are displayed in a "Previous modifications" section.

---

### User Story 2 - Vendor rejects a pending booking with a bilingual reason (Priority: P1)

A vendor opens the decision page and decides — for any business reason (no stock, away that weekend, customer outside service range) — to decline. They click **Reject**, fill in an EN + AR reason in a modal, and submit. The customer is notified, the booking_vendor is closed, and the row leaves the queue.

**Why this priority**: Rejection is half of the binary decision. Without explicit reject, vendors silently let bookings time out, harming customer experience and reporting.

**Independent Test**: Seed one pending `booking_vendor`, open its decision page, click Reject, supply bilingual reason text, submit. Assert: `sub_status` becomes `rejected`, `rejection_reason` JSON stored as `{en, ar}`, `booking_state_transitions` row written, `audit_logs` entry recorded, in-app warning toast shown.

**Acceptance Scenarios**:

1. **Given** a pending `booking_vendor`, **When** the vendor opens the reject modal, fills both EN and AR reasons, and submits, **Then** `VendorRejectBookingAction::execute()` is invoked with a `{en, ar}` translatable payload, `sub_status` becomes `rejected`, and the rejection reason is persisted.
2. **Given** the vendor leaves the reason fields blank, **When** they submit, **Then** the action proceeds with `rejection_reason = null` (matching current behavior of `VendorIncomingBookingsPage` reject flow).
3. **Given** the rejection succeeds, **When** the page reloads, **Then** the decision actions are no longer rendered (the page enters a read-only "Already decided" mode showing the recorded outcome).

---

### User Story 3 - Vendor initiates a modification to the booking (Priority: P1)

A vendor is willing to take the job but needs to change something — substitute an item, change quantity, alter the line price, or adjust the slot. They click **Modify**, which routes them to the existing `VendorBookingModificationBuilder` page (or, if that page is not yet wired, opens a placeholder modal showing what fields will be available). After they save a modification proposal, the booking enters `awaiting_customer_reapproval` and re-appears in the customer flow.

**Why this priority**: Modification is the third leg of the negotiation loop (`docs/specs/09_Phasing_Plan.md` Phase 3.2 success criterion: "Customer → submit → vendor accepts/modifies/rejects → customer re-approves → confirmed"). Skipping it breaks the loop.

**Independent Test**: Seed one pending `booking_vendor`, click Modify, follow the redirect, assert the modification builder receives the correct `booking_vendor` public_id. (When the builder is not yet shipped, the spec accepts a placeholder action that creates a `booking_modifications` draft row.)

**Acceptance Scenarios**:

1. **Given** the modification builder page is registered, **When** the vendor clicks **Modify**, **Then** they are navigated to `VendorBookingModificationBuilder` with the booking_vendor public_id pre-loaded.
2. **Given** the builder is not yet present, **When** the vendor clicks **Modify**, **Then** a modal warns "Modification builder coming soon — use Accept or Reject for now" and no state mutation occurs.

---

### User Story 4 - Vendor cannot act after the deadline lapses (Priority: P2)

A vendor opens the decision page after `response_deadline` has already passed. The deadline countdown shows `Expired`, all three action buttons are disabled, and an information banner explains that admin intervention is required to re-open the response window.

**Why this priority**: Enforcing the 24-hour SLA is a locked tech decision. Without this guard, vendors backdate decisions and reporting becomes meaningless.

**Independent Test**: Seed a `booking_vendor` with `response_deadline = now() - 1 hour`. Open the page. Assert: countdown reads "Expired" with `danger` color; Accept/Modify/Reject buttons are visually disabled and submitting via direct route returns 422 with a clear `ResponseDeadlineExpiredException`-style error.

**Acceptance Scenarios**:

1. **Given** the deadline is in the past and admin has not extended it, **When** the page renders, **Then** the action buttons are disabled and an info banner directs the vendor to contact support.
2. **Given** an admin has extended the deadline via Phase 6.5 intervention, **When** the page renders, **Then** the buttons re-enable and the countdown reflects the new deadline.

---

### User Story 5 - Wrong-vendor and unauthenticated access are blocked (Priority: P1)

A second vendor pastes another vendor's decision URL into their browser. They receive a 403 response with no data leakage. An unauthenticated user receives a redirect to login.

**Why this priority**: Cross-vendor booking exposure is the highest-severity data-leak risk in the vendor portal. Phase 3.2 cannot ship without this guarantee.

**Independent Test**: Seed booking_vendor A under vendor 1. Log in as vendor 2. Hit the decision URL for booking_vendor A. Assert HTTP 403. Log out. Hit the URL. Assert redirect to login.

**Acceptance Scenarios**:

1. **Given** a vendor authenticated as vendor 2, **When** they GET the decision page for a `booking_vendor` belonging to vendor 1, **Then** the response is HTTP 403 and no booking data is rendered.
2. **Given** an unauthenticated session, **When** the URL is requested, **Then** the system redirects to the vendor login page.
3. **Given** an authenticated user who does not own a `VendorProfile`, **When** they reach the page, **Then** a 403 is returned (covers staff-without-vendor cases).

---

### Edge Cases

- **Coverage area mismatch**: Booking event address resolves to a city/governorate **not** in `vendor_coverage_areas` for this vendor — a warning banner ("Outside your declared service area") is shown but actions remain enabled (vendor can still choose to accept).
- **Inventory conflict for rental items**: One of this vendor's `booking_items` overlaps with another active `service_inventory_reservations` row — a warning banner lists the conflicting item(s); Accept remains enabled but the vendor is informed.
- **Partial-payment state**: Parent `bookings.payment_status` is `partially_paid` or `paid` (rare for pending bookings but possible during admin intervention scenarios) — payment status badge is shown; the actions remain enabled (business rules in the Actions decide whether to block, not the page).
- **Locked booking**: An active `booking_locks` row exists on this booking → all three actions are disabled with a "Locked by admin/customer in progress" banner. Page is read-only.
- **Already-decided booking**: The `booking_vendor.sub_status` is no longer `pending` (e.g., `accepted`, `rejected`, `modified`) → page renders in read-only mode showing the recorded outcome and timestamp.
- **Customer has cancelled**: Parent `bookings.lifecycle_status` is `cancelled` → page is read-only with a "Booking cancelled by customer" banner; no actions.
- **Concurrent decision race**: Two staff in the same vendor org open the page and both click Accept — only the first succeeds; the second receives a clear error toast ("This booking is no longer pending").
- **Multilingual address rendering**: `booking_addresses.address_line` is JSON `{en, ar}` — the page renders in the admin's current locale, falling back to EN if AR is missing (and vice versa).
- **Customer notes are bilingual** (`bookings.customer_notes` JSON): same locale-pick-with-fallback rule.
- **Empty items list**: A `booking_vendor` exists with zero `booking_items` (data anomaly) → page renders a "No items assigned" warning and disables Accept/Modify; Reject remains enabled.

---

## Requirements *(mandatory)*

### Functional Requirements

**Page surface**

- **FR-EXT-030-001**: System MUST expose a Filament Page at `/vendor/booking-decisions/{bookingVendor}` (route param is the `booking_vendors.public_id` ULID) inside the existing Vendor panel and module folder `app/Modules/Booking/Filament/Vendor/Pages/`.
- **FR-EXT-030-002**: The page MUST NOT register a navigation entry (it is reached only via `VendorIncomingBookingsPage` and `VendorBookingDetailPage`). `protected static bool $shouldRegisterNavigation = false;`.
- **FR-EXT-030-003**: `VendorIncomingBookingsPage` MUST gain a row action "Decide" that links to this page for each pending row (in addition to the existing inline Accept/Reject quick actions, which remain).
- **FR-EXT-030-004**: `VendorBookingDetailPage` MUST gain a header action "Decide" that links to this page when `sub_status = pending`.

**Display**

- **FR-EXT-030-010**: System MUST display the booking reference (`bookings.reference_no`), event start datetime (`bookings.event_starts_at`), and event slot label (where applicable) in the page header.
- **FR-EXT-030-011**: System MUST display the event delivery address (`booking_addresses.address_line` JSON resolved to the admin locale) and a **coverage validation badge** (Green = inside, Amber = outside, Grey = no coverage areas declared).
- **FR-EXT-030-012**: System MUST display ONLY the `booking_items` belonging to the authenticated vendor's `booking_vendor` row. Items from other vendors on the same booking MUST NOT be rendered.
- **FR-EXT-030-013**: Each item row MUST show: product-type badge (per `.claude/rules/filament-components.md` §2 color map), name snapshot (`name_snapshot` JSON locale-resolved), quantity, unit price (money column), line total (money column), and any item-specific config from `type_snapshot`.
- **FR-EXT-030-014**: System MUST display the customer notes (`bookings.customer_notes`) in the current locale (fallback to the other locale).
- **FR-EXT-030-015**: System MUST display a live deadline countdown derived from `booking_vendors.response_deadline` with three states: `> 2h` warning amber, `< 2h` danger red, `expired` danger red with strike-through and "Expired" label.
- **FR-EXT-030-016**: System MUST display an **inventory warning banner** when any rental `booking_item` for this vendor overlaps with another active `service_inventory_reservations` row for the same service in the same window. Banner enumerates the conflicting item(s).
- **FR-EXT-030-017**: System MUST display the parent booking's `payment_status` (from `bookings`) as a badge (`unpaid`, `partially_paid`, `paid`, `refund_pending`, etc.).
- **FR-EXT-030-018**: When at least one `booking_modifications` row exists for the booking, the system MUST display a "Previous modifications" section listing each row's proposer, timestamp, and a one-line diff summary, newest first.
- **FR-EXT-030-019**: The page MUST be fully bilingual: all labels and banners use translation keys under the `vendor-portal.*` namespace with EN + AR entries.

**Actions**

- **FR-EXT-030-030**: System MUST expose three primary actions: **Accept**, **Modify**, **Reject**, rendered as Filament header actions (not row actions).
- **FR-EXT-030-031**: **Accept** MUST open a confirmation modal then call `VendorAcceptBookingAction::execute(VendorAcceptDTO)` with the resolved `booking_vendor_id`, `vendor_profile_id`, and `proposedByUserId = auth()->id()`.
- **FR-EXT-030-032**: **Reject** MUST open a modal with two `Textarea` fields ("Reason (EN)", "Reason (AR)"), then call `VendorRejectBookingAction::execute(VendorRejectDTO)` with the optional `rejection_reason` translatable payload.
- **FR-EXT-030-033**: **Modify** MUST navigate to `VendorBookingModificationBuilder` (when registered) with the booking_vendor public_id as a parameter; when not registered, it MUST show a "coming soon" notification and perform no mutation.
- **FR-EXT-030-034**: Each successful action MUST display a Filament `Notification` (success for Accept, success for Reject, info for Modify-handoff) and redirect to `VendorIncomingBookingsPage`.
- **FR-EXT-030-035**: All three actions MUST be **hidden** (not just disabled) when the page is in read-only mode (already-decided, locked, cancelled, expired).

**Authorization & guards**

- **FR-EXT-030-040**: The route MUST be protected by the Vendor panel auth guard. Unauthenticated users MUST be redirected to login.
- **FR-EXT-030-041**: The page mount MUST `abort(403)` when the loaded `booking_vendor.vendor_profile_id` does not equal `auth()->user()->vendorProfile->id`.
- **FR-EXT-030-042**: The page mount MUST `abort(403)` when the authenticated user does not own a `VendorProfile`.
- **FR-EXT-030-043**: A direct action submission (e.g., Livewire round-trip) MUST re-verify ownership; bypassing the mount-time check MUST NOT permit a state mutation on someone else's booking.
- **FR-EXT-030-044**: When `booking_vendors.response_deadline` is in the past, all three Application Actions MUST refuse to execute with a clear error (the Actions enforce this — the page renders the buttons as disabled, but the server-side check is authoritative).
- **FR-EXT-030-045**: When the parent booking has an active row in `booking_locks` (`released_at IS NULL`), all three actions MUST be blocked.
- **FR-EXT-030-046**: When `booking_vendor.sub_status != pending`, actions MUST be hidden and the page MUST render in read-only "Decision already recorded" mode.
- **FR-EXT-030-047**: When `bookings.lifecycle_status` is `cancelled` or `completed`, actions MUST be hidden.

**Auditing**

- **FR-EXT-030-050**: Every state mutation triggered by this page MUST result in an `audit_logs` entry. This is enforced by the existing Application Actions and their `WriteBookingStateTransitionListener`; the page MUST NOT bypass them.
- **FR-EXT-030-051**: The view-action of opening the decision page MUST NOT itself write to `audit_logs` (open is not a state change). It MAY emit an analytics event in `analytics_events` for funnel reporting (optional, deferred).

**Bilingual & localisation**

- **FR-EXT-030-060**: Translation keys MUST live in `app/Modules/Booking/Resources/lang/{en,ar}/vendor-portal.php` (or the existing equivalent file already used by the other vendor pages).
- **FR-EXT-030-061**: Money columns MUST use `->money('EGP', divideBy: 100)` per `.claude/rules/filament-components.md`.
- **FR-EXT-030-062**: Product-type badges MUST use the canonical color map (Rental=`warning`, Sale=`success`, Digital=`info`) from `filament-components.md` §2.
- **FR-EXT-030-063**: Page MUST be functionally verified in EN AND AR (RTL) — both locales must render without layout regressions.

### Key Entities *(include if feature involves data)*

- **booking_vendors** (read + status mutation): the per-vendor slice of a booking that this page operates on. Driven by `public_id`. Carries `sub_status`, `response_deadline`, `subtotal_minor`, `commission_minor`, `vendor_payout_minor`, `rejection_reason`.
- **bookings** (read-only): parent with `reference_no`, `event_starts_at`, `lifecycle_status`, `payment_status`, `fulfillment_status`, `customer_notes`.
- **booking_items** (read-only): filtered to those belonging to this `booking_vendor`. Each carries `product_type`, `name_snapshot`, `type_snapshot`, `quantity`, `unit_price_minor`, `line_total_minor`, `commission_bps`.
- **booking_addresses** (read-only): snapshot of customer-supplied event location.
- **booking_modifications** (read-only): history of prior modifications to show in the "Previous modifications" section.
- **booking_locks** (read-only): controls whether the page is in read-only mode.
- **booking_state_transitions** (write, indirectly via Actions): append-only history of decisions.
- **service_inventory_reservations** (read-only): used to compute inventory warnings.
- **vendor_coverage_areas** (read-only): used to compute the coverage validation badge.
- **audit_logs** (write, indirectly via listeners): append-only trail.
- **VendorProfile** (auth context): determines ownership.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A vendor with one pending booking can render the decision page in **under 3 seconds** (cold) on a 4G connection, and complete a decision (Accept or Reject) within the same page session without leaving and re-entering.
- **SC-002**: **100% of decision outcomes** initiated from this page result in matching `booking_state_transitions` and `audit_logs` entries (verified by feature tests).
- **SC-003**: **Zero cross-vendor data exposure**: every attempt by vendor B to access vendor A's decision URL returns HTTP 403, verified by an automated authorization test in CI.
- **SC-004**: **Zero post-deadline mutations**: after `response_deadline` elapses, the Application Actions reject any decision submission with a clear domain error, verified by an automated test.
- **SC-005**: Once shipped, the share of `booking_vendors` rows that **expire without a decision** drops by at least **30%** within four weeks of launch (proxy for: vendors actually use the page).
- **SC-006**: Both **EN** and **AR** renderings pass a manual locale-switch QA without layout regression.
- **SC-007**: A randomly-sampled set of 10 decision pages in staging shows correct rendering of coverage badge, inventory warnings, and previous-modification history for all 10.

---

## Assumptions

- The three Application Actions (`VendorAcceptBookingAction`, `VendorRejectBookingAction`, `VendorModifyBookingAction`) are already implemented and enforce their own deadline / status / authorisation guards. This spec assumes that authority and does not re-implement guards in the page.
- The Vendor Filament panel (`/vendor`) and its auth guard are wired (existing `VendorPanelProvider`).
- `VendorBookingModificationBuilder` may not yet exist; the spec uses a graceful fallback (toast notification) when the page class is absent.
- Coverage validation uses `vendor_coverage_areas` and the city/governorate FK on `booking_addresses`. If the booking address city is not resolvable to a coverage row, the badge falls back to `Grey — coverage unknown` rather than blocking.
- Inventory warnings rely on the existing `service_inventory_reservations` table and an existing helper (or simple query) to detect overlapping rentals. No new reservation logic is introduced.
- Locale resolution everywhere falls back EN → AR or AR → EN; no key produces a raw JSON dump in the UI.
- Real-time push (Reverb) and email/SMS notification of decisions are handled by the existing post-commit listeners attached to the Actions; this spec does not touch the notification layer.
- The page is desktop-first but must remain readable on tablet widths (no special mobile design required for Phase 1).
- Phase ID is **3.2** (Booking: Negotiation Loop, Week 4-5). No phasing backfill required.
- All schema references already exist in `docs/specs/11_DB_Schema.md`. No new migrations are introduced by this feature.
- This is a **Filament admin/portal page**, not a public API endpoint. No new REST endpoints, OpenAPI entries, Bruno collection rows, or `api-registry.md` entries are required (the page is server-rendered via Filament/Livewire). The API documentation constraint in the template applies only when an HTTP endpoint is introduced.
