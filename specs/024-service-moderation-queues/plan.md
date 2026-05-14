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
- FR traceability: existing PRD coverage cited from Phase 8.0 and Admin Journey; local FR-EXT items remain until PRD backfill.
- Schema traceability: this feature uses existing `services` lifecycle fields from `11_DB_Schema.md`; current migrations show schema drift that must be corrected.
- Phase alignment: Phase 8.0 - Admin Service Moderation & Publish Workflow.
- No new packages.
- No Phase 2 features.
- No API endpoints.
---

# Implementation Plan: Service Moderation Actions + Per-Type Queues

**Branch**: `024-service-moderation-queues` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)  
**Input**: Feature specification from `/specs/024-service-moderation-queues/spec.md`

## Summary

Wire the Catalog admin service moderation workflow so rental, sale, and digital services can be approved, rejected with bilingual notes, optionally sent through the accepted changes-requested workflow, and bulk moderated from type-specific pending queues. The implementation reuses the existing Catalog module, existing per-type service resources, the `services` lifecycle fields from the locked schema, `ADR-0013-admin-service-moderation.md`, and ADR-0018's shared change-request infrastructure. The plan also records a blocking schema reconciliation: the locked schema includes `rejected`, `moderation_notes`, `moderated_at`, and `moderated_by`, while current Catalog migrations do not fully match that schema.

## Technical Context

**Language/Version**: PHP 8.3+ on Laravel 12  
**Primary Dependencies**: Filament v3, Spatie Laravel Permission/Shield, Spatie Laravel Translatable, Spatie Media Library, Spatie Activitylog, Laravel Scout/Meilisearch for published-service indexing  
**Storage**: MySQL 8 / MariaDB 11, existing `services`, `change_requests`, `change_request_items`, `audit_logs`, and `idempotency_keys` tables; no new tables  
**Testing**: Pest, Pest Laravel, PHPStan/Larastan, Laravel Pint  
**Target Platform**: Laravel backend and Filament `/admin` panel  
**Project Type**: Modular monolith backend/admin application  
**Performance Goals**: Pending queues load via indexed `product_type + status` filters; sidebar badges use one count query per product type; bulk approve supports 50 services in one operation  
**Constraints**: No new packages; no frontend framework; no public or internal API endpoints; no new moderation history table; money stays integer minor units; type-aware behavior must cover rental, sale, and digital; domain events fire after commit  
**Scale/Scope**: Three service resources, three pending queue pages, row actions, bulk actions, translations, schema drift correction if current migrations lack locked service moderation columns, and feature tests across all product types

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence / Action |
|---|---|---|
| I. Modular Monolith | PASS | All work remains in `app/Modules/Catalog`; shared change requests are used through existing Shared contracts/models already introduced by Feature 020. |
| II. Three Product Types | PASS | Rental, sale, and digital each receive explicit queue, row-action, bulk-action, and test coverage. Cross-type shared actions must use `match(ProductType)` when product-type-specific branching is needed. |
| III. Money Discipline | PASS | Price is a material-edit trigger only. No money arithmetic is introduced; `base_price_minor` remains integer minor units. |
| IV. Bilingual EN+AR | PASS | Reject and request-edit reasons require EN+AR; labels and notifications require `catalog.php` translations in both locales. |
| V. Append-Only Tables | PASS | No append-only tables are mutated beyond existing audit infrastructure. No new moderation log table is introduced. |
| VI. ADR Before Code | PASS | `ADR-0013-admin-service-moderation.md` covers Phase 8.0 service moderation; ADR-0004 covers Catalog lifecycle baseline; ADR-0018 covers changes-requested workflow. |
| VII. Test-First Critical Paths | PASS | Pest tests must cover all three product types, transitions, events, bilingual notes, badges, queues, material edits, and 50-row bulk approve. |
| VIII. Idempotency | PASS | No new HTTP endpoints. Existing request-change HTTP actions keep idempotency; Filament row/bulk actions are admin UI actions and must be transactionally safe. |
| IX. Domain Events After Commit | PASS | Publish/reject/request-edits lifecycle events must be emitted through `DB::afterCommit()` or queued listeners after transaction commit. |
| X. Vendor Approval Gate | PASS | This feature does not change vendor approval/type approval gates. |
| XI. Document Storage | PASS | Service media remains Spatie Media Library on configured S3-compatible storage. |

