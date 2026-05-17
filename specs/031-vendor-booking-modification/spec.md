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
- FR coverage: PRD **FR-11**, **FR-12**, **FR-13**, **FR-14** and PRD **BR-3** (01_PRD.md L131–L134, L201) — the vendor-side leg of the negotiation loop. Locally extends with `FR-EXT-031-NNN` numbers for builder-UX behaviors not enumerated in the PRD.
- Schema traceability: uses existing tables `booking_modifications`, `booking_modification_items`, `booking_state_transitions`, `booking_vendors`, `booking_items`, `bookings`, `payments`, `audit_logs` (11_DB_Schema.md §7). **⚠️ MINOR SCHEMA CHANGE** — extend `ModificationStatus` ENUM with `'draft'` (currently `pending|customer_accepted|customer_rejected|withdrawn|expired`); update 11_DB_Schema.md §7 in the same migration commit. No new tables.
- Phase alignment: **Phase 3.2 — Booking: Negotiation Loop** (09_Phasing_Plan.md L95, L623–L658). Completes the negotiation-loop UI deliverable referenced in 030-vendor-booking-decision-page (User Story 3 "Modify" handoff target).
- Package use: no new packages. Reuses Filament v3 (Pages, Forms, Wizard, Repeater, Notifications), `brick/money`, `spatie/laravel-translatable`, existing Booking Application Action infrastructure. No additions to `10_Package_List.md`.

API DOCUMENTATION CONSTRAINT:
- Updates to existing customer-facing API Resources (`BookingResource`, `BookingVendorResource`, `BookingItemResource`) MUST be reflected with:
  - `@response` PHPDoc updated to include the new fields with realistic EN+AR example data
  - `.specify/memory/api-registry.md` entry updated for the affected endpoints
  - Bruno/Postman collection examples refreshed in `docs/api/collections/`
- The builder itself is a server-rendered Filament Page (Livewire) — no new REST endpoints are introduced for the vendor side.
---

# Feature Specification: Vendor Booking Modification Builder

**Feature Branch**: `031-vendor-booking-modification`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Build VendorBookingModificationBuilder — vendor can propose clear modifications to booking items, price, quantity, slot time, notes, surcharges, or added items, then send proposal back for customer approval."

---

## Overview

A dedicated **Filament Page** inside the Vendor panel (`/vendor`) that lets a vendor compose a structured modification proposal against a pending `booking_vendors` slice and submit it for the customer to re-approve. It is the handoff target of the "Modify" action on `VendorBookingDecisionPage` (spec 030) and the missing third leg of the Phase 3.2 negotiation loop alongside `VendorAcceptBookingAction` and `VendorRejectBookingAction`.

The builder shapes a proposal in three logical phases:

1. **Draft** — create a `booking_modifications` row with `status = 'draft'`, scoped to this `booking_vendor`.
2. **Compose** — add one or more `booking_modification_items` (add / remove / update) with before/after snapshots and per-change deltas, supplying a translatable vendor note and (optionally) an expiry.
3. **Submit** — finalize the proposal, recompute totals, flip status to `pending`, fire the domain event that re-enters the customer review flow (`booking.lifecycle_status` → `awaiting_customer_reapproval`).

The existing `VendorModifyBookingAction` (single-shot DTO-style modification, written in Phase 3.2 Day 1) is **kept** for server-to-server / API use cases but is **not** used by the builder. The builder uses a new family of granular Actions so each compose step can be validated, audited, and rolled back independently inside the same `booking_modifications` draft.

The builder is **type-aware**: per-item form fields, validation rules, and delta calculations switch on `ProductType` (`Rental`, `Sale`, `Digital`) using PHP `match($enum)`, never if/elseif on type strings (per `.claude/rules/product-types.md` and `.claude/rules/actions.md`).

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Vendor proposes a price change on an existing item (Priority: P1)

A vendor opens the modification builder for a pending booking from the decision page. The booking has one rental item priced at 200 EGP/unit × 3 units. The vendor recalculates and needs to charge 250 EGP/unit because of an outdoor-setup surcharge. They open the item, change `unit_price_minor` from 20000 to 25000, add a bilingual note explaining the surcharge, save the change, then click **Submit Proposal**. The proposal is sent to the customer, the booking enters `awaiting_customer_reapproval`, and the vendor lands back on the incoming-bookings queue with an "Awaiting customer" badge on this row.

**Why this priority**: Price modification is the single most common vendor-initiated change (PRD FR-12 explicit mention of "extra price"). Without it, vendors cannot adjust to real-world setup costs and resort to rejecting bookings.

