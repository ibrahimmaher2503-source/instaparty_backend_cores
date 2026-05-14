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

# Feature Specification: Admin Changes-Requested Workflow

**Feature Branch**: `020-admin-changes-request`
**Created**: 2026-05-04
**Status**: Draft

**Phase Alignment**:
- Day 1 attaches to **Phase 1.1** — Vendor Onboarding + Approval (Week 2) — cited from `09_Phasing_Plan.md §PHASE 1.1`
- Day 2 attaches to **Phase 8.0** — Admin Service Moderation & Publish Workflow — cited from `09_Phasing_Plan.md §PHASE 8.0`

**PRD Coverage**:
- FR-29 (admin approves vendors / reviews requests) — `01_PRD.md`
- Admin Journey step 5: "Reject / request more info" for vendor registration — `08_Admin_Journey.md`
- Admin Journey step 8: "Reject / request edit" for service moderation — `08_Admin_Journey.md`
- FR-EXT-001 through FR-EXT-009 below ⚠️ BACKFILL NEEDED: add to 01_PRD.md

**ADR Required**: `ADR-0018-changes-requested-workflow.md` (to be written before migrations)

**Depends On**: Phase 1.1 (Vendor Approval), Phase 8.0 (Service Moderation)

---

## User Scenarios & Testing

### User Story 1 — Admin Requests Document Changes from Vendor (Priority: P1)

An admin reviewing a vendor's registration documents discovers that one document is blurry or contains an error. Rather than outright rejecting the vendor (which would force a full re-application), the admin clicks **"Request Changes"**, builds a bilingual checklist of items to fix (e.g., "CR document is blurry — please re-upload", "IBAN number does not match business name"), and submits it. The vendor receives a notification, sees the checklist on their dashboard in both English and Arabic, makes the corrections, and resubmits. The admin then reviews the updated submission against the original and either approves the vendor or requests another round of changes. The loop cannot repeat more than 3 times — after the third resubmission the admin must make a final decision (approve or reject).

**Why this priority**: This is the primary workflow improvement over the current binary approve/reject. It directly reduces unnecessary full rejections for fixable issues during vendor onboarding, which is the most sensitive phase for vendor experience.

**Independent Test**: Can be fully tested by creating a vendor in `pending_review` status, having an admin submit a change request, verifying the vendor's status changes to `changes_requested`, having the vendor resubmit, and verifying the admin sees the updated submission with the change-request history alongside it.

**Acceptance Scenarios**:

1. **Given** a vendor profile in `pending_review` status, **When** an admin submits a change request with two items (one in EN, both in AR), **Then** the vendor profile status becomes `changes_requested`, a `change_requests` record is created with `cycle_number=1`, two `change_request_items` records exist with `item_status=pending`, and the vendor receives a bilingual push/email notification.

2. **Given** a `change_requests` record with `cycle_number=1` and status `open`, **When** the vendor resubmits updated documents, **Then** the change request status becomes `resubmitted`, the admin sees the new documents side-by-side with the original snapshots from `change_request_items.current_value_snapshot`, and the vendor profile status reverts to `pending_review`.

3. **Given** a `change_requests` record with `cycle_number=3` and status `resubmitted`, **When** the admin attempts to create another change request on the same vendor profile, **Then** the system rejects the attempt with an error, forces the admin to either approve or reject, and if the admin ignores it, `EscalateChangeRequestToRejectionAction` auto-escalates after a configurable grace period.

4. **Given** a resolved change request (status `resolved`), **When** a subsequent admin query fetches the vendor profile, **Then** the full change-request history (all cycles, all items, all snapshots) is still accessible and linked to the vendor profile — nothing is deleted.

---

### User Story 3 — System Enforces 3-Cycle Limit (Priority: P3)

The system prevents an infinite changes-requested loop by tracking the cycle count on each `change_requests` record. When a third cycle's resubmission is not resolved by the admin, the system auto-escalates the change request to a rejection state, preventing the vendor from remaining in limbo indefinitely.

**Why this priority**: Governance requirement — without a cycle cap, a vendor or admin could stall indefinitely. This is a policy enforcement feature, not user-facing functionality, so it ranks below the core workflow.

**Independent Test**: Can be fully tested in isolation by simulating 3 full change-request cycles on a vendor profile, attempting a 4th cycle, and verifying the system blocks the creation and escalates.

---

## ⚠️ DEFERRED TO PHASE 8.0 — Service Moderation Changes-Requested Workflow

