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
- FR traceability: cite specific FR numbers from 01_PRD.md, or define local FR-EXT-NNN with a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: cite existing tables from 11_DB_Schema.md. New tables flagged "⚠️ NEW TABLE — not yet in 11_DB_Schema.md".
- Phase alignment: cite Phase ID from 09_Phasing_Plan.md, or propose extension with "⚠️ PHASE BACKFILL NEEDED" note.
- Never suggest a package not in 10_Package_List.md.
- Never suggest a Phase 2 feature.
- Never contradict docs/specs/02_Tech_Decisions.md locked stack.

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Service Material Edit Approval Workflow

**Feature Branch**: `035-service-edit-approval`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Build service material edit approval workflow. Admin must approve material service edits such as price, description, category, images, availability-affecting fields, and product-type detail changes before publication."

## Traceability & Scope

- **PRD coverage**: `docs/specs/01_PRD.md` line 104 (Admin Journey): *"Approve service additions or material service edits where approval is required."* No dedicated numbered FR exists, so this spec uses local `FR-EXT-*` requirements and marks PRD backfill where needed. Aligned with FR-19 (vendors create services) and Admin Journey steps 6–8 (review/approve material edits).
- **Vendor Journey coverage**: `docs/specs/07_Vendor_Journey.md` step 6 — *"Admin moderates new services / material edits"*.
- **Admin Journey coverage**: `docs/specs/08_Admin_Journey.md` step 6 and the rule table line 258 — *"Admin must approve material service edits (price, description) — Vendor governance"*.
- **Phase alignment**: Extends **Phase 8.0 — Admin Service Moderation & Publish Workflow** (`docs/specs/09_Phasing_Plan.md §PHASE 8.0`, line 1577). Phase 8.0 ships the basic flow where material edits flip a published service back to `pending_review` (destructive — service goes dark until re-approved). This spec adds a **staged-edits** layer so the live published service keeps serving customers while the proposed material changes wait in a separate approval queue. Propose Phase ID **Phase 8.0.1 — Staged Material Edit Approval**. ⚠️ PHASE BACKFILL NEEDED.
- **Schema traceability**: Reuses existing `services` and 3 detail tables (`service_rental_details`, `service_sale_details`, `service_digital_details`) and `audit_logs` from `11_DB_Schema.md`. Introduces `service_change_requests` and `service_change_request_items` ⚠️ NEW TABLES — not yet in 11_DB_Schema.md. Distinct from the generic `change_requests` table in spec 020 (which models admin → vendor "fix these things before approval"); this feature models vendor → admin "please approve my proposed edit".
- **ADR required**: `ADR-0035-service-material-edit-approval.md` (to be written before migrations).
- **Depends on**:
  - Phase 2.1 / 2.2 / 2.3 — per-type service creation flows
  - Phase 8.0 (spec 024) — service moderation queues, `services.status` lifecycle, `service.moderate.{type}` permissions
  - Spec 026 — service lifecycle state machine
- **Distinct from spec 020 (Admin Changes-Requested)**: Spec 020 = admin requests corrections from vendor before approving the original record. This spec = vendor proposes an edit to an already-approved record and admin approves the diff. Different direction, different state machine, separate tables.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Vendor Submits a Material Edit and the Live Service Keeps Selling (Priority: P1)

A vendor with a published rental "Bouncy Castle XL" service decides to raise the price by 15% and rewrite the long description. They open the service in the vendor portal, change the fields, and click **Save**. The system detects that price and description are **material** fields, refuses to mutate the live `services` row, and instead creates a `service_change_request` capturing the before/after diff plus the vendor's optional note ("Cost of inflatables went up — adjusting for the season"). The live service continues to be discoverable and bookable at the old price. The vendor sees a yellow "Pending admin review" banner on the service and the same diff that the admin will see, with a clear status pill.

**Why this priority**: This is the headline behavior — without it, every price change blanks a vendor's published listing for hours or days. Reducing dark time on revenue-bearing listings is the entire reason for the staged-edit workflow.

**Independent Test**: Publish a service for each product type. As the vendor, submit a price + description edit. Verify (a) the live `services` row is unchanged, (b) a `service_change_request` exists with status `pending`, (c) the vendor portal shows the pending banner, and (d) the public discovery API still returns the old price.

