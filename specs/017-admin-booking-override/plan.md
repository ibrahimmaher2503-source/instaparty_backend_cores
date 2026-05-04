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
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Implementation Plan: Admin Booking Override

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](spec.md)
**Phase**: 6.5 (Week 7, 2 days) | **PRD**: FR-16, FR-17, FR-18
**ADR Required**: `docs/adr/0013-admin-booking-override.md`

> **ADR Note**: The phasing plan erroneously references ADR-0011. That number is taken by `0011-reviews-module.md`. Use **ADR-0013**.

---

## Summary

Admins need controlled tools to resolve stuck bookings: force-cancel with per-type refund, force vendor timeout, propose an alternative vendor (customer decides — FR-17 hard guard), and add internal notes. Every intervention is triple-logged: `booking_admin_interventions` (new), `booking_state_transitions` (append-only, for state mutations), `audit_logs` (append-only, all interventions). Customer notifications fire via the existing Communication module event/listener pipeline.

---

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12
**Primary Dependencies**: spatie/laravel-model-states (state machine), spatie/laravel-permission (roles/permissions), spatie/laravel-activitylog + custom audit_logs, Communication module NotificationDispatcher contract
**Storage**: MySQL 8 — `booking_admin_interventions` (new table), `booking_state_transitions` + `audit_logs` (append-only writes), `bookings` + `booking_vendors` (status mutations)
**Testing**: Pest (Feature + Unit)
**Target Platform**: Laravel API + Filament admin panel
**Project Type**: Modular monolith web service — `app/Modules/Booking/`
**Performance Goals**: Admin intervention < 500ms p95 (no external calls in the hot path; all side effects queued)
**Constraints**: FR-17 hard constraint — no automated vendor replacement; Constitution Principle I — no cross-module model imports; Constitution Principle V — `booking_admin_interventions` append-only except `customer_consent_status`
**Scale/Scope**: Admin-only paths (low volume); customer proposal response is a direct user action (moderate volume)

---

## Constitution Check

*GATE: Re-checked post-research. All principles satisfied.*

| # | Principle | Status | How Satisfied |
|---|---|---|---|
| I | Modular Monolith | ✅ PASS | All new code in `app/Modules/Booking/`. Cross-module refund and notification calls go via domain events; no direct imports of Payments or Communication models |
| II | Three Product Types — `match($enum)` | ✅ PASS | `ForceCancelBookingAction` iterates `booking_items` and calls `RefundPolicyService` (via event → Payments listener) per item's `product_type` using existing `match($productType)` inside `RefundPolicyService`. No new `if/elseif` chains introduced |
| III | Money Discipline | ✅ PASS | No new money columns. Refund amounts flow through existing `Brick\Money` infrastructure in `InitiateRefundAction` |
| IV | Bilingual EN+AR | ✅ PASS | Admin `reason` field is internal-only (EN plain text). Customer-facing notification templates require EN+AR in `notification_templates.subject` (JSON) and `notification_templates.body` (JSON). 4 new notification event keys registered with both locales |
| V | Append-Only Tables | ✅ PASS | `booking_state_transitions` and `audit_logs` get INSERT only. `booking_admin_interventions` is INSERT-only except `customer_consent_status` (status-column update, allowed per Principle V) |
| VI | ADR Before Code | ✅ PASS | ADR-0013 must be written and accepted before any migration is run |
| VII | Test-First Critical Paths | ✅ PASS | Day 2 covers all 4 intervention types. Force-cancel has explicit tests for all 3 product types. FR-17 compliance tested by asserting no auto-replacement under any code path |
| VIII | Idempotency | ✅ PASS | `force_cancel`, `timeout_vendor`, `propose_vendor` endpoints all carry `Idempotency-Key` header support via existing `idempotency_keys` middleware. `add_note` endpoint does not (no state mutation risk — duplicate notes are harmless and visible) |
| IX | Domain Events DB::afterCommit | ✅ PASS | All four new domain events (`BookingForceCancelled`, `VendorResponseTimedOut`, `AlternativeVendorProposed`, `VendorProposalDecided`) fire via `DB::afterCommit()`. Notification and refund listeners are queued |
| X | Vendor Approval Two-Step Gate | ✅ PASS | `ProposeAlternativeVendorAction` validates that the proposed vendor has an accepted `vendor_approved_product_types` entry for the booking's product type before creating the intervention |
| XI | Document Storage | ➖ N/A | No file uploads in this feature |

