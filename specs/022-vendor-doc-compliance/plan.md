# Implementation Plan: Vendor Document Compliance Lifecycle

**Branch**: `022-vendor-doc-compliance` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)  
**Phase**: 6.9 — Vendor Document Compliance Lifecycle ⚠️ PHASE BACKFILL NEEDED  
**ADR Required**: ADR-0021-vendor-doc-expiry.md (write + accept BEFORE any migration)

---

## Summary

Extends the existing vendor document flow (Phase 1.1) with a time-bounded compliance lifecycle: expiry dates on documents, a daily scheduled command that fires reminder notifications at 30/14/7/1 days before expiry, auto-suspension when a critical document expires, and an admin grace-period override with full audit trail. All lifecycle events land in the new append-only `vendor_compliance_events` table. A Filament widget surfaces the compliance state on the ops dashboard.

**No new HTTP API endpoints** are introduced. This is a fully internal (job + Filament) feature.

---

## Constitution Check

| # | Principle | Verdict | Notes |
|---|---|---|---|
| I | Modular monolith | ✅ PASS | All classes in `app/Modules/Identity/`. No cross-module Model imports. |
| II | Three product types | N/A | Compliance is vendor-identity scoped — no type-awareness needed. |
| III | Money discipline | N/A | No money columns. |
| IV | Bilingual EN+AR | ✅ PASS | `vendor_compliance_events.reason` JSON translatable; all 5 notification templates require EN+AR; Filament form validation enforces both locales on grace-period reason. |
| V | Append-only tables | ✅ PASS | `vendor_compliance_events` gets only `created_at` (no `updated_at`, no `softDeletes()`). Compliance events are immutable facts. |
| VI | ADR before code | ✅ GATE | ADR-0021 MUST reach `Accepted` status before this plan is implemented. See ADR section below. |
| VII | Test-first | ✅ PASS | Pest tests written same-day as Actions. Time-travel tests for reminder cascade. |
| VIII | Idempotency | ✅ PASS | `CheckDocumentExpiryCommand` is safe to re-run — `last_reminder_sent_at` guard prevents duplicate notifications. No HTTP state-changing endpoints → no `idempotency_keys` rows needed. |
| IX | Events DB::afterCommit | ✅ PASS | `VendorAutoSuspended` + `VendorGracePeriodGranted` both fire inside `DB::afterCommit`. |
| X | Two-step vendor gate | ✅ PASS | Auto-suspension sets `approval_status = 'suspended'` via same mechanism as existing `SuspendVendorAction`. `RevokeAllVendorTypesOnStatusChange` listener already handles cascading per-type revocation. |
| XI | Document storage | N/A | No new file uploads — metadata columns only. |

---

## ADR-0021 — Vendor Document Expiry (write before implementation)

**File**: `docs/adr/ADR-0021-vendor-doc-expiry.md`

**Key decisions to capture in the ADR:**

1. **Expiry is per-document, not per-vendor** — each `vendor_document` row has its own `expires_at` and `is_critical` flag. A vendor may have some critical and some non-critical docs.
2. **is_critical determines auto-suspension** — Phase 1 uses admin-set criticality at approval time. Per-doc-type rules (CR = critical, IBAN = not) are deferred to Phase 1.5.
3. **Grace period is fixed at 14 days** — configurable durations deferred to Phase 1.5.
4. **AutoSuspendForExpiredDocAction is independent from SuspendVendorAction** — the system-automated action carries a `suspension_reason`, sets `suspended_at = now()`, and creates a compliance event. The admin-manual `SuspendVendorAction` is unchanged.
5. **Compliance events are append-only** — they live in `vendor_compliance_events` and are never updated or deleted. They serve as the compliance audit trail.
6. **Re-suspension after grace expiry** — if the vendor's grace period elapses and the critical doc is still expired, the daily command re-suspends and logs a new `auto_suspended` event.

---

## Technical Context

