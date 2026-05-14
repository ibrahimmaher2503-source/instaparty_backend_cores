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

# Implementation Plan: Operational Dashboard Widgets (Full Set)

**Branch**: `026-admin-booking-view` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)
**Phase**: 8.1 (Admin Active Ops Dashboard & Queues)
**PRD Coverage**: FR-29, Admin Journey §6.3

---

## Summary

Replace the existing 3-stat `BookingStatsWidget` skeleton with 9 operational dashboard widgets: 5 stat widgets, 2 chart widgets, and 2 subscription widgets (already implemented in Phase 1.7). All widgets are **read-only aggregates** — no migrations, no new models beyond an `OpenChatFlagsStatWidget` that guards against the `chat_moderation_flags` table being absent (Phase 8.2). The implementation updates `AdminPanelProvider` with 4 new `discoverWidgets` calls and adds translation keys to 5 module lang files.

**Existing state (confirmed by codebase scan):**
- `BookingStatsWidget` → EXISTS at `app/Modules/Booking/Filament/Widgets/BookingStatsWidget.php` — will be **removed**
- `PastDueSubscriptionsStatWidget` → EXISTS at `app/Modules/Subscriptions/Filament/Widgets/PastDueSubscriptionsStatWidget.php` — keep, update `$sort`
- `VendorsByTierWidget` → EXISTS at `app/Modules/Subscriptions/Filament/Widgets/VendorsByTierWidget.php` — keep, update `$sort`

**Missing (7 widgets to create):**
1. `VendorsAwaitingApprovalWidget` (Identity)
2. `ServicesPendingModerationByTypeWidget` (Catalog)
3. `OverdueBookingsWidget` (Booking)
4. `WithdrawalsQueueWidget` (Settlement)
5. `OpenChatFlagsStatWidget` (Communication)
6. `RevenueByProductTypeChart` (Booking)
7. `BookingsByTypeChart` (Booking)

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12, Filament v3
**Primary Dependencies**: `filament/filament:^3.x`, `spatie/laravel-permission` (all in `10_Package_List.md`)
**Storage**: Read-only queries against MySQL 8 — no writes
**Testing**: Pest + `pestphp/pest-plugin-laravel`
**Target Platform**: Filament admin panel at `/admin`
**Project Type**: Backend (Laravel) + Admin (Filament v3) only — no frontend/mobile work
**Performance Goals**: Dashboard page load < 3s under normal dev-db load
**Constraints**: No new `composer require`. No migrations. `chat_moderation_flags` table may not exist — widget must guard with `Schema::hasTable()`.
**Scale/Scope**: Read aggregates only; no cache layer needed in Phase 1 (add Redis caching in Phase 7.0 Hardening if slow)

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Applies? | Status | Notes |
|---|---|---|---|---|
| I | Modular Monolith — no cross-module Model imports | YES | ✅ PASS | Each widget imports only its owning module's models. `OpenChatFlagsStatWidget` uses raw `DB::table()` since `ChatModerationFlag` model doesn't exist yet. |
| II | Three Product Types — `match($enum)`, no if/elseif | PARTIAL | ✅ PASS | `ServicesPendingModerationByTypeWidget` groups by `product_type` using Eloquent `groupBy`. Charts group by `product_type` cast to `ProductType` enum. No `if/elseif` on strings. |
| III | Money — integer minor units, never float | PARTIAL | ✅ PASS | Chart raw data uses `SUM(line_total_minor)` (integers). No float arithmetic. Display formatting is Chart.js responsibility, not PHP. |
| IV | Bilingual EN+AR mandatory | YES | ✅ PASS | All widget headings use translation keys. New `widgets.php` lang files created in each module with both `en` and `ar` keys. |
| V | Append-only tables — no softDeletes/UPDATE | NO | ✅ N/A | No writes. Read-only. |
| VI | Spec-driven — ADR before new module | NO | ✅ N/A | No new modules. All widgets added to existing modules. |
| VII | Test-first for critical paths | YES | ✅ PASS | Pest test per widget: count accuracy + link URL + AR locale heading. |
| VIII | Idempotency for state-changing endpoints | NO | ✅ N/A | No mutating endpoints. |
| IX | Domain Events fire `DB::afterCommit` | NO | ✅ N/A | No domain events. |
| X | Vendor Approval two-step gate | NO | ✅ N/A | Widget queries are reads, not approval actions. |
| XI | Document storage rules | NO | ✅ N/A | No file uploads. |

