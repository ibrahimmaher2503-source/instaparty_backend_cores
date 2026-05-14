# ADR-0018: Changes-Requested Workflow for Vendor & Service Moderation

**Date**: 2026-05-04  
**Status**: Accepted  
**Relates to**: Feature 020 (Admin Changes-Requested Workflow), Phase 1.1 (Vendor Onboarding), Phase 8.0 (Service Moderation)

---

## Context

**Problem**: Admins currently can only reject vendor registrations and service submissions outright. There is no workflow to request specific corrections and allow vendors to resubmit. This creates friction:
- Vendors lose trust when rejected without guidance on what to fix.
- Admins spend time emailing vendors asking for specific document fixes (e.g., "CR is blurry, please re-upload").
- Vendors resubmit to the full approval queue, extending timelines.

**Solution**: Introduce a structured "changes requested" workflow where admins flag specific corrections (bilingual, with persistence), vendors address them and resubmit, and the system enforces a 3-cycle maximum to prevent infinite loops.

---

## Decision 1: Module Ownership for `change_requests` Infrastructure

**Question**: The `change_requests` table is polymorphic — it links to both vendor profiles (Identity module) and services (Catalog module). Which module should own it?

**Decision**: **Shared module**.

**Rationale**:
- The Shared module is the InstaParty cross-cutting foundation (currently holds MoneyCast, HasPublicId, CmsPage, AppSetting, FeatureFlag).
- Neither Identity nor Catalog should own infrastructure that the other needs to write to, as that creates a hidden cross-module model import (forbidden by Constitution §I).
- Shared models are accessible by all modules without violating module boundaries.

**Alternatives rejected**:
- *Identity module ownership*: Catalog (service moderation) would need to import Identity's `ChangeRequest` model → violates Constitution §I.
- *Catalog module ownership*: Same problem in reverse.
- *New Moderation module*: Too thin in Phase 1; deferred to Phase 2 consideration.

---

## Decision 2: Polymorphic Subject Linking Strategy

**Question**: How should `change_requests` link to its subject (vendor profile or service)?

**Decision**: **Manual polymorphic columns** `subject_type ENUM('vendor_profile','service')` + `subject_id BIGINT` (no Laravel `morphTo` relationship macro).

**Rationale**:
- Laravel `morphTo` uses string class names by default (e.g., `App\Modules\Identity\Domain\Models\VendorProfile`). Class renames silently break lookups.
- An explicit ENUM for `subject_type` with short string values (`vendor_profile`, `service`) is database-introspectable, safe from refactoring, and matches the `audit_logs` polymorphic pattern already in the codebase.
- The `ChangeRequestSubject` interface (in `Shared\Domain\Contracts`) gives each subject model a `getChangeRequestSubjectType()` method that returns the canonical string — no magic strings scattered through Actions.

**Alternatives rejected**:
- *Laravel default morphTo with class FQN*: Class renames silently break morphs.
- *Separate tables per subject type* (`vendor_change_requests`, `service_change_requests`): Duplicates identical schema; makes `EscalateChangeRequestToRejectionAction` polymorphic resolution harder.

---

## Decision 3: Cycle Counter Location

**Question**: Where is the cycle count tracked — on `change_requests` or on the subject?

**Decision**: **On `change_requests.cycle_number`**. Each `change_requests` row stores the cycle it represents (1, 2, or 3).

**Rationale**:
- Keeping `cycle_number` on the change request row makes each record self-describing (no join needed to know "this is cycle 2").
- The Actions check `SELECT MAX(cycle_number) FROM change_requests WHERE subject_type=X AND subject_id=Y` before creating a new one — a single indexed query.
- Avoids adding `change_request_cycle` columns to `vendor_profiles` and `services`, keeping those tables focused on their own concerns.

**Index required**: Composite index on `(subject_type, subject_id, status)` for the cycle check query.

---

## Decision 4: Escalation Trigger Mechanism

**Question**: Does `EscalateChangeRequestToRejectionAction` run automatically (cron/job) or manually (admin-triggered)?

**Decision**: **Manual in Phase 1** — admin-triggered via "Force Reject (cycle limit reached)" Filament button. Phase 1.5 adds a scheduled job.

**Rationale**:
- Phase 1 cut-list explicitly defers SLA/auto-escalation.
- Admin-triggered escalation is auditable (actor = admin user) vs. job-triggered (actor = system).
- The Action itself is complete and tested; only the trigger mechanism changes in Phase 1.5 (job calls the same Action).

---

## Decision 5: Filament Checklist Builder UI

**Question**: How does the admin build the checklist of change-request items in Filament?

