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

CONSTRAINTS (non-negotiable):
- FR traceability — local FR-EXT numbers used where PRD coverage does not yet exist; ⚠️ BACKFILL flag carried over from spec.md.
- Schema traceability — every touched table cited from `11_DB_Schema.md`; new columns on `chat_threads` carry a ⚠️ NEW COLUMN flag (gated behind a real `chat_threads` migration).
- Phase alignment — extends Phase 6.5 (Admin Booking Override) and Phase 7.0 (Hardening / deferred intervention workflow); ⚠️ PHASE BACKFILL flag carried from spec.md.
- No new packages.
- No Phase 2 features.
- Does not contradict `02_Tech_Decisions.md`.

API DOCUMENTATION CONSTRAINT:
- No public HTTP endpoints are added. Existing `BookingInterventionController::forceCancel` is untouched.
- No `.specify/memory/api-registry.md` entries to create; no Bruno/Postman collection updates.
---

# Implementation Plan: Admin Booking Intervention Page

**Branch**: `029-admin-booking-intervention` (spec dir; current git branch is `028-financial-ledger-hardening` — create dedicated branch after 028 settles)
**Date**: 2026-05-15
**Spec**: [spec.md](./spec.md)

## Summary

Ship a single-purpose Filament admin page (`AdminBookingInterventionResource`) under `app/Modules/Booking/Filament/Resources/` that surfaces every booking in trouble (late vendor response, vendor rejection, customer review pending, stalled) and gives the operator six new Action classes — `SendVendorReminderAction`, `EscalateLateVendorResponseAction`, `SuggestAlternativeVendorsAction`, `FreezeBookingChatAction`, `ResumeBookingReviewAction`, `CreateAdminInterventionNoteAction` — to facilitate without ever assigning a replacement vendor on the customer's behalf.

The work reuses every existing primitive: the `BookingAdminIntervention` Eloquent model and its `InterventionType` enum (extended with four new cases), the `NotificationDispatcher` contract, the `AdminInboxItem` model, the `state_transitions` polymorphic ledger, and the `idempotency_keys` throttling helper. The single new dependency (`chat_threads.frozen_at` / `frozen_by`) is gated behind a real `chat_threads` migration — if it does not land in time, `FreezeBookingChatAction` is moved to the cut-list and shipped in a follow-up.

## Technical Context

**Language/Version**: PHP 8.3+, Laravel 12
**Primary Dependencies**: Filament v3, spatie/laravel-translatable, spatie/laravel-permission + filament-shield, spatie/laravel-model-states (already in `10_Package_List.md` §1–3)
**Storage**: MySQL 8 / MariaDB 11 (`utf8mb4`); Redis (queue) for `notification_dispatches` workers
**Testing**: Pest 3 (`./vendor/bin/pest`), with groups `booking`, `intervention`, plus the type groups `rental` / `sale` / `digital` for type-aware coverage
**Target Platform**: Linux server; admin UI in browsers via Filament v3 at `/admin`
**Project Type**: Web-service — modular monolith, admin-only Filament feature, no public HTTP API
**Performance Goals**: List page first-paint ≤1.5 s with 100 trouble rows; filter/sort ≤800 ms; reminder dispatch reaches `notification_dispatches.sent_at` within 60 s (per SC-002, SC-003)
**Constraints**: Append-only `audit_logs`, `state_transitions`, `booking_admin_interventions`, `notification_dispatches`; every mutation in `DB::transaction`; every event via `DB::afterCommit`; EN + AR mandatory; admin-only — never exposes a "choose replacement vendor" path
**Scale/Scope**: Expected steady-state ~500 active bookings, ~50 in trouble at any time; 6 new Action classes; 1 new Filament resource; 1 enum extension (4 new cases); 0 new top-level tables

## Constitution Check

*GATE — must pass before Phase 0; re-checked after Phase 1.*