| Item | Value |
|---|---|
| Framework | Laravel 12 (PHP 8.3+) |
| Module | `app/Modules/Identity/` |
| New tables | `vendor_compliance_events` (append-only) |
| Altered tables | `vendor_documents` (3 new columns), `vendor_profiles` (1 new column) |
| New Actions | `SetDocumentExpiryAction`, `AutoSuspendForExpiredDocAction`, `GrantDocGracePeriodAction` |
| New Command | `CheckDocumentExpiryCommand` (daily, 02:00 UTC) |
| New Events | `VendorAutoSuspended`, `VendorGracePeriodGranted` |
| New Enums | `ComplianceEventType` |
| New Models | `VendorComplianceEvent` |
| New Filament | `ExpiredDocsResource`, `VendorComplianceWidget` |
| Notification seeder | `DocExpiryNotificationTemplateSeeder` (5 templates × EN+AR) |
| Existing actions reused | `SuspendVendorAction` (unchanged), `DispatchNotificationAction` (Communication module) |
| Packages used | All from `10_Package_List.md` (no new packages) |

---

## Schema & Migration Plan

### Dependency Order

```
1. alter_vendor_profiles_add_suspension_reason
2. alter_vendor_documents_add_expiry_columns
3. create_vendor_compliance_events_table
```

`vendor_compliance_events` FK → `vendor_profiles` and `vendor_documents`, so both alters go first.

---

### Migration 1 — `alter_vendor_profiles_add_suspension_reason`

**File**: `app/Modules/Identity/Database/Migrations/2026_05_04_000001_alter_vendor_profiles_add_suspension_reason.php`

**Purpose**: Add `suspension_reason` (translatable JSON) to the existing `vendor_profiles` table so that system-generated suspensions (doc expiry) carry a human-readable, bilingual reason distinct from admin-manual suspensions.

**Column added**:

| Column | Type | Null | Notes |
|---|---|---|---|
| `suspension_reason` | JSON | YES | `{"en": "...", "ar": "..."}` — NULL for admin-manual suspensions that pre-date this feature |

**Indexes added**: None (queried via `approval_status` which is already indexed).

**Soft deletes**: Already present (`deleted_at`) — no change.

---

### Migration 2 — `alter_vendor_documents_add_expiry_columns`

**File**: `app/Modules/Identity/Database/Migrations/2026_05_04_000002_alter_vendor_documents_add_expiry_columns.php`

**Purpose**: Attach compliance metadata to each vendor document row.

**Columns added**:

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `expires_at` | DATE | YES | NULL | NULL = no expiry tracking for this doc |
| `is_critical` | TINYINT(1) UNSIGNED | NO | 0 | 1 = expiry triggers auto-suspension |
| `last_reminder_sent_at` | DATE | YES | NULL | Idempotency guard — updated each time a reminder fires |

**Indexes added**:
- `(expires_at, is_critical)` — the daily command WHERE clause
- `(last_reminder_sent_at)` — for duplicate-reminder guard

---

### Migration 3 — `create_vendor_compliance_events_table`

**File**: `app/Modules/Identity/Database/Migrations/2026_05_04_000003_create_vendor_compliance_events_table.php`

**Purpose**: Append-only audit log for all compliance lifecycle events (reminder sent, expired, auto-suspended, manually overridden). Every system or admin action on document compliance writes a row here — no rows are ever updated or deleted.

**Columns**:

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | `bigIncrements` |
| `public_id` | CHAR(26) UNIQUE | NO | ULID |
| `vendor_profile_id` | BIGINT UNSIGNED FK→vendor_profiles | NO | `restrictOnDelete` |
| `document_id` | BIGINT UNSIGNED FK→vendor_documents | YES | NULL for profile-level events |
| `event_type` | ENUM('reminder_sent','expired','auto_suspended','manually_overridden') | NO | |
| `occurred_at` | TIMESTAMP | NO | `useCurrent()` |
| `admin_id` | BIGINT UNSIGNED FK→users | YES | NULL for system-generated events |
| `reason` | JSON | YES | `{"en": "...", "ar": "..."}` — NULL for reminder events |

