# ADR-0013 - Admin Service Moderation & Publish Workflow

- **Status:** Accepted
- **Date:** 2026-05-04
- **Decision-makers:** Ibrahim
- **Tags:** catalog, filament, service-moderation, phase-8
- **Related:**
  - [`0004-catalog-module.md`](0004-catalog-module.md) - Catalog module and service lifecycle baseline
  - [`ADR-0018-changes-requested-workflow.md`](ADR-0018-changes-requested-workflow.md) - structured request-edits workflow
  - [`../specs/01_PRD.md`](../specs/01_PRD.md) - admin control panel and service governance scope
  - [`../specs/08_Admin_Journey.md`](../specs/08_Admin_Journey.md) - Admin Journey step 3, Service Moderation
  - [`../specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) - Phase 8.0
  - [`../specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) - `services` lifecycle fields
  - [`../../specs/024-service-moderation-queues/spec.md`](../../specs/024-service-moderation-queues/spec.md)

> Numbering note: this repository already contains other files using ADR-0013 for later scope additions. This file intentionally uses the exact filename referenced by `docs/specs/09_Phasing_Plan.md` Phase 8.0 so the historical Phase 8.0 requirement resolves without changing the source plan.

---

## 1. Context

The Catalog module already owns service creation and per-type resources for rental, sale, and digital services. Vendors can submit services and material edits can make published content customer-facing again, but admin moderation must be governed by an explicit workflow instead of manual status edits.

Phase 8.0 requires admins to review pending services in Filament, approve and publish valid services, reject invalid services with bilingual notes, request edits when the changes-requested workflow is available, and return published services to moderation when material fields change.

The locked schema already makes `services` the source of truth for Phase 1 moderation state through `status`, `moderation_notes`, `moderated_at`, and `moderated_by`. Phase 1 explicitly forbids a richer moderation-history table; auditability is provided by service fields plus existing audit infrastructure.

---

## 2. Decision

### 2.1 Catalog owns service moderation

Service moderation remains inside the existing Catalog module. No new Moderation module is introduced for Phase 1.

Catalog owns:

- service status transitions for publish, reject, archive, and return-to-review
- per-type admin moderation surfaces for rental, sale, and digital services
- service-level moderation metadata on `services`
- service lifecycle events published after commit
- material-edit detection for customer-facing service fields

Catalog does not own:

- vendor onboarding or vendor type approval, owned by Identity
- notification delivery, owned by Communication
- search infrastructure, owned by Discovery, although Catalog publishes lifecycle signals consumed by indexing/listeners
- shared change-request storage, owned by Shared per ADR-0018

### 2.2 No new moderation tables in Phase 1

Use existing `services` fields:

| Field | Purpose |
|---|---|
| `status` | Lifecycle state: `draft`, `pending_review`, `changes_requested`, `published`, `rejected`, `archived` |
| `moderation_notes` | JSON EN+AR notes for rejection and simple moderation decisions |
| `moderated_at` | Timestamp of latest admin moderation decision |
| `moderated_by` | Admin user who made the latest moderation decision |

If the current migrations do not match the locked schema, Phase 8.0 must add a targeted Catalog migration to align the database with `docs/specs/11_DB_Schema.md`.

### 2.3 Lifecycle and transitions

Allowed Phase 8.0 lifecycle:

```text
draft -> pending_review
pending_review -> published
pending_review -> rejected
pending_review -> changes_requested
changes_requested -> pending_review
published -> pending_review
published -> archived
rejected -> archived
archived -> draft
```

`published -> pending_review` is only for material edits or explicit restore/re-review flows. Free-form manual status edits must not be the primary moderation mechanism.

### 2.4 Request edits uses ADR-0018

The "Request Edits" decision delegates to ADR-0018's structured changes-requested workflow when available for services.

That means:

- request-edit creates `change_requests` and `change_request_items`
- service status becomes `changes_requested`
- duplicate open change requests are blocked
- the three-cycle cap remains enforced
- vendor resubmission returns the service to `pending_review`

If ADR-0018 service actions are unavailable in a running environment, the request-edit action must be hidden or disabled. Reject-with-bilingual-notes remains available.

### 2.5 Per-type queues are required

Rental, sale, and digital services each keep their own Filament resource and receive a dedicated pending-review queue page.

Each pending queue is hard-scoped to:

- the resource product type
- `status = pending_review`

This scope must be baked into the queue query, not represented only as a removable filter chip.

### 2.6 Action layering

Business mutations live in Catalog Application actions:

- `PublishServiceAction`
- `RejectServiceAction`
- `ArchiveServiceAction`
- `MarkServicePendingReviewForMaterialEditAction`
- existing ADR-0018 request-change actions for request edits

Filament action classes are UI adapters only. They configure labels, icons, forms, visibility, confirmation, and notifications, then delegate to Application actions.

The `Service` model remains limited to relationships, casts, and scopes.

### 2.7 Bulk actions reuse row actions

Bulk approve, reject, and archive must preserve row-action semantics:

- same transition validation
- same moderator metadata
- same bilingual note validation for reject
- same domain events after commit
- no raw bulk update that skips business rules