**Gate Result**: PASS FOR PLANNING. BLOCKING IMPLEMENTATION CONDITION: align current migrations to the locked `services` schema before wiring Filament decisions.

## Project Structure

### Documentation (this feature)

```text
specs/024-service-moderation-queues/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- admin-ui.md
|-- checklists/
|   `-- requirements.md
`-- tasks.md              # Created by /speckit.tasks, not by /speckit.plan
```

### Source Code (repository root)

```text
app/Modules/Catalog/
|-- Application/
|   `-- Actions/
|       |-- PublishServiceAction.php
|       |-- RejectServiceAction.php
|       |-- ArchiveServiceAction.php
|       |-- RequestRentalServiceChangesAction.php
|       |-- RequestSaleServiceChangesAction.php
|       |-- RequestDigitalServiceChangesAction.php
|       `-- MarkServicePendingReviewForMaterialEditAction.php
|-- Database/
|   `-- Migrations/
|       `-- *_align_services_moderation_columns.php
|-- Domain/
|   |-- Enums/
|   |   `-- ServiceStatus.php
|   |-- Events/
|   |   |-- ServicePublished.php
|   |   |-- ServiceRejected.php
|   |   |-- ServiceArchived.php
|   |   `-- ServiceReturnedToReview.php
|   `-- Models/
|       `-- Service.php
|-- Filament/
|   |-- Actions/
|   |   |-- ApproveServiceAction.php
|   |   |-- RejectServiceAction.php
|   |   `-- RequestServiceEditsAction.php
|   `-- Resources/
|       |-- RentalServiceResource.php
|       |-- RentalServiceResource/Pages/PendingRentalServicesPage.php
|       |-- SaleServiceResource.php
|       |-- SaleServiceResource/Pages/PendingSaleServicesPage.php
|       |-- DigitalServiceResource.php
|       `-- DigitalServiceResource/Pages/PendingDigitalServicesPage.php
`-- Resources/
    `-- lang/
        |-- en/catalog.php
        `-- ar/catalog.php

tests/Feature/Modules/Catalog/
|-- ServiceModerationActionTest.php
|-- ServiceModerationQueueTest.php
|-- ServiceModerationBulkActionTest.php
`-- ServiceMaterialEditGateTest.php
```

**Structure Decision**: Use the existing Catalog modular monolith structure. Keep business mutations in Application actions; keep Filament action classes as UI adapters that delegate to Application actions. Dedicated queue pages live under each resource's `Pages/` directory and are registered through the resource `getPages()`.

## Complexity Tracking

| Violation / Risk | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Schema drift in `services` migrations | Locked schema includes `rejected`, `moderation_notes`, `moderated_at`, `moderated_by`; current migrations do not fully include them. | Treating those fields as "already existing" would fail during implementation/tests. A targeted alignment migration preserves the locked schema. |

## Phase 0: Research Output

See [research.md](./research.md).

## Phase 1: Design Output

See [data-model.md](./data-model.md), [contracts/admin-ui.md](./contracts/admin-ui.md), and [quickstart.md](./quickstart.md).

## Post-Design Constitution Re-Check

| Principle | Status | Result |
|---|---|---|
| Modular Monolith | PASS | All planned source paths remain under Catalog, with existing Shared change-request integration only where already accepted. |
| Three Product Types | PASS | Design documents define rental, sale, digital queue and test coverage. |
| Money | PASS | No new money arithmetic; price only drives material-edit review. |
| Bilingual | PASS | EN+AR reason fields and translations are part of UI contract. |
| Append-Only | PASS | No new append-only table or forbidden mutation. |
| ADR | PASS | Phase 8.0 service moderation ADR exists and ADR-0018 remains the companion for request edits. |
| Tests | PASS | Quickstart and data model require per-type Pest coverage. |
| Idempotency | PASS | No new endpoints; existing API idempotency remains unchanged. |
| Events After Commit | PASS | Events are explicitly after-commit in design. |
| Vendor Gate | PASS | No changes to vendor approval. |
| Storage | PASS | Existing Media Library storage only. |

## Phase 2 Preview

`/speckit.tasks` should generate tasks in this order:

1. Schema reconciliation for `services` moderation columns/status if needed.
2. ServiceStatus enum and Service model casts/fillable/scopes.
3. Application actions for publish, reject, archive, material-edit re-review.
4. Filament action adapters and per-type resource wiring.
5. Pending queue pages and navigation badges.
6. Bulk actions.
7. EN/AR translations.
8. Pest feature tests and quality commands.
