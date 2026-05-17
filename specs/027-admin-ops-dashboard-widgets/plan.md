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
---

# Implementation Plan: Admin Operations Dashboard Widgets

**Branch**: `027-admin-ops-dashboard-widgets` | **Date**: 2026-05-15 | **Spec**: [spec.md](spec.md)
**Phase**: Phase 8.1 — Admin Active Ops Dashboard (⚠️ PHASE BACKFILL NEEDED — add nine widgets to Phase 8.1 deliverable list in `09_Phasing_Plan.md`)
**PRD Coverage**: FR-14, FR-15, FR-16, FR-17, FR-18, FR-19, FR-22, FR-23–FR-27, FR-28, FR-29, FR-30; Admin Journey §1–§6

---

## Summary

Build nine read-only `StatsOverviewWidget` classes that turn the `/admin` Filament dashboard into an operations command centre. Each widget queries one table for a critical-count alarm, shows the count, and links to the pre-filtered resource list. No new tables, no new packages, no new modules — pure aggregation over existing schema.

**Technical approach:** One `StatsOverviewWidget` per domain concern, placed in the owning module's `Filament/Widgets/` directory, auto-discovered via four new `discoverWidgets()` calls added to `AdminPanelProvider`. Authorization checked via `static::canView()` delegating to Filament Shield. Translation keys in per-module `Resources/lang/{en,ar}/widgets.php`.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Filament v3 (`filament/filament:^3.x`), `bezhansalleh/filament-shield`, `spatie/laravel-permission`
**Storage**: MySQL 8 (read-only queries — no writes)
**Testing**: Pest (`pestphp/pest` + `pestphp/pest-plugin-laravel`)
**Target Platform**: Filament admin panel at `/admin`
**Project Type**: Admin UI widgets (Filament `StatsOverviewWidget`)
**Performance Goals**: Widget queries complete in < 50 ms each (covered by existing indexes on `approval_status`, `status`, `sub_status`, `response_deadline`, `created_at`)
**Constraints**: No new packages. No new tables. No new modules. Widgets are strictly read-only. Each widget must degrade gracefully (show 0) if no data exists.
**Scale/Scope**: 9 widgets × 1 query each; all queries are O(indexed column lookup) — no full table scans

---

## Constitution Check

*Re-checked after Phase 1 design.*

| Principle | Applies? | Status | Notes |
|---|---|---|---|
| I — Modular Monolith | ✅ | **PASS** | Each widget lives in its owning module's `Filament/Widgets/`. No cross-module model imports — each widget imports only its own module's model. |
| II — Three Product Types | ✅ | **PASS** | `PendingServiceModerationWidget` produces 3 `Stat` objects using `match(ProductType)` for per-type URL routing. No `if/elseif` chains. |
| III — Money Discipline | N/A | SKIP | Widgets show row counts, not money values. No `_minor` columns touched. |
| IV — Bilingual EN+AR | ✅ | **PASS** | Translation keys for all widget headings and stat descriptions placed in both `lang/en/widgets.php` and `lang/ar/widgets.php` in each owning module. |
| V — Append-Only Tables | ✅ | **PASS** | No append-only tables are mutated. `notification_dispatches` (queried by `FailedNotificationDispatchesWidget`) is not in the append-only list — it has `updated_at`. |
| VI — ADR Before Code | ✅ | **PASS** | No new modules. All widgets live in existing modules (Identity, Catalog, Booking, Payments, Communication, Settlement). Existing ADRs (0003, 0004, 0005, ADR-0013) cover these modules. No new ADR required. |
| VII — Test-First | ✅ | **PASS** | Pest tests written same day as widgets. One test file per widget. See §Test Plan below. |
| VIII — Idempotency | N/A | SKIP | Widgets are read-only `GET` dashboard renders — no state mutations. |
| IX — Domain Events afterCommit | N/A | SKIP | Widgets emit no events. |
| X — Vendor Approval Two-Step Gate | N/A | SKIP | `PendingVendorApprovalsWidget` reads `approval_status` but makes no approval decision. |
| XI — Document Storage | N/A | SKIP | No file uploads or downloads. |