**Independent Test**: Seed one pending `booking_vendor` with one rental `booking_item`. Authenticate as the owning vendor. Navigate to the builder, change the unit price, save, submit. Assert: a `booking_modifications` row exists with `status='pending'`, exactly one `booking_modification_items` row with `change_kind='update'` and payload carrying `original_value.unit_price_minor=20000` + `proposed_value.unit_price_minor=25000` + `price_delta_minor=+15000` (for qty 3), `bookings.lifecycle_status='awaiting_customer_reapproval'`, `booking_state_transitions` row recorded, `audit_logs` entry written.

**Acceptance Scenarios**:

1. **Given** a pending `booking_vendor` with a rental `booking_item` at 200 EGP × 3, **When** the vendor edits the line price to 250 EGP and submits with a bilingual note, **Then** the `booking_modifications.diff_snapshot` carries the aggregated delta `+15000 minor`, `booking_modification_items.payload` carries `change_type='change_price'` plus before/after values, and the booking enters `awaiting_customer_reapproval`.
2. **Given** the same scenario, **When** the proposal is submitted, **Then** the proposal expires after the configured TTL (default **48 hours** unless the vendor sets an explicit `expires_at`) and the `expires_at` column is populated.
3. **Given** the proposal is in `draft` status, **When** the vendor leaves and re-enters the builder, **Then** they resume the same draft (one open draft per `booking_vendor` at a time).

---

### User Story 2 — Vendor adds a new line item (surcharge or substitute) (Priority: P1)

A vendor needs to add a "Setup fee" line item (sale type, 50 EGP) to a booking. In the builder they click **Add line item**, pick from their catalog or supply a free-text snapshot (bilingual `name_snapshot`), set quantity, unit price, and product type, save, then submit. The customer sees the new item flagged as `is_modified=true` with a "New" badge.

**Why this priority**: PRD FR-12 explicitly enumerates "new line items" and "operational surcharges". Without add-item support, vendors can't legitimately charge for delivery or setup discovered after the booking lands.

**Independent Test**: Seed a pending `booking_vendor`. Open builder. Add one new line (sale, 50 EGP × 1). Submit. Assert: a `booking_modification_items` row with `change_kind='add'`, `target_booking_item_id IS NULL`, payload carries the full new-item snapshot including `product_type='sale'`, `change_type='add_item'`. `booking_modifications.diff_snapshot.totals.delta_minor=+5000`. The customer-facing `BookingResource` shows `requires_customer_approval=true`.

**Acceptance Scenarios**:

1. **Given** a pending `booking_vendor`, **When** the vendor adds a new sale-type line item via the builder, **Then** a `booking_modification_items` row is created with `change_kind='add'` and the proposed value JSON carries product-type-specific fields (per `match($enum)` schema for sale: `is_perishable`, `lead_time_hours` if applicable).
2. **Given** the new item is a rental, **When** added, **Then** the proposed value JSON carries rental-specific fields (`reserved_starts_at`, `reserved_ends_at`, `setup_time_minutes`).
3. **Given** the new item is digital, **When** added, **Then** the proposed value JSON carries digital-specific fields (`delivery_method`, `redemption_url_template` if applicable) and the slot fields are omitted.
4. **Given** the vendor tries to add a line item whose service belongs to a **different** vendor, **When** they save, **Then** validation fails with "You may only add items from your own catalog".

---

### User Story 3 — Vendor proposes a slot time change for a rental item (Priority: P1)

A vendor accepts a rental booking in principle but the customer's requested slot conflicts with another reservation. The vendor opens the builder, edits the item, shifts the rental window by +2 hours, adds a bilingual note ("Earlier slot already booked — proposing 14:00–18:00 instead of 12:00–16:00"), saves, submits. The customer sees the slot diff highlighted with a "Time changed" badge.

**Why this priority**: PRD FR-12 explicit mention of "revised slot time". Without it, the rental product type — the largest by revenue per `docs/specs/03_Three_Product_Types.md` — cannot be salvaged when first-choice times conflict.

**Independent Test**: Seed a pending `booking_vendor` with one rental item slotted 12:00–16:00. Open builder, shift to 14:00–18:00, submit. Assert: `booking_modification_items.payload` carries `original_value.reserved_starts_at='12:00'`, `proposed_value.reserved_starts_at='14:00'`, `time_delta='+02:00:00'` (ISO-8601 duration `PT2H`), `change_type='change_slot'`, **no `price_delta_minor`** (price unchanged).

**Acceptance Scenarios**:

1. **Given** a rental booking item with slot 12:00–16:00, **When** the vendor edits to 14:00–18:00 in the builder, **Then** the modification payload carries the ISO-8601 time deltas and the customer-facing resource flags the item with a `slot_changed` change badge.
2. **Given** a sale item, **When** the vendor tries to edit slot fields, **Then** those fields are **not rendered** (per-type field schema). The builder enforces type-correctness via `match($enum)`.
3. **Given** a digital item, **When** the vendor tries to edit slot fields, **Then** those fields are not rendered (digital products have no slot).

---