**Acceptance Scenarios**:

1. **Given** a `published` rental service, **When** the vendor edits the price and submits, **Then** `DetectMaterialServiceChangesAction` returns at least one material field, `SubmitServiceChangeRequestAction` creates a `service_change_request` with `status=pending` and `proposed_changes` JSON containing the diff, the live `services.base_price_minor` is unchanged, and the vendor sees a "Pending admin review" status pill on the service card.
2. **Given** a `published` sale cake service, **When** the vendor changes only the rich text long description and the cover image, **Then** a single `service_change_request` is created, the existing cover image remains the live one until approval, and the diff displays both old and new images side by side.
3. **Given** a `published` digital e-invitation service, **When** the vendor changes the `delivery_method` (a `service_digital_details` field) and `redemption_url_template`, **Then** the change request records the diff inside the type-specific detail block and the vendor sees a vendor-side note field they can fill in to explain context to the admin.
4. **Given** the same service has an active `pending` change request, **When** the vendor tries to submit another material edit, **Then** the system returns a 409 with bilingual message *"You already have a pending edit awaiting admin review"* / *"لديك تعديل قيد المراجعة من قبل الإدارة"* and points them to the open request — no second `service_change_request` is created.

---

### User Story 2 — Admin Approves a Staged Edit From the Pending Service Edits Page (Priority: P1)

An admin opens the **Pending Service Edits** Filament page from the Services navigation group. They see a list filtered by product type, with columns showing the service, the vendor, the count of changed fields, the time since submission, and a status pill. Clicking a row opens a detail page with a **side-by-side before/after diff**: left column shows the current live values, right column shows the proposed values, and each changed field carries a coloured **field badge** (e.g., yellow for `price_minor`, blue for `category_id`, green for `images`, purple for type-specific detail). The admin reads the vendor's note, optionally writes an admin note in EN+AR, and clicks **Approve**. The system atomically applies the proposed changes to the live `services` row and the matching detail row, marks the change request `approved`, writes audit log entries for every changed field, fires a `ServiceChangeRequestApproved` event, and notifies the vendor.

**Why this priority**: This is the admin-side counterpart to Story 1; the workflow has no business value without an admin path to push approved edits live.

**Independent Test**: Create a `pending` `service_change_request` for each product type. Open the Pending Service Edits page as an admin with `service.moderate.{type}` permission. Approve each request. Verify the live `services` row and the matching detail row now reflect the proposed values, the change request status is `approved`, audit log entries exist for each changed field, the vendor received a notification, and the public discovery API returns the new price.

**Acceptance Scenarios**:

1. **Given** a `pending` rental `service_change_request` with price and security deposit changes, **When** the admin approves it, **Then** in one transaction `services.base_price_minor` and `service_rental_details.security_deposit_minor` are updated to the proposed values, `service_change_requests.status` becomes `approved` with `decided_by`, `decided_at`, and `admin_note` captured, an `audit_logs` row is written per changed field, and `ServiceChangeRequestApproved` fires via `DB::afterCommit`.
2. **Given** a `pending` change request that includes a category change, **When** the admin approves it, **Then** `services.category_id` is updated and any cached Meilisearch document for the service is queued for re-index.
3. **Given** an admin who lacks `service.moderate.rental` permission, **When** they open the Pending Service Edits page filtered to rentals, **Then** rental rows are hidden or read-only and the approve/reject/clarification actions are disabled with a tooltip referencing the missing permission.
4. **Given** an attempt to approve a `service_change_request` whose underlying service has been deleted or unpublished while the request was pending, **When** the admin clicks Approve, **Then** the system blocks the approval with a clear bilingual error and offers to reject instead. No partial mutation occurs.

---

### User Story 3 — Admin Rejects a Staged Edit With Bilingual Reason (Priority: P1)

An admin reviews a proposed edit where the vendor has changed the cover image to one with a competitor logo watermark and rewritten the title to include promotional shouting ("BEST PRICE!!!"). The admin clicks **Reject**, enters a bilingual reason ("Cover image violates content policy — please use a logo-free photo", "صورة الغلاف تنتهك سياسة المحتوى — يرجى استخدام صورة بدون شعار"), and submits. The change request becomes `rejected`, the live service is **untouched**, the vendor is notified, the vendor sees the admin reason on the service in their portal, and the vendor regains the ability to submit a new material edit on this service.