### User Story 2 — Admin Requests Changes on a Service Under Moderation (Priority: P2)

An admin reviewing a newly submitted rental/sale/digital service finds that one image has a watermark, or the lead time is too low for the category. Instead of rejecting the service outright, the admin clicks **"Request Changes"** on the service's Filament resource, adds specific change-request items (e.g., "Image #3 has a watermark — replace", "Lead time must be at least 48h for made-to-order cakes"), and submits. The vendor sees the checklist, updates the service, and resubmits. The same 3-cycle limit and history preservation apply.

**Why this priority**: Service moderation is a high-volume operation (every new service and material edit goes through it). A nuanced change-request workflow prevents quality degradation without discouraging vendors from listing services.

**Independent Test**: Can be fully tested by creating a service in `pending_review` for each product type (rental, sale, digital), submitting a change request, resubmitting as vendor, and verifying the service history and final published state.

**Acceptance Scenarios**:

1. **Given** a service (any product type) in `pending_review`, **When** an admin creates a change request with at least one `change_request_item`, **Then** the service `status` becomes `changes_requested`, a `change_requests` record is created with `subject_type=service`, and the vendor receives a bilingual notification specific to the service.

2. **Given** a service in `changes_requested`, **When** the vendor edits the service fields named in the change-request items and resubmits, **Then** the service status reverts to `pending_review` and the change request status becomes `resubmitted`.

3. **Given** change-request items with `item_status=pending`, **When** the vendor marks specific items as addressed during resubmission, **Then** those items update to `item_status=addressed`, any intentionally waived items update to `item_status=waived`, and only `pending` items remain visible as outstanding.

4. **Given** a service that went through a full cycle (changes_requested → resubmitted → published), **When** the admin queries the service history, **Then** the `change_requests` and `change_request_items` records remain linked to the service, providing a permanent audit trail.

The system prevents an infinite changes-requested loop by tracking the cycle count on each `change_requests` record. When a third cycle's resubmission is not resolved by the admin, the system auto-escalates the change request to a rejection state, preventing the vendor from remaining in limbo indefinitely.

**Why this priority**: Governance requirement — without a cycle cap, a vendor or admin could stall indefinitely. This is a policy enforcement feature, not user-facing functionality, so it ranks below the core workflow.

**Independent Test**: Can be fully tested in isolation by simulating 3 full change-request cycles on a vendor profile, attempting a 4th cycle, and verifying the system blocks the creation and escalates.

**Acceptance Scenarios**:

1. **Given** a change request at `cycle_number=3` and status `resubmitted`, **When** an admin attempts to create a new change request on the same subject, **Then** the system returns a validation error "Maximum change cycles reached — please approve or reject."

2. **Given** a change request at `cycle_number=3` and status `resubmitted` where the admin has not acted, **When** `EscalateChangeRequestToRejectionAction` runs (triggered by admin or by background job), **Then** the change request status becomes `escalated_to_rejection`, the subject (vendor profile or service) status transitions to `rejected`, and an audit log entry is written.

3. **Given** a change request at `cycle_number=1` or `cycle_number=2`, **When** the same endpoint is called, **Then** the action proceeds normally — the cycle cap only triggers at cycle 3.

---

### Edge Cases

- What happens if a vendor resubmits without addressing any of the requested items? The system still accepts the resubmission (vendor decides what to change), but the admin sees which items remain `pending` vs `addressed`.
- What if an admin creates a change request and then immediately approves the subject before the vendor resubmits? The change request resolves to `resolved` on approval — no dangling open request.
- What if two admins act on the same vendor simultaneously? Standard Eloquent pessimistic locking (`booking_locks` pattern) applies; the second admin sees a conflict error.
- What if a bilingual change-request item has an empty AR text? Validation must reject it — both `requested_change_en` and `requested_change_ar` are required per the bilingual-first rule (Constitution §IV).

---

## Requirements

### Functional Requirements

**Existing PRD coverage (cited):**

- **FR-29** (from `01_PRD.md`): Admin must be able to review and approve vendor registration and service submissions — this feature extends the resolution options beyond binary approve/reject.

**New requirements — ⚠️ BACKFILL NEEDED: add to 01_PRD.md:**

