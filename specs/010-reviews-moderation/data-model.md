# Data Model: Reviews + Moderation (Phase 5.1)

**Date**: 2026-05-03
**Source schema**: [`docs/specs/11_DB_Schema.md`](../../docs/specs/11_DB_Schema.md) §10 (locked)
**ADR**: [ADR-0011](../../docs/adr/0011-reviews-module.md)

---

## Entity-Relationship Overview

```
                          ┌──────────────────────┐
                          │ users (Identity)     │
                          └──────────┬───────────┘
                                     │ user_id (reviewer)
                                     │ moderated_by, moderator_id
                                     ▼
        ┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
        │ services         │    │ service_reviews  │    │ vendor_profiles │
        │ (Catalog)        │◄───│  service_id      │    │ (Identity)      │
        │ rating_avg       │    │  booking_item_id │    │ rating_avg      │
        │ rating_count     │    │  user_id         │    │ rating_count    │
        └─────────────────┘    └──────────────────┘    └────────┬────────┘
                ▲                       │                        │
                │                       │ booking_item_id        │ vendor_profile_id
                │                       ▼                        │
                │              ┌──────────────────┐              │
                │              │ booking_items    │              │
                │ service_id   │ (Booking)        │              │
                │              │  item_status     │              │
                │              └──────────────────┘              │
                │                                                 │
                │              ┌──────────────────┐    ┌─────────┴────────┐
                │              │ booking_vendors  │◄───│ vendor_reviews   │
                │              │ (Booking)        │    │  vendor_profile_id│
                │              └──────────────────┘    │  booking_vendor_id│
                │                                       │  user_id         │
                │                                       └──────────────────┘
                │                                                 ▲
                │                                                 │
                ▼                                                 │
        ┌──────────────────────────────────────────┐              │
        │ review_responses (polymorphic)           │              │
        │   review_type ENUM('service','vendor')   │              │
        │   review_id (no FK; logical pointer)     │              │
        └──────────────────────────────────────────┘              │
                                                                  │
        ┌──────────────────────────────────────────┐              │
        │ review_moderation_log (APPEND-ONLY,      │              │
        │   polymorphic by review_type)            │              │
        │   review_type, review_id, from→to,       │◄─────────────┘
        │   moderator_id, reason (JSON), created_at│
        └──────────────────────────────────────────┘
```

---

## 1. `service_reviews`

**Module**: Reviews
**Owns**: customer feedback per `booking_item`

### Columns

| Column | Type | Null | Default | Notes |
|---|---|:---:|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `public_id` | CHAR(26) | NO | — | UNIQUE; ULID; exposed in API URLs |
| `service_id` | BIGINT UNSIGNED | NO | — | FK → `services.id` ON DELETE RESTRICT |
| `booking_item_id` | BIGINT UNSIGNED | NO | — | FK → `booking_items.id` ON DELETE RESTRICT; **UNIQUE** |
| `user_id` | BIGINT UNSIGNED | NO | — | FK → `users.id` ON DELETE RESTRICT (reviewer) |
| `rating` | TINYINT UNSIGNED | NO | — | 1..5 (CHECK or app-level validation) |
| `body` | TEXT | YES | NULL | Plain text; HTML escaped on render |
| `locale` | ENUM('ar','en','mixed') | NO | — | Stamped from `customer_profiles.preferred_locale` |
| `moderation_status` | ENUM('pending','approved','rejected','hidden') | NO | `pending` | State machine via `spatie/laravel-model-states` |
| `moderated_by` | BIGINT UNSIGNED | YES | NULL | FK → `users.id` ON DELETE RESTRICT |
| `moderated_at` | TIMESTAMP | YES | NULL | Set on first transition out of `pending` |
| `created_at` | TIMESTAMP | NO | — | |
| `updated_at` | TIMESTAMP | NO | — | |
| `deleted_at` | TIMESTAMP | YES | NULL | Soft delete (whitelisted) |

### Indexes

- `PRIMARY KEY (id)`
- `UNIQUE KEY public_id (public_id)`
- `UNIQUE KEY booking_item_id (booking_item_id)` — enforces FR-R3 at DB level
- `INDEX (service_id, moderation_status)` — public listing + AVG aggregation
- `INDEX (user_id, created_at)` — customer's "my reviews"

### Validation rules (in `SubmitServiceReviewRequest` + Action)

- `rating` required, integer, between:1,5
- `body` nullable, string, max:2000
- `booking_item_id` resolved from path `{bookingItemPublicId}` → ownership + completed status checked via `BookingItemReviewabilityReader`
- `service_id` derived from `booking_items.service_id` (Reviews never trusts client input for this — read via Booking contract)
- `user_id` set from authenticated user
- `locale` set from `customer_profiles.preferred_locale`

### State transitions (FR-R7, Clarifications §Q2)

