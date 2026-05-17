# Phase 1 Data Model — Admin Booking Intervention Page

> Describes every entity touched by this feature: its fields, validation rules, state transitions, and the new enum cases. No new top-level tables. Two delta migrations only.

---

## Entities (existing — reused as-is unless noted)

### `bookings`

- **Owner**: Booking module
- **This feature**: READ-only from the listing query and detail view. Never mutates `bookings` columns. Status badges shown: `lifecycle_status`, `payment_status`, `fulfillment_status`.

### `booking_vendors`

- **Owner**: Booking module
- **This feature**: READ for listing + detail. UPDATE in **one** Action only: `EscalateLateVendorResponseAction` flips `sub_status` from `pending` → `timed_out`. No other columns mutated.
- **Mutable columns in scope**: `sub_status` only.
- **Guard**: pessimistic row lock (`SELECT … FOR UPDATE`) inside the transaction prevents two admins from double-timing-out the same row.

### `booking_modifications`

- **Owner**: Booking module
- **This feature**: READ only — used to detect "customer review pending" (open modification + age > N hours) and shown in the detail view's "Open modifications" section.

### `booking_admin_interventions`

- **Owner**: Booking module
- **This feature**: INSERT-only — every Action appends exactly one row.
- **Constraints**:
  - `intervention_type = 'vendor_proposal'` MUST have `proposed_vendor_id IS NULL` (enforced by FR-EXT-012 policy + a check constraint deferred to migration step if MariaDB version supports it; otherwise application-level only)
  - `before_state` and `after_state` are JSON; `after_state.suggested_vendor_ids = [...]` for `vendor_proposal`
- **Fillable extension required**: the existing model's `$fillable` already includes `proposed_vendor_id`; no model change needed.

### `state_transitions`

- **Owner**: Shared / Booking-cross-cutting
- **This feature**: INSERT-only — `EscalateLateVendorResponseAction` writes one row with `transitionable_type = App\Modules\Booking\Domain\Models\BookingVendor`.
- **Required fields**: `transitionable_type`, `transitionable_id`, `from_state`, `to_state`, `trigger_kind = 'admin'`, `triggered_by`, `context` (JSON containing `intervention_id`, `reason`, `booking_id`).

### `audit_logs`

- **Owner**: Cross-cutting (`app/Modules/Shared/`)
- **This feature**: INSERT-only — every Action appends one row.
- **Required fields**: `auditable_type = App\Modules\Booking\Domain\Models\Booking`, `auditable_id`, `user_id = adminId`, `action ∈ { 'booking.vendor_reminder', 'booking.vendor_timeout', 'booking.suggest_alternatives', 'booking.chat_frozen', 'booking.chat_resumed', 'booking.customer_review_reminder', 'booking.admin_note' }`, `changes` (JSON: at minimum `{ before, after, booking_vendor_id?, reason }`).

### `notification_dispatches`

- **Owner**: Communication module
- **This feature**: INSERT only via the `NotificationDispatcher` contract — never direct model use from Booking code.
- **Append-only** per constitution §V.

### `admin_inbox_items`

- **Owner**: Communication module
- **This feature**: INSERT only via the **new** `AdminInboxWriter` contract — never direct model use from Booking listeners.
- **Required fields**: `source_type = 'booking_admin_intervention'`, `source_id = intervention.id`, `severity ∈ { AdminInboxSeverity::Medium }` for the escalation case, `title` + `body` (JSON, EN + AR), `assigned_to_admin_id = adminId` (the escalating admin).

### `idempotency_keys`

- **Owner**: Cross-cutting
- **This feature**: INSERT + READ for `SendVendorReminderAction` and `ResumeBookingReviewAction`. See research §R-2 for scopes and TTLs.

### `chat_threads` *(soft dependency — see research §R-1)*

- **Owner**: Communication module
- **This feature**: UPDATE `frozen_at`, `frozen_by` from the freeze/resume Actions.
- **Required column delta**:
  - `frozen_at TIMESTAMP NULL`
  - `frozen_by BIGINT UNSIGNED NULL` FK → `users.id` `nullOnDelete()`
  - Indexed on `(booking_id, frozen_at)` to keep the "find frozen threads" admin query cheap