---

## Project Structure

### Documentation (this feature)

```text
specs/017-admin-booking-override/
├── spec.md                    ✅ Written
├── plan.md                    ✅ This file
├── research.md                ✅ Written
├── data-model.md              ✅ Written
├── contracts/
│   └── api-endpoints.md       ✅ Written
└── tasks.md                   ⬜ Output of /speckit.tasks
```

### Source Code Layout

```text
docs/adr/
└── 0013-admin-booking-override.md         [NEW — must be written before code]

app/Modules/Booking/
├── Application/
│   ├── Actions/
│   │   ├── ForceCancelBookingAction.php              [NEW]
│   │   ├── TimeoutVendorResponseAction.php           [NEW]
│   │   ├── ProposeAlternativeVendorAction.php        [NEW]
│   │   ├── AddAdminNoteAction.php                    [NEW]
│   │   └── RespondToVendorProposalAction.php         [NEW — customer accept/reject]
│   ├── DTOs/
│   │   ├── AdminInterventionDTO.php                  [NEW]
│   │   └── VendorProposalResponseDTO.php             [NEW]
│   └── Listeners/
│       └── ExpireVendorProposalsListener.php         [NEW — for scheduled command]
├── Console/
│   └── Commands/
│       └── ExpireVendorProposalsCommand.php          [NEW]
├── Database/
│   └── Migrations/
│       └── 2026_05_03_000012_create_booking_admin_interventions_table.php  [NEW]
│       └── 2026_05_03_000013_add_timed_out_to_booking_vendors_sub_status.php [NEW]
├── Domain/
│   ├── Enums/
│   │   ├── InterventionType.php                      [NEW]
│   │   ├── CustomerConsentStatus.php                 [NEW]
│   │   └── VendorSubStatus.php                       [MODIFY — add TimedOut case]
│   ├── Events/
│   │   ├── BookingForceCancelled.php                 [NEW]
│   │   ├── VendorResponseTimedOut.php                [NEW]
│   │   ├── AlternativeVendorProposed.php             [NEW]
│   │   └── VendorProposalDecided.php                 [NEW]
│   └── Models/
│       └── BookingAdminIntervention.php              [NEW]
├── Filament/
│   └── Resources/
│       └── BookingResource/
│           └── Pages/
│               └── ViewBooking.php                   [NEW — detail page with intervention panel]
│       (Extend BookingResource to add ViewPage + intervention Actions)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   └── BookingInterventionController.php     [NEW]
│   │   └── Customer/
│   │       └── VendorProposalResponseController.php  [NEW]
│   ├── Requests/
│   │   ├── ForceCancelBookingRequest.php             [NEW]
│   │   ├── TimeoutVendorResponseRequest.php          [NEW]
│   │   ├── ProposeAlternativeVendorRequest.php       [NEW]
│   │   ├── AddAdminNoteRequest.php                   [NEW]
│   │   └── RespondToVendorProposalRequest.php        [NEW]
│   └── Resources/
│       ├── BookingAdminInterventionResource.php      [NEW — admin view]
│       └── VendorProposalResource.php                [NEW — customer view]
├── Resources/
│   └── lang/
│       ├── en/booking.php                            [MODIFY — add intervention labels]
│       └── ar/booking.php                            [MODIFY — add intervention labels in AR]
└── Providers/
    └── BookingServiceProvider.php                    [MODIFY — register events, listeners, command]

app/Modules/Communication/
└── Application/
    └── Listeners/
        ├── OnBookingForceCancelled.php               [NEW]
        ├── OnVendorResponseTimedOut.php              [NEW]
        ├── OnAlternativeVendorProposed.php           [NEW]
        └── OnVendorProposalDecided.php               [NEW]

app/Modules/Payments/
└── Application/
    └── Listeners/
        └── OnBookingForceCancelledRefundListener.php [NEW — calls InitiateRefundAction per item]

tests/
├── Feature/
│   └── Modules/
│       └── Booking/
│           └── AdminInterventionTest.php             [NEW]
└── Unit/
    └── Modules/
        └── Booking/
            └── ForceCancelRefundPolicyTest.php       [NEW]
```