**Verdict: ALL GATES PASS. Proceed to implementation.**

---

## Project Structure

### Documentation (this feature)

```text
specs/001-dashboard-widgets/
├── plan.md              ← this file
├── research.md          ← Phase 0 output (below)
├── data-model.md        ← Phase 1 output (below)
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code (changes only)

```text
app/
├── Providers/
│   └── Filament/
│       └── AdminPanelProvider.php                   # ADD 4 discoverWidgets calls; REMOVE BookingStatsWidget from static list if present
│
├── Modules/
│   ├── Identity/
│   │   ├── Filament/
│   │   │   └── Widgets/
│   │   │       └── VendorsAwaitingApprovalWidget.php  # NEW
│   │   └── Resources/lang/
│   │       ├── en/widgets.php                          # NEW
│   │       └── ar/widgets.php                          # NEW
│   │
│   ├── Catalog/
│   │   ├── Filament/
│   │   │   └── Widgets/
│   │   │       └── ServicesPendingModerationByTypeWidget.php  # NEW
│   │   └── Resources/lang/
│   │       ├── en/widgets.php                                  # NEW
│   │       └── ar/widgets.php                                  # NEW
│   │
│   ├── Booking/
│   │   ├── Filament/
│   │   │   └── Widgets/
│   │   │       ├── BookingStatsWidget.php               # DELETE
│   │   │       ├── OverdueBookingsWidget.php            # NEW
│   │   │       ├── RevenueByProductTypeChart.php        # NEW
│   │   │       └── BookingsByTypeChart.php              # NEW
│   │   └── Resources/lang/
│   │       ├── en/widgets.php                           # NEW (or append to booking.php)
│   │       └── ar/widgets.php                           # NEW
│   │
│   ├── Settlement/
│   │   ├── Filament/
│   │   │   └── Widgets/
│   │   │       └── WithdrawalsQueueWidget.php           # NEW
│   │   └── Resources/lang/
│   │       ├── en/widgets.php                           # NEW
│   │       └── ar/widgets.php                           # NEW
│   │
│   ├── Communication/
│   │   ├── Filament/
│   │   │   └── Widgets/
│   │   │       └── OpenChatFlagsStatWidget.php          # NEW
│   │   └── Resources/lang/
│   │       ├── en/widgets.php                           # NEW
│   │       └── ar/widgets.php                           # NEW
│   │
│   └── Subscriptions/
│       └── Filament/
│           └── Widgets/
│               ├── PastDueSubscriptionsStatWidget.php   # UPDATE: set $sort = 8
│               └── VendorsByTierWidget.php              # UPDATE: set $sort = 9; add heading translation key

tests/
└── Feature/
    └── Modules/
        ├── Identity/
        │   └── Filament/
        │       └── VendorsAwaitingApprovalWidgetTest.php  # NEW
        ├── Catalog/
        │   └── Filament/
        │       └── ServicesPendingModerationByTypeWidgetTest.php  # NEW
        ├── Booking/
        │   └── Filament/
        │       ├── OverdueBookingsWidgetTest.php              # NEW
        │       ├── RevenueByProductTypeChartTest.php          # NEW
        │       └── BookingsByTypeChartTest.php                # NEW
        ├── Settlement/
        │   └── Filament/
        │       └── WithdrawalsQueueWidgetTest.php             # NEW
        └── Communication/
            └── Filament/
                └── OpenChatFlagsStatWidgetTest.php            # NEW