**Gate result: PASS — no violations. Proceeding to Phase 1.**

---

## No New Packages

All implementation uses built-in Filament v3 `StatsOverviewWidget` and existing Laravel Eloquent queries. No package amendments to `10_Package_List.md` required.

---

## No New Tables

All nine widgets query existing tables confirmed in `docs/specs/11_DB_Schema.md`:

| Table | Module | Widget |
|---|---|---|
| `vendor_profiles` | Identity | `PendingVendorApprovalsWidget` |
| `services` | Catalog | `PendingServiceModerationWidget` |
| `booking_vendors` | Booking | `LateVendorResponsesWidget` |
| `bookings` | Booking | `BookingsWaitingCustomerApprovalWidget` |
| `payments` | Payments | `FailedPaymentsWidget` |
| `notification_dispatches` | Communication | `FailedNotificationDispatchesWidget` |
| `withdrawals` | Settlement | `PendingWithdrawalsWidget` |
| `excel_imports` | Catalog | `ExcelImportsWithErrorsWidget` |
| `admin_inbox_items` | Communication | `CriticalAdminInboxWidget` |

---

## Project Structure

### Documentation (this feature)

```text
specs/027-admin-ops-dashboard-widgets/
├── plan.md                        # This file
├── research.md                    # Phase 0 output (below)
├── data-model.md                  # Phase 1 output (below)
└── checklists/requirements.md     # Validation checklist (created)
```

### Source Code — New Files

```text
app/Modules/Identity/Filament/Widgets/
└── PendingVendorApprovalsWidget.php

app/Modules/Catalog/Filament/Widgets/
├── PendingServiceModerationWidget.php
└── ExcelImportsWithErrorsWidget.php

app/Modules/Booking/Filament/Widgets/
├── LateVendorResponsesWidget.php
└── BookingsWaitingCustomerApprovalWidget.php

app/Modules/Payments/Filament/
└── Widgets/
    └── FailedPaymentsWidget.php

app/Modules/Communication/Filament/Widgets/
├── FailedNotificationDispatchesWidget.php
└── CriticalAdminInboxWidget.php

app/Modules/Settlement/Filament/Widgets/
└── PendingWithdrawalsWidget.php
```

### Source Code — Modified Files

```text
app/Providers/Filament/AdminPanelProvider.php      # +4 discoverWidgets() calls

# Per-module translation files (new or extended):
app/Modules/Identity/Resources/lang/en/widgets.php
app/Modules/Identity/Resources/lang/ar/widgets.php
app/Modules/Catalog/Resources/lang/en/widgets.php
app/Modules/Catalog/Resources/lang/ar/widgets.php
app/Modules/Booking/Resources/lang/en/widgets.php
app/Modules/Booking/Resources/lang/ar/widgets.php
app/Modules/Payments/Resources/lang/en/widgets.php
app/Modules/Payments/Resources/lang/ar/widgets.php
app/Modules/Communication/Resources/lang/en/widgets.php
app/Modules/Communication/Resources/lang/ar/widgets.php
app/Modules/Settlement/Resources/lang/en/widgets.php
app/Modules/Settlement/Resources/lang/ar/widgets.php
```

### Test Files

```text
tests/Feature/Modules/Identity/Filament/Widgets/PendingVendorApprovalsWidgetTest.php
tests/Feature/Modules/Catalog/Filament/Widgets/PendingServiceModerationWidgetTest.php
tests/Feature/Modules/Catalog/Filament/Widgets/ExcelImportsWithErrorsWidgetTest.php
tests/Feature/Modules/Booking/Filament/Widgets/LateVendorResponsesWidgetTest.php
tests/Feature/Modules/Booking/Filament/Widgets/BookingsWaitingCustomerApprovalWidgetTest.php
tests/Feature/Modules/Payments/Filament/Widgets/FailedPaymentsWidgetTest.php
tests/Feature/Modules/Communication/Filament/Widgets/FailedNotificationDispatchesWidgetTest.php
tests/Feature/Modules/Communication/Filament/Widgets/CriticalAdminInboxWidgetTest.php
tests/Feature/Modules/Settlement/Filament/Widgets/PendingWithdrawalsWidgetTest.php
```