---

## ADR Reference

**ADR-0013** (`docs/adr/0013-admin-booking-override.md`) must be written with status `Accepted` before any migration.

**Key decisions to capture in ADR-0013**:
1. New `booking_admin_interventions` table added to the 60-table schema (explicit Phase 6.5 addition)
2. `customer_consent_status` is the only mutable column — all other columns are insert-only
3. Cross-module refund trigger via `BookingForceCancelled` event (not direct `InitiateRefundAction` call)
4. `VendorSubStatus::TimedOut` added to distinguish admin-triggered timeout from natural cancellation
5. FR-17 enforcement mechanism: `ProposeAlternativeVendorAction` creates a `pending` proposal; only `RespondToVendorProposalAction` (authenticated as the customer) can transition to `accepted`

---

## Tables Touched (citing 11_DB_Schema.md)

| Table | Operation | Columns Affected |
|---|---|---|
| `booking_admin_interventions` | CREATE (new) | All — see data-model.md |
| `bookings` | UPDATE | `lifecycle_status` (force_cancel, vendor_timeout only) |
| `booking_vendors` | UPDATE | `sub_status` → `'timed_out'` (vendor_timeout only) |
| `booking_state_transitions` | INSERT (append-only) | `from_state`, `to_state`, `trigger_kind = 'admin'` |
| `audit_logs` | INSERT (append-only) | `event_type`, `auditable_type`, `auditable_id`, `actor_type`, `actor_id`, `payload` |
| `booking_admin_interventions` | UPDATE | `customer_consent_status` only (proposal response) |

---

## Per-Type Coverage

This feature is **partially type-aware**:

- `ForceCancelBookingAction` iterates each `booking_item` with its `product_type` and defers to `RefundPolicyService::policyFor($productType, ...)` — which uses `match($enum)` internally. Three explicit test cases required (rental, sale, digital).
- `TimeoutVendorResponseAction`, `ProposeAlternativeVendorAction`, `AddAdminNoteAction` are type-agnostic (same behavior regardless of product type). Single test case sufficient.
- `ProposeAlternativeVendorAction` validates the proposed vendor's `vendor_approved_product_types` against the booking items' product types. If a booking has mixed product types, the proposed vendor must be approved for ALL types present.

---

## Locale Coverage (EN+AR)

| Surface | EN | AR |
|---|---|---|
| `booking_admin_interventions.reason` | Plain text (admin internal) | Not required |
| Notification template: `booking.admin_force_cancelled` | ✅ Required | ✅ Required |
| Notification template: `booking.vendor_timed_out` | ✅ Required | ✅ Required |
| Notification template: `booking.vendor_proposal_received` | ✅ Required | ✅ Required |
| Notification template: `booking.vendor_proposal_expiry_reminder` | ✅ Required | ✅ Required |
| Filament intervention panel labels | ✅ Required | ✅ Required |
| API Resource: `intervention_type` label | ✅ locale-converted | ✅ locale-converted |
| API Resource: `customer_consent_status` label | ✅ locale-converted | ✅ locale-converted |

Locale conversion happens at the API Resource layer (`BookingAdminInterventionResource`, `VendorProposalResource`).

---

## Idempotency Keys

| Endpoint | Idempotency-Key Required | Rationale |
|---|---|---|
| `POST /admin/bookings/{id}/force-cancel` | ✅ Yes | State-mutating; accidental double-submit would cancel twice |
| `POST /admin/bookings/{id}/timeout-vendor` | ✅ Yes | State-mutating |
| `POST /admin/bookings/{id}/propose-vendor` | ✅ Yes | Creates a record and triggers notification |
| `POST /admin/bookings/{id}/notes` | ❌ No | Append-only with no state consequence; duplicate notes are visible and harmless |
| `PATCH /customer/bookings/{id}/vendor-proposals/{id}/respond` | ❌ No | Idempotent by nature (status field — second `accepted` returns same result) |

---

## Domain Events Fired (with DB::afterCommit confirmation)