- **⚠️ Gated**: only land this migration if the base `chat_threads` table already exists.

### `notification_templates`

- **Owner**: Communication module
- **This feature**: INSERT new rows via migration seeder. See research §R-7 for the 7 event keys.

---

## New / modified domain types

### `InterventionType` enum (MODIFIED)

**Path**: `app/Modules/Booking/Domain/Enums/InterventionType.php`

**Existing cases** (unchanged):
- `ForceCancel = 'force_cancel'`
- `VendorTimeout = 'vendor_timeout'`
- `VendorProposal = 'vendor_proposal'`
- `AdminNote = 'admin_note'`

**New cases** (added by this feature):
- `VendorReminder = 'vendor_reminder'`
- `ChatFrozen = 'chat_frozen'`
- `ChatResumed = 'chat_resumed'`
- `CustomerReviewReminder = 'customer_review_reminder'`

**Migration impact**: the DB column type for `booking_admin_interventions.intervention_type` is `VARCHAR(64)` (or `ENUM` per the original Phase 6.5 migration). If it is an `ENUM`, an `ALTER TABLE` migration is required to extend the allowed values. If it is `VARCHAR`, no migration is required.

**Preflight in `/speckit.implement`**: `SHOW CREATE TABLE booking_admin_interventions \G` — read the `intervention_type` column definition. If `ENUM(...)`, add migration `2026_05_15_100003_extend_intervention_type_enum.php`.

### `AdminInterventionDTO` (existing, REUSED)

**Path**: `app/Modules/Booking/Application/DTOs/AdminInterventionDTO.php`

**Fields** (unchanged signature):
- `int $bookingId`
- `int $adminId`
- `InterventionType $interventionType`
- `string $reason`
- `?int $bookingVendorId = null` *(NEW field — extends current DTO; nullable so existing `ForceCancelBookingAction` is unaffected)*
- `?int $proposedVendorId = null` *(NEW field — kept NULL for `vendor_proposal` per FR-EXT-012)*
- `?array $suggestedVendorIds = null` *(NEW field — only populated for `vendor_proposal`)*

### `SuggestedAlternativeVendorsDTO` (NEW)

**Path**: `app/Modules/Booking/Application/DTOs/SuggestedAlternativeVendorsDTO.php`

**Fields**:
- `int $bookingId`
- `int $adminId`
- `array<int> $vendorProfileIds` *(min 1, max `config('booking.intervention.suggest_max_candidates', 5)`)*
- `string $reason` *(1..1000 chars)*

**Validation**:
- `vendorProfileIds`: each must (a) exist, (b) be approved, (c) be approved for the booking's `product_type`, (d) cover the booking's governorate, (e) not already be on `booking_vendors` for this booking. The Action delegates this check to `AlternativeVendorFinder::validateCandidates(Booking, array)`.

---

## State transitions in scope

### `BookingVendor.sub_status` — admin-driven escalation

| From | To | Action | Guard |
|---|---|---|---|
| `pending` | `timed_out` | `EscalateLateVendorResponseAction::execute()` | `response_deadline + grace_period < now()` AND row lock acquired |

All other sub-status transitions remain owned by `VendorAcceptBookingAction`, `VendorRejectBookingAction`, `VendorModifyBookingAction`, and `MarkBookingItemStateAction` — out of scope here.

### `Booking.lifecycle_status` — NONE

This feature does not move the booking lifecycle. Even after escalation, lifecycle remains at `vendor_review` (or wherever it was). The customer-driven flow (pick from suggested alternatives, accept modification, etc.) advances lifecycle.

### `chat_threads.frozen_at` toggle *(gated)*

| From | To | Action | Guard |
|---|---|---|---|
| `NULL` | `now()` | `FreezeBookingChatAction::execute()` | thread exists; not already frozen |
| `<set>` | `NULL` | `ResumeBookingChatAction::execute()` | thread exists; currently frozen |

---

## Validation rules summary

