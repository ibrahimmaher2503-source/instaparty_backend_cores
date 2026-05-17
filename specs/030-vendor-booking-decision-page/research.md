# Phase 0 — Research

> Resolves all NEEDS CLARIFICATION items and unknowns surfaced in `plan.md`.

---

## R-001 — Server-side enforcement of `response_deadline`

**Question**: Where should the response-deadline guard live — in the Filament Page, in the Application Actions, or in a Form Request?

**Decision**: Add the guard inside each of the three Application Actions (`VendorAcceptBookingAction`, `VendorRejectBookingAction`, `VendorModifyBookingAction`), throwing a new `ResponseDeadlineExpiredException` rendered as HTTP 409.

**Rationale**:
- Action classes are the authoritative server-side boundary per CLAUDE.md §"Coding Conventions". The page is UI; it can disable buttons but cannot be trusted.
- Placing the guard in the Action also protects future HTTP API entry-points (Phase 3.2 will likely add `POST /api/v1/vendor/bookings/{id}/accept`).
- Co-locating with the existing ownership + status guards keeps the precondition checks together.

**Alternatives considered**:
1. **Filament `->disabled()` only** — rejected: client-side. A vendor with browser dev tools could still post the Livewire action.
2. **Form Request** — rejected: the existing flow uses DTOs, not Form Requests, for these Actions; adding a Form Request would diverge from the established pattern.
3. **Middleware on the page route** — rejected: middleware does not have access to the loaded `BookingVendor` model without extra DB lookup; the Action already loads it under `lockForUpdate()`.

**Backward compatibility**: Existing callers (`VendorIncomingBookingsPage` quick actions, future API) get the same guard for free. The guard is a CHECK, not a behavior change — bookings with `response_deadline = null` are unaffected.

---

## R-002 — Modify-action UX when `VendorBookingModificationBuilder` is not yet shipped

**Question**: The Vendor Portal plan mentions `VendorBookingModificationBuilder` as a future page. What does **Modify** do today?

**Decision**: Runtime detection via `class_exists(VendorBookingModificationBuilder::class)`. If present → redirect to its URL with the booking_vendor public_id. If absent → emit a Filament `Notification` (`info`) titled "Modification builder coming soon" and do not navigate or mutate.

**Rationale**: This keeps the decision page deployable today and forward-compatible when the modification builder ships. No state mutation on the fallback path means no risk of half-built modifications.

**Alternatives considered**:
1. **Open an inline modal that creates a `booking_modifications` draft** — rejected: too much logic for a placeholder; risks creating orphan draft rows.
2. **Hide the Modify button entirely when class absent** — rejected: hurts vendor expectation discovery ("can I modify? I don't see it"). Showing-but-deferring is clearer.

---

## R-003 — Coverage validation lookup

**Question**: How does the page determine whether the booking event address falls inside the authenticated vendor's declared coverage?

**Decision**: A single existence query against `vendor_coverage_areas` keyed by the authenticated `vendor_profile_id` and the booking address's `city_id` (or, when only `governorate_id` is available, by governorate). Result memoised on the page instance.

Pseudocode:

```php
private function isAddressInCoverage(BookingAddress $address, VendorProfile $vendor): bool
{
    return VendorCoverageArea::query()
        ->where('vendor_profile_id', $vendor->id)
        ->where(function ($q) use ($address) {
            $q->where('city_id', $address->city_id)
              ->orWhere(fn ($q) => $q->whereNull('city_id')->where('governorate_id', $address->governorate_id));
        })
        ->exists();
}
```

**Rationale**: O(1) check, no joins to load full coverage data, and respects the existing `vendor_coverage_areas` schema (whole-governorate rows have `city_id = NULL`).

**Fallback**: If the vendor has zero coverage rows declared, the badge renders **grey** ("Coverage not declared") and Accept is **not** blocked. That choice is intentional — many vendors during Phase 1 will skip coverage onboarding; we surface the gap but do not gate the action.

---

## R-004 — Inventory overlap detection

**Question**: How does the page show "inventory warning" for rental items?

**Decision**: For each rental `booking_item` belonging to this vendor's `booking_vendor`, query `service_inventory_reservations` for the same `service_id` whose `[reserved_starts_at, reserved_ends_at]` window overlaps the event window AND whose `status IN ('held', 'confirmed')` AND whose `booking_id != $thisBooking->id`. If any rows exist, list the offending item names in a warning banner.

**Rationale**: This is a read-only warning, not an enforcement gate (gating happens at booking creation, upstream). The query uses the existing `(service_id, reserved_starts_at, reserved_ends_at, status)` index per `.claude/rules/schema-cheatsheet.md`. Total cost: one indexed range query per rental item.

**Alternatives considered**:
1. **Materialised view** — rejected: premature optimisation; the page is single-user/single-booking.
2. **Block Accept on conflict** — rejected: a real conflict at this stage typically means a prior reservation already expired but the booking_item exists; gating would block legitimate accepts. Warning > gating.

---

## R-005 — Read-only mode trigger matrix

**Question**: Under exactly which conditions is the page rendered in read-only mode (actions hidden)?

