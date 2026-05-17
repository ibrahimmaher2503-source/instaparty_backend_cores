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
- FR coverage: PRD §7 (Booking flow). Local extensions `FR-EXT-030-NNN` (UI-specific).
- Schema traceability: reuses existing tables only — no new tables, no new columns.
- Phase alignment: **Phase 3.2 — Booking: Negotiation Loop** (09_Phasing_Plan.md L95, L623).
- No new packages.
---

# Implementation Plan: Vendor Booking Decision Page

**Branch**: `030-vendor-booking-decision-page` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/030-vendor-booking-decision-page/spec.md`

---

## Summary

Build a single, decision-focused Filament page inside the Vendor panel at `/vendor/booking-decisions/{bookingVendor}` that lets a vendor review and act on a pending `booking_vendors` record in one screen. The page reuses three already-implemented Application Actions (`VendorAcceptBookingAction`, `VendorRejectBookingAction`, `VendorModifyBookingAction`). The work is mostly UI + a small server-side hardening of the existing Accept/Reject actions to enforce the response deadline (currently they enforce only ownership + status).

The page is reached only via deep-links from `VendorIncomingBookingsPage` and `VendorBookingDetailPage` — it deliberately does NOT register a navigation entry. It composes Filament Infolists for the read view, header Actions for the three decisions, and a Livewire-driven countdown derived from `booking_vendors.response_deadline`.

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12 (per `02_Tech_Decisions.md`)
**Primary Dependencies**: Filament v3 (Pages, Infolists, Forms, Notifications), `spatie/laravel-translatable`, `brick/money`, existing `App\Modules\Booking\Application\Actions\Vendor{Accept,Reject,Modify}BookingAction`
**Storage**: MySQL 8 — existing tables only: `booking_vendors`, `bookings`, `booking_items`, `booking_addresses`, `booking_modifications`, `booking_state_transitions`, `service_inventory_reservations`, `vendor_coverage_areas`, `audit_logs`, `idempotency_keys`. **No migrations.**
**Testing**: Pest v3 + `pestphp/pest-plugin-laravel`
**Target Platform**: Laravel monolith served behind Reverb. Vendor panel = Filament v3 admin context at `/vendor` (existing `App\Providers\Filament\VendorPanelProvider`).
**Project Type**: Modular monolith (per Constitution §I). Feature lives entirely under `app/Modules/Booking/`.
**Performance Goals**: Page must render in **< 3s cold, < 1s warm** on a 4G connection (SC-001). All queries N+1-free (eager-load `booking.customer`, `booking.address`, `items.service`, `booking.modifications`, `booking.locks` once).
**Constraints**:
- No new tables/migrations
- No new packages
- No new public REST endpoints (Filament/Livewire surface)
- Server-side guard for response deadline must be added to the three Application Actions (the page-level disabling is advisory only)
- Bilingual EN+AR mandatory at launch (Constitution §IV)
**Scale/Scope**: One Filament Page class, one Blade view, one trait or service for coverage/inventory checks, two updated Action classes (deadline guard), one new Domain Exception (`ResponseDeadlineExpiredException`), one set of translation entries, one Pest feature test file.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies? | How satisfied |
|---|---|---|
| **I. Modular Monolith** | YES | All artifacts under `app/Modules/Booking/Filament/Vendor/Pages/` and `app/Modules/Booking/Application/Actions/`. No cross-module model imports — coverage/inventory checks query via existing Booking module models. |
| **II. Three Product Types — `match($enum)`** | YES | Page renders `booking_items.product_type` as a badge using `match(ProductType $state)` exactly as `VendorBookingDetailPage` already does. No new type-aware logic introduced. |
| **III. Money Discipline** | YES | All money fields rendered via `->money('EGP', divideBy: 100)` Filament helper. No floats. |
| **IV. Bilingual EN+AR Mandatory** | YES | All labels under `vendor-portal.*` translation keys with EN + AR entries. Customer notes and address use locale-pick-with-fallback. Page tested in both locales. |
| **V. Append-Only Tables** | YES | The page does NOT write directly to `booking_state_transitions` or `audit_logs` — both are written by the existing Actions and their `WriteBookingStateTransitionListener`. The page bypasses neither. |
| **VI. Spec-Driven Development — ADR** | YES | This feature does NOT create a new module — it adds a page to the existing Booking module (ADR-0006 / existing Booking ADR governs). No new ADR required. |
| **VII. Test-First for Critical Paths** | YES | Pest feature test covers: happy path Accept, happy path Reject, Modify navigation, deadline-expired guard, wrong-vendor 403, unauthenticated redirect, already-decided read-only, locked-booking read-only. Bookings are a critical path → 80%+ coverage target. |
| **VIII. Idempotency** | PARTIAL | The Actions already support optional `idempotencyKey` in their DTOs. The page does not currently produce keys (Livewire is single-flight per session); the spec does not require key generation. Constitution compliant because (a) the Action accepts but does not require the key, and (b) double-click is naturally guarded by the Action's `sub_status=pending` check returning HTTP 409. **No change needed.** |
| **IX. Domain Events Fire DB::afterCommit** | YES | Already enforced by the existing Actions — page does not bypass. |
| **X. Vendor Approval Two-Step Gate** | N/A | This page acts on already-onboarded vendors and existing bookings; approval gating happens at booking-creation time, upstream. |
| **XI. Document Storage** | N/A | No file uploads on this page. |

**Verdict**: **PASS**. No constitution violations. No entries in Complexity Tracking required.

**Cross-cutting note (small server-side hardening):**
The current `VendorAcceptBookingAction` and `VendorRejectBookingAction` enforce ownership + `sub_status=pending`, but **not** the `response_deadline`. To honor `FR-EXT-030-044` server-side, the plan adds a deadline guard to both Actions (and to `VendorModifyBookingAction` once verified). This is a **hardening of existing Actions**, not a constitution violation — it strengthens an already-locked principle (server-side authority over UI).

---

## Project Structure

### Documentation (this feature)

```text
specs/030-vendor-booking-decision-page/
├── plan.md              # This file
├── spec.md              # Already created
├── research.md          # Phase 0 (this run)
├── data-model.md        # Phase 1 (this run)
├── quickstart.md        # Phase 1 (this run)
├── contracts/
│   └── vendor-booking-decision-page.md  # Page/Action interface contract (no HTTP API)
├── checklists/
│   └── requirements.md  # Already created (all green)
└── tasks.md             # Created by /speckit.tasks (NOT this command)
```

### Source Code (repository root)

```text
app/Modules/Booking/
├── Application/
│   └── Actions/
│       ├── VendorAcceptBookingAction.php          # MODIFY: add deadline guard
│       ├── VendorRejectBookingAction.php          # MODIFY: add deadline guard
│       └── VendorModifyBookingAction.php          # MODIFY: add deadline guard
├── Domain/
│   └── Exceptions/
│       └── ResponseDeadlineExpiredException.php   # NEW: thrown when deadline lapsed
├── Filament/
│   └── Vendor/
│       └── Pages/
│           ├── VendorIncomingBookingsPage.php     # MODIFY: add "Decide" row action
│           ├── VendorBookingDetailPage.php        # MODIFY: add "Decide" header action
│           └── VendorBookingDecisionPage.php      # NEW: this feature
└── Resources/
    └── lang/
        ├── en/vendor-portal.php                   # MODIFY: add decision-page keys
        └── ar/vendor-portal.php                   # MODIFY: add decision-page keys

