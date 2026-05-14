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
- FR traceability: If the feature maps to existing PRD coverage -> cite specific FR numbers from 01_PRD.md. If the feature is NEW or extends beyond the PRD -> define local requirement numbers prefixed FR-EXT-NNN and add a "BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: If using an existing table -> cite its name from 11_DB_Schema.md. If this feature introduces NEW tables -> list them explicitly with a "NEW TABLE - not yet in 11_DB_Schema.md" marker.
- Phase alignment: If the feature belongs to an existing phase -> cite the Phase ID from 09_Phasing_Plan.md. If the feature is new work not yet phased -> propose a Phase ID extension (e.g., Phase 1.X) and add a "PHASE BACKFILL NEEDED" note.
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- No public or internal API endpoints are added by this feature. API registry and Bruno collection updates are not required unless planning later adds an HTTP route.
---

# Feature Specification: Service Moderation Actions + Per-Type Queues

**Feature Branch**: `024-service-moderation-queues`  
**Created**: 2026-05-04  
**Status**: Draft  
**Phase**: Phase 8.0 - Admin Service Moderation & Publish Workflow  
**Input**: User description: "Service Moderation Actions + Per-Type Queues"

## Traceability & Scope

- **PRD coverage**: PRD admin workflow says admins approve service additions or material service edits; Phase 8.0 cites FR-19, FR-22, FR-29 plus Admin Journey steps 6-8 for service moderation. Because `01_PRD.md` does not have a dedicated numbered FR for the moderation UI itself, this spec uses local `FR-EXT-*` requirements and marks PRD backfill where needed.
- **Admin Journey coverage**: `docs/specs/08_Admin_Journey.md` section 3 requires separate service moderation queues per product type with "Approve & publish" and "Reject / request edits" decisions.
- **Schema traceability**: Uses existing `services` columns from `docs/specs/11_DB_Schema.md`: `status`, `moderation_notes`, `moderated_at`, and `moderated_by`. No new tables are introduced.
- **Phase alignment**: `docs/specs/09_Phasing_Plan.md` Phase 8.0 explicitly requires moderation queue views for rental, sale, and digital services, bulk approve/reject/archive, EN+AR rejection notes, and material-edit re-review.
- **ADR coverage**: `docs/adr/ADR-0013-admin-service-moderation.md` covers Phase 8.0 service moderation. `ADR-0018-changes-requested-workflow.md` remains the accepted companion ADR for structured request-edits.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Moderate One Pending Service (Priority: P1)

An admin reviewing newly submitted or materially edited services needs clear row-level decisions for each service type: approve and publish, reject with bilingual notes, or request edits when the structured changes-requested workflow is available.

**Why this priority**: This is the core operations workflow. Today admins can only edit status manually, which bypasses auditability, bilingual notes, and consistent service lifecycle handling.

**Independent Test**: Create one pending service for each product type, open each type-specific admin service list, approve one service, reject one service with EN+AR reason, and request edits for one service. Verify each decision updates status, moderator, timestamp, and notes or change-request state as applicable.

**Acceptance Scenarios**:

1. **Given** a rental service is in `pending_review`, **When** an admin chooses "Approve & Publish", **Then** the service becomes `published`, `moderated_by` is set to the admin, `moderated_at` is set, and the publish event is recorded for downstream listeners.
2. **Given** a sale service is in `pending_review`, **When** an admin chooses "Reject" and enters both English and Arabic reason text, **Then** the service becomes `rejected`, bilingual moderation notes are persisted, and moderator metadata is set.
3. **Given** a digital service is in `pending_review`, **When** an admin attempts to reject it with only one language completed, **Then** the decision is blocked and the admin sees that both English and Arabic notes are required.
4. **Given** the changes-requested workflow is available, **When** an admin chooses "Request Edits" on a pending service, **Then** the service enters the changes-requested path with bilingual correction guidance and can later be resubmitted to `pending_review`.

---

### User Story 2 - Work Dedicated Pending Queues Per Product Type (Priority: P1)

An admin needs separate pending-review queues for rental, sale, and digital services so moderation work can be assigned and scanned by product type without accidentally mixing incompatible service requirements.