**Append-only constraints** (per Constitution §V):
- ❌ NO `updated_at` column
- ❌ NO `softDeletes()`
- Only `occurred_at` timestamp (= `created_at` semantically)

**Indexes**:
- `(vendor_profile_id, event_type, occurred_at)` — vendor compliance history queries
- `(document_id, event_type)` — document-specific event lookup
- `(event_type, occurred_at)` — admin dashboard aggregations

**Schema backfill required**: Add `vendor_compliance_events` to `docs/specs/11_DB_Schema.md` Identity section.

---

## Source Code Structure

```
app/Modules/Identity/
├── Domain/
│   ├── Enums/
│   │   └── ComplianceEventType.php          [NEW] enum with 4 cases
│   ├── Events/
│   │   ├── VendorAutoSuspended.php          [NEW] fired after auto-suspend commit
│   │   └── VendorGracePeriodGranted.php     [NEW] fired after grace period commit
│   └── Models/
│       ├── VendorDocument.php               [MODIFY] add $casts for expires_at, scopes
│       ├── VendorProfile.php                [MODIFY] add suspension_reason cast
│       └── VendorComplianceEvent.php        [NEW] append-only model
├── Application/
│   └── Actions/
│       ├── SetDocumentExpiryAction.php      [NEW]
│       ├── AutoSuspendForExpiredDocAction.php [NEW]
│       └── GrantDocGracePeriodAction.php    [NEW]
├── Console/
│   └── Commands/
│       └── CheckDocumentExpiryCommand.php   [NEW]
├── Filament/
│   ├── Resources/
│   │   └── ExpiredDocsResource.php          [NEW]
│   └── Widgets/
│       └── VendorComplianceWidget.php       [NEW]
└── Database/
    ├── Migrations/                          [3 new files]
    └── Seeders/
        └── DocExpiryNotificationTemplateSeeder.php [NEW — 5 templates × EN+AR]
```

---

## Action Designs

### SetDocumentExpiryAction

```php
execute(VendorDocument $document, Carbon $expiresAt, bool $isCritical): VendorDocument
```

- Validates `$expiresAt` is in the future (throws `InvalidArgumentException` otherwise)
- Wraps in `DB::transaction`
- Updates `vendor_documents`: `expires_at`, `is_critical`
- Returns updated `VendorDocument`
- No domain event (this is a configuration action, not a lifecycle event)
- Called from: Filament `ExpiredDocsResource` "Renew" action + existing `VendorDocumentResource` approval form

---

### AutoSuspendForExpiredDocAction

```php
execute(VendorDocument $document): VendorProfile
```

- Called by `CheckDocumentExpiryCommand` for each `is_critical=true` expired document
- `DB::transaction`:
  1. Sets `vendor_profiles.approval_status = 'suspended'`, `suspended_at = now()`, `suspended_by = null` (system), `suspension_reason = {"en": "Automatically suspended: {doc_type} document expired on {date}", "ar": "تم التعليق تلقائياً: وثيقة {doc_type} انتهت صلاحيتها في {date}"}`
  2. Inserts `vendor_compliance_events` row: `event_type='auto_suspended'`, `admin_id=null`, `reason=<same JSON>`
  3. `DB::afterCommit`: fires `VendorAutoSuspended` event (which `RevokeAllVendorTypesOnStatusChange` listener already handles, revoking per-type approvals)
  4. Dispatches `vendor.doc_expired` notification via Communication module's `DispatchNotificationAction` (queued listener on `VendorAutoSuspended`)
- Returns updated `VendorProfile`
- NOTE: intentionally does NOT call `SuspendVendorAction` — that action uses `auth()->id()` for actor tracking. This action is system-automated (no authenticated user).