### User Story 4 — Vendor proposes quantity change (Priority: P2)

A vendor needs to reduce quantity from 5 to 3 because of stock. They open the item, change `quantity` to 3, save, submit. The customer sees `quantity_delta=-2` and the recomputed line total.

**Why this priority**: PRD FR-12 explicit mention of "revised quantity". Lower frequency than price changes but still common, especially for sale products with stock constraints.

**Independent Test**: Seed a pending `booking_vendor` with one sale item × 5 units @ 100 EGP. Open builder, set quantity to 3, submit. Assert: `booking_modification_items.payload` carries `quantity_delta=-2`, `price_delta_minor=-20000`, `change_type='change_quantity'`.

**Acceptance Scenarios**:

1. **Given** a sale item × 5 units, **When** the vendor reduces to 3, **Then** the line total in `proposed_value` is recomputed and `RecalculateBookingModificationTotalsAction` produces the new booking-vendor subtotal.
2. **Given** quantity drops to 0, **When** the vendor saves, **Then** the change is upgraded to `change_kind='remove'` automatically (no zero-quantity items).

---

### User Story 5 — Vendor cancels (withdraws) an in-progress draft (Priority: P2)

A vendor starts composing a modification, decides to accept-as-is instead, and clicks **Discard draft**. The draft is deleted with a confirmation prompt; no proposal is sent to the customer.

**Why this priority**: Avoids stranded draft rows that pollute the audit trail and clutter the vendor's queue.

**Independent Test**: Open builder, add one change to the draft, click Discard, confirm. Assert: the `booking_modifications` row with `status='draft'` is hard-deleted (not soft-deleted; drafts are append-only-exempt because they never carry customer-visible state). No event fired. No `audit_logs` entry for the discard (open/discard is not a state change).

**Acceptance Scenarios**:

1. **Given** a draft modification with two pending changes, **When** the vendor clicks Discard and confirms, **Then** the modification and its items are removed.
2. **Given** the modification has already been submitted (`status='pending'`), **When** the vendor opens it again, **Then** Discard is **hidden** — only Withdraw (which is a customer-visible audit event setting `status='withdrawn'`) is available.

---

### User Story 6 — Payment guard prevents modification after payment capture (Priority: P1)

A customer has already paid the booking (rare with negotiation loops but possible for fast-confirmed bookings or admin-amended flows). The vendor opens the builder. All compose actions are **hidden** and a danger banner explains "Payment captured — admin must intervene to modify this booking". Attempting the route via direct request returns a 422 domain error.

**Why this priority**: Mutating a paid booking without re-confirming and triggering a refund/charge differential would silently desync ledger and money. This is the highest-severity guard in the feature.

**Independent Test**: Seed a `booking_vendor` whose parent booking has `payment_status='paid'` and at least one captured `payments` row. Open builder. Assert: all action buttons hidden, banner shown. Attempt `CreateBookingModificationAction::execute()` server-side. Assert: throws `PaymentAlreadyCapturedException` (or equivalent), 422 response in HTTP context, no DB writes.

**Acceptance Scenarios**:

1. **Given** parent `bookings.payment_status='paid'`, **When** the builder loads, **Then** the read-only payment-locked banner appears and Submit is hidden.
2. **Given** parent `bookings.payment_status='partially_paid'`, **When** the builder loads, **Then** the same lock applies (any captured cash means admin-only).
3. **Given** parent `bookings.fulfillment_status` is `active` or `completed`, **When** the builder loads, **Then** Submit is hidden with a "Fulfillment in progress / completed — modification not allowed" banner.
4. **Given** parent `bookings.lifecycle_status` is `cancelled`, **When** the builder loads, **Then** the page is fully read-only with "Booking cancelled" banner.
5. **Given** the customer has already approved an in-flight modification on this same booking (`booking_modifications.status='customer_accepted'` chain leading to `lifecycle_status='confirmed'`), **When** the vendor opens the builder, **Then** a new proposal can still be drafted only if `payment_status='unpaid'` AND `fulfillment_status` not in `active/completed`. Once payment captured or fulfillment starts, the guard fires regardless of lifecycle.

---

### User Story 7 — Cross-vendor access is blocked (Priority: P1)

Vendor B pastes the builder URL for vendor A's `booking_vendor`. They receive a 403 with no booking data leaked. An unauthenticated user is redirected to login.

**Why this priority**: Same severity as the decision-page authorization (spec 030 SC-003) — cross-vendor data exposure is the highest-impact vendor-portal data-leak risk.

**Independent Test**: Seed booking_vendor X under vendor A. Authenticate as vendor B. Hit the builder URL. Assert HTTP 403, no JSON/HTML body containing the booking reference. Repeat unauthenticated → redirect to vendor login.

**Acceptance Scenarios**:

1. **Given** vendor B authenticated, **When** they GET the builder route for vendor A's booking_vendor, **Then** response is HTTP 403.
2. **Given** the page mount passes (correct vendor), **When** any compose Action is then invoked via Livewire, **Then** ownership is re-verified server-side; a forged `booking_vendor_id` in the payload MUST NOT mutate someone else's booking.
3. **Given** an authenticated user with no `VendorProfile`, **When** they hit the route, **Then** response is HTTP 403.

---

### User Story 8 — Bilingual proposal (EN + AR) and customer review (Priority: P1)

A vendor composes a modification with an Arabic note. The customer (whose locale is AR) opens the booking and sees the modification diff with all snapshots and the vendor note rendered in Arabic; an English-locale customer sees the EN translation (with AR fallback when EN is missing).

**Why this priority**: Bilingual coverage is a constitutional requirement for every feature (CLAUDE.md L20, locked Tech Decisions). Without bilingual snapshots the proposal is unreadable to half the user base.

**Independent Test**: Seed a pending `booking_vendor`. Submit a modification with `vendor_explanation = {en: "Higher setup fee", ar: "رسوم تركيب إضافية"}`. Hit the customer-side `GET /api/customer/bookings/{public_id}` endpoint twice with `Accept-Language: ar` then `en`. Assert: both responses return the correct localized note; missing-locale fallback works.

**Acceptance Scenarios**:

1. **Given** a vendor enters EN-only or AR-only notes, **When** they save, **Then** validation passes and the missing locale falls back to the supplied one for customer display (no raw JSON dump).
2. **Given** the customer locale is AR, **When** they view the modified booking, **Then** all item `name_snapshot`s and `vendor_explanation`s render in Arabic where present.
3. **Given** the Filament page is opened in `en` then switched to `ar`, **When** the locale toggles, **Then** form labels, badges, and helper text re-render in the new locale without page reload.

---

### Edge Cases

- **Response-deadline expired**: `booking_vendors.response_deadline` is in the past — all builder actions are disabled with an "Expired" banner (same rule as decision page, spec 030 FR-EXT-030-044). Compose Actions refuse to execute server-side.
- **Booking locked**: an active `booking_locks` row (`released_at IS NULL`) — builder is fully read-only.
- **Already-decided booking_vendor** (`sub_status != pending` AND no active draft) — builder is read-only with an "Already decided" banner; existing modifications are visible but no new draft can be created.
- **Multiple drafts not allowed**: only **one** `booking_modifications` row with `status='draft'` per `booking_vendor` at any time. Attempting to create a second draft returns the existing draft (idempotent open).
- **Stale catalog reference**: vendor adds a new item via "pick from my catalog" but the chosen `service.status='archived'` between selection and submit — submit fails with "This service is no longer available" and the row is highlighted; vendor must remove or re-pick.
- **Money currency mismatch**: vendor tries to add a new item priced in a currency different from `booking_vendors.subtotal_currency` — validation fails. All booking-vendor math is single-currency.
- **Concurrent submit race**: two staff at the same vendor org submit the same draft simultaneously — DB unique constraint on `(booking_vendor_id, status='draft')` plus optimistic versioning ensures only the first succeeds; the second receives "This draft was already submitted".
- **Customer paying mid-draft**: vendor opens builder while booking is `unpaid`, but the customer pays before submit — submit fails with `PaymentAlreadyCapturedException` (SC-006 below); the draft remains for admin/audit reference but cannot be submitted.
- **Customer approves a parallel modification mid-draft**: rare but possible — submit fails with "A different modification was already accepted; refresh and start over".
- **Recalculation drift**: the per-item delta math is recomputed every save, not just at submit, so the running booking-vendor total displayed in the builder reflects the current draft.
- **Slot conflict on rental add/edit**: the proposed slot for a rental change overlaps with another active `service_inventory_reservations` row for the same service — a warning is shown (not blocking; customer may accept anyway, and reservation re-check happens on customer-confirm).
- **All items removed**: a draft that ends up with `change_kind='remove'` for every item is **blocked at submit** — that pattern should go through the Reject path, not a modification.
- **Empty proposal**: a draft with zero `booking_modification_items` cannot be submitted (validation: "Add at least one change").
- **Vendor explanation absent**: `vendor_explanation` is **required** at submit time (at least one locale populated) — UX-rationale for customer review.

---

## Requirements *(mandatory)*

### Functional Requirements

**Page surface**