```

---

## Phase 0: Research

### R-01 — `chat_moderation_flags` table status

**Decision**: Table does NOT exist yet (Phase 8.2 work). Confirmed by scanning `app/Modules/Communication/Database/Migrations/` — no `create_chat_moderation_flags_table` migration present.

**Rationale**: `OpenChatFlagsStatWidget` must use `Schema::hasTable('chat_moderation_flags')` guard and return a stat with value 0 when the table is absent. The widget is registered now so the dashboard slot is reserved.

**Alternatives considered**: Skip the widget entirely until Phase 8.2 — rejected because the spec explicitly requires 9 widgets on Day 1, and a graceful 0 is better than a missing slot.

---

### R-02 — Revenue chart data source

**Decision**: Aggregate from `booking_items.line_total_minor` grouped by `booking_items.product_type` and date, filtering via JOIN to `booking_vendors → bookings` where `bookings.payment_status = 'paid'`.

**Query shape**:
```sql
SELECT
    DATE(bookings.created_at) AS day,
    booking_items.product_type,
    SUM(booking_items.line_total_minor) AS total_minor
FROM booking_items
JOIN booking_vendors ON booking_vendors.id = booking_items.booking_vendor_id
JOIN bookings ON bookings.id = booking_vendors.booking_id
WHERE bookings.payment_status = 'paid'
  AND bookings.created_at >= NOW() - INTERVAL 30 DAY
  AND bookings.deleted_at IS NULL
GROUP BY day, booking_items.product_type
ORDER BY day
```

All models (`BookingItem`, `BookingVendor`, `Booking`) live in the Booking module. No cross-module imports needed. 

**Rationale**: `booking_items.line_total_minor` is the per-item pre-commission revenue (integer minor units). It's the closest proxy to "revenue by type." Commission is deducted at settlement; for a revenue chart we want gross item totals.

**Alternatives considered**: Use `Booking.total_minor` — rejected because `bookings` doesn't carry `product_type`. Aggregate from `commissions` — rejected because commissions are a settlement concern, not a booking concern.

---

### R-03 — Bookings-by-type chart data source

**Decision**: Count `booking_items` rows grouped by `product_type` and day, filtering through `bookings.created_at` in the last 7 days and `bookings.deleted_at IS NULL`.

**Rationale**: A booking can contain items of multiple types. Counting `booking_items` per type per day gives the most meaningful "demand signal" per type. Counting `bookings` only (ignoring type) would lose per-type granularity.

**Alternatives considered**: Count distinct `bookings` per type — impossible since bookings don't have a single `product_type`. Count `booking_vendors` per type — also impossible since `booking_vendors` doesn't carry `product_type` directly.

---

### R-04 — `OverdueBookingsWidget` URL target

**Decision**: Link to `BookingsMonitorResource::getUrl()` since `BookingsMonitorResource` exists at `app/Modules/Booking/Filament/Resources/BookingsMonitorResource.php`.

**Rationale**: This is the admin's overdue-booking triage page. A direct URL pointing into the resource with a pre-applied filter would be ideal, but Filament v3 URL construction for filtered resource lists requires building query string manually. The plan opts for the resource index URL as the link target — admin can then filter manually from there. Full pre-filtered URL can be added in Phase 8.8 (Daily Ops).

---

### R-05 — Translation file strategy

**Decision**: Create new `widgets.php` lang files in each module's `Resources/lang/{en,ar}/` rather than appending to existing files (e.g., `booking.php`).

**Rationale**: Keeps widget-specific strings isolated and easy to find. Follows the principle of one concern per file. Module ServiceProviders already call `$this->loadTranslationsFrom()`, so new files in the same lang dir are automatically available with the existing `{module}::widgets.{key}` pattern.

**Translation key convention**:
```php
// Identity module
__('identity::widgets.vendors_awaiting_approval')
__('identity::widgets.vendors_awaiting_approval_description')

// Catalog module
__('catalog::widgets.pending_moderation_by_type')
__('catalog::widgets.pending_rental')
__('catalog::widgets.pending_sale')
__('catalog::widgets.pending_digital')

// Booking module
__('booking::widgets.overdue_bookings')
__('booking::widgets.overdue_bookings_description')
__('booking::widgets.revenue_by_type_heading')
__('booking::widgets.bookings_by_type_heading')

