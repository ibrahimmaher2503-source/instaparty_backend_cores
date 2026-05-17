# ADR-0035: Service Material Edit Approval Workflow

**Date**: 2026-05-16
**Status**: Proposed
**Relates to**: Feature 035 (Service Material Edit Approval), Phase 8.0 (Service Moderation), ADR-0013 (Admin Service Moderation)

---

## Context

**Problem**: Phase 8.0 (`MarkServicePendingReviewForMaterialEditAction`) handles material edits on published services by reverting them to `pending_review`. This creates a **dark window**: the listing disappears from discovery for the entire admin review period (potentially hours or days). For a vendor mid-season raising prices or updating availability, this destroys revenue on an active listing.

**Material edits** include: name, short/long description, base price, category, gallery images, availability windows, excluded dates, pricing tiers, and all product-type-specific detail columns that affect customer-visible behavior or commercial terms.

**Non-material edits** (internal notes, internal SKU, vendor contact phone) should continue to apply directly with no queue.

**Phase 1 requirement (SC-001)**: Zero dark-window exposure — a published service's listing must remain visible to customers throughout the admin review cycle.

---

## Decision

Adopt a **staged-edits** strategy:

1. When a vendor saves a material change to a `published` service, the live `services` row is **left unchanged**.
2. The proposed changes are stored in a new `service_change_requests` table alongside a `before_snapshot` of the current live state.
3. Per-field rows in `service_change_request_items` power the admin diff UI.
4. Admin reviews the diff in a new `PendingServiceEditsPage` and can **Approve**, **Reject**, or **Request Clarification** (max 3 rounds).
5. On **Approve**, `ApplyServiceChangeToLiveAction` atomically copies the proposed values onto the live `services` + matching `service_{type}_details` rows in one `DB::transaction`. Scout re-indexes via `saved` event after commit.
6. On **Reject**, the live row is untouched; the vendor can submit a fresh edit.

### New tables

| Table | Purpose |
|---|---|
| `service_change_requests` | One row per staged edit; optimistic-lock `version` column; `open_lock_key` generated column enforces one open request per service |
| `service_change_request_items` | One row per changed field; drives diff UI; append-only |
| `service_change_request_messages` | Bilingual clarification thread; append-only, no `updated_at` |

### Deprecation

`MarkServicePendingReviewForMaterialEditAction` is annotated `@deprecated` for published services and short-circuited to return the service unchanged when `$service->status === ServiceStatus::Published`. Non-published flows (draft → pending_review) continue to use it unchanged.

---

## Status

**Proposed** — implementation under branch `035-service-edit-approval`. Ratification required before migrations are merged to `002-identity-vendor-onboarding`.

---

## Consequences

### Positive

- Published listings stay live throughout admin review — no revenue impact.
- Clear audit trail: `before_snapshot` captures the exact state at submission time; per-field audit rows written per applied field on approval.
- Bilingual admin notes + clarification thread stored with the request, not in a separate inbox.
- Concurrent admin decisions protected by optimistic locking (`version` column + version-check UPDATE).

### Negative / Trade-offs

- Three new tables (63 total, up from 60).
- Vendors with a `pending` change request cannot submit a second one until the first is resolved (enforced by `open_lock_key` UNIQUE constraint). This is intentional (one-at-a-time queue), but may be limiting if a vendor needs urgent corrections mid-review.
- `ApplyServiceChangeToLiveAction` must replay media gallery ops, availability windows, and pricing tiers in deterministic order — ordering bugs would cause silent partial apply. Mitigated by the `ApplyAtomicityTest` Pest test.
- No bulk-approve in Phase 1 (row-level only). Admin throughput on large queues is bounded by UI. Bulk approve deferred to Phase 1.5.

### Deferred (Phase 1.5 cut-list)

- Stale-edit auto-reminder cron (>14 days pending → admin inbox alert).
- `MAX_CLARIFICATIONS` configurable via `app_settings` instead of hardcoded `3`.
- Vendor-initiated cancel of a pending request (`cancelled_by_vendor` status).
- Bulk approve from the admin queue.
- Public mobile-app endpoint (`GET /v1/vendor/services/{id}/change-requests`).

---

## Alternatives Rejected

### Shadow/draft row in `services` with `parent_service_id` self-reference

Pollutes the canonical table, breaks uniqueness constraints (`vendor_profile_id`, `slug`), and complicates Meilisearch indexing (shadow rows must be filtered from every query). Rejected.

### Versioned `services` table with all rows kept

Phase 2-scale architecture. Adds overhead to every query and Scout index. Rejected for Phase 1.

### Keep revert-to-pending + shrink admin SLA

Even with a 5-minute SLA, listings go dark when admins are unavailable (nights, weekends). Does not meet SC-001. Rejected.

---

## References

- `specs/035-service-edit-approval/research.md` R1–R9 — architectural decisions
- `specs/035-service-edit-approval/data-model.md` — table schemas
- `specs/035-service-edit-approval/contracts/` — API contracts
- `docs/specs/09_Phasing_Plan.md` Phase 8.0 — original `MarkServicePendingReviewForMaterialEditAction` context
- `docs/specs/11_DB_Schema.md` — schema backfill required after merge (13 → 16 tables in Catalog)
