# Phase 0 Research: Service Material Edit Approval Workflow

**Feature**: `035-service-edit-approval`
**Date**: 2026-05-16

---

## R1 — Staged-edits vs. revert-to-pending strategy

**Decision**: Adopt a **staged-edits** strategy. Vendor's proposed edits are stored in a new `service_change_requests` table; the live `services` row is unchanged until admin approval. Approval atomically copies the proposed values onto the live row.

**Rationale**:
- Phase 8.0's current `MarkServicePendingReviewForMaterialEditAction` flips `services.status` from `published` back to `pending_review` on a material edit. While simple, it **takes the listing offline** for the duration of admin review (potentially hours/days), which destroys vendor revenue on a price change. The spec's SC-001 (zero dark-window exposure) is unreachable with that approach.
- Staged edits decouple **review state** from **publish state**, which is the correct domain model: the service stays published; a side document represents the proposed mutation.

**Alternatives considered**:
- *Shadow/draft row in `services` with a `parent_service_id` self-reference* — rejected. Pollutes the canonical table, breaks uniqueness constraints (`vendor_profile_id`, `slug`), and complicates Meilisearch indexing (would need to filter out shadow rows from every query).
- *Versioned `services` table with all rows kept and a `current_version_id` pointer* — rejected as Phase 2-scale architecture. Adds churn to every query and Scout index for a feature that only needs a queue of pending diffs.
- *Keep the revert-to-pending approach and only shrink admin SLA* — rejected. Best case admin reviews in 5 minutes; we still take listings dark when admins are asleep. Doesn't meet SC-001.

---

## R2 — Material field set per product type

**Decision**: A single class `MaterialFieldRegistry` holds the per-`ProductType` material field set as constant arrays. Cross-type code uses `match(ProductType)` to dispatch into the correct array. Membership is recursive: pricing tiers, media gallery operations, and availability windows are encoded as virtual field paths (e.g., `pricing_tiers`, `gallery_ops`, `availability_windows`, `excluded_dates`).

**Shared material fields** (all three types):
`name.{en,ar}`, `short_description.{en,ar}`, `long_description.{en,ar}`, `base_price_minor`, `base_price_currency`, `category_id`, `gallery_ops`, `is_available_for_booking`, `availability_windows`, `excluded_dates`, `pricing_tiers`.

**Rental-only material**: `service_rental_details.*` except `internal_notes`, `vendor_contact_phone` (non-material). Specifically material: `security_deposit_minor`, `default_rental_duration_hours`, `setup_time_minutes`, `requires_electricity`, `requires_outdoor_space`, `min_age`, `max_capacity`.

**Sale-only material**: `service_sale_details.*` except `internal_sku`. Specifically material: `is_perishable`, `is_made_to_order`, `lead_time_hours`, `stock_quantity`, `customization_fields`.

**Digital-only material**: `service_digital_details.*` except `internal_notes`. Specifically material: `delivery_method`, `has_expiry`, `expiry_days_after_purchase`, `is_refundable_after_delivery`, `redemption_url_template`.

**Rationale**: Phase 8.0 (`09_Phasing_Plan.md` line 1602) names *price, category, title, short/long description, core media* as the canonical "material" baseline. We extend it to cover availability/pricing-tier/type-specific detail edits because all of those affect customer-visible behavior or commercial terms. Non-material exclusions (internal notes, internal SKU, vendor contact phone) are admin-invisible operational fields that vendors should be able to update freely.

**Alternatives considered**:
- *Make every column on `services` and detail tables material by default* — rejected. Floods the admin queue with trivial internal-note edits and degrades P95 review SLA.
- *Per-column boolean in a config file* — rejected. Harder to test exhaustively and harder to dispatch via `match(ProductType)`.

---

## R3 — One-pending-edit-per-service enforcement

**Decision**: Enforce via **partial unique index** on `service_change_requests.service_id` filtered to `status IN ('pending', 'awaiting_clarification')`. Application layer also runs a `lockForUpdate` on the service row inside the `Submit*` Action transaction.