// Settlement module
__('settlement::widgets.pending_withdrawals')
__('settlement::widgets.pending_withdrawals_description')

// Communication module
__('communication::widgets.open_chat_flags')
__('communication::widgets.open_chat_flags_description')
```

---

### R-06 — `BookingStatsWidget` removal

**Decision**: Delete `app/Modules/Booking/Filament/Widgets/BookingStatsWidget.php`. The Booking module's `discoverWidgets` call in `AdminPanelProvider` will no longer discover it.

**Rationale**: The spec explicitly states "Replace the 3-stat skeleton dashboard." The existing `BookingStatsWidget` (draft / submitted-today / confirmed-today) is that skeleton. The 9 new widgets provide superior operational visibility. The draft/submitted/confirmed counts can be re-added as a separate PR if requested.

---

### R-07 — Widget sort order (deterministic layout)

**Decision**: Sort order determines Filament dashboard layout top-to-bottom, left-to-right.

| Sort | Widget | Type |
|------|--------|------|
| 1 | VendorsAwaitingApprovalWidget | StatsOverview |
| 2 | ServicesPendingModerationByTypeWidget | StatsOverview (3 stats) |
| 3 | OverdueBookingsWidget | StatsOverview |
| 4 | WithdrawalsQueueWidget | StatsOverview |
| 5 | OpenChatFlagsStatWidget | StatsOverview |
| 6 | RevenueByProductTypeChart | ChartWidget |
| 7 | BookingsByTypeChart | ChartWidget |
| 8 | PastDueSubscriptionsStatWidget | StatsOverview (update from 2) |
| 9 | VendorsByTierWidget | ChartWidget (update from 1) |

---

## Phase 1: Design & Contracts

### data-model.md content

No new tables. No new migrations. All queries are aggregates over existing tables:

| Table | Module | Used by | Key columns |
|---|---|---|---|
| `vendor_profiles` | Identity | VendorsAwaitingApprovalWidget | `approval_status` (enum, cast to `ApprovalStatus`) |
| `services` | Catalog | ServicesPendingModerationByTypeWidget | `status` (cast to `ServiceStatus`), `product_type` (cast to `ProductType`) |
| `booking_vendors` | Booking | OverdueBookingsWidget | `response_deadline` (datetime), `sub_status` (cast to `VendorSubStatus`) |
| `booking_items` | Booking | RevenueByProductTypeChart, BookingsByTypeChart | `product_type`, `line_total_minor`, `booking_vendor_id`, `created_at` |
| `bookings` | Booking | Charts (join) | `payment_status` (cast to `PaymentStatus`), `created_at`, `deleted_at` |
| `withdrawals` | Settlement | WithdrawalsQueueWidget | `status` (cast to `WithdrawalStatus`) |
| `chat_moderation_flags` | Communication | OpenChatFlagsStatWidget | Does not exist yet; guarded with `Schema::hasTable()` |
| `subscription_invoices` | Subscriptions | PastDueSubscriptionsStatWidget (already exists) | `status` (cast to `InvoiceStatus`) |
| `vendor_subscriptions` | Subscriptions | VendorsByTierWidget (already exists) | `plan_id`, `status` |

### Widget class designs

#### 1. VendorsAwaitingApprovalWidget
```
Namespace:   App\Modules\Identity\Filament\Widgets
Extends:     StatsOverviewWidget
Sort:        1
Query:       VendorProfile::where('approval_status', ApprovalStatus::Pending)->count()
url():       VendorApprovalQueueResource::getUrl('index')
Heading key: identity::widgets.vendors_awaiting_approval
Color:       warning
Icon:        heroicon-m-clock
```

#### 2. ServicesPendingModerationByTypeWidget
```
Namespace:   App\Modules\Catalog\Filament\Widgets
Extends:     StatsOverviewWidget
Sort:        2
Query (×3):  Service::where('status', ServiceStatus::PendingReview)
             ->where('product_type', ProductType::{Type})->count()