- **FR-EXT-031-001**: System MUST expose a Filament Page at `/vendor/booking-modifications/{bookingVendor}` (route param is `booking_vendors.public_id` ULID) inside the existing Vendor panel under `app/Modules/Booking/Filament/Vendor/Pages/VendorBookingModificationBuilder.php`.
- **FR-EXT-031-002**: The page MUST NOT register a navigation entry (`protected static bool $shouldRegisterNavigation = false;`). It is reached only via the **Modify** action on `VendorBookingDecisionPage` (spec 030 FR-EXT-030-033) and via a row link on `VendorIncomingBookingsPage`.
- **FR-EXT-031-003**: The page MUST use a Filament `Wizard` or stepped form layout with three logical sections: **Review** (read-only summary of the current booking-vendor slice), **Compose** (repeater of changes per item + add-item action + vendor note + expiry), **Confirm** (read-only diff preview + Submit).
- **FR-EXT-031-004**: The Compose section MUST use a `Repeater` of "change rows" where each row picks a target (`booking_item` or "new item") and a change kind, then exposes type-specific fields based on `ProductType` via `match($enum)`.
- **FR-EXT-031-005**: The page MUST display the running aggregated delta (total `price_delta_minor`, count of changes, type breakdown) at the bottom of the Compose step, updated live via Livewire on every change.
- **FR-EXT-031-006**: The page MUST be fully bilingual: every label, helper, validation message, and banner uses translation keys under `vendor-portal.*` namespace in `app/Modules/Booking/Resources/lang/{en,ar}/vendor-portal.php`.

**Application Actions (new)**

- **FR-EXT-031-010**: `CreateBookingModificationAction::execute(CreateBookingModificationDTO): BookingModification` MUST create or return the existing `booking_modifications` row with `status='draft'` for the given `(booking_vendor_id, vendor_user_id)` (idempotent — one open draft per booking_vendor). DTO carries `bookingVendorId`, `proposedByUserId`. Action MUST first call `PreventModificationAfterPaymentAction` to enforce all payment/fulfillment/lifecycle guards.
- **FR-EXT-031-011**: `AddBookingModificationItemAction::execute(AddBookingModificationItemDTO): BookingModificationItem` MUST append a `booking_modification_items` row to a draft modification, validate the change against the target item's `product_type` via `match($enum)`, compute and store `price_delta_minor`, `quantity_delta`, `time_delta` (ISO-8601 duration string), and persist `change_type`, `original_value`, `proposed_value` inside `booking_modification_items.payload` JSON. Action MUST refuse if the modification's `status != 'draft'`.
- **FR-EXT-031-012**: `RecalculateBookingModificationTotalsAction::execute(BookingModification): BookingModification` MUST recompute the aggregated delta and store it in `booking_modifications.diff_snapshot` JSON with shape `{totals: {price_delta_minor, currency, item_count, by_change_kind}, items: [{target_id, change_kind, change_type, ...}]}`. Idempotent — safe to call multiple times during composition.
- **FR-EXT-031-013**: `SubmitBookingModificationProposalAction::execute(SubmitBookingModificationProposalDTO): BookingModification` MUST: (a) re-run `PreventModificationAfterPaymentAction`; (b) require `vendor_explanation` populated in at least one locale; (c) require at least one `booking_modification_items` row; (d) require at least one item NOT marked `remove` (per "all items removed → use reject" edge case); (e) set `expires_at` to the provided value or `now() + 48h`; (f) flip status from `draft` to `pending`; (g) write a `booking_state_transitions` row (`from=draft → to=pending`) and an `audit_logs` entry; (h) update the parent `bookings.lifecycle_status` to `awaiting_customer_reapproval`; (i) fire `BookingModificationProposed` domain event **after commit** via `DB::afterCommit()` for notification delivery.
- **FR-EXT-031-014**: `PreventModificationAfterPaymentAction::execute(Booking|BookingVendor): void` (throws on failure) MUST refuse when ANY of the following are true: (a) `bookings.payment_status` ∈ {`partially_paid`, `paid`}; (b) any non-failed/non-refunded `payments` row exists with `status='captured'`; (c) `bookings.fulfillment_status` ∈ {`active`, `completed`}; (d) `bookings.lifecycle_status` ∈ {`cancelled`, `completed`}; (e) `booking_vendors.response_deadline` is in the past; (f) an active `booking_locks` row exists. Throws a typed domain exception per case so the UI can render the precise banner. **This Action is the single source of truth for the payment/lifecycle guard** — all four other Actions delegate to it.
- **FR-EXT-031-015**: A `DiscardBookingModificationDraftAction::execute(BookingModification): void` MUST hard-delete a `status='draft'` modification and its items in a single transaction. Refuses on any other status. (Implicitly required by User Story 5; treated as part of the Action set.)
- **FR-EXT-031-016**: All five Actions MUST follow `.claude/rules/actions.md`: single `execute()` method, constructor injection, `DB::transaction()` wrapping mutations, domain events via `DB::afterCommit()` only.
- **FR-EXT-031-017**: All five Actions MUST use `match(ProductType)` for any per-type branching (validation, delta computation, payload shape). No `if/elseif` on `product_type` strings.

**Schema**