**Why this priority**: Rental, sale, and digital services have different review criteria. Separate queues reduce operator mistakes and make backlog counts actionable.

**Independent Test**: Seed pending, published, rejected, and archived services across all three product types. Open each pending queue and verify it shows only `pending_review` services for its own product type, with no removable filter that reveals other statuses.

**Acceptance Scenarios**:

1. **Given** rental, sale, and digital services exist in `pending_review`, **When** an admin opens the rental pending queue, **Then** only rental services in `pending_review` appear.
2. **Given** published or rejected rental services exist, **When** an admin opens the rental pending queue, **Then** those non-pending services do not appear.
3. **Given** sale services are pending, **When** an admin opens the rental pending queue, **Then** sale services do not appear.
4. **Given** an admin opens a type-specific queue, **Then** the queue clearly displays type and status badges appropriate to the services shown.

---

### User Story 3 - See Pending Backlog Counts in Navigation (Priority: P2)

An admin needs sidebar badges showing how many pending services exist per product type before opening each queue.

**Why this priority**: Moderation backlog is operationally time-sensitive. Counts in navigation help admins prioritize queues without running manual filters.

**Independent Test**: Seed known pending counts per product type and verify the sidebar badge for each service type matches the pending-review count for that type.

**Acceptance Scenarios**:

1. **Given** five rental services are pending review, **When** the admin views the sidebar, **Then** the rental service navigation badge shows `5`.
2. **Given** no digital services are pending review, **When** the admin views the sidebar, **Then** the digital service badge shows `0` or is visually absent according to the existing admin navigation convention.
3. **Given** a pending sale service is approved, **When** the admin returns to navigation, **Then** the sale pending badge decrements by one.

---

### User Story 4 - Apply Bulk Moderation Decisions (Priority: P2)

An admin needs to approve, reject, or archive multiple selected services in one operation when reviewing high-volume queues.

**Why this priority**: Service moderation can be repetitive. Bulk actions reduce operational effort while preserving the same lifecycle and bilingual requirements as row actions.

**Independent Test**: Select 50 pending services of one product type and approve them in one bulk operation. Verify all selected services become published, moderator metadata is populated, and the operation either completes for all selected services or reports a clear failure without partial silent success.

**Acceptance Scenarios**:

1. **Given** multiple pending services are selected, **When** an admin chooses "Approve selected", **Then** every selected service becomes `published` and receives moderation metadata.
2. **Given** multiple pending services are selected, **When** an admin chooses "Reject selected" with one shared EN+AR reason, **Then** every selected service becomes `rejected` with the same bilingual moderation notes and moderation metadata.
3. **Given** multiple non-archived services are selected, **When** an admin chooses "Archive selected", **Then** every eligible selected service becomes `archived` and ineligible rows are skipped or reported clearly.
4. **Given** a bulk operation includes a service that is no longer eligible because another admin changed it, **When** the operation runs, **Then** the admin receives a clear conflict result and no silent lifecycle bypass occurs.

---

### User Story 5 - Re-Moderate Material Edits (Priority: P1)

An admin needs published services to automatically return to moderation when a vendor changes customer-facing material content.

**Why this priority**: Published service pages directly affect customer trust and booking decisions. Material edits must not bypass review.

**Independent Test**: Publish one service of each product type, edit material fields, and verify each service returns to `pending_review`. Edit a non-material field and verify the service remains published.

**Acceptance Scenarios**:

1. **Given** a published rental service, **When** the vendor changes price, category, title, description, or customer-facing media, **Then** the service returns to `pending_review`.
2. **Given** a published sale service, **When** the vendor changes only a non-material admin-only field, **Then** the service remains `published`.
3. **Given** a published digital service returns to `pending_review` after a material edit, **When** an admin opens the digital pending queue, **Then** that service appears in the queue.

### Edge Cases