**Why this priority**: Admin must be able to refuse bad edits without taking the live listing down. Same priority tier as approval because the workflow is incomplete without it.

**Independent Test**: Submit a `pending` `service_change_request` and reject it as admin with bilingual rationale. Verify (a) the live service row is unchanged, (b) the change request shows `status=rejected` with both EN and AR notes captured, (c) the vendor's "you have a pending edit" lock is released, and (d) a fresh edit on the same service is allowed.

**Acceptance Scenarios**:

1. **Given** a `pending` change request on a sale service, **When** the admin rejects with only EN text filled, **Then** the action is blocked at the FormRequest layer with a 422 demanding both EN and AR text (bilingual-first rule from Constitution §IV).
2. **Given** a `pending` change request, **When** the admin rejects it with both EN and AR text, **Then** `service_change_requests.status=rejected`, `admin_note_en` and `admin_note_ar` are persisted, the vendor receives a bilingual notification, and an `audit_logs` row records who rejected, when, and the reason hash.
3. **Given** a rejected change request, **When** the vendor opens the service in the portal, **Then** they see the admin's bilingual reason inline on the service with an action to retry the edit.
4. **Given** a rejected change request, **When** the vendor submits a corrected material edit, **Then** a fresh `service_change_request` is created — the old rejected record is preserved untouched for audit.

---

### User Story 4 — Admin Requests Clarification Without Closing the Request (Priority: P2)

An admin reviewing a proposed price hike wants to ask the vendor "Why are you raising this 40% mid-season?" without rejecting. They click **Request Clarification**, enter a bilingual question, and submit. The change request moves to status `awaiting_clarification`, the live service stays unchanged, the vendor is notified, and the vendor portal exposes a single bilingual reply field on that change request. After the vendor replies, the request returns to `pending` with the conversation visible to both sides. A change request may go through clarification cycles up to a configurable cap (`ServiceEditApprovalPolicy::MAX_CLARIFICATIONS = 3`).

**Why this priority**: Reduces unnecessary rejections and reduces vendor frustration, but the workflow is functional without it (admin could just reject and ask vendor to redo). Ranks P2.

**Independent Test**: Submit a `pending` change request. As admin, request clarification with bilingual question. Verify the status transitions to `awaiting_clarification`, vendor receives notification, vendor can reply, and request returns to `pending` with the exchange preserved.

**Acceptance Scenarios**:

1. **Given** a `pending` change request, **When** the admin requests clarification with bilingual text, **Then** status becomes `awaiting_clarification`, `clarification_round` increments, and the vendor is notified.
2. **Given** an `awaiting_clarification` request, **When** the vendor replies, **Then** the request returns to `pending` and the admin sees the full exchange on the detail page.
3. **Given** a request at `clarification_round=3` and `awaiting_clarification`, **When** the admin attempts another clarification round, **Then** the system blocks with a 422 instructing the admin to approve or reject.
4. **Given** an `awaiting_clarification` request, **When** the admin attempts to approve, **Then** the action is allowed (clarification does not gate approval — admins may resolve at any time).

---

### User Story 5 — Non-Material Edits Bypass the Approval Queue (Priority: P2)

A vendor with a `published` digital service updates internal vendor notes and a contact phone number — neither of which affects what customers see or pay. The system detects no material fields changed, `DetectMaterialServiceChangesAction` returns an empty set, and the edit is applied directly to the live `services` row without creating a `service_change_request` or triggering admin review.

**Why this priority**: Material-only gating prevents the queue from drowning in trivial edits. Functional without it (admin would just rubber-stamp non-material changes) but operationally essential.

**Independent Test**: As vendor, edit a non-material field on a published service. Verify the live record is updated immediately, no change request is created, and the public listing reflects the change without admin involvement.

**Acceptance Scenarios**:

1. **Given** a `published` service, **When** the vendor updates only non-material fields, **Then** the live `services` row is updated atomically, no `service_change_request` row is created, and a normal `services.updated` audit log entry is written.
2. **Given** a `published` service, **When** the vendor's single save touches one material field and one non-material field, **Then** the entire save is staged via a `service_change_request` — partial application is forbidden because it would split the vendor's intent across two visibility states.
3. **Given** a `draft` or `pending_review` service (not yet published), **When** the vendor edits any field, **Then** changes apply directly because there is no live customer-facing version to protect.

---

### Edge Cases

- **What if the admin approves a change request and the proposed category no longer exists?** Approval is blocked with a clear bilingual error; admin must reject and ask vendor to pick a valid category.
- **What if the vendor edits a service and the corresponding `service_change_request` references an image that was deleted from media library before approval?** Approval blocks with an error; vendor is invited to resubmit. Cleanup job preserves images referenced by pending change requests.
- **What if the same vendor is editing two different published services concurrently?** Each service has its own pending-edit lock — vendor may have multiple pending change requests across different services, but never two on the same service.
- **What if the change request has been pending for more than 14 days?** A nightly job flags stale `pending` requests for admin attention via the existing admin inbox routing (spec 019). No auto-decision occurs in Phase 1.
- **What if the vendor's account is suspended while a change request is pending?** Existing suspension flow cancels the change request with status `cancelled_vendor_suspended` and writes an audit entry. Service itself follows the suspension rules independent of the staged edit.
- **What if a published service is archived while a change request is pending?** The change request transitions to `cancelled_service_unavailable`; the archive action blocks until the change request is decided in admin override mode.
- **What about translatable-field-only changes (e.g., only the Arabic description was reworded)?** Description is material. The change request applies, but the diff UI highlights per-locale on the Arabic side only.
- **Two admins open the same change request at the same time** — the second click hits a 409 with the message "This request was already decided by {name} at {time}". No double-application.

---

## Requirements *(mandatory)*

### Functional Requirements

**Existing PRD coverage (cited):**

- **PRD §6 Admin Journey** (line 104): *"Approve service additions or material service edits where approval is required."* — establishes admin approval gate for material edits.
- **FR-19** (`01_PRD.md`): Vendors must create services by choosing a group/category first — material edit approval inherits the same per-type permission scoping.
- **Admin Journey step 6** (`08_Admin_Journey.md`): Receive new services or material edits for moderation (every type).
- **Admin Journey rule table line 258** (`08_Admin_Journey.md`): *"Admin must approve material service edits (price, description) — Vendor governance."*
- **Phase 8.0** (`09_Phasing_Plan.md` line 1602): *"Define 'material edit' set: price, category, title, short/long description, core media"* — this spec adopts that definition and extends it.

**New requirements — ⚠️ BACKFILL NEEDED: add to 01_PRD.md:**

- **FR-EXT-001**: System MUST classify every save on a `published` service as either **material** or **non-material** based on the set of fields touched. The material field set MUST include:
  - `services.base_price_minor`, `services.base_price_currency`
  - `services.name` (EN and AR translations independently — change in either locale is material)
  - `services.short_description`, `services.long_description` (EN or AR)
  - `services.category_id`
  - Cover image and gallery additions/removals/reorderings via Spatie Media Library `gallery` collection
  - Availability-affecting fields: `services.is_available_for_booking`, weekly availability windows, blackout dates (`service_availability_blocks`), excluded dates (`service_excluded_dates`)
  - Pricing tiers (`service_pricing_tiers`)
  - All columns on the matching detail table (`service_rental_details`, `service_sale_details`, or `service_digital_details`) **except** purely internal fields enumerated in the policy class
  - The exact set is enforced by `MaterialFieldRegistry` per `ProductType` using `match($enum)`.