**Decision**: A **`Filament\Forms\Components\Repeater`** with three fields per item:
- `TextInput::make('field_path')` — dot-notation path (e.g., `documents.cr_document`)
- `TextInput::make('requested_change_en')` — English description
- `TextInput::make('requested_change_ar')->dir('rtl')` — Arabic description

**Rationale**:
- `Repeater` is in the Filament core — no new packages.
- The three-field structure matches `change_request_items` schema exactly.
- `->dir('rtl')` on the AR field gives correct visual direction in the admin UI.

---

## Decision 6: Notification Dispatch

**Question**: How are `vendor.changes_requested` and `vendor.resubmitted` notifications sent?

**Decision**: Seeded into `notification_templates` table (Communication module). Dispatched via `DispatchNotificationAction` using event keys. Dispatched inside `DB::afterCommit()` listeners on `ChangeRequestCreated` and `VendorResubmitted` events.

**Rationale**:
- Communication module already owns notification dispatch. Calling it from Identity/Catalog Actions via `DB::afterCommit()` listeners follows Constitution §IX.
- No new notification infrastructure needed — just two new template records.

**Template channels**: `push` (primary), `email` (secondary). SMS deferred to Phase 1.5 campaign.

---

## Decision 7: Vendor-Side API Endpoint

**Question**: Does the vendor need a dedicated API endpoint to view their open change requests?

**Decision**: Yes — **`GET /api/v1/vendor/change-requests`** (paginated, filterable by status). Owned by Shared module routes.

**Rationale**:
- The vendor mobile app (Flutter, Phase 1.5) needs to surface change requests across both vendor-profile and service contexts in a single list view.
- Without this endpoint, the Flutter app would have to poll vendor profile + every service separately.
- Read-only endpoint; no idempotency key needed.

---

## Decision 8: ServiceStatus Enum — `Rejected` Case

**Observation**: The `ServiceStatus` enum has `Draft`, `PendingReview`, `Published`, `Archived` — NOT `Rejected`. Phase 8.0 will add `Rejected` properly.

**Decision**: This feature adds **ONLY `ChangesRequested`** to `ServiceStatus`. Adding `Rejected` is Phase 8.0 scope.

**Important**: The `ServiceStatus.canTransitionTo()` method must be updated to include `ChangesRequested` transitions in this feature:
- `PendingReview → ChangesRequested` (admin requests changes)
- `ChangesRequested → PendingReview` (vendor resubmits)

Phase 8.0 will add the `Rejected` case in its own migration.

---

## Consequences

**Positive**:
- Admins can request targeted corrections without full rejection.
- Vendors see bilingual guidance on exactly what to fix.
- Audit trail is permanent (change requests are append-only after status transitions).
- 3-cycle cap prevents infinite loops.
- Shared module infrastructure is reusable for future moderation workflows (Phase 2+).

**Negative**:
- Adds 2 new tables and 4 migrations (vendor + service variants for all migrations).
- Adds operational complexity: admins must understand the 3-cycle limit.
- Phase 1.5 SLA/auto-escalation deferred — vendors can let change requests languish if auto-deadline is not in place.

**Mitigation**:
- Clear Filament UI messaging on the "Force Reject" button.
- Quickstart guide documenting the 3-cycle limit.
- Phase 1.5 scheduled job for auto-escalation at SLA deadline.

---

## Implementation Status

✅ **Accepted** — Ready for Phase 2 (Foundational Infrastructure)

Phase 2 will implement:
- 3 enums (ChangeRequestStatus, ChangeRequestItemStatus, ChangeRequestSubjectType)
- 1 interface (ChangeRequestSubject)
- 1 policy class (ChangeRequestPolicy with MAX_CYCLES=3)
- 2 migrations (change_requests, change_request_items)
- 2 Eloquent models (ChangeRequest, ChangeRequestItem)
- 2 API Resources (ChangeRequestResource, ChangeRequestItemResource)
- Base HTTP layer (VendorChangeRequestListController)
- Notification seeder (ChangeRequestNotificationTemplateSeeder)

---

## References

- Feature 020: `specs/020-admin-changes-request/spec.md`
- Implementation Plan: `specs/020-admin-changes-request/plan.md`
- Data Model: `specs/020-admin-changes-request/data-model.md`
- API Contracts: `specs/020-admin-changes-request/contracts/api.md`
- Phase 1.1 (Vendor Onboarding): `docs/specs/09_Phasing_Plan.md` Week 2
- Phase 8.0 (Service Moderation): `docs/specs/09_Phasing_Plan.md` Week 7