- If a service is already `published`, `rejected`, `archived`, or `changes_requested`, row-level approve/reject/request-edit actions must not appear unless the target transition is valid.
- If another admin changes a service between queue load and decision submit, the decision must fail clearly instead of overwriting the newer state.
- If bulk rejection is submitted without both EN and AR reason text, no selected service may be rejected.
- If a queue has no pending services, the admin sees an empty state that identifies the product type and pending-review scope.
- If the changes-requested workflow is unavailable during implementation, request-edit exposure must be blocked until ADR-0018-backed actions are available; reject-with-notes remains available.
- If a material media edit cannot be classified automatically, the conservative default is to return the service to `pending_review`.

## Requirements *(mandatory)*

### Functional Requirements

**Moderation Decisions**

- **FR-EXT-001**: Admin MUST be able to approve and publish a `pending_review` rental, sale, or digital service from the admin service management surface. BACKFILL NEEDED: add explicit service moderation UI requirement to `01_PRD.md`.
- **FR-EXT-002**: Approving a service MUST set the service status to `published`, set `moderated_by`, set `moderated_at`, and emit the publish lifecycle signal after the decision is committed.
- **FR-EXT-003**: Admin MUST be able to reject a `pending_review` rental, sale, or digital service with a required bilingual reason.
- **FR-EXT-004**: Rejecting a service MUST persist moderation notes with both `en` and `ar` values, set the service status to `rejected`, and set moderator metadata.
- **FR-EXT-005**: Admin MUST be able to request edits for a `pending_review` service when the ADR-0018 changes-requested workflow is present and available for services.
- **FR-EXT-006**: Moderation decision controls MUST be hidden or blocked for services whose current status does not allow the requested transition.

**Per-Type Pending Queues**

- **FR-EXT-007**: Admin MUST have a dedicated pending-review queue for rental services, scoped to product type `rental` and status `pending_review`.
- **FR-EXT-008**: Admin MUST have a dedicated pending-review queue for sale services, scoped to product type `sale` and status `pending_review`.
- **FR-EXT-009**: Admin MUST have a dedicated pending-review queue for digital services, scoped to product type `digital` and status `pending_review`.
- **FR-EXT-010**: The pending-review scope MUST be built into each queue and must not be removable through a visible filter control.
- **FR-EXT-011**: Each queue MUST display status and product-type indicators so admins can confirm what they are reviewing.

**Navigation Badges**

- **FR-EXT-012**: Each service type navigation entry MUST show the count of services for that product type currently in `pending_review`.
- **FR-EXT-013**: Navigation badge counts MUST update after approve, reject, request-edit, archive, or material-edit transitions that change the pending-review count.

**Bulk Actions**

- **FR-EXT-014**: Admin MUST be able to approve selected pending services in one bulk operation.
- **FR-EXT-015**: Admin MUST be able to reject selected pending services in one bulk operation using one shared bilingual reason.
- **FR-EXT-016**: Admin MUST be able to archive selected eligible services in one bulk operation.
- **FR-EXT-017**: Bulk moderation MUST preserve the same status, moderation notes, moderator metadata, and lifecycle signals as equivalent row-level actions.
- **FR-EXT-018**: Bulk approval MUST support at least 50 selected services in one operation.

**Material-Edit Gate**

- **FR-EXT-019**: A material edit to a published service MUST return the service to `pending_review`.
- **FR-EXT-020**: Material edits MUST include changes to price, category, title/name, short or long description, and customer-facing core media.
- **FR-EXT-021**: Non-material edits MUST NOT return a published service to `pending_review` unless they affect customer-facing service claims.

**Localization, Permissions, and Auditability**

- **FR-EXT-022**: All moderation labels, confirmation text, empty states, validation messages, and success/failure notifications MUST be available in English and Arabic.
- **FR-EXT-023**: Rejection and request-edit reason entry MUST require both English and Arabic values.
- **FR-EXT-024**: Only admins with service moderation permission may see or execute service moderation decisions.
- **FR-EXT-025**: Moderation decisions MUST be auditable through existing service fields and existing audit infrastructure; this feature MUST NOT introduce a new moderation history table in Phase 1.

### Key Entities

