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
- FR traceability per spec.md.
- Schema traceability — flag new tables.
- Phase alignment — propose Phase 8.0.1 extension.
- Never suggest a package not in 10_Package_List.md.
- Never suggest a Phase 2 feature.
- Never contradict docs/specs/02_Tech_Decisions.md locked stack.
---

# Implementation Plan: Service Material Edit Approval Workflow

**Branch**: `035-service-edit-approval` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/035-service-edit-approval/spec.md`

## Summary

Add a **staged-edits** layer over the existing Phase 8.0 service moderation. A vendor editing a `published` service triggers `DetectMaterialServiceChangesAction`; if any material field changed, `SubmitServiceChangeRequestAction` stores the diff in a new `service_change_requests` table (plus per-field items and clarification messages) without mutating the live row. Admins decide via a new `PendingServiceEditsPage` (Filament) with `ApproveServiceChangeRequestAction`, `RejectServiceChangeRequestAction`, and `RequestServiceChangeClarificationAction`. Approval atomically applies the diff to `services` + the matching `service_{type}_details` row. Non-material edits keep the existing direct-apply path. Cross-type branching uses `match(ProductType)`. Pest covers all three product types plus permission/wrong-vendor/locked-service negatives. **Replaces** the destructive `MarkServicePendingReviewForMaterialEditAction` for `published` services — that Action is deprecated by this feature.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12 (per `02_Tech_Decisions.md §1`).
**Primary Dependencies**:
- `filament/filament` v3 (admin UI — already installed)
- `spatie/laravel-translatable` (bilingual EN+AR fields — already installed)
- `spatie/laravel-medialibrary` (gallery diffs — already installed)
- `spatie/laravel-permission` (per-type `service.moderate.{rental|sale|digital}` permissions)
- `spatie/laravel-model-states` (status enum on `service_change_requests`)
- `brick/money` (price diff handling)
- `laravel/scout` + Meilisearch (re-index on approval)
- `bezhansalleh/filament-shield` (regenerate after new Resource)
No new packages required (`10_Package_List.md` unchanged).
**Storage**: MySQL 8 / MariaDB 11, utf8mb4 / utf8mb4_unicode_ci. Three new tables (`service_change_requests`, `service_change_request_items`, `service_change_request_messages`). Append-only on content; only `status`, `decided_by`, `decided_at`, `admin_note_*` may update.
**Testing**: Pest with `tests/Feature/Modules/Catalog/ServiceChangeRequest/*` and `tests/Unit/Modules/Catalog/Policies/MaterialFieldRegistryTest.php`. Groups: `catalog`, `rental`, `sale`, `digital`, `moderation`.
**Target Platform**: Laravel API (vendor portal) + Filament admin panel at `/admin`. No public unauthenticated endpoints.
**Project Type**: Backend (modular monolith) per repo convention.
**Performance Goals**:
- Material-field detection runs on every save of a published service; must complete in < 50 ms p95 (single in-memory `match` + array_diff over loaded model).
- Pending Service Edits page loads in < 800 ms p95 with eager-loaded service + vendor relations.
- Approval transaction completes in < 1500 ms p95 including Scout re-index queue dispatch.
**Constraints**:
- Bilingual EN+AR mandatory for admin reason and clarification messages (`04_Bilingual_Spec.md`).
- Money always integer minor units + currency (`03 Money Discipline` from constitution).
- Domain events fire via `DB::afterCommit` only.
- One pending edit per service at a time; enforced by partial unique index.
- All decisions written to `audit_logs` (append-only).
**Scale/Scope**:
- Estimated 1k–5k pending change requests at any time once Phase 1 vendors are onboarded.
- New tables grow ~linearly with material edits (≈10k rows/month after launch); partition not needed in Phase 1.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle (from `.specify/memory/constitution.md`) | Status | Notes |
|---|---|---|
| I. Modular Monolith — Catalog module owns these tables and Actions | ✅ Pass | Lives under `app/Modules/Catalog/` |
| II. Three Product Types — `match($enum)` for branching | ✅ Pass | `MaterialFieldRegistry`, `ApplyChangeToService` use `match(ProductType)` |
| III. Money Discipline — integer minor units, `Brick\Money` | ✅ Pass | Price diffs reference `base_price_minor` + `base_price_currency` columns |
| IV. Bilingual EN+AR mandatory | ✅ Pass | Admin reason + clarification messages both have `_en` and `_ar` JSON keys |
| V. Append-only ledger discipline | ✅ Pass | `service_change_request_messages` is append-only; main table allows only status / decision column updates |
| VI. Domain events fire after commit | ✅ Pass | `DB::afterCommit(fn () => event(...))` in every Action |
| VII. `audit_logs` on every state transition + admin action | ✅ Pass | Listener `WriteServiceChangeRequestAuditListener` |
| VIII. Idempotency on payment-mutating endpoints | ⚪ N/A | This feature does not mutate payments |
| IX. Filament resources in module's `Filament/Resources/` | ✅ Pass | `app/Modules/Catalog/Filament/Pages/PendingServiceEditsPage.php` |
| X. No package not in `10_Package_List.md` | ✅ Pass | No new packages |
| XI. No Phase 2 features | ✅ Pass | Stale-edit auto-reminders deferred to Phase 1.5; manual filter only in Phase 1 |
| XII. Phase alignment cited | ✅ Pass | Phase 8.0.1 proposed (backfill needed) |

No violations → **Complexity Tracking** section intentionally left empty.

## Project Structure

### Documentation (this feature)

```text
specs/035-service-edit-approval/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   ├── vendor-submit-edit.md
│   ├── admin-decide.md
│   └── events.md
└── tasks.md             # Phase 2 output (NOT created here)
```

### Source Code (repository root)

```text
app/Modules/Catalog/
├── Domain/
│   ├── Enums/
│   │   └── ServiceChangeRequestStatus.php          # NEW (pending, awaiting_clarification, approved, rejected, cancelled_*)
│   ├── Events/
│   │   ├── ServiceChangeRequestSubmitted.php       # NEW
│   │   ├── ServiceChangeRequestApproved.php        # NEW
│   │   ├── ServiceChangeRequestRejected.php        # NEW
│   │   ├── ServiceChangeRequestClarificationRequested.php  # NEW
│   │   └── ServiceChangeRequestClarificationReplied.php    # NEW
│   ├── Models/
│   │   ├── ServiceChangeRequest.php                # NEW
│   │   ├── ServiceChangeRequestItem.php            # NEW
│   │   └── ServiceChangeRequestMessage.php         # NEW
│   ├── Policies/
│   │   ├── MaterialFieldRegistry.php               # NEW (per-ProductType material field set)
│   │   └── ServiceEditApprovalPolicy.php           # NEW (constants: MAX_CLARIFICATIONS=3)
│   └── States/
│       └── ServiceChangeRequestState.php           # NEW (spatie/laravel-model-states)
├── Application/
│   ├── Actions/
│   │   ├── DetectMaterialServiceChangesAction.php  # NEW (returns ServiceFieldDiff DTO)
│   │   ├── SubmitServiceChangeRequestAction.php    # NEW
│   │   ├── ApproveServiceChangeRequestAction.php   # NEW
│   │   ├── RejectServiceChangeRequestAction.php    # NEW
│   │   ├── RequestServiceChangeClarificationAction.php  # NEW
│   │   ├── ReplyToServiceChangeClarificationAction.php  # NEW (vendor reply)
│   │   ├── CancelServiceChangeRequestAction.php    # NEW (vendor suspend / service archive paths)
│   │   ├── ApplyServiceChangeToLiveAction.php      # NEW (atomic apply step — match(ProductType))
│   │   └── (deprecate) MarkServicePendingReviewForMaterialEditAction.php  # kept for non-published edge cases until migration sunset
│   ├── DTOs/
│   │   ├── ServiceFieldDiff.php                    # NEW
│   │   ├── SubmitServiceChangeRequestDTO.php       # NEW
│   │   └── DecideServiceChangeRequestDTO.php       # NEW
│   └── Listeners/
│       └── WriteServiceChangeRequestAuditListener.php  # NEW
├── Filament/
│   ├── Pages/
│   │   └── PendingServiceEditsPage.php             # NEW (table + diff modal)
│   └── Widgets/
│       └── PendingServiceEditsBadgeWidget.php      # NEW (nav badge)
├── Http/
│   ├── Controllers/Vendor/
│   │   ├── VendorServiceChangeRequestController.php  # NEW (show, replyClarification, cancel)
│   │   └── (edits to existing VendorServiceController to route material edits through SubmitServiceChangeRequestAction)
│   ├── Controllers/Admin/
│   │   └── AdminServiceChangeRequestController.php  # NEW (decide endpoints — admin SPA-fallback; Filament covers primary UI)
│   ├── Requests/Vendor/
│   │   └── ReplyServiceChangeClarificationRequest.php  # NEW
│   ├── Requests/Admin/
│   │   ├── ApproveServiceChangeRequest.php         # NEW
│   │   ├── RejectServiceChangeRequest.php          # NEW (bilingual reason validation)
│   │   └── RequestServiceChangeClarificationRequest.php  # NEW
│   └── Resources/
│       ├── ServiceChangeRequestResource.php        # NEW (vendor + admin shared shape)
│       └── ServiceChangeRequestItemResource.php    # NEW
└── Database/
    ├── Migrations/
    │   ├── 2026_05_16_000010_create_service_change_requests_table.php
    │   ├── 2026_05_16_000011_create_service_change_request_items_table.php
    │   └── 2026_05_16_000012_create_service_change_request_messages_table.php
    └── Factories/
        ├── ServiceChangeRequestFactory.php
        ├── ServiceChangeRequestItemFactory.php
        └── ServiceChangeRequestMessageFactory.php

tests/
├── Feature/Modules/Catalog/ServiceChangeRequest/
│   ├── SubmitMaterialEditTest.php          # all 3 types: live row unchanged, duplicate-pending 409
│   ├── ApproveEditTest.php                 # all 3 types: atomic apply, audit, event, Scout re-index
│   ├── RejectEditTest.php                  # bilingual reason required, live row untouched
│   ├── RequestClarificationTest.php        # round cap=3, status flow
│   ├── VendorReplyClarificationTest.php
│   ├── NonMaterialEditBypassTest.php
│   ├── WrongVendorAuthTest.php             # 403 on cross-vendor access
│   ├── PublishedServiceSafetyTest.php      # discovery API returns old values while pending
│   ├── ArchivedServiceCancellationTest.php
│   └── ConcurrentDecisionConflictTest.php  # 409 on second admin click
└── Unit/Modules/Catalog/Policies/
    └── MaterialFieldRegistryTest.php       # exhaustive per-type field membership
```

**Structure Decision**: Single-project modular monolith. All new code lives under `app/Modules/Catalog/` (this is a Catalog concern — the service lifecycle is catalog's domain). Filament admin lives inside the same module per the Filament-resource discovery convention. No frontend code in this repo (vendor portal is Next.js in a sister repo).

## Complexity Tracking

> Constitution Check passes with no violations. Section intentionally empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