---

## Phase 0: Research

### R-01: Status Query Strategy — Enum Values vs. Model-States `whereState()`

**Decision**: Use enum `->value` properties directly in queries (e.g., `ApprovalStatus::Pending->value`). Do NOT use spatie/laravel-model-states `->whereState()` unless the model explicitly declares a `HasStates` cast.

**Rationale**: Inspection of existing models shows `approval_status`, `lifecycle_status`, `sub_status`, `status` fields are cast as PHP 8.1 backed enums (`protected function casts()`), NOT as spatie model-state subclasses. `ExcelImport.status` is a plain string cast. Using `->whereState()` on non-model-states columns throws a runtime exception. Enum `->value` produces the same SQL and is type-safe.

**Alternative considered**: Force model-states cast on all models — rejected because it requires migration-equivalent changes to existing model casts, which is out of scope for a widget feature and violates "no new tables / no model mutations" constraint.

### R-02: Authorization Strategy

**Decision**: Each widget class implements `public static function canView(): bool` calling `auth()->user()->can('view_any_{resource_permission}')` via Filament Shield's policy-derived gates.

**Rationale**: Filament v3 `Widget::canView()` is the standard extension point. Shield generates `view_any_*` gate names from resource class names. This is the zero-config pattern already used by all InstaParty resources.

| Widget | Required Permission Gate |
|---|---|
| `PendingVendorApprovalsWidget` | `view_any_vendor_profile` |
| `PendingServiceModerationWidget` | `view_any_rental_service` (shows all three types; if can view any type, show the widget) |
| `LateVendorResponsesWidget` | `view_any_bookings_monitor` |
| `BookingsWaitingCustomerApprovalWidget` | `view_any_booking` |
| `FailedPaymentsWidget` | `view_any_payment` |
| `FailedNotificationDispatchesWidget` | `view_any_notification_dispatch` |
| `PendingWithdrawalsWidget` | `view_any_withdrawals_queue` |
| `ExcelImportsWithErrorsWidget` | `view_any_excel_import` |
| `CriticalAdminInboxWidget` | `view_any_admin_inbox_item` |

### R-03: Resource URL Generation for Widget Links

**Decision**: Use `ResourceClass::getUrl('index')` for all resource links. For pre-filtered links, append `?tableFilters[field][value]=val` as a query string (Filament v3 table URL format).

**Confirmed resource slugs** (from codebase inspection):

| Resource | Slug / URL |
|---|---|
| `VendorApprovalQueueResource` | `/admin/vendor-approval-queue` (slug declared explicitly) |
| `RentalServiceResource` pending page | `/admin/rental-services/pending` (via `PendingRentalServicesPage`) |
| `SaleServiceResource` pending page | `/admin/sale-services/pending` (via `PendingSaleServicesPage`) |
| `DigitalServiceResource` pending page | `/admin/digital-services/pending` (via `PendingDigitalServicesPage`) |
| `BookingsMonitorResource` | `/admin/bookings-monitor` |
| `BookingResource` | `/admin/bookings?tableFilters[lifecycle_status][value]=customer_review` |
| `PaymentResource` | `/admin/payments?tableFilters[status][value]=failed` |
| `NotificationDispatchResource` | `/admin/notification-dispatches?tableFilters[status][value]=failed` |
| `WithdrawalsQueueResource` | `/admin/settlement-withdrawals` (slug declared explicitly) |
| `ExcelImportResource` | `/admin/excel-imports` |
| `AdminInboxResource` | `/admin/admin-inbox-items?tableFilters[severity][value]=critical` |

### R-04: Widget Discovery Registration

**Decision**: Four new `discoverWidgets()` calls added to `AdminPanelProvider::panel()`.