```
                    submit
       ┌────────────────────────┐
       │                        │
       ▼                        │
   ┌────────┐                   │
   │ pending│                   │
   └───┬────┘                   │
       │                        │
       ├──── approve ──────► ┌──────────┐ ────── hide ─────► ┌────────┐
       │                     │ approved │                    │ hidden │
       ├──── reject ───────► ┌──────────┐ ◄──── unhide ──── └────────┘
       │                     │ rejected │ ◄─ reject ──┐
       │                     └──────────┘             │
       │                          ▲                   │
       │                          │ reject (terminal) │
       └──────────────────────────┘                   │
                                                       └────────────────  approve(rejected) is FORBIDDEN
```

**Allowed transitions:** `pending → approved`, `pending → rejected`, `approved → hidden`, `hidden → approved`, `approved → rejected`.
**Forbidden transitions:** anything out of `rejected` (terminal), `pending → hidden` (must approve first), `hidden → rejected` (admin must reinstate to approved then reject).

### Behaviors

- On INSERT: `moderation_status='pending'`, `moderated_by=null`, `moderated_at=null`, `ReviewSubmitted` event after commit.
- On state transition via `ModerateReviewAction`: log row appended; `ReviewApproved` / `ReviewRejected` / `ReviewHidden` event after commit; `moderated_by` and `moderated_at` updated.
- On soft-delete (customer initiates): `deleted_at` set; `ReviewSelfDeleted` event after commit.

---

## 2. `vendor_reviews`

**Module**: Reviews
**Owns**: customer feedback per `booking_vendor`

### Columns

| Column | Type | Null | Default | Notes |
|---|---|:---:|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `public_id` | CHAR(26) | NO | — | UNIQUE; ULID |
| `vendor_profile_id` | BIGINT UNSIGNED | NO | — | FK → `vendor_profiles.id` ON DELETE RESTRICT |
| `booking_vendor_id` | BIGINT UNSIGNED | NO | — | FK → `booking_vendors.id` ON DELETE RESTRICT; **UNIQUE** |
| `user_id` | BIGINT UNSIGNED | NO | — | FK → `users.id` ON DELETE RESTRICT |
| `rating` | TINYINT UNSIGNED | NO | — | 1..5 |
| `body` | TEXT | YES | NULL | |
| `locale` | ENUM('ar','en','mixed') | NO | — | |
| `moderation_status` | ENUM('pending','approved','rejected','hidden') | NO | `pending` | |
| `moderated_by` | BIGINT UNSIGNED | YES | NULL | FK → `users.id` |
| `moderated_at` | TIMESTAMP | YES | NULL | |
| `created_at` | TIMESTAMP | NO | — | |
| `updated_at` | TIMESTAMP | NO | — | |
| `deleted_at` | TIMESTAMP | YES | NULL | Soft delete |

### Indexes

- `PRIMARY KEY (id)`
- `UNIQUE KEY public_id (public_id)`
- `UNIQUE KEY booking_vendor_id (booking_vendor_id)`
- `INDEX (vendor_profile_id, moderation_status)`
- `INDEX (user_id, created_at)`

### Validation rules

Same as `service_reviews` except eligibility uses `BookingVendorReviewabilityReader::isReviewable($bookingVendorId, $userId)` which requires **every** `booking_items` row under the booking_vendor to have `item_status='completed'`.

### State transitions

Identical to `service_reviews`.

---

## 3. `review_responses`

**Module**: Reviews
**Owns**: vendor's reply to a customer review (schema only in 5.1; flow in Phase 6.0)

### Columns

| Column | Type | Null | Default | Notes |
|---|---|:---:|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `review_type` | ENUM('service','vendor') | NO | — | Discriminator |
| `review_id` | BIGINT UNSIGNED | NO | — | Polymorphic — no DB FK because target table is determined by `review_type` |
| `vendor_profile_id` | BIGINT UNSIGNED | NO | — | FK → `vendor_profiles.id` ON DELETE RESTRICT (responder) |
| `body` | TEXT | NO | — | |
| `locale` | ENUM('ar','en','mixed') | NO | — | |
| `moderation_status` | ENUM('pending','approved','rejected','hidden') | NO | `pending` | |
| `created_at` | TIMESTAMP | NO | — | |
| `updated_at` | TIMESTAMP | NO | — | |

### Indexes

- `INDEX (review_type, review_id)` — fetch responses for a given review
- `INDEX (vendor_profile_id, moderation_status)` — vendor's response queue

### Behavior in Phase 5.1

- Migration creates the table.
- Eloquent model exists with relationships defined.
- `RespondToReviewAction::execute()` is **scaffold only**: throws `App\Modules\Shared\Exceptions\NotImplementedYet('Phase 6.0')`.
- No customer or vendor endpoint exposed.
- Filament has no UI for this table in 5.1.
- Phase 6.0 will activate the flow without further migrations.

---

## 4. `review_moderation_log` (APPEND-ONLY)

**Module**: Reviews
**Owns**: immutable audit trail of every moderation transition