- **Service** (`services`): Existing customer-facing offering submitted by a vendor. Key moderation attributes are `product_type`, `status`, `moderation_notes`, `moderated_at`, and `moderated_by`.
- **Product Type**: Existing discriminator with rental, sale, and digital values. Queue scoping and tests must cover all three types.
- **Moderator/Admin**: Existing admin user who performs moderation decisions and is recorded as the actor for decisions.
- **Moderation Notes**: Bilingual reason content attached to rejected services, and correction guidance when request-edits is used through the changes-requested workflow.
- **Pending Queue**: Type-specific admin view of services whose `status` is `pending_review`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can approve or reject a pending service from any product type in under 30 seconds after opening its row.
- **SC-002**: Each type-specific pending queue shows 100% of pending services for its product type and 0 services from other product types in seeded validation data.
- **SC-003**: Navigation badge counts match the true pending-review count for rental, sale, and digital services in seeded validation data.
- **SC-004**: Bulk approve handles 50 selected pending services in one operation with all selected services ending in `published`.
- **SC-005**: 100% of rejected services created through row or bulk actions contain non-empty EN and AR moderation notes.
- **SC-006**: 100% of material-edit test cases across rental, sale, and digital return published services to `pending_review`.
- **SC-007**: All feature tests for row actions, queue scoping, badge counts, bulk actions, and material-edit re-review pass for rental, sale, and digital services.

## Constitution Check

- **I. Modular Monolith**: PASS - Work remains in the existing Catalog module and uses existing cross-module boundaries.
- **II. Three Product Types**: PASS - Rental, sale, and digital each have explicit queue and test coverage.
- **III. Money Discipline**: PASS - Price is only used as a material-edit trigger; no money arithmetic or schema changes are introduced.
- **IV. Bilingual EN+AR**: PASS - Rejection/request-edit notes and admin UI text require both locales.
- **V. Append-Only Tables**: PASS - No append-only tables are modified and no new moderation table is introduced.
- **VI. ADR Before Code**: PASS - `ADR-0013-admin-service-moderation.md` covers this Phase 8.0 moderation workflow; ADR-0018 covers request edits.
- **VII. Test-First Critical Paths**: PASS - Moderation lifecycle and type-aware behavior require Pest coverage for all three product types.
- **VIII. Idempotency**: PASS - No HTTP state-changing endpoints are added by this feature.
- **IX. Domain Events After Commit**: PASS - Publish/reject/request-edit lifecycle signals must fire only after the moderation decision is committed.
- **X. Vendor Approval Gate**: PASS - This feature moderates services after vendor/service submission flows; it does not alter vendor type approval rules.
- **XI. Document Storage**: PASS - Core media moderation uses existing media storage; no new storage strategy is introduced.

## Assumptions

- Phase 8.0 domain-level moderation actions from prior work exist or will be completed before the UI is wired.
- ADR-0018 changes-requested workflow is accepted and may be used for "Request Edits"; if service-specific request-edit actions are not complete, planning must add tasks to finish them before exposing the action.
- The canonical service lifecycle for this phase includes `draft`, `pending_review`, `published`, `rejected`, `archived`, and, when ADR-0018 is active, `changes_requested`.
- Dedicated pending queues are admin-only surfaces and do not add customer, vendor, or public API endpoints.
- "Core media" means customer-facing media used to evaluate or present the service, including primary gallery images and service preview media.

## Out of Scope

- Duplicate-from-existing admin shortcut is deferred to Phase 1.5.
- Side-by-side diff view of pre/post moderation edits is deferred to Phase 1.5.
- Rich moderation history timeline is deferred to Phase 1.5; Phase 1 uses existing service fields and audit logs.
- New moderation tables are out of scope for Phase 1.
- Frontend framework work is out of scope for this backend/admin repository.

## Exit Criteria

- Admin can approve or reject pending rental, sale, and digital services from row actions.
- Admin can open dedicated pending-review queues for rental, sale, and digital services.
- Sidebar badges show pending-review counts per service type.
- Bulk approve, reject, and archive work across all three product types.
- Material edits to published services return all three product types to `pending_review`.
- Pest coverage validates row actions, bulk actions, queue scoping, badge counts, bilingual notes, event behavior, and material-edit re-review.