- **FR-EXT-002**: When a save on a `published` service contains at least one material field, the system MUST NOT mutate the live `services` row. Instead it MUST create a `service_change_request` capturing a `before_snapshot` and `proposed_changes` JSON block plus an optional bilingual vendor note. The save MUST NOT cause `services.status` to change.
- **FR-EXT-003**: A `published` service MUST allow at most one `pending` or `awaiting_clarification` `service_change_request` at a time. A second material edit attempt while one is open MUST return HTTP 409 with a bilingual message and point to the open request.
- **FR-EXT-004**: Admin MUST be able to approve a `pending` (or `awaiting_clarification`) change request via `ApproveServiceChangeRequestAction`. Approval MUST in one DB transaction (a) apply `proposed_changes` to the live `services` row and matching detail row, (b) set `service_change_requests.status='approved'`, `decided_by`, `decided_at`, and the bilingual `admin_note_*`, (c) write one `audit_logs` row per changed field, and (d) re-index the service in Meilisearch via the existing Scout pipeline. The `ServiceChangeRequestApproved` event MUST fire via `DB::afterCommit`.
- **FR-EXT-005**: Admin MUST be able to reject a `pending` or `awaiting_clarification` change request via `RejectServiceChangeRequestAction`. Bilingual EN+AR admin reason MUST be required by FormRequest validation. The live service MUST be unchanged. The vendor MUST be notified bilingually. After rejection the vendor regains the ability to submit a fresh material edit on the same service. The `ServiceChangeRequestRejected` event MUST fire via `DB::afterCommit`.
- **FR-EXT-006**: Admin MUST be able to request clarification on a `pending` request via `RequestServiceChangeClarificationAction`. The action MUST require bilingual EN+AR admin question, set status to `awaiting_clarification`, increment `clarification_round`, and notify the vendor. The maximum clarification round count is **3**. A 4th attempt MUST be blocked at the FormRequest layer.
- **FR-EXT-007**: Vendor MUST be able to reply to clarification questions inside the same change request. After a vendor reply the request status MUST return to `pending` and the full conversation MUST be visible to both sides.
- **FR-EXT-008**: Every state transition on `service_change_requests` (create, approve, reject, request_clarification, vendor_reply, cancel) MUST write an `audit_logs` entry with `subject_type='service'`, `subject_id=service_id`, the change request public id, and the actor.
- **FR-EXT-009**: Non-material edits on `published` services MUST apply directly to the live row without creating a `service_change_request`. If a single save touches both material and non-material fields, the entire save MUST be staged (no partial application).
- **FR-EXT-010**: Edits on `draft`, `pending_review`, `rejected`, or `archived` services MUST bypass the staged-edit workflow because no live customer-facing version exists to protect; existing moderation rules (Phase 8.0) continue to apply.
- **FR-EXT-011**: The admin Pending Service Edits page MUST support filtering by `product_type` (using the per-type colored badge convention from `.claude/rules/filament-components.md` §2), `vendor_profile_id`, and `status`. Default sort is `created_at desc`.
- **FR-EXT-012**: The change request detail page MUST display a side-by-side before/after diff with per-field badges, the vendor's note (bilingual), the admin's note slot (bilingual), and the full clarification thread when present.
- **FR-EXT-013**: All three product types (rental, sale, digital) MUST be supported using `match(ProductType)` for the material-field set and the detail-table apply step. `if/elseif` on type strings is forbidden (Constitution §I).
- **FR-EXT-014**: Vendors MUST be notified bilingually (push + email per the existing notifications pipeline) when (a) a change request is approved, (b) rejected, or (c) clarification is requested. Notification templates MUST exist in EN and AR with `event_key` `service.change_request.{approved|rejected|clarification_requested}`.
- **FR-EXT-015**: All change request records, items, snapshots, and clarification messages MUST be retained permanently (no deletion), even after the request reaches a terminal state, to support audit and compliance review. The tables are append-only-on-content; only the `status`, `decided_by`, `decided_at`, and admin note columns may update.

### Key Entities

- **ServiceChangeRequest**: One vendor-proposed material edit on a published service. Tracks the requesting vendor (via service ownership), the snapshot of current values, the proposed changes (JSON), the optional vendor note (bilingual), the optional admin note (bilingual), the current status, the deciding admin, the timestamps, and the clarification round counter.
  - Table: `service_change_requests` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md
  - Relates to: `services` (existing — 11_DB_Schema.md), `users` (existing, requesting vendor and deciding admin), `audit_logs` (existing)
  - Status enum: `pending`, `awaiting_clarification`, `approved`, `rejected`, `cancelled_vendor_suspended`, `cancelled_service_unavailable`
  - Soft-deletes: ❌ none (append-only-on-content per Constitution §15)