**Rationale**:
- MySQL 8 lacks native partial unique indexes, but the project targets MySQL 8 + MariaDB 11 (`02_Tech_Decisions.md`). We emulate it via a generated column `open_lock_key` = `IF(status IN ('pending','awaiting_clarification'), service_id, NULL)` + a UNIQUE index on that column. Mirrors the pattern used in `booking_modifications.draft_slot` (cited from `11_DB_Schema.md`).
- Defense-in-depth: the `Submit*` Action additionally `lockForUpdate`s the service inside the transaction and re-checks for an open request, so a race on the generated column raises a 409 ValidationException rather than a SQL-level constraint error.

**Alternatives considered**:
- *Application-layer check only* — rejected. Race condition on simultaneous vendor saves (e.g., from two browser tabs) would allow two open requests.
- *Pessimistic `booking_locks`-style table* — rejected. Heavier-weight than needed; the generated-column pattern is already established.

---

## R4 — Atomic apply step per product type

**Decision**: `ApplyServiceChangeToLiveAction` handles the apply step using `match(ProductType)` to dispatch into a type-specific applier (`applyRental`, `applySale`, `applyDigital`). All updates happen inside one `DB::transaction`. Shared columns on `services` are updated first; the corresponding `service_{type}_details` row is updated second; media gallery operations replay last (so a mid-transaction failure rolls everything back including media-library sync via the queued listener that fires `afterCommit` only).

**Rationale**:
- Single Action per spec convention (`actions.md`: "fat, single-purpose"). Cross-type apply is unavoidable since both base and detail tables update — the `match(ProductType)` dispatch confines it to one place.
- The media-library plugin sync (reordering/adding/removing in `gallery` collection) is idempotent at the model level (`syncMedia`) and is replayed inside the transaction. The Scout indexer subscribes to Eloquent `saved` events and queues the re-index automatically.

**Alternatives considered**:
- *Three separate Actions (`ApproveRentalServiceChangeAction`, etc.)* — rejected. Triplicates 80% of identical code (status mutation, audit, event); only the detail-table update is type-specific. `match(ProductType)` inside a single Action is the canonical pattern from `02_Tech_Decisions.md §2.2`.
- *Apply changes through a queued job* — rejected. Decoupling the apply from the admin's click introduces user-visible delay and a race window where the admin sees the request marked `approved` but the live row hasn't updated yet.

---

## R5 — Concurrent admin decision protection

**Decision**: Optimistic locking via a `version` column on `service_change_requests`. Every decision Action increments `version` and the UPDATE uses `WHERE id = ? AND version = ?`. If 0 rows affected → throw HTTP 409 with bilingual "already decided" message.

**Rationale**:
- Cheaper than `booking_locks`-style row locks for a low-contention surface (admins rarely open the same change request concurrently).
- Filament's row-action click hits an endpoint; on 409 the page reloads with the latest state — no orphaned UI.

**Alternatives considered**:
- *Pessimistic `lockForUpdate` on every Filament page load* — rejected. Holds DB row locks for the duration of admin reading time, harmful under load.
- *`booking_locks`-style separate lock table* — rejected. Over-engineered for this surface.

---

## R6 — Bilingual notification templates

**Decision**: Add three notification templates to `notification_templates`:
- `service.change_request.approved`
- `service.change_request.rejected`
- `service.change_request.clarification_requested`

Each is bilingual (EN + AR) and routed to the vendor user with channels `push` + `email`. Routing reuses the existing `Communication` module pipeline (spec 009).

**Rationale**: Established pattern; no new infrastructure needed.

**Alternatives considered**:
- *Inline notification creation in each Action* — rejected. Bypasses the centralized template management.

---

## R7 — Diff representation in storage and UI

**Decision**: `service_change_requests.proposed_changes` is a single `JSON` column shaped as:

```json
{
  "shared": {"name": {"en": "...", "ar": "..."}, "base_price_minor": 12500},
  "type_specific": {"security_deposit_minor": 50000},
  "gallery_ops": [{"op": "add", "media_id": 4711}, {"op": "remove", "media_id": 4500}],
  "availability_windows": [...],
  "excluded_dates": [...],
  "pricing_tiers": [...]
}
```

Per-field `service_change_request_items` rows mirror this structure flattened for query/badge rendering: one row per changed field, each carrying `field_path` (dot-notation), `before_value` (JSON), `after_value` (JSON), and `field_classification` (enum: `shared`, `rental`, `sale`, `digital`, `media`, `availability`, `pricing_tier`).

**Rationale**:
- The JSON blob on the parent row is the canonical apply-step input (single-source-of-truth, mirrors `booking_snapshots` from `11_DB_Schema.md`).
- Per-field items power the side-by-side diff UI and per-field badges in the admin Filament page without re-parsing JSON every render.
- Both representations are written in the same transaction by `SubmitServiceChangeRequestAction`; if they diverge, the JSON blob wins and an audit log flag is raised.

**Alternatives considered**:
- *Only the JSON blob, no per-field items* — rejected. Admin UI rendering requires per-field rows for table-style listing and badge counts.
- *Only per-field items, no JSON blob* — rejected. Reassembling the apply input from N rows is fragile and ordering-sensitive (gallery operations must replay in order).

---

## R8 — Permission model

**Decision**: Reuse existing per-type `service.moderate.{rental|sale|digital}` permissions from Phase 8.0 (cited from `08_Admin_Journey.md` and `bezhansalleh/filament-shield` registry). Each decision Action authorizes with `Gate::authorize('service.moderate.' . $service->product_type->value, $service)`.

**Rationale**: Established pattern; no new permission strings needed. Filament Shield automatically renders/hides rows and bulk actions per permission.

**Alternatives considered**:
- *New permission `service.change_request.approve`* — rejected. Adds permission sprawl when product-type-scoped moderation is already the right granularity.

---

## R9 — Deprecating `MarkServicePendingReviewForMaterialEditAction`

**Decision**: Keep the class for non-published edge cases (services in `draft`/`pending_review` that get material-touched). For `published` services, callers route through `SubmitServiceChangeRequestAction` instead. A `@deprecated` PHPDoc and a clear branch in the calling controller mark the boundary. Full removal is deferred until Phase 1.5 once we confirm no remaining callers hit the published branch.

**Rationale**: Safer than ripping out the existing Action and risking regressions on the non-published flow. The new Action is the gate; the old Action becomes a no-op for published services.

**Alternatives considered**:
- *Hard-remove the old Action* — rejected. Brittle until we have full call-site visibility via a Pest integration run.

---

## R10 — Test strategy

**Decision**: One Pest file per scenario class (see `plan.md` structure). Each file uses `it()` blocks with `->group('rental'|'sale'|'digital')` so the per-type CI matrix already in `.github/workflows/ci.yml` runs them in parallel. The `MaterialFieldRegistryTest` walks every member of every per-type array and asserts each is correctly classified by `DetectMaterialServiceChangesAction`.

**Rationale**: Matches `03_Three_Product_Types.md` "test all three" mandate and Pest grouping convention from `CLAUDE.md` Testing Conventions.

**Alternatives considered**:
- *Single parameterized test per scenario* — rejected. Less readable failure output and harder to grep failing scenarios.

---

## Resolved NEEDS CLARIFICATION

The spec had **no** `[NEEDS CLARIFICATION]` markers; all open questions were resolved via reasonable defaults documented in the spec's Assumptions section. This research file documents the decisions behind those defaults so they can be revisited in `/speckit.clarify` if needed.

## Outstanding (deferred to Phase 1.5)

- Stale-edit auto-reminder (>14 days pending) → admin inbox routing (spec 019).
- Configurable `MAX_CLARIFICATIONS` via `app_settings` instead of hardcoded constant.
- Public `GET /v1/vendor/services/{id}/change-requests` endpoint for the mobile vendor app.