| Field / input | Rule |
|---|---|
| `reason` on every Action | required, 10..1000 chars, plain text |
| Note body (`CreateAdminInterventionNoteAction`) | required, 1..2000 chars, plain text |
| `vendorProfileIds` (`SuggestAlternativeVendorsAction`) | required array, 1..N (N from config, default 5), each must pass candidate-filter `AlternativeVendorFinder::validateCandidates` |
| `intervention_type = vendor_proposal` row | `proposed_vendor_id IS NULL` (FR-EXT-012) |
| Escalation guard | `response_deadline + grace_period < now()` |
| Reminder throttle | no `idempotency_keys` row with scope `admin.intervention.vendor_reminder`, key `booking_vendor_id`, `expires_at > now()` |
| Customer-review reminder throttle | no `idempotency_keys` row with scope `admin.intervention.customer_review_reminder`, key `booking_id`, `expires_at > now()` |
| Freeze guard | `chat_threads.frozen_at IS NULL` |
| Resume guard | `chat_threads.frozen_at IS NOT NULL` |

---

## Derived "trouble bucket" query (listing)

**Decision**: derive at query time — no new column on `bookings`.

```sql
-- pseudocode shape; actual query lives in the Resource's getEloquentQuery()
SELECT b.*,
  EXISTS (SELECT 1 FROM booking_vendors bv WHERE bv.booking_id = b.id
          AND bv.sub_status = 'pending' AND bv.response_deadline < NOW())     AS late_vendor_response,
  EXISTS (SELECT 1 FROM booking_vendors bv WHERE bv.booking_id = b.id
          AND NOT EXISTS (SELECT 1 FROM booking_vendors bv2
                          WHERE bv2.booking_id = b.id AND bv2.sub_status <> 'rejected'))
                                                                              AS all_vendors_rejected,
  EXISTS (SELECT 1 FROM booking_modifications bm
          JOIN booking_vendors bv ON bv.id = bm.booking_vendor_id
          WHERE bv.booking_id = b.id AND bm.status = 'pending'
          AND bm.created_at < NOW() - INTERVAL 24 HOUR)                       AS customer_review_pending,
  (b.lifecycle_status IN ('submitted','vendor_review')
   AND b.submitted_at < NOW() - INTERVAL 48 HOUR)                             AS stalled
FROM bookings b
WHERE b.deleted_at IS NULL
  AND b.lifecycle_status NOT IN ('completed','cancelled')
  AND (late_vendor_response OR all_vendors_rejected OR customer_review_pending OR stalled)
```

**Performance note**: relies on existing indexes per `11_DB_Schema.md` cheatsheet:
- `booking_vendors (vendor_profile_id, sub_status, response_deadline)` — used by the late-vendor subquery
- `bookings (lifecycle_status, submitted_at)` — used by the stalled subquery

If query plans show full scans on `booking_modifications` for the customer-review subquery, add `(booking_vendor_id, status, created_at)` index as a follow-up — not in scope for this PR.

---

## Per-product-type story

- `bookings.product_type` is set at booking creation. The listing query carries `product_type` through for badging and filtering.
- `SuggestAlternativeVendorsAction` is the only Action whose **logic** depends on product type — see research §R-5 (item 2 of the filter list).
- All other Actions are type-agnostic.
- Pest tests assert the listing renders for each type; the suggestion action has three feature tests (one per type).

---

## Bilingual story

| String surface | Source |
|---|---|
| Admin page nav label, table column headers, filter labels | `app/Modules/Booking/Resources/lang/{en,ar}/booking.php` keys under `booking.intervention.*` |
| Action button labels, confirmation modals, success/error toasts | same |
| Notification subjects + bodies | `notification_templates` rows seeded with both `subject` and `body` JSON (EN + AR) |
| Admin inbox `title`, `body` | `AdminInboxWriter::create()` accepts arrays `['en' => ..., 'ar' => ...]` |
| Vendor `business_name`, customer notes, rejection_reason rendered in detail view | rendered via the model's `getTranslation('field', app()->getLocale())` |

---

## Append-only invariants — confirmation

- `audit_logs`, `state_transitions`, `booking_admin_interventions`, `notification_dispatches` — INSERT-only, no UPDATE in any new Action. Verified by `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` + manual inspection of every Action's `DB::transaction` body.