url() each:  {Type}ServiceResource::getUrl('index')  (pre-filtered via tabUrl or resource URL)
Heading keys: catalog::widgets.pending_rental / pending_sale / pending_digital
Colors:      warning (rental), success (sale), info (digital)
Icons:       heroicon-m-wrench (rental), heroicon-m-shopping-bag (sale), heroicon-m-bolt (digital)
```

#### 3. OverdueBookingsWidget
```
Namespace:   App\Modules\Booking\Filament\Widgets
Extends:     StatsOverviewWidget
Sort:        3
Query:       BookingVendor::where('sub_status', VendorSubStatus::Pending)
             ->where('response_deadline', '<', now())->count()
url():       BookingsMonitorResource::getUrl('index')
Heading key: booking::widgets.overdue_bookings
Color:       danger
Icon:        heroicon-m-exclamation-circle
```

#### 4. WithdrawalsQueueWidget
```
Namespace:   App\Modules\Settlement\Filament\Widgets
Extends:     StatsOverviewWidget
Sort:        4
Query:       Withdrawal::where('status', WithdrawalStatus::Pending)->count()
url():       WithdrawalsQueueResource::getUrl('index')
Heading key: settlement::widgets.pending_withdrawals
Color:       warning
Icon:        heroicon-m-banknotes
```

#### 5. OpenChatFlagsStatWidget
```
Namespace:   App\Modules\Communication\Filament\Widgets
Extends:     StatsOverviewWidget
Sort:        5
Query:       Schema::hasTable('chat_moderation_flags')
             ? DB::table('chat_moderation_flags')->whereNull('resolved_at')->count()
             : 0
