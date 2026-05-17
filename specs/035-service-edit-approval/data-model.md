# Phase 1 Data Model: Service Material Edit Approval Workflow

**Feature**: `035-service-edit-approval`
**Date**: 2026-05-16

> All three tables are **NEW** ⚠️ NEW TABLES — not yet in `docs/specs/11_DB_Schema.md` (backfill required after ADR-0035 is accepted).

---

## Table 1 — `service_change_requests`

One row per vendor-proposed material edit on a published service. The live `services` row is unchanged until `status = approved`.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PK` | NO | — | Internal ID |
| `public_id` | `CHAR(26)` | NO | — | ULID, exposed in API URLs, UNIQUE |
| `service_id` | `BIGINT UNSIGNED` | NO | — | FK → `services.id`, `restrictOnDelete()` |
| `product_type` | `ENUM('rental','sale','digital')` | NO | — | Denormalized for fast filter on the admin queue |
| `vendor_profile_id` | `BIGINT UNSIGNED` | NO | — | FK → `vendor_profiles.id`, `restrictOnDelete()` (denormalized for auth scope queries) |
| `submitted_by` | `BIGINT UNSIGNED` | NO | — | FK → `users.id`, vendor user who submitted |
| `status` | `ENUM('pending','awaiting_clarification','approved','rejected','cancelled_vendor_suspended','cancelled_service_unavailable')` | NO | `pending` | spatie/laravel-model-states |
| `proposed_changes` | `JSON` | NO | — | Canonical diff payload (see R7) |
| `before_snapshot` | `JSON` | NO | — | Snapshot of `services` + matching detail row + media/availability/pricing-tier state at submission time |
| `vendor_note` | `JSON` | YES | NULL | `{en, ar}` — optional vendor explanation |
| `admin_note` | `JSON` | YES | NULL | `{en, ar}` — required on reject/clarification, optional on approve |
| `clarification_round` | `TINYINT UNSIGNED` | NO | `0` | Increments on each `RequestClarification`; max 3 |
| `decided_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` (admin) |
| `decided_at` | `TIMESTAMP` | YES | NULL | UTC |
| `version` | `INT UNSIGNED` | NO | `1` | Optimistic locking guard |
| `open_lock_key` | `BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('pending','awaiting_clarification'), service_id, NULL)) STORED` | YES | — | Enforces one-pending-edit-per-service |
| `created_at` | `TIMESTAMP` | NO | — | |
| `updated_at` | `TIMESTAMP` | NO | — | |

**Indexes:**
- UNIQUE `(open_lock_key)` — partial uniqueness enforcing one open request per service
- INDEX `(status, product_type, created_at)` — admin queue pagination
- INDEX `(vendor_profile_id, status)` — vendor's pending edits screen
- INDEX `(service_id, created_at)` — history view on a service
- FK `service_id → services.id` ON DELETE RESTRICT
- FK `vendor_profile_id → vendor_profiles.id` ON DELETE RESTRICT
- FK `submitted_by → users.id` ON DELETE RESTRICT
- FK `decided_by → users.id` ON DELETE SET NULL

**Charset/Collation:** `utf8mb4` / `utf8mb4_unicode_ci`.

**Soft deletes:** ❌ none (retention is permanent per FR-EXT-015).

**Update rules:**
- Permitted UPDATEs: `status`, `decided_by`, `decided_at`, `admin_note`, `clarification_round`, `version`.
- All other columns are write-once on INSERT.

---

## Table 2 — `service_change_request_items`

One row per changed field within a change request. Drives the side-by-side diff UI and per-field badges.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PK` | NO | — | |
| `service_change_request_id` | `BIGINT UNSIGNED` | NO | — | FK → `service_change_requests.id`, `cascadeOnDelete()` (cascade safe — only set if parent is hard-deleted, which never happens in app code; cascade is for DDL-level cleanup if a future migration removes the parent) |
| `field_path` | `VARCHAR(191)` | NO | — | Dot-notation, e.g. `base_price_minor`, `service_rental_details.security_deposit_minor`, `gallery_ops[3]`, `pricing_tiers[1].price_minor`, `name.ar` |
| `field_classification` | `ENUM('shared','rental','sale','digital','media','availability','pricing_tier')` | NO | — | Drives badge color in admin UI |
| `before_value` | `JSON` | YES | NULL | NULL allowed for "added" fields (e.g., new pricing tier) |
| `after_value` | `JSON` | YES | NULL | NULL allowed for "removed" fields (e.g., deleted blackout date) |
| `created_at` | `TIMESTAMP` | NO | — | |

**Indexes:**
- INDEX `(service_change_request_id)` — eager load on detail page
- INDEX `(field_classification)` — bulk filtering on admin diff metrics

**Update rules:** Append-only. No updates, no deletes.

---

## Table 3 — `service_change_request_messages`