- **ServiceChangeRequestItem**: One field-level entry inside a change request — the field path, the before value snapshot, the proposed value, and a flag indicating which type-aware classification applies (shared / rental / sale / digital). Used to drive the side-by-side diff and the per-field badges in the admin UI.
  - Table: `service_change_request_items` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md
  - Relates to: `service_change_requests`

- **ServiceChangeRequestMessage** *(used only for clarification thread)*: One message in the admin↔vendor clarification thread tied to a change request. Each row is bilingual (EN+AR text) and carries the author role and timestamp.
  - Table: `service_change_request_messages` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md
  - Append-only (no updates, no deletes)

- **Service** (existing): Live row stays untouched while a change request is pending. The `services` table itself gains no new columns — staged edits live in the new tables. Cited from `11_DB_Schema.md` Catalog module.

- **AuditLog** (existing): Receives one row per state transition and one row per field on approval. Cited from `11_DB_Schema.md` cross-cutting tables.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A published service whose vendor submits a material edit remains discoverable and bookable at the **previous** values for 100% of the time between vendor submission and admin decision. Zero "dark window" exposure during admin review. Verified by a Pest test that hits the public discovery endpoint while a `service_change_request` is `pending`.
- **SC-002**: 100% of approved change requests result in **all** proposed field values reaching the live row in a single transaction — no partial application. Verified by a Pest test that mutates 6 fields across base + detail tables and asserts atomicity.
- **SC-003**: 100% of rejected change requests leave the live `services` row and its detail row byte-identical to the pre-submission state. Verified by hash-comparison Pest test.
- **SC-004**: Bilingual admin reason (EN + AR) present on 100% of `rejected` and `awaiting_clarification` records — enforced at FormRequest validation.
- **SC-005**: Each of rental, sale, and digital product types has at least one Pest test per state transition (submit, approve, reject, request_clarification, vendor_reply) covering happy path, wrong-vendor 403, and missing-permission 403.
- **SC-006**: Material-field detection accuracy: 100% recall on the material field set (no material change leaks past the gate as a non-material edit). Verified by an exhaustive Pest test that walks every member of `MaterialFieldRegistry` per product type.
- **SC-007**: An admin can decide (approve / reject / request clarification) a typical change request in under 60 seconds from opening the Pending Service Edits page — measured by Filament-level UI ergonomics, not network latency.

---

## Assumptions

- The set of "material" fields is policy and lives in `App\Modules\Catalog\Domain\Policies\MaterialFieldRegistry`, keyed by `ProductType`. It is hardcoded in Phase 1 and can be promoted to `app_settings` later if admin-configurability is needed.
- Material edits are gated only on `published` services. Services in `draft`, `pending_review`, `rejected`, or `archived` continue under the existing Phase 8.0 moderation rules and bypass the staged-edits workflow entirely.
- The clarification-thread cap of 3 mirrors the cycle cap convention used in spec 020 (admin changes-requested workflow). Promotable to `app_settings` later.
- Notifications use existing `Communication` module templates with `event_key` namespace `service.change_request.*`. New templates required in EN+AR.
- This feature does not introduce a public REST API in Phase 1. Vendors interact via the existing vendor portal (Next.js → existing internal endpoints), and admins interact via Filament. Internal Action classes are the integration surface. A public `GET /v1/vendor/services/{id}/change-requests` endpoint may be added in Phase 1.5 if the mobile vendor app needs read access.
- Spatie Media Library handles image diffs via the `gallery` collection. The change request stores the **media IDs** that would be added/removed/reordered, not new blob uploads — vendors must upload images first, then reference them in the proposed change.
- Meilisearch reindex on approval reuses the existing Scout `Searchable` trait observers on `Service` — no custom code required beyond ensuring the apply step touches the model through Eloquent (not raw DB updates).
- Concurrent admin decisions are protected by Eloquent optimistic locking via a `version` column on `service_change_requests` plus a transactional `lockForUpdate` inside each decision Action. Pattern mirrors `booking_locks` from `11_DB_Schema.md` but lighter-weight (no separate lock table).
- The change-request retention policy (permanent, append-only-on-content) aligns with the audit-log retention rules in Constitution §15.
- An automatic stale-edit reminder ("Pending > 14 days") is deferred to Phase 1.5 and will route via the existing admin inbox (spec 019). Phase 1 ships the data and a manual stale filter only.