**Decision**: Read-only when ANY of the following is true:

1. `booking_vendors.sub_status !== Pending` — already decided
2. `booking_vendors.response_deadline !== null && response_deadline->isPast()` — deadline lapsed
3. `bookings.lifecycle_status` in `Cancelled` or `Completed`
4. An active `booking_locks` row exists for this booking (`released_at IS NULL`)
5. `booking_vendors.items()->count() === 0` — anomaly: zero items assigned to this vendor

Conditions 1, 3, 4, 5 also produce a banner explaining WHY. Condition 2 produces an "Expired" banner with admin-contact hint.

**Rationale**: Five distinct conditions cover every state where a decision is invalid. Each maps to a translation key and a Filament banner color (gray, warning, danger).

---

## R-006 — Concurrency under double-click / two staff simultaneously

**Question**: Two staff in the same vendor org open the page and both click Accept. What happens?

**Decision**: The first request wins via `VendorAcceptBookingAction`'s existing `lockForUpdate()` + `sub_status === Pending` guard. The second request hits the 409 `abort_if` and the page surfaces a Filament `danger` notification ("This booking is no longer pending — refreshing").

**Rationale**: No new locking introduced — the Action already handles this correctly with `SELECT ... FOR UPDATE`. The page only needs to catch the abort and surface it cleanly via Livewire's error handler (Filament wraps thrown exceptions in user-facing notifications by default).

---

## R-007 — Idempotency strategy

**Question**: Should the Page generate `Idempotency-Key` values per Constitution §VIII?

**Decision**: NO. The Constitution requires idempotency keys for **HTTP API** state-changing endpoints (per §VIII's enumerated list). This page is a Filament/Livewire surface, not an HTTP API. Livewire prevents double-submits at the client level, and the Action's `sub_status=pending` precondition handles any server-side race.

**Rationale**: The Action's DTOs already accept an optional `idempotencyKey`. We are not removing that capability — we are simply not generating one from the page surface. When Phase 3.2 ships its public REST endpoint, the controller (not the Action) will mint and pass the key.

**Alternative considered**: Generate per-session keys from the Page — rejected: false sense of security (the key would just be the session id and Livewire's CSRF token already covers that ground).

---

## R-008 — Translation key namespace

**Question**: Which translation namespace owns the page's keys?

**Decision**: Reuse the existing `vendor-portal.*` namespace, with a new `vendor-portal.decision.*` sub-tree.

Examples:
- `vendor-portal.decision.title`
- `vendor-portal.decision.accept`
- `vendor-portal.decision.reject`
- `vendor-portal.decision.modify`
- `vendor-portal.decision.coverage.inside`
- `vendor-portal.decision.coverage.outside`
- `vendor-portal.decision.coverage.unknown`
- `vendor-portal.decision.deadline.expired`
- `vendor-portal.decision.deadline.urgent`
- `vendor-portal.decision.inventory.warning`
- `vendor-portal.decision.payment_status.{paid,partial,unpaid,refund_pending}`
- `vendor-portal.decision.readonly.{already_decided,cancelled,completed,locked,no_items}`

**Rationale**: Co-locates with the existing `vendor-portal.bookings.*` keys used by `VendorIncomingBookingsPage` and `VendorBookingDetailPage`. Same file, same locale folders.

---

## R-009 — Coverage area data when fields are NULL

**Question**: What if `booking_addresses.city_id` is NULL (e.g., free-text address)?

**Decision**: Fall back to `governorate_id`. If both are NULL, render the coverage badge as `Grey — coverage unknown` (do not block actions).

**Rationale**: The geography schema (`docs/specs/11_DB_Schema.md` Geography module) makes city the canonical level but allows governorate-only addresses. The fallback respects that.

---

## R-010 — Performance: N+1 prevention

**Question**: How to render the page in < 3s (SC-001) with all the related entities?

**Decision**: `mount()` performs one query with eager-loads:

```php
BookingVendor::query()
    ->where('public_id', $bookingVendor)
    ->with([
        'booking.customer',
        'booking.address',
        'booking.modifications' => fn ($q) => $q->latest(),
        'booking.locks' => fn ($q) => $q->whereNull('released_at'),
        'items.service.type{Rental,Sale,Digital}Details',
    ])
    ->firstOrFail();
```

Two additional queries are made post-mount: coverage check (one EXISTS) and inventory check (one grouped query keyed by service_ids). Total: ~4 queries.

**Rationale**: Filament's Infolist + RepeatableEntry will iterate `items` — eager loading prevents an O(N) query explosion.

---

## R-011 — Navigation registration

**Question**: Does the page appear in the vendor's left navigation?

**Decision**: NO. `protected static bool $shouldRegisterNavigation = false;`. Reached only via deep-link from the `VendorIncomingBookingsPage` row action ("Decide") and the `VendorBookingDetailPage` header action ("Decide").

**Rationale**: A per-record decision page in the nav rail would be meaningless — there's no "the decision" without a record context.

---

## Summary

All Phase 0 unknowns resolved. No `NEEDS CLARIFICATION` remains. The plan is ready for Phase 1 design output.