url():       null (Communication Filament resource doesn't exist yet in Phase 1)
Heading key: communication::widgets.open_chat_flags
Color:       danger
Icon:        heroicon-m-flag
```

#### 6. RevenueByProductTypeChart
```
Namespace:   App\Modules\Booking\Filament\Widgets
Extends:     ChartWidget
Sort:        6
Type:        'bar' (stacked)
Period:      Last 30 days
Heading key: booking::widgets.revenue_by_type_heading
Datasets:    3 (Rental, Sale, Digital) — SUM(line_total_minor) per day per type
Join:        booking_items → booking_vendors → bookings (payment_status=paid, deleted_at IS NULL)
Data shape:  Raw integer minor units; Chart.js formats as currency label via formatted display
```

#### 7. BookingsByTypeChart
```
Namespace:   App\Modules\Booking\Filament\Widgets
Extends:     ChartWidget
Sort:        7
Type:        'bar' (stacked)
Period:      Last 7 days
Heading key: booking::widgets.bookings_by_type_heading
Datasets:    3 (Rental, Sale, Digital) — COUNT(*) per day per product_type from booking_items
Join:        booking_items → booking_vendors → bookings (deleted_at IS NULL)
```

#### 8. PastDueSubscriptionsStatWidget (UPDATE ONLY)
```
Change: protected static ?int $sort = 8;  (was 2)
No other changes needed.
```

#### 9. VendorsByTierWidget (UPDATE ONLY)
```
Change: protected static ?int $sort = 9;  (was 1)
Change: add heading translation: booking::widgets... no, subscriptions module
Add:   protected static ?string $heading = null;  (use translation key via getHeading())
Note:  Current hardcoded heading 'Vendors by Subscription Tier' should become a translation key.
```

### Translation files

**`app/Modules/Identity/Resources/lang/en/widgets.php`**
```php
return [
    'vendors_awaiting_approval' => 'Vendors Awaiting Approval',
    'vendors_awaiting_approval_description' => 'Pending vendor profiles',
];
```

**`app/Modules/Identity/Resources/lang/ar/widgets.php`**
```php
return [
    'vendors_awaiting_approval' => 'البائعون في انتظار الموافقة',
    'vendors_awaiting_approval_description' => 'ملفات البائعين المعلقة',
];
```

**`app/Modules/Catalog/Resources/lang/en/widgets.php`**
```php
return [
    'pending_moderation_by_type' => 'Services Pending Moderation',
    'pending_rental' => 'Rental Services',
    'pending_sale' => 'Sale Services',
    'pending_digital' => 'Digital Services',
];
```

**`app/Modules/Catalog/Resources/lang/ar/widgets.php`**
```php
return [
    'pending_moderation_by_type' => 'خدمات تنتظر المراجعة',
    'pending_rental' => 'خدمات الإيجار',
    'pending_sale' => 'خدمات البيع',
    'pending_digital' => 'الخدمات الرقمية',
];
```

**`app/Modules/Booking/Resources/lang/en/widgets.php`**
```php
return [
    'overdue_bookings' => 'Overdue Vendor Responses',
    'overdue_bookings_description' => 'Past response deadline',
    'revenue_by_type_heading' => 'Revenue by Product Type (Last 30 Days)',
    'bookings_by_type_heading' => 'Bookings by Product Type (Last 7 Days)',
];
```

**`app/Modules/Booking/Resources/lang/ar/widgets.php`**
```php
return [
    'overdue_bookings' => 'ردود البائعين المتأخرة',
    'overdue_bookings_description' => 'تجاوز الموعد النهائي للرد',
    'revenue_by_type_heading' => 'الإيرادات حسب نوع المنتج (آخر 30 يومًا)',
    'bookings_by_type_heading' => 'الحجوزات حسب نوع المنتج (آخر 7 أيام)',
];
```

**`app/Modules/Settlement/Resources/lang/en/widgets.php`**
```php
return [
    'pending_withdrawals' => 'Pending Withdrawals',
    'pending_withdrawals_description' => 'Awaiting admin approval',
];
```

**`app/Modules/Settlement/Resources/lang/ar/widgets.php`**
```php
return [
    'pending_withdrawals' => 'طلبات السحب المعلقة',
    'pending_withdrawals_description' => 'في انتظار موافقة المشرف',
];
```

**`app/Modules/Communication/Resources/lang/en/widgets.php`**
```php
return [
    'open_chat_flags' => 'Open Chat Compliance Flags',
    'open_chat_flags_description' => 'Unresolved chat moderation items',
];
```

**`app/Modules/Communication/Resources/lang/ar/widgets.php`**
```php
return [
    'open_chat_flags' => 'تنبيهات الامتثال في الدردشة',
    'open_chat_flags_description' => 'عناصر الإشراف غير المحلولة',
];
```

### AdminPanelProvider changes

Add 4 `discoverWidgets` calls (after existing ones):
```php
->discoverWidgets(in: app_path('Modules/Identity/Filament/Widgets'), for: 'App\\Modules\\Identity\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Catalog/Filament/Widgets'), for: 'App\\Modules\\Catalog\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Settlement/Filament/Widgets'), for: 'App\\Modules\\Settlement\\Filament\\Widgets')
->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\\Modules\\Communication\\Filament\\Widgets')
```

Remove `BookingStatsWidget` by deleting its file — discovery will stop auto-registering it.

### Contracts (N/A)

All widgets are Filament components within their owning modules. No cross-module interfaces are introduced. `OpenChatFlagsStatWidget` uses `DB::table()` directly rather than a Model contract because the `ChatModerationFlag` model doesn't exist yet. When Phase 8.2 creates the model, the widget can be updated to use it without changing the widget's API contract.

---

## Complexity Tracking

> No constitution violations requiring justification.

---

## Cut-list (inherited from Phase 8.1)

- Drag-and-drop dashboard customization → Phase 1.5
- Per-admin dashboard layouts → Phase 1.5
- Pre-filtered resource links (stat widget clicks open filtered list) → Phase 8.8 Daily Ops (for now, link to resource index)
- `OpenChatFlagsStatWidget` full query logic → Phase 8.2 (when `chat_moderation_flags` table exists)

---

## Exit Criteria

- [ ] Dashboard shows 9 widgets (Filament dashboard `/admin` page renders all 9)
- [ ] `BookingStatsWidget` file deleted; no longer appears on dashboard
- [ ] AR locale: all 9 widget headings render Arabic strings (not raw keys or English fallback)
- [ ] `./vendor/bin/pest --group=dashboard-widgets` all green
- [ ] `php artisan pint` and PHPStan pass with no errors on changed files
