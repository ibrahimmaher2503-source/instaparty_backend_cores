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
- FR traceability: If the feature maps to existing PRD coverage → cite specific FR numbers from 01_PRD.md. If the feature is NEW or extends beyond the PRD → define local requirement numbers prefixed FR-EXT-NNN and add a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: If using an existing table → cite its name from 11_DB_Schema.md. If this feature introduces NEW tables → list them explicitly with a "⚠️ NEW TABLE — not yet in 11_DB_Schema.md" marker.
- Phase alignment: If the feature belongs to an existing phase → cite the Phase ID from 09_Phasing_Plan.md. If the feature is new work not yet phased → propose a Phase ID extension (e.g., Phase 1.X) and add a "⚠️ PHASE BACKFILL NEEDED" note.
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

# Implementation Plan: Admin Changes-Requested Workflow

**Branch**: `020-admin-changes-request` | **Date**: 2026-05-04 | **Spec**: [spec.md](spec.md)

**PRD Coverage**:
- FR-29 (`01_PRD.md`) — admin reviews/approves vendors and services
- Admin Journey step 5 ("Reject / request more info") — `08_Admin_Journey.md`
- Admin Journey step 8 ("Reject / request edit") — `08_Admin_Journey.md`
- FR-EXT-001 through FR-EXT-009 (spec.md) ⚠️ BACKFILL NEEDED

**Phase Alignment**:
- Day 1 → **Phase 1.1** Vendor Onboarding + Approval (`09_Phasing_Plan.md §PHASE 1.1`)
- Day 2 → **Phase 8.0** Admin Service Moderation (`09_Phasing_Plan.md §PHASE 8.0`)

**ADR**: `docs/adr/ADR-0018-changes-requested-workflow.md` (must be written and Accepted before any migration)

---

## Summary

Adds a "Request Changes" resolution path to both the vendor onboarding approval queue (Phase 1.1) and the service moderation queue (Phase 8.0). Instead of binary approve/reject, admins can build a bilingual itemized checklist of corrections, which the vendor sees, addresses, and resubmits. The system tracks cycles (max 3) and auto-escalates to rejection if the limit is exceeded.

**Technical approach:**
- Two new shared tables (`change_requests`, `change_request_items`) owned by the `Shared` module with polymorphic subject linking to `vendor_profiles` or `services`
- `ApprovalStatus` enum gains `ChangesRequested` case (Identity module)
- `ServiceStatus` enum gains `ChangesRequested` case (Catalog module)
- Actions split by domain: `RequestVendorChangesAction` in Identity, `RequestServiceChangesAction` in Catalog; `EscalateChangeRequestToRejectionAction` in Shared (polymorphic)
- Filament "Request Changes" button wired to a Repeater-based bilingual form in existing Resources
- Notifications via existing Communication module templates

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**: Filament v3, spatie/laravel-translatable, spatie/laravel-activitylog, spatie/laravel-permission
**Storage**: MySQL 8 — two new tables in Shared module migrations
**Testing**: Pest (Feature tests in `tests/Feature/Modules/`)
**Target Platform**: Laravel API + Filament admin panel
**Project Type**: Modular monolith — module extensions (Shared, Identity, Catalog)
**Performance Goals**: Change-request creation < 500ms p95; no hot-path involvement (admin-only action)
**Constraints**: Bilingual EN+AR required on all item text; 3-cycle hard cap; audit trail permanent
**Scale/Scope**: Admin-only write path; vendor read path (checklist display); low write volume

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design — all pass.*

| Principle | Status | Evidence |
|---|---|---|
| I — Modular monolith | ✅ Pass | Shared module owns infrastructure; Identity/Catalog own domain Actions; no cross-module Model imports |
| II — Three product types | ✅ Pass | `RequestServiceChangesAction` is cross-type (uses `$service->product_type`). Filament buttons added to all three per-type Resources. Pest covers rental/sale/digital. |
| III — Money discipline | ✅ N/A | No money columns in this feature |
| IV — Bilingual EN+AR | ✅ Pass | `requested_change_en` + `requested_change_ar` both required (non-nullable, validated empty-string). Notification templates have EN+AR. |
| V — Append-only tables | ✅ Pass | `change_requests` and `change_request_items` are NOT append-only (status transitions required). Audit trail goes to `audit_logs` (append-only, via activitylog). |
| VI — ADR before code | ✅ Pass | ADR-0018 must be written and Accepted before migration files are created |
| VII — Test-first | ✅ Pass | Pest test cases specified in spec; written same day as Actions |
| VIII — Idempotency | ✅ Pass | `POST .../change-requests` and `POST .../resubmit` require `Idempotency-Key` header; checked via `idempotency_keys` table |
| IX — Domain events afterCommit | ✅ Pass | `ChangeRequestCreated`, `VendorResubmitted`, `ServiceResubmitted`, `ChangeRequestEscalated` fire via `DB::afterCommit()` |
| X — Vendor two-step gate | ✅ Pass | `RequestVendorChangesAction` checks profile exists; `VendorResubmitAfterChangesAction` checks `approval_status === ChangesRequested` before accepting |
| XI — Document storage | ✅ N/A | No new document-type storage; re-uploaded vendor documents use existing spatie/laravel-medialibrary collections on S3 |

---

## Project Structure

### Documentation (this feature)

```text
specs/020-admin-changes-request/
├── spec.md               # Feature specification
├── plan.md               # This file
├── research.md           # Phase 0 — module ownership + design decisions
├── data-model.md         # Phase 1 — entity schema, state machines, enums
├── contracts/
│   └── api.md            # Phase 1 — API endpoint contracts
├── quickstart.md         # Phase 1 — local dev + test guide
├── checklists/
│   └── requirements.md   # Quality checklist
└── tasks.md              # Phase 2 — generated by /speckit.tasks
```

