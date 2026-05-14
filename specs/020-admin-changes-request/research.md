# Research: Admin Changes-Requested Workflow

**Branch**: `020-admin-changes-request` | **Date**: 2026-05-04
**Phase**: 0 — all design unknowns resolved before implementation begins

---

## Decision 1: Module Ownership for `change_requests` Infrastructure

**Question**: The `change_requests` table is polymorphic — it links to `vendor_profiles` (Identity module) and `services` (Catalog module). Which module should own it?

**Decision**: `Shared` module.

**Rationale**:
- The `Shared` module is the InstaParty cross-cutting foundation by design (it currently holds `MoneyCast`, `HasPublicId`, `CmsPage`, `AppSetting`, `FeatureFlag`).
- Neither Identity nor Catalog should own infrastructure that the other needs to write to, as that creates a hidden cross-module model import (forbidden by Constitution §I).
- `Shared` models are accessible by all modules without violating module boundaries — they are the common layer.

**Alternatives considered and rejected**:
- *Identity module ownership*: Rejected — Catalog (service moderation) would need to import Identity's `ChangeRequest` model, violating cross-module rules.
- *Catalog module ownership*: Rejected — same problem in reverse.
- *New `Moderation` module*: Rejected — too thin to justify a new bounded context in Phase 1; deferred to Phase 2 consideration.

---

## Decision 2: Polymorphic Subject Linking Strategy

**Question**: How should `change_requests` link to its subject (vendor profile or service)?

**Decision**: Manual polymorphic columns `subject_type ENUM('vendor_profile','service')` + `subject_id BIGINT` (no Laravel `morphTo` relationship macro).

**Rationale**:
- Laravel `morphTo` uses string class names in the database by default (e.g., `App\Modules\Identity\Domain\Models\VendorProfile`). Class renames would silently break lookups.
- An explicit ENUM for `subject_type` with short string values (`vendor_profile`, `service`) is database-introspectable, safe from refactoring, and matches the `audit_logs` polymorphic pattern already in the codebase.
- The `ChangeRequestSubject` interface (in `Shared\Domain\Contracts`) gives each subject model a `getChangeRequestSubjectType()` method that returns the canonical string — no magic strings scattered through Actions.

**Alternatives considered and rejected**:
- *Laravel default `morphTo` with class FQN*: Rejected — class renames silently break morphs.
- *Separate tables per subject type* (`vendor_change_requests`, `service_change_requests`): Rejected — duplicates identical schema; makes `EscalateChangeRequestToRejectionAction` polymorphic resolution harder.

---

## Decision 3: Cycle Counter Location

**Question**: Where is the cycle count tracked — on `change_requests` or on the subject?

**Decision**: On `change_requests.cycle_number`. Each `change_requests` row stores the cycle it represents (1, 2, or 3). The max cycle for a subject is determined by querying `MAX(cycle_number)` on its related change requests.

**Rationale**:
- Keeping `cycle_number` on the change request row makes each record self-describing (no join needed to know "this is cycle 2").
- The Actions check `SELECT MAX(cycle_number) FROM change_requests WHERE subject_type=X AND subject_id=Y` before creating a new one — a single indexed query.
- Avoids adding a `change_request_cycle` column to `vendor_profiles` and `services`, keeping those tables focused on their own concerns.

**Index required**: Composite index on `(subject_type, subject_id, cycle_number)` for the MAX() query. For status-filtered queries, use `(subject_type, subject_id, status)`.

**Concurrency protection**: Enforce uniqueness on `(subject_type, subject_id, cycle_number)` to prevent duplicate cycles. Actions must run in transactions with `SELECT MAX(cycle_number) ... FOR UPDATE` or use advisory locks to prevent concurrent cycle creation.

---

## Decision 4: Escalation Trigger Mechanism

**Question**: Does `EscalateChangeRequestToRejectionAction` run automatically (cron/job) or manually (admin-triggered)?

**Decision**: Manual in Phase 1 — admin-triggered. A Filament button "Force Reject (cycle limit reached)" appears only when `cycle_number=3` and `status=resubmitted`. Phase 1.5 adds a scheduled job.

**Rationale**:
- Phase 1 cut-list explicitly defers SLA/auto-escalation.
- Admin-triggered escalation is auditable (actor = admin user) vs. job-triggered (actor = system).
- The Action itself is complete and tested; only the trigger mechanism changes in Phase 1.5 (job calls the same Action).

---

## Decision 5: Filament Checklist Builder UI

**Question**: How does the admin build the checklist of change-request items in Filament?

**Decision**: A `Filament\Forms\Components\Repeater` with three fields per item:
- `TextInput::make('field_path')` — dot-notation path (e.g., `documents.cr_document`)
- `TextInput::make('requested_change_en')->required()` — English description
- `TextInput::make('requested_change_ar')->required()->dir('rtl')` — Arabic description

**Rationale**:
- `Repeater` is in the Filament core (`filament-components.md` §1) — no new packages.
- The three-field structure matches `change_request_items` schema exactly.
- `->dir('rtl')` on the AR field gives correct visual direction in the admin UI.

---

## Decision 6: Notification Dispatch

**Question**: How are `vendor.changes_requested` and `vendor.resubmitted` notifications sent?

**Decision**: Seeded into `notification_templates` table (Communication module). Dispatched via `DispatchNotificationAction` (already implemented in Communication module) using event keys `vendor.changes_requested` and `vendor.resubmitted`. Dispatched inside `DB::afterCommit()` listeners on `ChangeRequestCreated` and `VendorResubmitted` events.

**Rationale**:
- Communication module already owns notification dispatch. Calling it from Identity/Catalog Actions via `DB::afterCommit()` listeners follows Constitution §IX.
- No new notification infrastructure needed — just two new template records.

**Template channels**: `push` (primary), `email` (secondary). SMS deferred to Phase 1.5 campaign.

---

## Decision 7: Vendor-Side API Endpoint

**Question**: Does the vendor need a dedicated API endpoint to view their open change requests?

**Decision**: Yes — `GET /api/v1/vendor/change-requests` (paginated, filterable by status). Owned by `Shared` module routes (`vendor.php`).

**Rationale**:
- The vendor mobile app (Flutter, Phase 1.5) needs to surface change requests across both vendor-profile and service contexts in a single list view.
- Without this endpoint, the Flutter app would have to poll vendor profile + every service separately.
- Read-only endpoint; no idempotency key needed.

---

## Decision 8: `ServiceStatus` — `Rejected` Case

**Observation**: The `ServiceStatus` enum currently has `Draft`, `PendingReview`, `Published`, `Archived` — but NOT `Rejected`. The DB schema doc (`11_DB_Schema.md`) lists `rejected` in the enum. Phase 8.0 will add `Rejected` properly. This feature adds `ChangesRequested`.

**Decision**: This feature adds ONLY `ChangesRequested` to `ServiceStatus`. Adding `Rejected` is Phase 8.0 scope. The migration for this feature's `services.status` alter uses MySQL `MODIFY COLUMN` adding `changes_requested` to the existing 4-value enum. Phase 8.0 will add `rejected` in its own migration.

**Important**: The `ServiceStatus.canTransitionTo()` method must be updated to include `ChangesRequested` transitions in this feature:
- `PendingReview → ChangesRequested` (admin requests changes)
- `ChangesRequested → PendingReview` (vendor resubmits)