Bulk approval must handle at least 50 selected services in one operation.

### 2.8 Material edit gate

A published service returns to `pending_review` when customer-facing material content changes.

Material fields are:

- `base_price_minor`
- `base_price_currency`
- `category_id`
- `name`
- `short_description`
- `long_description`
- customer-facing core service media, especially gallery/preview media

Non-material edits do not return a service to review unless they affect customer-facing service claims. If classification is unclear, use the conservative rule and return the service to review.

### 2.9 Events fire after commit

Catalog publishes lifecycle events only after the database transaction commits:

| Event | Trigger |
|---|---|
| `ServicePublished` | `pending_review -> published` |
| `ServiceRejected` | `pending_review -> rejected` |
| `ServiceArchived` | eligible state -> `archived` |
| `ServiceReturnedToReview` | `published -> pending_review` after material edit |

Request-edits events are governed by ADR-0018 (`ChangeRequestCreated`, resubmission events).

### 2.10 Permissions and localization

Only admins with service moderation permission may execute moderation decisions.

All admin-facing moderation labels, confirmations, validation messages, empty states, and notifications must have English and Arabic translation keys. Reject and request-edit reasons require both EN and AR values.

---

## 3. Alternatives Considered

### Manual status editing

Rejected. It bypasses transition validation, bilingual reason requirements, moderator metadata, and event timing.

### One unified moderation page for all product types

Rejected for Phase 8.0. Admin Journey and Phase 8.0 both require separate queues per product type because rental, sale, and digital services have different review criteria.

### New service moderation history table

Rejected for Phase 1. Phase 8.0 says no new moderation tables; current source of truth remains `services` fields plus existing audit infrastructure. A richer moderation timeline is deferred to Phase 1.5.

### Request edits as `rejected + moderation_notes`

Rejected when ADR-0018 is available. ADR-0018 provides structured, itemized, bilingual change requests and vendor resubmission. Simple rejection with notes remains available for terminal rejection.

### Business logic in Filament closures

Rejected. It duplicates lifecycle rules across three resources and violates the project rule that business logic belongs in Actions.

### Business logic on the Service model

Rejected. Models may only contain relationships, casts, and scopes.

---

## 4. Consequences

### Positive

- Admins get a consistent workflow for approving, rejecting, and requesting edits.
- Per-type queues reduce moderation mistakes.
- Bilingual rejection notes become mandatory and auditable.
- Material edits cannot silently bypass review.
- Bulk decisions improve operations throughput while preserving lifecycle rules.
- Search and notification listeners can rely on after-commit domain events.

### Negative

- Requires schema alignment if current migrations drift from `11_DB_Schema.md`.
- Requires careful tests across all three product types.
- Adds Filament complexity across three resources and page classes.
- Existing duplicate ADR-0013 numbering remains a documentation wart.

### Mitigations

- Keep the workflow in Application actions and thin Filament adapters.
- Add architecture and feature tests for type-aware coverage.
- Document this ADR as the exact Phase 8.0 reference to avoid ambiguity.
- Defer rich moderation history and diff views to Phase 1.5.

---

## 5. Implementation Notes

Implementation belongs to `specs/024-service-moderation-queues`.

Required implementation order:

1. Align `services` schema with locked moderation fields if needed.
2. Update `ServiceStatus` to include `Rejected` and valid transitions.
3. Add service moderation Application actions.
4. Add domain events and emit them after commit.
5. Add Filament action adapters.
6. Add pending queue pages under rental, sale, and digital resources.
7. Add navigation badges for pending counts per type.
8. Add bulk approve, reject, and archive actions.
9. Add EN/AR translation keys.
10. Add Pest coverage for all three product types.

No public or internal API endpoints are added by this ADR.

---

## 6. Testing Strategy

Pest groups: `catalog`, `service-moderation`, `rental`, `sale`, `digital`.

Required tests:

- approve rental, sale, and digital services
- reject rental, sale, and digital services with EN+AR notes
- reject validation fails when either locale is blank
- request edits delegates to ADR-0018 service change-request workflow
- pending rental queue excludes sale/digital services
- pending sale queue excludes rental/digital services
- pending digital queue excludes rental/sale services
- navigation badges count `pending_review` per type
- bulk approve handles 50 services
- bulk reject persists shared bilingual notes
- material edits return published services to `pending_review`
- non-material edits do not return published services to review
- publish/reject/archive/return-to-review events fire after commit

---

## 7. Cut List

Deferred to Phase 1.5:

- side-by-side diff view of pre/post moderation edits
- rich moderation history timeline
- duplicate-from-existing admin shortcut
- automated moderation SLA escalation

---

## 8. References

- Feature spec: `specs/024-service-moderation-queues/spec.md`
- Plan: `specs/024-service-moderation-queues/plan.md`
- Admin UI contract: `specs/024-service-moderation-queues/contracts/admin-ui.md`
- Catalog ADR: `docs/adr/0004-catalog-module.md`
- Changes-requested ADR: `docs/adr/ADR-0018-changes-requested-workflow.md`
- Phase 8.0: `docs/specs/09_Phasing_Plan.md`
- Admin Journey: `docs/specs/08_Admin_Journey.md`