The following module widget folders are NOT yet registered:
- `app/Modules/Identity/Filament/Widgets` → `App\Modules\Identity\Filament\Widgets`
- `app/Modules/Catalog/Filament/Widgets` → `App\Modules\Catalog\Filament\Widgets`
- `app/Modules/Payments/Filament/Widgets` → `App\Modules\Payments\Filament\Widgets`
- `app/Modules/Communication/Filament/Widgets` → `App\Modules\Communication\Filament\Widgets`

Already registered (no change needed):
- `app/Modules/Booking/Filament/Widgets` ✅
- `app/Modules/Settlement/Filament/Widgets` ✅

### R-05: Translation Namespace Convention

**Decision**: Use per-module translation files under `Resources/lang/{en,ar}/widgets.php` with a flat key structure (no namespace prefix — the module directory IS the namespace). Each owning module gets its own `widgets.php` translation file.

Translation key format: `{widget_snake_case}.heading`, `{widget_snake_case}.description_singular`, `{widget_snake_case}.description_plural`.

Example for `PendingVendorApprovalsWidget`:
```php
// Resources/lang/en/widgets.php
'pending_vendor_approvals_heading' => 'Pending Vendor Approvals',
'pending_vendor_approvals_description' => ':count vendor awaiting review|:count vendors awaiting review',

// Resources/lang/ar/widgets.php
'pending_vendor_approvals_heading' => 'موردون في انتظار الاعتماد',
'pending_vendor_approvals_description' => 'مورد واحد في انتظار المراجعة|:count موردين في انتظار المراجعة',
```

Translation files are loaded by each module's `ServiceProvider::boot()` via `loadTranslationsFrom`.

---

## Phase 1: Design

### Widget Implementation Pattern

Every widget follows this canonical pattern:

```php
<?php

declare(strict_types=1);

namespace App\Modules\{Module}\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class {WidgetName} extends StatsOverviewWidget
{
    protected static ?int $sort = {N};  // deterministic sort order

    public static function canView(): bool
    {
        return auth()->user()?->can('{view_any_permission}') ?? false;
    }

    protected function getStats(): array
    {
        $count = {Model}::query()
            ->{scope or where}(...)
            ->count();

        return [
            Stat::make(
                label: __('identity::widgets.{key}_heading'),
                value: $count,
            )
                ->description(__('identity::widgets.{key}_description'))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-{icon}')
                ->url({ResourceClass}::getUrl('index')),
        ];
    }
}
```

### Widget Specifications

#### 1. `PendingVendorApprovalsWidget`
- **Module**: Identity
- **Sort**: 10
- **Model**: `VendorProfile`
- **Query**: `->where('approval_status', ApprovalStatus::Pending->value)->count()`
- **Color**: `danger` when > 0, `success` when 0
- **Icon**: `heroicon-o-user-plus`
- **URL**: `VendorApprovalQueueResource::getUrl('index')`
- **Permission**: `view_any_vendor_profile`

#### 2. `PendingServiceModerationWidget`
- **Module**: Catalog
- **Sort**: 20
- **Model**: `Service`
- **Query**: Three counts — one per `ProductType` — where `status = ServiceStatus::PendingReview->value`
- **Output**: Three `Stat` objects (Rental, Sale, Digital)
- **URLs**: Per-type, using `match(ProductType)`:
  - `Rental` → `RentalServiceResource::getUrl('pending')`
  - `Sale` → `SaleServiceResource::getUrl('pending')`
  - `Digital` → `DigitalServiceResource::getUrl('pending')`
- **Icon**: `heroicon-o-shield-check`
- **Permission**: `view_any_rental_service` (any type permission satisfies widget visibility)
- **NOTE**: This is the only multi-stat widget. Uses `array_merge` to return all three `Stat` objects from `getStats()`.

