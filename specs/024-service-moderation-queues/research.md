# Research: Service Moderation Actions + Per-Type Queues

## Decision 1: ADR Coverage

**Decision**: Use `docs/adr/ADR-0013-admin-service-moderation.md` as the Phase 8.0 service moderation ADR. Use `docs/adr/ADR-0018-changes-requested-workflow.md` only for the request-edits path.

**Rationale**: `docs/specs/09_Phasing_Plan.md` Phase 8.0 requires this ADR by filename. ADR-0004 covers the broader Catalog module and ADR-0018 covers shared changes-requested infrastructure, but the approve/reject/publish queues need their own explicit Phase 8.0 decision record.

**Alternatives considered**:
- Treat ADR-0018 as full replacement: rejected because ADR-0018 covers changes-requested workflow, not the whole publish/reject moderation UI.
- Create a new unrelated ADR number: rejected because Phase 8.0 references an exact filename.
- Leave the decision only in the feature plan: rejected because Phase 8.0 requires an ADR before implementation.

## Decision 2: Schema Drift Handling

**Decision**: Add a targeted Catalog migration during implementation if the current database migrations lack the locked `services` moderation shape: `status` with `rejected`, JSON `moderation_notes`, nullable `moderated_at`, and nullable `moderated_by`.

**Rationale**: `docs/specs/11_DB_Schema.md` documents those fields as existing. Current code search shows the base `services` migration has `draft`, `pending_review`, `published`, `archived`, while the changes-requested migration adds `changes_requested` but still omits `rejected` and moderation metadata. The UI cannot persist reject reasons or moderator metadata without aligning current migrations to the locked schema.

**Alternatives considered**:
- Build UI against non-existent columns: rejected because tests and runtime would fail.
- Create a new moderation table: rejected by Phase 8.0, which says no new moderation tables in Phase 1.
- Store notes in `change_requests` only: rejected because reject-with-notes is explicitly represented on `services.moderation_notes` in the locked schema.

## Decision 3: Action Layering

**Decision**: Use Application actions for actual state changes and Filament action classes as UI adapters.

**Rationale**: Project rules require business logic outside models and thin UI/controller layers. Filament table actions should validate UI forms and delegate to actions such as `PublishServiceAction`, `RejectServiceAction`, and `ArchiveServiceAction`. The requested `app/Modules/Catalog/Filament/Actions/*` classes can return configured Filament actions while staying thin.

**Alternatives considered**:
- Put mutation closures directly in each resource: rejected because it duplicates workflow rules across three resources.
- Put methods on `Service`: rejected because models may only contain relationships, casts, and scopes.
- Build one polymorphic resource: rejected because Phase 8.0 and Admin Journey require separate queues per product type.

## Decision 4: Request Edits Integration

**Decision**: Reuse ADR-0018's changes-requested workflow and existing per-type request-change actions when available. Keep request-edits hidden if the service-specific workflow is unavailable or invalid for the current service status.

**Rationale**: The repo already contains `RequestRentalServiceChangesAction`, `RequestSaleServiceChangesAction`, and `RequestDigitalServiceChangesAction` in progress. They create shared `change_requests` records and transition services to `changes_requested`. Reusing them avoids introducing a second edit-request concept.

**Alternatives considered**:
- Model request edits as `rejected + moderation_notes`: rejected for this feature because ADR-0018 is accepted and the user explicitly wants request edits if adopted.
- Build a separate service edit-request table: rejected by ADR-0018 and Phase 8.0 no-new-moderation-table scope.

## Decision 5: Pending Queue Design

**Decision**: Add one pending page under each existing service resource, with query scope fixed to product type and `pending_review`. Keep the main resource index available for broader browsing and filters.

**Rationale**: This satisfies the "baked in, not editable filter chip" requirement without removing the existing resource list. Per-resource pages keep Filament routing and permissions aligned with the existing service resources.

**Alternatives considered**:
- Add only a status filter on the existing index: rejected because the prompt requires a dedicated pending queue with a non-removable scope.
- Build one dashboard page with tabs: rejected because Admin Journey specifies separate queues per product type.

## Decision 6: Bulk Moderation Semantics

**Decision**: Bulk approve/reject/archive must run through the same Application actions as row actions and should process selected services in one database transaction where practical. Bulk approval must support at least 50 services.

**Rationale**: Shared actions prevent row/bulk drift. A transaction prevents silent partial state changes for the normal 50-row scope. If an individual service becomes ineligible, the action should report a conflict rather than bypass transition rules.

**Alternatives considered**:
- Direct bulk update query: rejected because it would skip per-service events, notes, and transition validation.
- One queued job per service: rejected for Phase 1 admin UX because the acceptance criteria expects immediate queue count and status updates.

## Decision 7: Material Edit Gate

**Decision**: Treat changes to price, category, name/title, short or long description, and customer-facing media as material. A material edit to a published service returns it to `pending_review`; non-material edits do not.

**Rationale**: This matches Phase 8.0 and the feature spec. The gate belongs in the update flow/action layer, not in model business logic. Media edits require explicit handling because gallery changes may not pass through normal scalar field dirty checks.

**Alternatives considered**:
- Return every edit to review: rejected because it creates unnecessary moderation load for harmless admin/vendor metadata changes.
- Allow media edits without review: rejected because images are customer-facing and moderation-sensitive.

## Decision 8: Event Timing

**Decision**: Publish, reject, archive, request-edit, and return-to-review lifecycle events must be dispatched only after database commit.

**Rationale**: Constitution Principle IX requires events after commit. This prevents downstream search indexing or notification listeners from observing rolled-back service states.

**Alternatives considered**:
- Dispatch events inline before transaction commit: rejected by constitution.
- Skip events for bulk actions: rejected because bulk operations must preserve row-action lifecycle behavior.