### Source Code (repository root)

```text
app/Modules/Shared/
├── Domain/
│   ├── Models/
│   │   ├── ChangeRequest.php          (NEW)
│   │   └── ChangeRequestItem.php      (NEW)
│   ├── Enums/
│   │   ├── ChangeRequestStatus.php    (NEW)
│   │   ├── ChangeRequestItemStatus.php (NEW)
│   │   └── ChangeRequestSubjectType.php (NEW)
│   └── Contracts/
│       └── ChangeRequestSubject.php   (NEW — interface implemented by VendorProfile + Service)
├── Application/
│   └── Actions/
│       └── EscalateChangeRequestToRejectionAction.php (NEW)
└── Database/
    └── Migrations/
        ├── 2026_05_04_000001_create_change_requests_table.php (NEW)
        └── 2026_05_04_000002_create_change_request_items_table.php (NEW)

app/Modules/Identity/
├── Domain/
│   └── Enums/
│       └── ApprovalStatus.php         (MODIFY — add ChangesRequested case)
├── Application/
│   └── Actions/
│       ├── RequestVendorChangesAction.php        (NEW)
│       └── VendorResubmitAfterChangesAction.php  (NEW)
├── Filament/
│   └── Resources/
│       └── VendorProfileResource.php             (MODIFY — add "Request Changes" action)
└── Routes/
    ├── admin.php                                  (MODIFY — add POST change-requests route)
    └── vendor.php                                 (MODIFY — add POST resubmit route)

app/Modules/Catalog/
├── Domain/
│   └── Enums/
│       └── ServiceStatus.php                     (MODIFY — add ChangesRequested case)
├── Application/
│   └── Actions/
│       ├── RequestServiceChangesAction.php        (NEW)
│       └── VendorResubmitServiceAfterChangesAction.php (NEW)
├── Filament/
│   └── Resources/
│       ├── RentalServiceResource.php              (MODIFY — add "Request Changes" action)
│       ├── SaleServiceResource.php                (MODIFY — add "Request Changes" action)
│       └── DigitalServiceResource.php             (MODIFY — add "Request Changes" action)
└── Routes/
    ├── admin.php                                  (MODIFY — add POST change-requests route)
    └── vendor.php                                 (MODIFY — add POST resubmit route)

database/migrations/                              (⚠️ Shared module migrations auto-loaded via ServiceProvider)

tests/
├── Feature/
│   └── Modules/
│       ├── Identity/
│       │   └── VendorChangesRequestedTest.php     (NEW)
│       └── Catalog/
│           └── ServiceChangesRequestedTest.php    (NEW)
```

**Structure Decision**: Shared module owns the polymorphic infrastructure (models, enums, migrations). Identity and Catalog own the domain-specific Actions and Filament UI. This follows the cross-module contract pattern from Constitution §I — neither Identity nor Catalog imports the other's models; both call Shared models which are, by design, accessible across all modules.

---

## Phase 0: Research

See `research.md` for full details. Key resolved decisions:

| Question | Decision | Rationale |
|---|---|---|
| Where do `change_requests` tables live? | `Shared` module | Polymorphic subject spans Identity + Catalog; neither owns the other |
| How do Actions in Identity/Catalog access `ChangeRequest`? | Direct Shared model access (Shared is cross-cutting by design) | Shared ≠ foreign module; it is the common foundation |
| Is escalation one Action or two (per subject type)? | One polymorphic `EscalateChangeRequestToRejectionAction` in Shared | Same business rule (cycle=3 → reject) regardless of subject type |
| How does Filament "Request Changes" build the checklist? | `Filament\Forms\Components\Repeater` with `TextInput` (EN) + `TextInput` (AR) + `TextInput` (field_path) | Repeater is in the locked package list; no new dependencies |
| How are new notification templates dispatched? | Existing `Communication` module `DispatchNotificationAction` — new template keys `vendor.changes_requested` and `vendor.resubmitted` seeded | Communication module owns all notification dispatch per module design |
| Does the vendor API need a new endpoint for viewing change requests? | Yes: `GET /api/v1/vendor/change-requests?status=open` (paginated) | Vendor needs to see their open checklist without polling vendor profile endpoint |

---

## Phase 1: Design

See `data-model.md` for entity schema and state machines.
See `contracts/api.md` for full endpoint contracts.
See `quickstart.md` for local development guide.

### Constitution Check re-evaluation (post-design)

All gates still pass. Notable confirmations:

- **Bilingual (IV)**: `RequestVendorChangesFormRequest` and `RequestServiceChangesFormRequest` both validate that every item in the `items[]` array has non-empty `requested_change_en` and `requested_change_ar`.
- **Idempotency (VIII)**: `POST /admin/vendor-profiles/{id}/change-requests` and `POST /admin/services/{id}/change-requests` both check `idempotency_keys` at the top of their Actions. Same for the vendor resubmit endpoints.
- **Three product types (II)**: `RequestServiceChangesAction` accepts a `Service` model — it is cross-type (works for rental/sale/digital) and does not branch on product type internally. Filament adds the button to all three per-type Resources identically.
- **ADR (VI)**: ADR-0018 must be written and status set to `Accepted` before the first migration is run. The first task in `tasks.md` will be the ADR.

---

## Cut-list (from spec)

Deferred to Phase 1.5 per spec decision:
- Auto-suggestion rule library (common change reason templates)
- SLA timer on vendor side ("respond within N days") + auto-escalation by cron

These are explicitly out of scope for this implementation.