#### 3. `LateVendorResponsesWidget`
- **Module**: Booking
- **Sort**: 30
- **Model**: `BookingVendor`
- **Query**: `->whereNotNull('response_deadline')->where('response_deadline', '<', now())->where('sub_status', VendorSubStatus::Pending->value)->count()`
- **Color**: `danger` when > 0, `success` when 0
- **Icon**: `heroicon-o-clock`
- **URL**: `BookingsMonitorResource::getUrl('index')`
- **Permission**: `view_any_bookings_monitor`

#### 4. `BookingsWaitingCustomerApprovalWidget`
- **Module**: Booking
- **Sort**: 40
- **Model**: `Booking`
- **Query**: `->where('lifecycle_status', LifecycleStatus::CustomerReview->value)->count()`
- **Color**: `warning` when > 0, `success` when 0
- **Icon**: `heroicon-o-clock`
- **URL**: `BookingResource::getUrl('index') . '?tableFilters[lifecycle_status][value]=customer_review'`
- **Permission**: `view_any_booking`

#### 5. `FailedPaymentsWidget`
- **Module**: Payments
- **Sort**: 50
- **Model**: `Payment` (from Payments module)
- **Query**: `->where('status', PaymentStatus::Failed->value)->where('created_at', '>=', now()->subHours(48))->count()`
- **Color**: `danger` when > 0, `success` when 0
- **Icon**: `heroicon-o-credit-card`
- **URL**: `PaymentResource::getUrl('index') . '?tableFilters[status][value]=failed'`
- **Permission**: `view_any_payment`
- **IMPORTANT**: Must import `App\Modules\Payments\Domain\Enums\PaymentStatus`, not `App\Modules\Booking\Domain\Enums\PaymentStatus`.

#### 6. `FailedNotificationDispatchesWidget`
- **Module**: Communication
- **Sort**: 60
- **Model**: `NotificationDispatch`
- **Query**: `->whereIn('status', [DispatchStatus::Failed->value, DispatchStatus::Bounced->value])->where('created_at', '>=', now()->subHours(24))->count()`
- **Color**: `warning` when > 0, `success` when 0
- **Icon**: `heroicon-o-bell-slash`
- **URL**: `NotificationDispatchResource::getUrl('index') . '?tableFilters[status][value]=failed'`
- **Permission**: `view_any_notification_dispatch`

#### 7. `PendingWithdrawalsWidget`
- **Module**: Settlement
- **Sort**: 70
- **Model**: `Withdrawal`
- **Query**: `->where('status', WithdrawalStatus::Pending->value)->count()`
- **Color**: `warning` when > 0, `success` when 0
- **Icon**: `heroicon-o-banknotes`
- **URL**: `WithdrawalsQueueResource::getUrl('index')`
- **Permission**: `view_any_withdrawals_queue`

#### 8. `ExcelImportsWithErrorsWidget`
- **Module**: Catalog
- **Sort**: 80
- **Model**: `ExcelImport`
- **Query**:
  ```php
  ->where(fn ($q) => $q->where('status', 'failed')->orWhere('error_rows', '>', 0))
  ->where('created_at', '>=', now()->subDays(7))
  ->count()
  ```
  Note: `status` is a plain string cast on `ExcelImport` (not an enum) — raw string `'failed'` is correct here.
- **Color**: `danger` when > 0, `success` when 0
- **Icon**: `heroicon-o-document-chart-bar`
- **URL**: `ExcelImportResource::getUrl('index')`
- **Permission**: `view_any_excel_import`

#### 9. `CriticalAdminInboxWidget`
- **Module**: Communication
- **Sort**: 90
- **Model**: `AdminInboxItem`
- **Query**:
  ```php
  ->where('severity', AdminInboxSeverity::Critical->value)
  ->whereIn('status', [AdminInboxStatus::Unread->value, AdminInboxStatus::Read->value])
  ->count()
  ```
- **Color**: `danger` when > 0, `success` when 0
- **Icon**: `heroicon-o-exclamation-triangle`
- **URL**: `AdminInboxResource::getUrl('index') . '?tableFilters[severity][value]=critical'`
- **Permission**: `view_any_admin_inbox_item`

---

## Data Model

No new tables. All queries are read-only aggregates over existing schema.

### Query Index Verification

