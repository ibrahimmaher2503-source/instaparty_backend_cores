# Data Model: Admin Changes-Requested Workflow

**Branch**: `020-admin-changes-request` | **Date**: 2026-05-04

---

## New Tables

### `change_requests` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md

**Owned by**: `Shared` module
**Module path**: `app/Modules/Shared/Database/Migrations/2026_05_04_000001_create_change_requests_table.php`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | NO | Internal ID |
| `public_id` | CHAR(26) UNIQUE | NO | ULID — exposed in API URLs |
| `subject_type` | ENUM('vendor_profile','service') | NO | Discriminator |
| `subject_id` | BIGINT UNSIGNED | NO | FK to `vendor_profiles.id` or `services.id` (no DB-level FK — polymorphic) |
| `requested_by_admin_id` | BIGINT UNSIGNED FK→users | NO | Admin who created this request |
| `status` | ENUM('open','resubmitted','resolved','escalated_to_rejection') | NO | Default `'open'` |
| `cycle_number` | TINYINT UNSIGNED | NO | 1, 2, or 3. Max is `ChangeRequestPolicy::MAX_CYCLES = 3` |

**Constraints**:
- `chk_cycle_number_max`: `cycle_number BETWEEN 1 AND 3`
| `resolution_notes` | TEXT | YES | Admin notes on final resolution (approve or escalate) |
| `resolved_by_admin_id` | BIGINT UNSIGNED FK→users | YES | NULL until resolved |
| `resolved_at` | TIMESTAMP | YES | NULL until resolved |
| `created_at` | TIMESTAMP | NO | `useCurrent()` |

**No `updated_at`** — status transitions are the only mutations; timestamp changes are recorded via `resolved_at`. No soft deletes.

**Indexes**:
- `(subject_type, subject_id, status)` — cycle check query: "get latest open or resubmitted request for this subject"
- `(requested_by_admin_id)`
- `(status, created_at)` — admin queue views

---

### `change_request_items` ⚠️ NEW TABLE — not yet in 11_DB_Schema.md

**Owned by**: `Shared` module
**Module path**: `app/Modules/Shared/Database/Migrations/2026_05_04_000002_create_change_request_items_table.php`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | NO | Internal ID |
| `public_id` | CHAR(26) UNIQUE | NO | ULID |
| `change_request_id` | BIGINT UNSIGNED FK→change_requests(id) | NO | `restrictOnDelete()` — items must not be orphaned |
| `field_path` | VARCHAR(255) | NO | Dot-notation path e.g. `documents.cr_document`, `pricing.lead_time_hours` |
| `current_value_snapshot` | JSON | YES | Point-in-time snapshot of the field value at request creation |
| `requested_change_en` | TEXT | NO | English description of required change |
| `requested_change_ar` | TEXT | NO | Arabic description — BOTH required, empty string fails validation |
| `item_status` | ENUM('pending','addressed','waived') | NO | Default `'pending'` |
| `created_at` | TIMESTAMP | NO | `useCurrent()` |

**No `updated_at`** — `item_status` is the only mutable column after creation. No soft deletes.

**Indexes**:
- `(change_request_id, item_status)` — "show pending items for this request"

---

## Modified Enums

### `ApprovalStatus` — Identity module

**File**: `app/Modules/Identity/Domain/Enums/ApprovalStatus.php`
**Table**: `vendor_profiles` (existing — `11_DB_Schema.md §vendor_profiles`)
**Migration**: New migration `2026_05_04_000003_add_changes_requested_to_vendor_profiles_approval_status.php` in Identity module

Before:
```php
case Pending = 'pending';
case Approved = 'approved';
case Rejected = 'rejected';
case Suspended = 'suspended';
```

After (add):
```php
case ChangesRequested = 'changes_requested';
```

New helper on enum:
```php
public function isActionable(): bool
{
    // States where admin can take a resolution action
    return in_array($this, [self::Pending, self::ChangesRequested], true);
}
```

---

### `ServiceStatus` — Catalog module

**File**: `app/Modules/Catalog/Domain/Enums/ServiceStatus.php`
**Table**: `services` (existing — `11_DB_Schema.md §services`)
**Migration**: New migration `2026_05_04_000004_add_changes_requested_to_services_status.php` in Catalog module

Before:
```php
case Draft = 'draft';
case PendingReview = 'pending_review';
case Published = 'published';
case Archived = 'archived';
```

After (add):
```php
case ChangesRequested = 'changes_requested';
```

Updated `canTransitionTo()`:
```php
public function canTransitionTo(self $next): bool
{
    return match ($this) {
        self::Draft           => $next === self::PendingReview,
        self::PendingReview   => $next === self::Published
                              || $next === self::Draft
                              || $next === self::ChangesRequested,   // NEW
        self::ChangesRequested => $next === self::PendingReview,     // NEW — vendor resubmits
        self::Published       => $next === self::Archived || $next === self::Draft || $next === self::ChangesRequested,
        self::Archived        => $next === self::Draft,
    };
}
```

Updated `color()`:
```php
self::ChangesRequested => 'warning',
```

---

## New Models

### `ChangeRequest` — Shared module

**File**: `app/Modules/Shared/Domain/Models/ChangeRequest.php`