### Columns

| Column | Type | Null | Default | Notes |
|---|---|:---:|---|---|
| `id` | BIGINT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `review_type` | VARCHAR(80) | NO | — | `'service'`, `'vendor'`, or `'response'` (forward-compat) |
| `review_id` | BIGINT UNSIGNED | NO | — | Polymorphic |
| `from_status` | VARCHAR(40) | YES | NULL | NULL only when reserved for initial-state seed (we do NOT log creation; first transition has from_status=`'pending'`) |
| `to_status` | VARCHAR(40) | NO | — | |
| `moderator_id` | BIGINT UNSIGNED | NO | — | FK → `users.id` ON DELETE RESTRICT |
| `reason` | JSON | YES | NULL | Translatable `{"en": "...", "ar": "..."}` — set at INSERT only |
| `created_at` | TIMESTAMP | NO | `CURRENT_TIMESTAMP` | **No `updated_at`. No `deleted_at`. No soft-delete.** |

### Indexes

- `PRIMARY KEY (id)`
- `INDEX (review_type, review_id, created_at)` — full audit trail per review, chronological
- `INDEX (moderator_id, created_at)` — admin activity reports

### Append-only enforcement

- Migration explicitly omits `$table->timestamps()` — uses `$table->timestamp('created_at')->useCurrent()` instead.
- Eloquent model: `public $timestamps = false;` — model writes `created_at` manually on `create()`.
- No Eloquent `update()` / `save()` calls on existing rows in any Action.
- Architecture test (`AppendOnlyTablesHaveNoSoftDeletesTest`) asserts the model does not use `SoftDeletes` and the table has no `updated_at` column.
- DB-level: a row, once written, is never UPDATEd or DELETEd. Audit completeness depends on this.

---

## 5. Related rating columns (NOT created in 5.1 — already in locked schema)

These columns are owned by Catalog and Identity respectively. The Reviews aggregation listener writes to them via Contracts.

### `services.rating_avg`, `services.rating_count`

| Column | Type | Default | Owner module |
|---|---|---|---|
| `rating_avg` | DECIMAL(3,2) | 0.00 | Catalog |
| `rating_count` | INT UNSIGNED | 0 | Catalog |

Reviews module writes via `Reviews\Domain\Contracts\ServiceRatingWriter::update(int $serviceId, float $newAvg, int $newCount): void` (implementation: `Catalog\Infrastructure\Repositories\EloquentServiceRatingWriter`).

### `vendor_profiles.rating_avg`, `vendor_profiles.rating_count`

| Column | Type | Default | Owner module |
|---|---|---|---|
| `rating_avg` | DECIMAL(3,2) | 0.00 | Identity |
| `rating_count` | INT UNSIGNED | 0 | Identity |

Reviews module writes via `Reviews\Domain\Contracts\VendorRatingWriter::update(int $vendorProfileId, float $newAvg, int $newCount): void` (implementation: `Identity\Infrastructure\Repositories\EloquentVendorRatingWriter`).

---

## Key invariants enforced by the data model

| Invariant | Where enforced |
|---|---|
| One review per `booking_item` (FR-R3) | DB UNIQUE on `service_reviews.booking_item_id` |
| One review per `booking_vendor` (FR-R3) | DB UNIQUE on `vendor_reviews.booking_vendor_id` |
| `rating ∈ [1,5]` (FR-R4) | App-level validation; CHECK constraint optional in MySQL 8.0.16+ |
| Only completed bookings reviewable (FR-R1, FR-R2) | `BookingItemReviewabilityReader` / `BookingVendorReviewabilityReader` contracts in Action |
| `review_moderation_log` immutable (FR-R8) | No `updated_at`, no soft-delete; architecture test |
| Rejection terminal (Clarifications §Q1) | Moderation state machine in `ReviewModerationState`; UNIQUE on booking_item_id blocks resubmission |
| Reviews module isolated (FR-R15) | Architecture test `ReviewsModuleNoCrossImportTest` |
| Approved-only in public listings (FR-R10) | Repository scope `whereModerationStatus('approved')->whereNull('deleted_at')` |

---

## Soft-delete + aggregation interaction

| Review state | Counts toward `rating_avg` / `rating_count`? | Visible in public listing? | Visible in customer's "my reviews"? |
|---|:---:|:---:|:---:|
| `pending` (live) | ❌ | ❌ | ✅ |
| `approved` (live) | ✅ | ✅ | ✅ |
| `rejected` (live) | ❌ | ❌ | ✅ |
| `hidden` (live) | ❌ | ❌ | ✅ |
| `approved` (soft-deleted) | ❌ | ❌ | ✅ (until soft-delete TTL) |
| any (soft-deleted by customer with deleted user) | ❌ | ❌ | ❌ (per FR-R13) |

The aggregation query is therefore: `WHERE moderation_status = 'approved' AND deleted_at IS NULL AND user.deleted_at IS NULL`.