| Action | Event | afterCommit | Listeners |
|---|---|---|---|
| `ForceCancelBookingAction` | `BookingForceCancelled` | ✅ | `OnBookingForceCancelled` (Communication), `OnBookingForceCancelledRefundListener` (Payments), existing `ReleaseInventoryOnCancellationListener` (Booking) |
| `TimeoutVendorResponseAction` | `VendorResponseTimedOut` | ✅ | `OnVendorResponseTimedOut` (Communication) |
| `ProposeAlternativeVendorAction` | `AlternativeVendorProposed` | ✅ | `OnAlternativeVendorProposed` (Communication) |
| `RespondToVendorProposalAction` | `VendorProposalDecided` | ✅ | `OnVendorProposalDecided` (Communication) |

All Communication listeners are **queued** (implement `ShouldQueue`). The Payments listener is also **queued**.

---

## Architecture Tests Required

These must be added or verified in `tests/Architecture/`:

1. `NoCrossModuleModelImportsTest.php` — already exists; verify `ForceCancelBookingAction` does not import `App\Modules\Payments\...` directly
2. `NoFloatForMoneyTest.php` — already exists; no money arithmetic in new code
3. `AppendOnlyTablesHaveNoSoftDeletesTest.php` — already exists; verify `BookingAdminIntervention` model has no `SoftDeletes` trait

---

## Filament Integration

Extend the existing `BookingResource` (in `app/Modules/Booking/Filament/Resources/BookingResource.php`) to add a `ViewBooking` detail page. This page includes:

1. **Infolist**: booking summary (existing)
2. **Intervention History** table (read-only): lists all `booking_admin_interventions` for the booking, newest first
3. **Action buttons** (each opens a modal form gated by permission):
   - "Force Cancel" (`force_cancel_booking` permission) — `Action::make('forceCancelBooking')`
   - "Force Vendor Timeout" (`timeout_vendor_response` permission) — only shown when `lifecycle_status = 'vendor_review'`
   - "Propose Alternative Vendor" (`propose_alternative_vendor` permission) — shown when appropriate state
   - "Add Note" (`add_booking_note` permission) — always visible to admins
4. All action closures delegate 100% to the corresponding Application Action class — no business logic in Filament closures.

Run `php artisan shield:generate --all` after adding the ViewBooking page and new permissions.

---

## New Permissions (Shield)

To be added to `super_admin` and `booking_manager` roles:
- `force_cancel_booking`
- `timeout_vendor_response`
- `propose_alternative_vendor`
- `add_booking_note`

---

## Cut-List (if behind schedule)

1. Defer `ExpireVendorProposalsCommand` scheduler — make expiry a manual admin "Expire Proposal" Filament action for now
2. Defer `vendor_proposal_expiry_reminder` notification template (keep only the initial proposal notification)
3. Defer multi-vendor booking force-cancel edge case (single-vendor bookings only for initial delivery)
4. Defer GET `/admin/bookings/{id}/interventions` paginated list endpoint — show interventions only in Filament panel for now

---

## API Registry Updates Required

After implementation, add these rows to `.specify/memory/api-registry.md`:

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response |
|---|---|---|---|---|---|---|---|
| POST | /api/v1/admin/bookings/{id}/force-cancel | Booking | 6.5 | sanctum-token | super_admin, booking_manager | ForceCancelBookingRequest | ApiResponse{data: intervention summary} |
| POST | /api/v1/admin/bookings/{id}/timeout-vendor | Booking | 6.5 | sanctum-token | super_admin, booking_manager | TimeoutVendorResponseRequest | ApiResponse{data: intervention summary} |
| POST | /api/v1/admin/bookings/{id}/propose-vendor | Booking | 6.5 | sanctum-token | super_admin, booking_manager | ProposeAlternativeVendorRequest | ApiResponse{data: intervention with proposal details} |
| POST | /api/v1/admin/bookings/{id}/notes | Booking | 6.5 | sanctum-token | super_admin, booking_manager | AddAdminNoteRequest | ApiResponse{data: intervention summary} |
| GET | /api/v1/admin/bookings/{id}/interventions | Booking | 6.5 | sanctum-token | super_admin, booking_manager | — | ApiResponse{data: BookingAdminInterventionResource[]} |
| PATCH | /api/v1/customer/bookings/{id}/vendor-proposals/{ivId}/respond | Booking | 6.5 | sanctum-token | customer | RespondToVendorProposalRequest | ApiResponse{data: proposal response} |

---

## Complexity Tracking

No constitution violations. All choices follow established project patterns.