resources/views/vendor-portal/pages/
└── vendor-booking-decision.blade.php              # NEW: thin wrapper around Filament page

tests/Feature/Modules/Booking/VendorPortal/
└── VendorBookingDecisionPageTest.php              # NEW: Pest feature tests
```

**Structure Decision**: Follow the existing module layout (mirrors `VendorBookingDetailPage`). The page lives in `app/Modules/Booking/Filament/Vendor/Pages/` as a Filament `Page` subclass with `HasInfolists` + (optional) `InteractsWithForms` traits, registered via the existing Vendor panel auto-discovery in `VendorPanelProvider`.

---

## Phase 0 — Research

See `research.md` for full details. Key decisions resolved in this phase:

1. **Modify-action UX**: link out to `VendorBookingModificationBuilder` if it exists; otherwise show a "coming soon" notification. **Resolved**: detect class existence via `class_exists()` at runtime.
2. **Deadline server-side enforcement**: add a guard to the three Application Actions. **Resolved**: introduce `ResponseDeadlineExpiredException` and `abort(409)` with a clear message when `response_deadline` is non-null and in the past.
3. **Coverage validation algorithm**: resolve `booking_addresses.city_id` (or governorate fallback) against `vendor_coverage_areas` rows for the authenticated vendor. **Resolved**: simple EXISTS query; cache on the Page instance for the request.
4. **Inventory overlap algorithm**: for each rental `booking_item`, query `service_inventory_reservations` rows that overlap the event window for the same `service_id` and are still active. **Resolved**: a single grouped query, executed in `mount()`, results memoised on the page.
5. **Page navigation registration**: do NOT register navigation (`$shouldRegisterNavigation = false`) — page is only deep-linked from list/detail pages.
6. **Read-only mode resolution**: 5 conditions trigger read-only (already-decided, deadline expired, parent cancelled/completed, active booking_locks, no-items anomaly).
7. **Concurrency**: rely on `VendorAcceptBookingAction`'s existing `lockForUpdate()` + `sub_status=pending` check — race losers receive a 409 toast.
8. **Idempotency**: keys are optional on the DTOs; the page does not generate them (Livewire prevents double-submit on the client; the Action's status check handles server side).

---

## Phase 1 — Design & Contracts

See `data-model.md`, `contracts/vendor-booking-decision-page.md`, and `quickstart.md` for full details.

**Entities referenced (no schema changes):** `BookingVendor`, `Booking`, `BookingItem`, `BookingAddress`, `BookingModification`, `BookingLock`, `ServiceInventoryReservation`, `VendorCoverageArea`, `VendorProfile`, `User`.

**New Domain Exception:** `App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException` (extends `\RuntimeException`; rendered to HTTP 409 with translatable message).

**Action surface changes (additive, backward-compatible):**

| Action | Change |
|---|---|
| `VendorAcceptBookingAction::execute()` | After ownership + status guards, add: `if ($bookingVendor->response_deadline !== null && $bookingVendor->response_deadline->isPast()) { throw new ResponseDeadlineExpiredException(); }` |
| `VendorRejectBookingAction::execute()` | Same guard |
| `VendorModifyBookingAction::execute()` | Same guard |

**Page contract:** see `contracts/vendor-booking-decision-page.md`. Public methods: `mount(string $bookingVendor): void`, `infolist(Infolist $infolist): Infolist`, `getHeaderActions(): array`, plus private helpers for coverage/inventory/lock checks.

---

## Phase 2 — (out of scope for /speckit.plan)

`/speckit.tasks` will derive an ordered task list from this plan.

---

## Complexity Tracking

No constitution violations. **No entries required.**