- **FR-EXT-031-020**: The `ModificationStatus` enum (`app/Modules/Booking/Domain/Enums/ModificationStatus.php`) MUST be extended with a `Draft = 'draft'` case. The corresponding migration MUST alter the `booking_modifications.status` ENUM to add `'draft'` as the new default-eligible value. (⚠️ **MINOR SCHEMA CHANGE** — record in `docs/specs/11_DB_Schema.md` §7 in the same commit.)
- **FR-EXT-031-021**: A partial UNIQUE index on `booking_modifications (booking_vendor_id) WHERE status='draft'` MUST enforce one open draft per booking_vendor. (MySQL 8 supports this via a generated column workaround if needed; document the chosen mechanism in the migration.)
- **FR-EXT-031-022**: The `booking_modification_items.payload` JSON column shape MUST follow this contract (no migration needed — JSON shape is enforced by DTO + Eloquent cast):
  ```json
  {
    "change_type": "change_price|change_quantity|change_slot|add_item|remove_item|add_surcharge|add_note",
    "original_value": { ... },
    "proposed_value": { ... },
    "price_delta_minor": -15000,
    "quantity_delta": -2,
    "time_delta": "PT2H",
    "vendor_note": { "en": "...", "ar": "..." }
  }
  ```
- **FR-EXT-031-023**: `booking_modifications.vendor_explanation` (existing JSON column, translatable) MUST store the proposal-level vendor note. Per-change item-level notes go into `booking_modification_items.payload.vendor_note`.
- **FR-EXT-031-024**: `booking_modifications.expires_at` (existing TIMESTAMP) MUST be populated at submit time. Default: `now() + 48 hours`. Vendor may override.
- **FR-EXT-031-025**: NO new migrations beyond the enum extension and partial unique index in FR-EXT-031-020/021. No new tables.

**Customer-facing API resource flags**