```
Attributes:
- id, public_id, subject_type, subject_id
- requested_by_admin_id, status (cast: ChangeRequestStatus enum)
- cycle_number, resolution_notes, resolved_by_admin_id, resolved_at
- created_at

Relationships:
- items(): HasMany → ChangeRequestItem
- requestedByAdmin(): BelongsTo → User (via requested_by_admin_id)
- resolvedByAdmin(): BelongsTo → User (via resolved_by_admin_id)
- subject(): resolved manually via subject_type (not Laravel morphTo — see research.md Decision 2)

Scopes:
- scopeOpen(): where status = 'open'
- scopeResubmitted(): where status = 'resubmitted'
- scopeForSubject(string $type, int $id): where subject_type + subject_id

Traits: HasPublicId (from Shared)
```

### `ChangeRequestItem` — Shared module

**File**: `app/Modules/Shared/Domain/Models/ChangeRequestItem.php`

```
Attributes:
- id, public_id, change_request_id
- field_path, current_value_snapshot (cast: array)
- requested_change_en, requested_change_ar
- item_status (cast: ChangeRequestItemStatus enum)
- created_at

Relationships:
- changeRequest(): BelongsTo → ChangeRequest

Traits: HasPublicId
```

---

## New Enums (Shared module)

### `ChangeRequestStatus`

```php
enum ChangeRequestStatus: string
{
    case Open                = 'open';
    case Resubmitted         = 'resubmitted';
    case Resolved            = 'resolved';
    case EscalatedToRejection = 'escalated_to_rejection';
}
```

### `ChangeRequestItemStatus`

```php
enum ChangeRequestItemStatus: string
{
    case Pending   = 'pending';
    case Addressed = 'addressed';
    case Waived    = 'waived';
}
```

### `ChangeRequestSubjectType`

```php
enum ChangeRequestSubjectType: string
{
    case VendorProfile = 'vendor_profile';
    case Service       = 'service';
}
```

---

## New Contract (Shared module)

### `ChangeRequestSubject` interface

**File**: `app/Modules/Shared/Domain/Contracts/ChangeRequestSubject.php`

```php
interface ChangeRequestSubject
{
    public function getChangeRequestSubjectType(): ChangeRequestSubjectType;
    public function getKey(): int; // returns id
}
```

**Implementations**:
- `VendorProfile` implements `ChangeRequestSubject` → returns `ChangeRequestSubjectType::VendorProfile`
- `Service` implements `ChangeRequestSubject` → returns `ChangeRequestSubjectType::Service`

---

## State Machine: Change Request Lifecycle

```
[Admin creates request]
        ↓
     OPEN (cycle_number = N)
        ↓ vendor resubmits
  RESUBMITTED
        ↓ admin approves subject          ↓ admin requests changes again (N < 3)
     RESOLVED                          OPEN (cycle_number = N+1)
        ↓ admin escalates (or N=3, forced) ↓
  ESCALATED_TO_REJECTION
```

### Transition guards

| From | To | Guard | Actor | Notes |
|---|---|---|---|---|
| (none) | `open` | `cycle_number <= MAX_CYCLES`; no other open/resubmitted request for same subject | admin | Initial request creation |
| `open` | `resubmitted` | subject `approval_status`/`status` = `changes_requested`; actor = vendor who owns subject | vendor | Vendor addresses changes |
| `open` | `escalated_to_rejection` | no vendor response within timeout period | system | Auto-escalation after SLA timeout |
| `resubmitted` | `resolved` | actor = admin; subject transitions to `approved`/`published` | admin | Admin approves subject |
| `resubmitted` | `open` (new cycle) | `cycle_number < MAX_CYCLES`; actor = admin | admin | Admin requests more changes |
| `resubmitted` | `escalated_to_rejection` | `cycle_number === MAX_CYCLES`; actor = admin (or forced escalation action) | admin | Final rejection after max cycles |

### Invariant

At most ONE change request per subject may have `status IN ('open', 'resubmitted')` at any time. Enforced by `unique_active_change_request` partial unique index: `(subject_type, subject_id)` WHERE `status IN ('open', 'resubmitted')`. (MySQL partial unique index not directly supported — enforced via Action-level guard + DB unique constraint with a workaround: add a `active` virtual column or enforce in Action only, noting that DB-level enforcement requires MariaDB 10.5+ CHECK CONSTRAINT or application-level lock.)

**Decision**: Enforce at Action level (application-level guard) for Phase 1, with a composite regular index on `(subject_type, subject_id, status)` to make the check fast. Document in ADR-0018.

---

## Domain Events (fire via DB::afterCommit)

| Event | Fired by | Listeners |
|---|---|---|
| `ChangeRequestCreated` | `RequestVendorChangesAction`, `RequestServiceChangesAction` | `DispatchVendorChangesRequestedNotificationListener` |
| `VendorProfileResubmitted` | `VendorResubmitAfterChangesAction` | `DispatchVendorResubmittedNotificationListener`, writes audit log |
| `ServiceResubmitted` | `VendorResubmitServiceAfterChangesAction` | `DispatchVendorResubmittedNotificationListener`, writes audit log |
| `ChangeRequestEscalated` | `EscalateChangeRequestToRejectionAction` | Writes audit log, dispatches notification to vendor |