| # | Principle | Applies? | How this plan satisfies it |
|---|---|---|---|
| I | Modular Monolith / cross-module rules | Yes | New code lives under `app/Modules/Booking/`. Cross-module reach goes through contracts: `NotificationDispatcher` (Communication), `AdminInboxItem` is accessed via a thin `AdminInboxWriter` contract in `Communication/Domain/Contracts/` (added if not present) — **no direct cross-module model import** from Booking actions. Architecture test `tests/Architecture/NoCrossModuleModelImportsTest.php` already enforces this and will be re-run. |
| II | Three product types — `match($enum)` only | Yes (listing is type-aware) | The list query filters by `bookings.product_type`/`booking_items.product_type` via `match(ProductType)` only where branching is needed; no `if/elseif` on product-type strings. Per-type acceptance tests included (`->group('rental')`, `->group('sale')`, `->group('digital')`). |
| III | Money discipline | Yes | Total displayed via `->money('EGP', divideBy: 100)`. No money math in any of the six Actions. No floats introduced. |
| IV | Bilingual EN + AR mandatory | Yes | All admin strings in `app/Modules/Booking/Resources/lang/{en,ar}/booking.php` + `admin.php`. Notification dispatches use existing `notification_templates` (EN + AR rows already required). Detail view shows translatable fields in current locale. AR RTL verified manually + via locale Pest test. |
| V | Append-only tables | Yes | `audit_logs`, `state_transitions`, `booking_admin_interventions`, `notification_dispatches` are all append-only and only ever inserted into. Escalation updates `booking_vendors.sub_status` (mutable status column, allowed). Freeze toggles `chat_threads.frozen_at` (status-like column on a Firestore-mirror table, not in the append-only list). |
| VI | Spec-driven dev / ADR before code | Yes | No new module introduced. Reuses Booking + Communication modules. References existing ADR-0003 (Identity — admin actors), ADR-0005 (Payments — `RefundPolicyService` is not invoked here), and ADR-0028 (Financial Ledger — not touched). A lightweight ADR (`docs/adr/ADR-0029-admin-booking-intervention-page.md`) is added to record the "admin cannot assign replacement vendor" hard boundary. |
| VII | Test-first for critical paths | Yes | Booking state mutation = critical path. Pest tests cover every Action's happy + guard paths, plus the regression test from FR-EXT-011 and per-type listing tests. Tests written in the same day as the Action per Daily Discipline §2. |
| VIII | Idempotency for state-changing endpoints | Yes (internally) | This feature ships no HTTP endpoints, but `SendVendorReminderAction` and `ResumeBookingReviewAction` use the existing `idempotency_keys` table to throttle by `(scope, key)` so duplicate clicks within 5 min / 4 h don't double-dispatch. Scopes: `admin.intervention.vendor_reminder` and `admin.intervention.customer_review_reminder`. |
| IX | Domain events fire `DB::afterCommit` | Yes | Every Action wraps in `DB::transaction(function (){…})` and schedules events with `DB::afterCommit(fn () => event(...))`. No event fires inside the transaction. Pest assertions use `Event::fake()` + `Event::assertDispatched` to verify timing. |
| X | Vendor approval — two-step gate | Indirectly | `SuggestAlternativeVendorsAction` filters candidates by `vendor_profiles.approval_status='approved'` AND `vendor_approved_product_types` for the matching product type. Candidates failing either gate never appear. |
| XI | Document storage rules | No | No file uploads in this feature. |

**Constitution Check: PASS** — no violations to justify in the Complexity Tracking table.

### Phase 1 forbidden features check (per Constitution §"Phase 1 Forbidden Features")
- ❌ Vendor subscription tiers — not touched
- ❌ Dispute-resolution engine — explicitly NOT what this is; this is a manual triage tool with hard boundaries
- ❌ Per-category card templates — not touched
- ❌ Vendor page slider, QR catalog, advanced tax invoicing — not touched
- ❌ Multi-currency activation — money rendered as EGP only
- ❌ GCC payment gateway adapters — not touched