- **FR-EXT-031-030**: `BookingResource` (`app/Modules/Booking/Http/Resources/BookingResource.php`) MUST add a top-level boolean flag `requires_customer_approval` — `true` when at least one `booking_modifications` row exists with `status='pending'` for this booking.
- **FR-EXT-031-031**: `BookingItemResource` (`app/Modules/Booking/Http/Resources/BookingItemResource.php`) MUST add: (a) `is_modified` boolean — `true` when this `booking_item_id` appears as `target_booking_item_id` in any pending `booking_modification_items` row; (b) `change_badges` array — list of `change_type` strings from the pending modification rows targeting this item (e.g., `["change_price", "change_quantity"]`), used by the customer UI to render per-item badges (FR-13 visual highlighting).
- **FR-EXT-031-032**: `BookingVendorResource` MUST add an `active_modification_proposal` nested object when a pending modification exists for this booking_vendor, containing: `public_id`, `proposal_kind` (computed from constituent change_types), `vendor_explanation` (locale-resolved), `expires_at`, `total_price_delta_minor`, `change_count`.
- **FR-EXT-031-033**: All three new fields MUST be backwards-compatible: omitting them MUST NOT break any existing consumer (additive fields only, no renames or type changes to existing fields).
- **FR-EXT-031-034**: The `@response` PHPDoc on each updated Resource MUST include realistic EN+AR example data for the new fields (per the template's API DOCUMENTATION CONSTRAINT).

**Authorization & guards**

- **FR-EXT-031-040**: Route MUST be protected by the Vendor panel auth guard. Unauthenticated → redirect to vendor login.
- **FR-EXT-031-041**: Page mount MUST `abort(403)` when the loaded `booking_vendor.vendor_profile_id != auth()->user()->vendorProfile->id`.
- **FR-EXT-031-042**: Page mount MUST `abort(403)` when the authenticated user does not own a `VendorProfile`.
- **FR-EXT-031-043**: Every compose Action MUST re-verify the vendor owns the `booking_vendor` server-side before mutating, regardless of what the Livewire payload claims.
- **FR-EXT-031-044**: Every compose Action MUST refuse if the target `booking_item` (when present) does NOT belong to this `booking_vendor` (cross-booking-vendor item edits forbidden).
- **FR-EXT-031-045**: Adding a new item from the catalog MUST be restricted to services where `services.vendor_profile_id = auth_vendor.id` (vendor can only sell their own services in the modification).
- **FR-EXT-031-046**: All five Actions MUST call `PreventModificationAfterPaymentAction` at the top of `execute()`. The single guard is authoritative; the UI button-disabling is convenience only.

**Auditing**

- **FR-EXT-031-050**: Every mutation MUST result in an `audit_logs` row, via the existing `WriteBookingStateTransitionListener` / `WriteAuditLogListener` infrastructure. Page MUST NOT bypass the Actions.
- **FR-EXT-031-051**: `audit_logs.context` JSON MUST carry the modification's `public_id` and the `change_type`(s) involved so admin debugging can correlate.
- **FR-EXT-031-052**: Opening the builder MUST NOT itself write to `audit_logs`. Creating a draft MUST write one row (state transition `null → draft`). Submitting MUST write one row (`draft → pending`). Discarding a draft MUST write one row (`draft → withdrawn`-style audit entry with `trigger_kind='user'`, even though the modification row itself is hard-deleted — preserve the trail).

**Bilingual & localisation**

- **FR-EXT-031-060**: Translation keys live in `app/Modules/Booking/Resources/lang/{en,ar}/vendor-portal.php` (shared with spec 030).
- **FR-EXT-031-061**: Money columns MUST use `->money('EGP', divideBy: 100)` per `.claude/rules/filament-components.md`. Internal storage MUST use integer minor units everywhere; `Brick\Money` for arithmetic; no floats.
- **FR-EXT-031-062**: Product-type badges MUST use the canonical color map (Rental=`warning`, Sale=`success`, Digital=`info`).
- **FR-EXT-031-063**: Builder MUST be functionally verified in EN AND AR (RTL) before considered done.

**Testing**

- **FR-EXT-031-070**: Pest feature tests under `tests/Feature/Modules/Booking/VendorBookingModificationBuilder/` MUST cover, at minimum, the following scenarios — each tagged with the appropriate `->group()`:
  - `it_creates_a_draft_modification` (idempotent open)
  - `it_adds_a_price_change_item` (User Story 1)
  - `it_adds_a_new_line_item_rental_sale_digital` — three parallel cases via `match($enum)` — group: `rental`, `sale`, `digital`
  - `it_proposes_a_slot_time_change_for_rental` (User Story 3)
  - `it_proposes_a_quantity_change` (User Story 4)
  - `it_recalculates_aggregated_totals_on_each_save`
  - `it_submits_and_transitions_booking_to_awaiting_customer_reapproval` (User Story 1 end-to-end)
  - `it_discards_a_draft_cleanly` (User Story 5)
  - `it_refuses_for_a_wrong_vendor` (User Story 7) — group: `auth`
  - `it_refuses_after_payment_captured` (User Story 6) — group: `guards`
  - `it_refuses_after_response_deadline_expired` — group: `guards`
  - `it_refuses_after_booking_cancelled` — group: `guards`
  - `it_refuses_when_an_active_lock_exists` — group: `guards`
  - `it_renders_proposal_bilingual_for_customer` (User Story 8) — group: `i18n`
  - `it_parallels_the_three_existing_decision_actions` (Accept/Modify/Reject) — group: `negotiation`
- **FR-EXT-031-071**: Tests MUST use the existing module factories (`BookingFactory`, `BookingVendorFactory`, `BookingItemFactory`) and add factories for `BookingModification` and `BookingModificationItem` if not yet present.
- **FR-EXT-031-072**: Tests MUST NOT mock the database — integration-level Pest tests against MySQL (per `.claude/rules/migrations.md` and project test conventions).

### Key Entities *(include if feature involves data)*

- **booking_modifications** (read + write): a row per proposed modification. Carries `public_id`, `booking_vendor_id`, `proposed_by`, `proposal_kind` (denormalized rollup of constituent change kinds), `status` (extended with `draft`), `customer_decision_at`, `expires_at`, `vendor_explanation` (JSON translatable), `diff_snapshot` (JSON aggregate). Driven by `public_id` in the builder route.
- **booking_modification_items** (read + write): rows for each proposed change inside a modification. Carries `booking_modification_id`, `target_booking_item_id` (nullable for `change_kind='add'`), `change_kind` (`add|remove|update`), `payload` JSON (now standardized to carry `change_type`, `original_value`, `proposed_value`, deltas, and item-level vendor note).
- **booking_vendors** (read + status mutation via Actions): the vendor's slice of the booking. Builder operates against one row; multiple `booking_modifications` may stack over time but only one `draft` per `booking_vendor`.
- **bookings** (read + lifecycle mutation on submit): parent. On submit, `lifecycle_status → awaiting_customer_reapproval`. Read fields: `reference_no`, `payment_status`, `fulfillment_status`, `event_starts_at`.
- **booking_items** (read-only in builder; written-against indirectly when customer accepts a proposal — out of scope for this spec): the items the vendor proposes changes against.
- **payments** (read-only): used by `PreventModificationAfterPaymentAction` to detect captured payments.
- **booking_state_transitions** (write, indirectly via Actions and listeners): append-only history of `draft → pending → customer_accepted/rejected/withdrawn/expired`.
- **booking_locks** (read-only): when active, builder is read-only.
- **audit_logs** (write, indirectly via listeners): append-only trail of every create/add/submit/discard step.
- **ProductType enum** (`app/Modules/Catalog/Domain/Enums/ProductType.php`): drives all per-type branching via `match($enum)`.
- **VendorProfile** (auth context): determines ownership.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A vendor can compose and submit a modification with **one or more changes** in under **3 minutes** from opening the builder on a 4G connection.
- **SC-002**: **100% of submitted modifications** result in a matching `booking_state_transitions` row, an `audit_logs` row, and a `BookingModificationProposed` event delivered to the notification dispatcher — verified by automated feature tests.
- **SC-003**: **Zero cross-vendor data exposure**: every attempt by vendor B to access vendor A's builder URL returns HTTP 403, verified by an automated authorization test in CI (parallels spec 030 SC-003).
- **SC-004**: **Zero post-payment mutations**: 100% of submit attempts against a booking with `payment_status ∈ {paid, partially_paid}` or `fulfillment_status ∈ {active, completed}` are rejected by `PreventModificationAfterPaymentAction`, verified by automated test (the single most critical financial guard in this feature).
- **SC-005**: **100% of pending modifications** flip the parent booking's `lifecycle_status` to `awaiting_customer_reapproval` and surface `requires_customer_approval=true` on the customer-facing `BookingResource` — verified by an integration test against the customer endpoint.
- **SC-006**: Per-item `is_modified=true` and a populated `change_badges` array appear on every `BookingItemResource` whose item is referenced by a pending modification — verified by integration test.
- **SC-007**: **All three product types** (Rental, Sale, Digital) have a dedicated passing Pest test for the "add a new line item" scenario via the type-specific `match($enum)` path — non-negotiable per project type-coverage rule.
- **SC-008**: A randomly-sampled set of **10 proposals in staging** show correct rendering of localized `vendor_explanation`, item diff badges, and aggregated `price_delta_minor` in both EN and AR within the customer flow.
- **SC-009**: **Within 4 weeks of launch**, the share of pending bookings that move through a modification → customer re-approval round-trip (vs. direct accept or reject) is **measurable in reporting** (`booking_modifications.status='customer_accepted'` count > 0 per active vendor per week), confirming vendors actually use the builder.
- **SC-010**: Both EN and AR renderings of the builder UI pass a manual locale-switch QA without RTL layout regression before the feature is marked done.

---

## Assumptions

- The existing `VendorModifyBookingAction` (Phase 3.2 Day 1) is **kept** as a thin server-to-server / API-style entry point. The builder uses the new granular Actions internally and does NOT delegate to it. If duplicate logic emerges, it is consolidated into the new Action family with `VendorModifyBookingAction` becoming a thin wrapper — but that refactor is out of scope for this spec.
- The `VendorBookingDecisionPage` (spec 030) provides the "Modify" entry point. The decision page's User Story 3 fallback ("modification builder not yet present") is **deprecated** when this spec ships — the fallback toast is removed and a direct navigation is wired.
- The Vendor Filament panel (`/vendor`) and its auth guard already exist (used by spec 030 and prior vendor-portal work).
- `ProductType` enum (`App\Modules\Catalog\Domain\Enums\ProductType`) is canonical and stable.
- Per-type field schemas (rental/sale/digital) for the "Add new line item" path reuse the existing `category_field_schemas` table referenced by Catalog (PRD §11) — the builder consults this table to render type-correct fields without hard-coding them in the page.
- The customer-side re-approval flow (`CustomerConfirmModifiedBookingAction`, already implemented per file listing in `app/Modules/Booking/Application/Actions/`) is the consumer of `status='pending'` modifications and is **out of scope** for this spec. This spec hands off cleanly to that Action via `lifecycle_status='awaiting_customer_reapproval'` and the `BookingModificationProposed` event.
- The default proposal TTL of **48 hours** mirrors the customer-side response window. The TTL is configurable per booking but the default is hard-coded in `SubmitBookingModificationProposalAction`.
- The `notification_templates` row for the `booking.modification.proposed` event already exists (Phase 5.1 Communication module) or will be added in the same PR — this spec assumes its existence for SC-002.
- Snapshot deltas in `booking_modification_items.payload` are computed and stored at the moment of add — they are NOT re-resolved against live `booking_items` at customer-review time. This guarantees the customer sees what the vendor proposed, even if the underlying item changes through admin intervention in the meantime.
- The optional partial UNIQUE index on `booking_modifications (booking_vendor_id) WHERE status='draft'` is implemented via either MySQL 8 functional/generated-column technique or an equivalent application-level lock. The migration documents the choice.
- Mobile vendor app (Flutter) does not yet integrate with this builder. The customer-side API resource flags (FR-EXT-031-030..033) are designed to feed the customer Next.js/Flutter UI; the vendor mobile app reads modification proposals only after Phase 7.
- All schema references already exist in `docs/specs/11_DB_Schema.md` except the `ModificationStatus.Draft` extension (FR-EXT-031-020). No other migrations introduced.
- This spec's Pest tests run against the same MySQL test database used by the existing booking-module tests; no schema for the test environment is mocked.