Each widget query relies on existing or derivable indexes:

| Widget | Table | Query predicate | Existing index |
|---|---|---|---|
| PendingVendorApprovals | `vendor_profiles` | `approval_status = 'pending'` | `(approval_status)` — should exist on vendor_profiles |
| PendingServiceModeration | `services` | `(status, product_type)` | `(category_id, product_type, status)` covers partial scan |
| LateVendorResponses | `booking_vendors` | `(sub_status, response_deadline)` | `(vendor_id, sub_status, response_deadline)` per schema |
| BookingsWaitingCustomerApproval | `bookings` | `lifecycle_status = 'customer_review'` | `(customer_id, status, created_at)` — may need addition |
| FailedPayments | `payments` | `(status, created_at)` | Payments has `(gateway, gateway_ref)` UNIQUE; `(status, created_at)` may need addition |
| FailedNotificationDispatches | `notification_dispatches` | `(status, created_at)` | Should have index on `created_at` |
| PendingWithdrawals | `withdrawals` | `status = 'pending'` | Index on `status` |
| ExcelImportsWithErrors | `excel_imports` | `(status OR error_rows > 0) AND created_at` | Index on `created_at` |
| CriticalAdminInbox | `admin_inbox_items` | `(severity, status)` | Check if index exists |

> **Note for implementation**: Add migration if a required index is missing. The migration should be a standalone `add_indexes_for_admin_ops_widgets` migration touching no data — safe to run at any time.

---

## AdminPanelProvider Changes

Add these four `discoverWidgets()` calls immediately after the existing Booking and Settlement widget discovery lines:

```php
// After line 92 (->discoverWidgets Settlement line):
->discoverWidgets(in: app_path('Modules/Identity/Filament/Widgets'), for: 'App\\Modules\\Identity\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Catalog/Filament/Widgets'), for: 'App\\Modules\\Catalog\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Payments/Filament/Widgets'), for: 'App\\Modules\\Payments\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\\Modules\\Communication\\Filament\\Widgets')
```

---

## Test Plan

### Test Strategy

Each widget gets one Pest feature test file with these test cases:

1. **Count test**: Seed the relevant model with the triggering state; assert widget stat count equals seeded count.
2. **Zero test**: Seed the relevant model in a NON-triggering state; assert widget stat count is 0.
3. **Time-window test** (for FailedPayments, FailedNotificationDispatches, ExcelImports): Seed old records outside the time window; assert count remains 0.
4. **Authorization test**: Create admin with permission and without; assert `canView()` returns the correct boolean.
5. **Locale test**: Assert that stat label is non-empty in both `en` and `ar` locale.

### Test Group Tags

All widget tests use `->group('widgets', 'admin')`. Type-specific widget tests also use `->group('rental')`, `->group('sale')`, `->group('digital')` where applicable (specifically `PendingServiceModerationWidget`).

---

## Complexity Tracking

No constitution violations. No complexity tracking required.

---

## Cut-List (if slipping on time)

In priority order, defer:

1. Locale test assertions (can test manually in AR locale via panel switcher)
2. Missing-index migration (add in follow-up — queries will be slow but correct)
3. Authorization tests (manual verification via Shield role assignment)
4. `PendingServiceModerationWidget` — defer to second pass (lowest risk: pending pages already exist)

Keep: Core count logic + `discoverWidgets` registration + EN translation keys (AR can be added post-merge).

---

## Exit Criteria

- [ ] All 9 widget files created in their owning module's `Filament/Widgets/` directory
- [ ] `AdminPanelProvider` updated with 4 new `discoverWidgets()` calls
- [ ] EN+AR translation keys present for all 9 widget headings
- [ ] `php artisan shield:generate --all` run — no permission exceptions on widget load
- [ ] `./vendor/bin/pest --group=widgets` all green
- [ ] `./vendor/bin/pint` zero violations
- [ ] `./vendor/bin/phpstan analyse` zero errors
- [ ] Dashboard `/admin` loads all 9 widgets without errors in both EN and AR locales