### ADR reference
- New ADR: **ADR-0029 — Admin Booking Intervention Page (hard boundary: admin never assigns replacement vendor)**.
  Drafted with `/new-module-adr` style sections: Status, Context, Decision (six Actions + zero replacement-assignment surface), Consequences (operator unblocks customers without breaching marketplace boundary), Alternatives Considered ((a) full admin reassignment — rejected on trust/legal grounds, (b) automated re-routing — rejected as Phase 2), Internal Decisions (throttle windows, suggestion cap N=5), Related ADRs (ADR-0005 Payments — for context on `RefundPolicyService`, not invoked here).

### Tables touched

| Table | Action | Source |
|---|---|---|
| `bookings` | READ (listing query joins) | `11_DB_Schema.md` §Booking |
| `booking_vendors` | READ + UPDATE (`sub_status` → `timed_out` in escalation only) | `11_DB_Schema.md` §Booking |
| `booking_modifications` | READ (list + detail) | `11_DB_Schema.md` §Booking |
| `booking_admin_interventions` | INSERT (every Action) | `11_DB_Schema.md` §Booking — exists per Phase 6.5 |
| `booking_customer_notes` | READ (detail view) | `11_DB_Schema.md` §Booking |
| `state_transitions` | INSERT (escalation, polymorphic to `BookingVendor`) | `11_DB_Schema.md` cross-cutting |
| `audit_logs` | INSERT (every Action) | `11_DB_Schema.md` cross-cutting |
| `notification_dispatches` | INSERT via `NotificationDispatcher` (reminder, escalation, suggestion, freeze, resume, customer-review reminder) | `11_DB_Schema.md` §Communication |
| `admin_inbox_items` | INSERT (escalation only) | `11_DB_Schema.md` §Communication |
| `idempotency_keys` | INSERT + READ (throttle reminder, resume-review) | `11_DB_Schema.md` cross-cutting |
| `chat_threads` | UPDATE `frozen_at`, `frozen_by` (Freeze/Resume Actions) | `11_DB_Schema.md` §Communication — ⚠️ TABLE NOT YET MIGRATED + columns NOT YET DEFINED; gated by precondition |
| `notification_templates` | READ (event keys must exist for new events) | `11_DB_Schema.md` §Communication — new rows seeded |

### Per-type coverage (rental / sale / digital)

The listing is type-aware (the four trouble buckets apply equally to all three product types, but the filtering UI exposes a product-type filter and the test suite asserts each bucket renders for each type). No Action branches on product type; vendor escalation, reminders, and freezes are uniform. The candidate query in `SuggestAlternativeVendorsAction` *does* filter by the booking's `product_type` to ensure the suggested vendor is approved for that type — that's the only per-type branch and is implemented via the candidate-query WHERE clause, not via `match`.

### Locale coverage (EN + AR)

- All Filament strings via `__()` resolving to `app/Modules/Booking/Resources/lang/{en,ar}/booking.php` + `admin.php`.
- New notification templates seeded with both `subject` and `body` in EN + AR — fail closed if either is empty (existing `notification_templates` validation already enforces this).
- AR RTL detail-view test asserts vendor business name renders in Arabic.

### Idempotency keys

| Endpoint / Action | Scope | TTL | Key |
|---|---|---|---|
| `SendVendorReminderAction` | `admin.intervention.vendor_reminder` | 5 min | `booking_vendor_id` |
| `ResumeBookingReviewAction` | `admin.intervention.customer_review_reminder` | 4 h | `booking_id` |
| `EscalateLateVendorResponseAction` | (none — guarded by `sub_status` check + DB row-level lock via `for_update`) | — | — |
| `SuggestAlternativeVendorsAction` | (none — re-suggestion is allowed; audit log is the trail) | — | — |
| `FreezeBookingChatAction` / `ResumeBookingChatAction` | (none — guarded by `frozen_at IS NULL` / `frozen_at IS NOT NULL`) | — | — |
| `CreateAdminInterventionNoteAction` | (none — notes are stackable) | — | — |

No new HTTP endpoint is exposed, so HTTP `Idempotency-Key` middleware does not apply. Internal throttling is handled by writing a row into `idempotency_keys` keyed by `(scope, hash(booking_vendor_id))` before the dispatch.