- **FR-EXT-001**: Admin MUST be able to request specific, itemized changes from a vendor without rejecting the vendor profile outright, during the vendor onboarding review step.
- **FR-EXT-002**: ⚠️ DEFERRED to Phase 8.0 — Admin MUST be able to request specific, itemized changes on a service (rental, sale, or digital) during the moderation review step, without rejecting the service outright.
- **FR-EXT-003**: Each change request MUST contain one or more items, where each item specifies the field or document to change and the required change description in both English and Arabic.
- **FR-EXT-004**: The system MUST record a snapshot of the current value at the time the change request is created, so the admin can compare old and new during resubmission review.
- **FR-EXT-005**: Vendor MUST be notified (push + email) in both EN and AR when a change request is opened against their vendor profile or any of their services.
- **FR-EXT-006**: Vendor MUST be able to resubmit a vendor profile or service after addressing change-request items, which returns the subject to the admin review queue with the change-request history visible alongside.
- **FR-EXT-007**: The system MUST track the number of change-request cycles per subject (vendor profile or service). The maximum cycle count is 3.
- **FR-EXT-008**: After the 3rd resubmission cycle, the system MUST prevent additional change requests and force the admin to approve or reject the subject. If the admin does not act, the system MUST auto-escalate to rejection.
- **FR-EXT-009**: All change request records and their items MUST be retained permanently (no deletion), even after the subject is approved or rejected, to support audit and compliance review.

### Key Entities

- **ChangeRequest**: Represents one round of admin-requested corrections for a subject (vendor profile or service). Tracks subject identity, requesting admin, current status, and cycle count. Linked to the subject polymorphically via `subject_type` + `subject_id`.
  - Tables: `change_requests` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md
  - Relates to: `vendor_profiles` (existing — 11_DB_Schema.md), `services` (existing — 11_DB_Schema.md)

- **ChangeRequestItem**: One specific correction item within a change request. Captures which field needs to change, what the current value was (snapshot), and the bilingual description of the required change.
  - Table: `change_request_items` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md

- **VendorProfile** (existing): Gains a new `approval_status` enum value `changes_requested` alongside existing `pending`, `approved`, `rejected`, `suspended`.
  - Table: `vendor_profiles` (existing — 11_DB_Schema.md, `approval_status` column)

- **Service** (existing): ⚠️ DEFERRED to Phase 8.0 — Gains a new `status` enum value `changes_requested` alongside existing `draft`, `pending_review`, `published`, `rejected`, `archived`.
  - Table: `services` (existing — 11_DB_Schema.md, `status` column)

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Admins can resolve a vendor onboarding issue via a targeted change request in under 2 minutes, compared to the current binary reject-and-restart flow.
- **SC-002**: 100% of change requests and their items persist after the subject reaches a terminal state (approved or rejected) — verified by the audit-trail test cases.
- **SC-003**: Zero vendor profiles or services remain in `changes_requested` status beyond the 3-cycle cap — the escalation action eliminates indefinite limbo states.
- **SC-004**: Bilingual content (EN + AR) is present on 100% of change-request items before they reach the vendor — enforced at the API validation layer.
- **SC-005**: All three product-type service workflows (rental, sale, digital) support the change-request cycle identically — confirmed by Pest test groups covering each type.

---

## Assumptions

- The 3-cycle maximum is a product policy decision. It is hardcoded as a constant (`ChangeRequestPolicy::MAX_CYCLES = 3`) and can be promoted to an `app_settings` value in Phase 1.5 if admin-configurability is needed.
- Auto-escalation to rejection after the 3rd cycle is triggered manually by an admin action (or via a scheduled job if Phase 1.5 adds an SLA timer). In Phase 1, the escalation action exists but is not automatically time-triggered.
- The `field_path` column on `change_request_items` is a dot-notation string (e.g., `documents.cr_document`, `pricing.lead_time_hours`) — not a foreign key reference. It is for human-readable display only in Phase 1.
- The `current_value_snapshot` column stores a JSON blob of the relevant value at change-request creation time. It is a point-in-time snapshot, not a live reference.
- The vendor "resubmit" action does not require re-uploading every document — only the documents/fields named in the outstanding `change_request_items` need to change. The system does not enforce this programmatically in Phase 1 (admin judgment); programmatic field-level gating is deferred to Phase 1.5.
- Chat integration (vendor messaging admin inside a change request thread) is out of scope for Phase 1. Vendors communicate intent via the notification + resubmit loop only.
- SLA timer ("vendor must respond within 7 days") is deferred to Phase 1.5 per the stated cut-list.
- Auto-suggestion rule library for common change reasons is deferred to Phase 1.5 per the stated cut-list.