Append-only thread of clarification messages between admin and vendor for a single change request.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PK` | NO | — | |
| `service_change_request_id` | `BIGINT UNSIGNED` | NO | — | FK → `service_change_requests.id`, `cascadeOnDelete()` |
| `author_user_id` | `BIGINT UNSIGNED` | NO | — | FK → `users.id` |
| `author_role` | `ENUM('admin','vendor')` | NO | — | Denormalized for fast lookup |
| `body` | `JSON` | NO | — | `{en, ar}` — bilingual required |
| `clarification_round` | `TINYINT UNSIGNED` | NO | — | Snapshot of parent round counter at write time |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | append-only — no `updated_at` |

**Indexes:**
- INDEX `(service_change_request_id, created_at)` — thread render order

**Update rules:** Append-only. No updates, no deletes.

---

## State Machine — `ServiceChangeRequestStatus`

Implemented via `spatie/laravel-model-states`.

```text
                  RequestClarification
        +------> awaiting_clarification ----+ VendorReply
        |              |                    |
        |              | Approve / Reject   v
[pending] <-----------+----+-----------> [pending]
   |                       |
   | Approve               | Reject
   v                       v
[approved]              [rejected]
   ^                       ^
   | (terminal)            | (terminal)

[cancelled_vendor_suspended]   ← from any non-terminal state on user suspension
[cancelled_service_unavailable] ← from any non-terminal state on service archive
```

**Allowed transitions:**

| From | To | Trigger |
|---|---|---|
| `pending` | `approved` | `ApproveServiceChangeRequestAction` |
| `pending` | `rejected` | `RejectServiceChangeRequestAction` |
| `pending` | `awaiting_clarification` | `RequestServiceChangeClarificationAction` |
| `awaiting_clarification` | `pending` | `ReplyToServiceChangeClarificationAction` (vendor reply) |
| `awaiting_clarification` | `approved` | `ApproveServiceChangeRequestAction` (admin override — allowed) |
| `awaiting_clarification` | `rejected` | `RejectServiceChangeRequestAction` (admin override — allowed) |
| `pending` / `awaiting_clarification` | `cancelled_vendor_suspended` | suspension flow |
| `pending` / `awaiting_clarification` | `cancelled_service_unavailable` | archive flow |
| `approved` / `rejected` / `cancelled_*` | (terminal) | no further transitions |

**Guards:**
- `RequestClarification` blocked when `clarification_round >= 3`.
- All decision transitions guarded by optimistic-lock `version` increment.
- All transitions require `Gate::authorize('service.moderate.' . $product_type->value, $service)` for admin actions, or vendor ownership for vendor reply.

---

## Validation Rules (FormRequest-enforceable)

| Rule | Source | Where enforced |
|---|---|---|
| `vendor_note.en` and `vendor_note.ar` both required if vendor supplies a note | FR-EXT-002 + Constitution §IV | `VendorSubmitServiceEditFormRequest` |
| `admin_note.en` and `admin_note.ar` both required on reject | FR-EXT-005 | `RejectServiceChangeRequest` |
| `admin_note.en` and `admin_note.ar` both required on clarification | FR-EXT-006 | `RequestServiceChangeClarificationRequest` |
| `body.en` and `body.ar` both required on every clarification message | FR-EXT-007 + Constitution §IV | `ReplyServiceChangeClarificationRequest` |
| `clarification_round` may not exceed 3 | FR-EXT-006 | `RequestServiceChangeClarificationRequest::authorize()` |
| Only one pending/awaiting_clarification request per `service_id` | FR-EXT-003 | DB unique + `SubmitServiceChangeRequestAction` re-check |
| Admin reason required to be ≤ 4000 chars per locale | Standard limits | FormRequest `max:4000` |

---

## Derived/Computed Properties

| Property | Definition | Where computed |
|---|---|---|
| `field_count` | `service_change_request_items.count()` | Filament table column |
| `is_locked` | `status IN ('pending','awaiting_clarification')` | Service model accessor for vendor portal |
| `has_open_request` | scope on `Service::hasOpenChangeRequest()` | Service model scope |

---

## Cross-table consistency invariants (enforced by tests)

1. **Apply-step atomicity**: After `ApproveServiceChangeRequestAction` returns, the live `services` + matching `service_{type}_details` row reflects every field in `proposed_changes`. Verified by hash-compare Pest test.
2. **Live-row immutability while pending**: For any change request in `pending` or `awaiting_clarification`, the live `services` row and matching detail row are unchanged from `before_snapshot`. Verified by Pest discovery-endpoint test (SC-001).
3. **Audit log parity**: Number of `audit_logs` rows referencing the change request = number of state transitions + (on approval) number of items applied. Verified by `WriteServiceChangeRequestAuditListener` integration test.
4. **Message append-only**: No row in `service_change_request_messages` is ever updated. Enforced by an architecture test that scans for UPDATE statements targeting that table.

---

## Migration ordering

1. `2026_05_16_000010_create_service_change_requests_table.php`
2. `2026_05_16_000011_create_service_change_request_items_table.php`
3. `2026_05_16_000012_create_service_change_request_messages_table.php`

No changes to existing tables. No new columns on `services` or detail tables.

---

## ⚠️ Backfill required after merge

- `docs/specs/11_DB_Schema.md`: add three tables under the Catalog module section (current count goes from 13 → 16 in Catalog; total goes from 60 → 63 across all modules).
- `.claude/rules/schema-cheatsheet.md`: add the three tables to the Catalog inventory line.
- `docs/specs/01_PRD.md` §5: add FR-EXT-001..015.
- `docs/specs/09_Phasing_Plan.md`: insert "Phase 8.0.1 — Staged Material Edit Approval (2 days)" after Phase 8.0.
- `.specify/memory/project-index.md`: add ADR-0035 row.