### Domain events (fired via `DB::afterCommit`)

| Action | Event | Listeners |
|---|---|---|
| `SendVendorReminderAction` | `VendorReminderSent` (new) | none in this PR — payload + queue-job dispatch via `NotificationDispatcher` happens inside the Action (synchronous insert, then deferred-publish via the existing dispatcher worker) |
| `EscalateLateVendorResponseAction` | `BookingVendorTimedOut` (new — or reuse existing `VendorResponseDeadlineExceeded` if it exists in the codebase; research will confirm) | listener creates `AdminInboxItem`; listener dispatches vendor + customer notification via `NotificationDispatcher` |
| `SuggestAlternativeVendorsAction` | `AdminSuggestedAlternativeVendors` (new) | listener dispatches customer notification |
| `FreezeBookingChatAction` | `BookingChatFrozen` (new) | listener pushes Firestore-mirror signal via existing chat gateway; listener dispatches both-party notification |
| `ResumeBookingReviewAction` | `CustomerReviewReminderSent` (new) | listener dispatches customer notification |
| `CreateAdminInterventionNoteAction` | (no event) | — |

All events live in `App\Modules\Booking\Domain\Events\`. Listeners live in `App\Modules\Booking\Application\Listeners\` and `App\Modules\Communication\Application\Listeners\` (the latter wires inbox-item + notification dispatch). Architecture test ensures listeners that touch external services are queueable (`ShouldQueue`).

### Architecture tests added / updated

- **Updated**: `tests/Architecture/NoCrossModuleModelImportsTest.php` — confirms Booking Actions reach Communication only via the `NotificationDispatcher` and new `AdminInboxWriter` contracts.
- **New**: `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` — implements FR-EXT-011. Asserts no class under `App\Modules\Booking` has a name matching `/AssignReplacementVendor/i`, no method on Booking Actions matches `/assignReplacementVendor/i`, and no Filament `Action` registered in `BookingsMonitorResource` / `AdminBookingInterventionResource` carries the `assign_replacement_vendor` permission slug.
- **New**: `tests/Architecture/VendorProposalInterventionHasNullProposedVendorTest.php` — asserts every persisted row with `intervention_type = vendor_proposal` has `proposed_vendor_id IS NULL` (DB-level + policy-level assertion).
- **Reused**: existing `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` — confirms no `softDeletes()` snuck into the new migration (only one is planned: `chat_threads` columns, no soft-delete added).

### Cut-list (inherited from phase + this feature)

If running tight against the Phase 6.5 / 7.0 timebox:
1. **Defer `FreezeBookingChatAction` / `ResumeBookingChatAction`** if the `chat_threads` table is not yet migrated by Communication module. Ship the other five Actions; FreezeChat moves to a follow-up alongside the Communication chat-mirror migration.
2. **Defer `SuggestAlternativeVendorsAction`** if the candidate query depends on `vendor_coverage_areas` / `service_availability_blocks` data not yet seeded. The four critical P1 actions (Reminder, Escalate, ResumeReview, Note) cover the unblocking case.
3. **Defer per-product-type listing test triplet** (keep at least one per type, drop full matrix). Architecture test FR-EXT-011 stays — non-negotiable.
4. **Defer the consolidated detail-view infolist** — fall back to linking out to the existing `BookingResource` detail page. The list + Actions still ship.

Item (1) is gated on a real `chat_threads` migration existing before implementation begins.

## Project Structure

### Documentation (this feature)

```text
specs/029-admin-booking-intervention/
├── plan.md              # This file (/speckit.plan command output)
├── spec.md              # Feature spec from /speckit.specify
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output — Action signatures, event payloads, permission strings
│   ├── actions.md
│   ├── events.md
│   └── permissions.md
├── checklists/
│   └── requirements.md  # From /speckit.specify
└── tasks.md             # Phase 2 output — generated by /speckit.tasks (NOT this command)
```

### Source Code (repository root)

```text
app/Modules/Booking/
├── Application/
│   ├── Actions/
│   │   ├── SendVendorReminderAction.php                 # NEW
│   │   ├── EscalateLateVendorResponseAction.php         # NEW
│   │   ├── SuggestAlternativeVendorsAction.php          # NEW
│   │   ├── FreezeBookingChatAction.php                  # NEW (gated on chat_threads)
│   │   ├── ResumeBookingChatAction.php                  # NEW (gated on chat_threads)
│   │   ├── ResumeBookingReviewAction.php                # NEW
│   │   ├── CreateAdminInterventionNoteAction.php        # NEW
│   │   └── ForceCancelBookingAction.php                 # existing — untouched
│   ├── DTOs/
│   │   ├── AdminInterventionDTO.php                     # existing — reused
│   │   └── SuggestedAlternativeVendorsDTO.php           # NEW (collects vendor_profile_ids, reason, locale)
│   └── Listeners/
│       ├── OnBookingVendorTimedOutCreateInboxItem.php   # NEW
│       └── OnBookingChatFrozenPushFirestore.php         # NEW (gated)
├── Domain/
│   ├── Enums/
│   │   └── InterventionType.php                          # MODIFIED — 4 new cases
│   └── Events/
│       ├── VendorReminderSent.php                        # NEW
│       ├── BookingVendorTimedOut.php                     # NEW
│       ├── AdminSuggestedAlternativeVendors.php          # NEW
│       ├── BookingChatFrozen.php                         # NEW (gated)
│       ├── CustomerReviewReminderSent.php                # NEW
│       └── BookingForceCancelled.php                     # existing
├── Filament/
│   └── Resources/
│       └── AdminBookingInterventionResource/             # NEW
│           ├── AdminBookingInterventionResource.php
│           ├── Pages/
│           │   ├── ListBookingInterventions.php
│           │   └── ViewBookingIntervention.php
│           └── Widgets/
│               └── TroubleBucketSummaryWidget.php       # NEW (optional headline stats)
├── Database/
│   └── Migrations/
│       └── 2026_05_15_100001_seed_intervention_notification_templates.php  # NEW seeder-as-migration
└── Resources/
    └── lang/{en,ar}/
        ├── booking.php  # MODIFIED — new strings
        └── admin.php    # MODIFIED — new strings