---

### GrantDocGracePeriodAction

```php
execute(VendorDocument $document, User $admin, array $reason): VendorProfile
```

- `$reason` = `['en' => '...', 'ar' => '...']` (both required, validated by Filament form)
- `DB::transaction`:
  1. Extends `vendor_documents.expires_at` = `today() + 14 days`
  2. Sets `vendor_profiles.approval_status = 'approved'`, clears `suspended_at`, clears `suspension_reason`
  3. Inserts `vendor_compliance_events` row: `event_type='manually_overridden'`, `admin_id=$admin->id`, `reason=$reason`
  4. `DB::afterCommit`: fires `VendorGracePeriodGranted` event
  5. Dispatches notification to vendor confirming grace period
- Returns updated `VendorProfile`

---

## CheckDocumentExpiryCommand Design

```
php artisan identity:check-document-expiry
```

Runs daily at 02:00 UTC via `Schedule::command('identity:check-document-expiry')->dailyAt('02:00')` in `app/Console/Kernel.php` (or the module's ServiceProvider schedule registration).

**Processing logic** (pseudo-code, simplified):

```
REMINDER_THRESHOLDS = [30, 14, 7, 1]

docs = VendorDocument::whereNotNull('expires_at')
                     ->where('expires_at', '>=', today())
                     ->get()

foreach doc in docs:
    days_until_expiry = today().diffInDays(doc.expires_at, absolute=true)
    
    if days_until_expiry in REMINDER_THRESHOLDS:
        if doc.last_reminder_sent_at != today():
            dispatch reminder notification for doc
            doc.update(last_reminder_sent_at: today())
            log compliance event: reminder_sent

expired_docs = VendorDocument::whereNotNull('expires_at')
                              ->where('expires_at', '<', today())
                              .get()

foreach doc in expired_docs:
    if doc.is_critical and doc.vendor.approval_status != 'suspended':
        AutoSuspendForExpiredDocAction::execute(doc)
    elif not doc.is_critical and doc.last_reminder_sent_at != today():
        dispatch vendor.doc_expired notification
        doc.update(last_reminder_sent_at: today())
        log compliance event: expired
```

**Idempotency**: `last_reminder_sent_at = today()` guard prevents duplicate notifications when run multiple times per day.

**Error isolation**: Each document is processed in its own try/catch. A failure on one document is logged to `audit_logs` and does not halt processing of subsequent documents.

---

## Notification Templates (5 × EN+AR)

Seeded by `DocExpiryNotificationTemplateSeeder` into `notification_templates` table.

| event_key | channel | audience | EN subject | AR subject |
|---|---|---|---|---|
| `vendor.doc_expiring_30d` | `in_app` | `vendor` | "Your {doc_type} expires in 30 days" | "وثيقة {doc_type} تنتهي خلال 30 يوماً" |
| `vendor.doc_expiring_14d` | `in_app` | `vendor` | "Your {doc_type} expires in 14 days" | "وثيقة {doc_type} تنتهي خلال 14 يوماً" |
| `vendor.doc_expiring_7d` | `in_app` | `vendor` | "Urgent: {doc_type} expires in 7 days" | "عاجل: وثيقة {doc_type} تنتهي خلال 7 أيام" |
| `vendor.doc_expiring_1d` | `in_app` | `vendor` | "Final notice: {doc_type} expires tomorrow" | "إشعار أخير: وثيقة {doc_type} تنتهي غداً" |
| `vendor.doc_expired` | `in_app` | `vendor` | "Account suspended: {doc_type} has expired" | "تم تعليق الحساب: انتهت صلاحية {doc_type}" |

All five also seeded for `email` channel (same event_key, same content, different channel row).

---

## Filament Resources

### ExpiredDocsResource

**File**: `app/Modules/Identity/Filament/Resources/ExpiredDocsResource.php`  
**Navigation group**: "Vendor Compliance"  
**Model**: `VendorDocument`  
**Default scope**: `whereNotNull('expires_at')->where('expires_at', '<=', today()->addDays(7))`

**Table columns**:
- Vendor name (link to vendor profile page)
- Doc type badge
- `expires_at` (date, colored: red = expired, orange = ≤ 7 days, yellow = ≤ 14 days)
- `is_critical` icon column (`->boolean()`)
- Vendor `approval_status` badge

**Per-row actions**:
1. `Renew` — opens form with `DatePicker::make('expires_at')` (future date required) + `Toggle::make('is_critical')`. Calls `SetDocumentExpiryAction`.
2. `Grant Grace Period` — opens form with `Textarea::make('reason_en')` + `Textarea::make('reason_ar')` (both required). Calls `GrantDocGracePeriodAction`. Only visible when vendor `approval_status = 'suspended'`.
3. `Suspend Manually` — `->requiresConfirmation()`. Calls `AutoSuspendForExpiredDocAction`. Only visible when vendor `approval_status != 'suspended'`.

**Shield permissions**: `view_any_expired_doc`, `update_expired_doc` (generated by `shield:generate --all`).

---

### VendorComplianceWidget

**File**: `app/Modules/Identity/Filament/Widgets/VendorComplianceWidget.php`  
**Extends**: `\Filament\Widgets\StatsOverviewWidget`  
**Registered on**: ops dashboard (Phase 8.1) and Identity module's `getWidgets()` registration

**Stats**:
1. "Expiring This Month" — count of `vendor_documents` where `expires_at BETWEEN today AND end_of_month`; description lists top 3 by soonest expiry.
2. "Auto-Suspended Vendors" — count of `vendor_profiles` where `approval_status = 'suspended'` AND latest `vendor_compliance_events.event_type = 'auto_suspended'`; color `danger`.
3. "Grace Periods Active" — count of `vendor_compliance_events` where `event_type = 'manually_overridden'` AND corresponding `vendor_document.expires_at > today`; color `warning`.

---

## Domain Events

### VendorAutoSuspended

```php
class VendorAutoSuspended
{
    public function __construct(
        public readonly VendorProfile $vendorProfile,
        public readonly VendorDocument $document,
    ) {}
}
```

**Listeners registered in `IdentityServiceProvider::boot()`**:
- `RevokeAllVendorTypesOnStatusChange` (already handles `VendorSuspended` — extend to also handle `VendorAutoSuspended`)
- `SendDocExpiredNotificationListener` [NEW] — dispatches `vendor.doc_expired` notification via Communication module

### VendorGracePeriodGranted

```php
class VendorGracePeriodGranted
{
    public function __construct(
        public readonly VendorProfile $vendorProfile,
        public readonly VendorDocument $document,
        public readonly int $grantedByAdminId,
    ) {}
}
```

**Listeners**:
- `SendGracePeriodGrantedNotificationListener` [NEW] — dispatches grace period confirmation notification

---

## Test Plan (Pest)

**File**: `tests/Feature/Modules/Identity/VendorDocumentComplianceTest.php`

| Test | Group | Time Travel | Assertion |
|---|---|---|---|
| reminder fires at 30 days | compliance | `travelTo(expires_at - 30 days)` | notification dispatched, `last_reminder_sent_at = today` |
| reminder fires at 14 days | compliance | `travelTo(expires_at - 14 days)` | `vendor.doc_expiring_14d` dispatched |
| reminder fires at 7 days | compliance | `travelTo(expires_at - 7 days)` | `vendor.doc_expiring_7d` dispatched |
| reminder fires at 1 day | compliance | `travelTo(expires_at - 1 day)` | `vendor.doc_expiring_1d` dispatched |
| no duplicate reminder same day | compliance | run command twice same day | notification dispatched once only |
| critical doc expiry triggers auto-suspend | compliance | `travelTo(expires_at + 1 day)` | `approval_status = 'suspended'`, compliance event `auto_suspended` |
| auto-suspend fires VendorAutoSuspended event | compliance | `travelTo(expires_at + 1 day)` | `Event::assertDispatched(VendorAutoSuspended::class)` |
| auto-suspend revokes per-type approvals | compliance | `travelTo(expires_at + 1 day)` | `vendor_approved_product_types.revoked_at` set for all types |
| auto-suspend does not affect existing bookings | compliance | `travelTo(expires_at + 1 day)` | bookings retain their `lifecycle_status` |
| non-critical doc expiry sends notification, no suspend | compliance | `travelTo(expires_at + 1 day)` | `approval_status` unchanged, `vendor.doc_expired` dispatched |
| null expires_at skipped entirely | compliance | any | no notification, no action |
| grace period un-suspends vendor | compliance | — | `approval_status = 'approved'`, event `manually_overridden`, `admin_id` non-null |
| grace period requires bilingual reason | compliance | — | validation error when `reason_en` or `reason_ar` is blank |
| grace period extends expires_at by 14 days | compliance | — | `vendor_documents.expires_at = today + 14 days` |
| re-suspension after grace expiry | compliance | `travelTo(grace_expires_at + 1 day)` | vendor re-suspended, new `auto_suspended` event |
| SetDocumentExpiryAction rejects past date | compliance | — | `InvalidArgumentException` thrown |

All tests grouped with `->group('compliance', 'identity')`.

---

## Execution Order (Day 1)

1. **Write ADR-0021** → get it to `Accepted` (Constitution §VI gate)
2. **3 migrations** in dependency order → `php artisan migrate`
3. **VendorComplianceEvent model** + `ComplianceEventType` enum
4. **VendorDocument model** updates (casts, scopes)
5. **VendorProfile model** update (suspension_reason cast)
6. **3 Actions** (SetDocumentExpiryAction, AutoSuspendForExpiredDocAction, GrantDocGracePeriodAction)
7. **2 Domain Events** (VendorAutoSuspended, VendorGracePeriodGranted)
8. **2 Listeners** (SendDocExpiredNotificationListener, SendGracePeriodGrantedNotificationListener) → register in IdentityServiceProvider
9. **Extend RevokeAllVendorTypesOnStatusChange** to handle VendorAutoSuspended
10. **CheckDocumentExpiryCommand** → register in schedule
11. **DocExpiryNotificationTemplateSeeder** (5 templates × 2 channels × EN+AR)
12. **ExpiredDocsResource** + `php artisan shield:generate --all`
13. **VendorComplianceWidget** → register in IdentityServiceProvider
14. **Pest tests** (all 16 cases)
15. `php artisan pint` + `./vendor/bin/phpstan analyse` + `./vendor/bin/pest --group=compliance --bail`

---

## Backfill TODOs (after implementation)

- [ ] Add `vendor_compliance_events` table spec to `docs/specs/11_DB_Schema.md` Identity section
- [ ] Add `suspension_reason JSON NULL` column to `vendor_profiles` in `docs/specs/11_DB_Schema.md`
- [ ] Add `expires_at`, `is_critical`, `last_reminder_sent_at` columns to `vendor_documents` in `docs/specs/11_DB_Schema.md`
- [ ] Add FR-EXT-001/002/003 to `docs/specs/01_PRD.md`
- [ ] Add Phase 6.9 to `docs/specs/09_Phasing_Plan.md`

---

## Complexity Notes

No constitution violations. No new packages. No Phase 2 features. The one noteworthy design choice:

| Choice | Reason |
|---|---|
| `AutoSuspendForExpiredDocAction` does NOT delegate to `SuspendVendorAction` | `SuspendVendorAction` reads `auth()->id()` for `suspended_by` — system-automated suspensions have no authenticated user. Keeping separate actions avoids forcing a nullable auth context into the manual-admin action. Both set `approval_status = 'suspended'` directly. |