app/Modules/Communication/
├── Domain/
│   └── Contracts/
│       └── AdminInboxWriter.php                          # NEW (thin contract)
├── Infrastructure/
│   └── Repositories/
│       └── EloquentAdminInboxWriter.php                  # NEW (implements contract)
└── Database/
    └── Migrations/
        └── 2026_05_15_100002_add_frozen_to_chat_threads.php  # NEW — gated on chat_threads existing

tests/
├── Architecture/
│   ├── AdminCannotAssignReplacementVendorTest.php        # NEW (FR-EXT-011)
│   └── VendorProposalInterventionHasNullProposedVendorTest.php  # NEW (FR-EXT-012)
├── Feature/
│   └── Modules/
│       └── Booking/
│           └── AdminIntervention/
│               ├── SendVendorReminderActionTest.php       # NEW
│               ├── EscalateLateVendorResponseActionTest.php  # NEW
│               ├── SuggestAlternativeVendorsActionTest.php   # NEW
│               ├── FreezeBookingChatActionTest.php           # NEW (gated)
│               ├── ResumeBookingReviewActionTest.php         # NEW
│               ├── CreateAdminInterventionNoteActionTest.php # NEW
│               ├── AdminBookingInterventionPageTest.php      # NEW — listing, filters, sort, AR locale
│               └── AdminBookingInterventionAuthorizationTest.php  # NEW — permission gates
```

**Structure Decision**: Single project, modular monolith. New code is concentrated in `app/Modules/Booking/` with two cross-module touches (the `AdminInboxWriter` contract in Communication and an optional `chat_threads` column migration). No new top-level folders are introduced.

## Complexity Tracking

Constitution Check passes. The table below is intentionally empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
