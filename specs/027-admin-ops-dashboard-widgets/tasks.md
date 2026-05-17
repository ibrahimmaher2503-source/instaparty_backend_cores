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

# Tasks: Admin Operations Dashboard Widgets

**Feature**: `027-admin-ops-dashboard-widgets`
**Input**: `specs/027-admin-ops-dashboard-widgets/`
**Phase**: Phase 8.1 â€” Admin Active Ops Dashboard
**PRD**: FR-14â€“FR-18, FR-19, FR-22â€“FR-30; Admin Journey Â§1â€“Â§6
**Total tasks**: 37

## Format: `[ID] [P?] [Story?] Description â€” file path`

- **[P]**: Can run in parallel (touches different files, no blocking dependency)
- **[US1â€“US4]**: Which user story this task serves
- Tests included per constitution Â§VII (test-first for critical paths)

---

## Phase 1: Setup â€” Widget Discovery & Directory Structure

**Purpose**: Wire Filament auto-discovery for the 4 new module widget folders. No widget code yet â€” just the infrastructure so `discoverWidgets()` can find classes when they are created in Phase 3.

- [X] T001 Add 4 new `->discoverWidgets()` calls to `AdminPanelProvider::panel()` immediately after the existing Settlement widget discovery call â€” file: `app/Providers/Filament/AdminPanelProvider.php`

  ```php
  // Insert after line 96 (->discoverWidgets Settlement):
  ->discoverWidgets(in: app_path('Modules/Identity/Filament/Widgets'), for: 'App\\Modules\\Identity\\Filament\\Widgets')
  ->discoverWidgets(in: app_path('Modules/Catalog/Filament/Widgets'), for: 'App\\Modules\\Catalog\\Filament\\Widgets')
  ->discoverWidgets(in: app_path('Modules/Payments/Filament/Widgets'), for: 'App\\Modules\\Payments\\Filament\\Widgets')
  ->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\\Modules\\Communication\\Filament\\Widgets')
  ```

- [X] T002 [P] Create `Filament/Widgets/` directory in the Identity module â€” place a `.gitkeep` to track the empty directory: `app/Modules/Identity/Filament/Widgets/.gitkeep`

- [X] T003 [P] Create `Filament/Widgets/` directory in the Catalog module: `app/Modules/Catalog/Filament/Widgets/.gitkeep`

- [X] T004 [P] Create `Filament/Widgets/` directory in the Payments module: `app/Modules/Payments/Filament/Widgets/.gitkeep`

- [X] T005 [P] Create `Filament/Widgets/` directory in the Communication module: `app/Modules/Communication/Filament/Widgets/.gitkeep`

**Checkpoint**: `php artisan filament:cache-components` runs without errors after this phase.

---

## Phase 2: Foundational â€” Translation Scaffolding & Index Migration

**Purpose**: Create the EN translation stub files and an optional performance index migration. All widget implementations depend on the translation keys existing.

- [X] T006 Create EN translation stub for Identity widgets â€” file: `app/Modules/Identity/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'pending_vendor_approvals_heading' => 'Pending Vendor Approvals',
      'pending_vendor_approvals_description' => ':count vendor awaiting review|:count vendors awaiting review',
  ];
  ```

- [X] T007 [P] Create EN translation stub for Catalog widgets â€” file: `app/Modules/Catalog/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'pending_service_moderation_rental_heading' => 'Pending Rental Moderation',
      'pending_service_moderation_sale_heading'   => 'Pending Sale Moderation',
      'pending_service_moderation_digital_heading'=> 'Pending Digital Moderation',
      'pending_service_moderation_description'    => ':count service awaiting review|:count services awaiting review',
      'excel_imports_with_errors_heading'         => 'Excel Imports with Errors',
      'excel_imports_with_errors_description'     => ':count import with errors in last 7 days|:count imports with errors in last 7 days',
  ];
  ```

- [X] T008 [P] Create EN translation stub for Booking widgets â€” file: `app/Modules/Booking/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'late_vendor_responses_heading'                  => 'Late Vendor Responses',
      'late_vendor_responses_description'              => ':count vendor overdue|:count vendors overdue',
      'bookings_waiting_customer_approval_heading'     => 'Awaiting Customer Review',
      'bookings_waiting_customer_approval_description' => ':count booking pending customer approval|:count bookings pending customer approval',
  ];
  ```

- [X] T009 [P] Create EN translation stub for Payments widgets â€” file: `app/Modules/Payments/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'failed_payments_heading'     => 'Failed Payments (48 h)',
      'failed_payments_description' => ':count failed payment|:count failed payments',
  ];
  ```

- [X] T010 [P] Create EN translation stub for Communication widgets â€” file: `app/Modules/Communication/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'failed_notification_dispatches_heading'     => 'Failed Notifications (24 h)',
      'failed_notification_dispatches_description' => ':count failed dispatch|:count failed dispatches',
      'critical_admin_inbox_heading'               => 'Critical Inbox Items',
      'critical_admin_inbox_description'           => ':count critical item unresolved|:count critical items unresolved',
  ];
  ```

- [X] T011 [P] Create EN translation stub for Settlement widgets â€” file: `app/Modules/Settlement/Resources/lang/en/widgets.php`

  ```php
  <?php
  return [
      'pending_withdrawals_heading'     => 'Pending Withdrawals',
      'pending_withdrawals_description' => ':count withdrawal awaiting approval|:count withdrawals awaiting approval',
  ];
  ```

- [X] T012 Create performance index migration for admin ops widget queries â€” file: `app/Modules/Shared/Database/Migrations/{YYYY_MM_DD_HHMMSS}_add_indexes_for_admin_ops_widgets.php`

  Follows migration rules: `declare(strict_types=1)`, anonymous class, `utf8mb4` charset, `$table->charset`/`$table->collation` not needed for `table()` (only for `create()`). Adds:
  1. `bookings` â€” index on `lifecycle_status`
  2. `payments` â€” composite index on `(status, created_at)`
  3. `notification_dispatches` â€” composite index on `(status, created_at)`
  4. `admin_inbox_items` â€” composite index on `(severity, status)`

  Use short explicit index names (â‰¤ 64 chars, MySQL constraint):
  ```php
  $table->index('lifecycle_status', 'bk_lifecycle_status_idx');
  $table->index(['status', 'created_at'], 'pay_status_created_at_idx');
  $table->index(['status', 'created_at'], 'nd_status_created_at_idx');
  $table->index(['severity', 'status'], 'ai_severity_status_idx');
  ```

  Run migration after creation: `php artisan migrate`.

**Checkpoint**: `php artisan migrate` succeeds. Translation files exist for all 6 modules.

---

## Phase 3: User Story 1 â€” Live Operational Stat Counts

**Goal**: All 9 widgets created and visible on `/admin` dashboard with correct live counts.

**Independent Test**: Seed one row per widget's trigger condition; load `/admin`; assert all 9 widget headings appear in HTML and each shows count â‰¥ 1.

Each widget class extends `Filament\Widgets\StatsOverviewWidget`, implements `static::canView()`, declares `$sort`, and returns `Stat::make()` with `->color()`, `->icon()`, `->url()`. Use enum `->value` for all WHERE clauses (see research.md R-01).

### Implementation

- [X] T013 [P] [US1] Create `PendingVendorApprovalsWidget` in Identity module.

  File: `app/Modules/Identity/Filament/Widgets/PendingVendorApprovalsWidget.php`

  - Namespace: `App\Modules\Identity\Filament\Widgets`
  - Imports: `VendorProfile`, `ApprovalStatus`, `VendorApprovalQueueResource`
  - Query: `VendorProfile::query()->where('approval_status', ApprovalStatus::Pending->value)->count()`
  - `$sort = 10`
  - Color: `'danger'` when > 0, `'success'` when 0
  - Icon: `heroicon-o-user-plus`
  - URL: `VendorApprovalQueueResource::getUrl('index')`
  - Label (EN): `__('identity::widgets.pending_vendor_approvals_heading')`
  - `canView()`: `auth()->user()?->can('view_any_vendor_profile') ?? false`

- [X] T014 [P] [US1] Create `PendingServiceModerationWidget` in Catalog module.

  File: `app/Modules/Catalog/Filament/Widgets/PendingServiceModerationWidget.php`

  - Namespace: `App\Modules\Catalog\Filament\Widgets`
  - Imports: `Service`, `ServiceStatus`, `ProductType`, `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource`
  - Returns three `Stat` objects â€” one per `ProductType` case. Use `match($type)` for URL and heading:
    ```php
    protected function getStats(): array
    {
        $stats = [];
        foreach (ProductType::cases() as $type) {
            $count = Service::query()
                ->where('status', ServiceStatus::PendingReview->value)
                ->where('product_type', $type->value)
                ->count();
            $stats[] = Stat::make(
                label: __("catalog::widgets.pending_service_moderation_{$type->value}_heading"),
                value: $count,
            )
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-shield-check')
                ->url(match ($type) {
                    ProductType::Rental  => RentalServiceResource::getUrl('pending'),
                    ProductType::Sale    => SaleServiceResource::getUrl('pending'),
                    ProductType::Digital => DigitalServiceResource::getUrl('pending'),
                });
        }
        return $stats;
    }
    ```
  - `$sort = 20`
  - `canView()`: `auth()->user()?->can('view_any_rental_service') ?? false`

- [X] T015 [P] [US1] Create `LateVendorResponsesWidget` in Booking module.

  File: `app/Modules/Booking/Filament/Widgets/LateVendorResponsesWidget.php`

  - Imports: `BookingVendor`, `VendorSubStatus`, `BookingsMonitorResource`
  - Query:
    ```php
    BookingVendor::query()
        ->whereNotNull('response_deadline')
        ->where('response_deadline', '<', now())
        ->where('sub_status', VendorSubStatus::Pending->value)
        ->count()
    ```
  - `$sort = 30`, color `'danger'`/`'success'`, icon `heroicon-o-clock`
  - URL: `BookingsMonitorResource::getUrl('index')`
  - `canView()`: `auth()->user()?->can('view_any_bookings_monitor') ?? false`

- [X] T016 [P] [US1] Create `BookingsWaitingCustomerApprovalWidget` in Booking module.

  File: `app/Modules/Booking/Filament/Widgets/BookingsWaitingCustomerApprovalWidget.php`

  - Imports: `Booking`, `LifecycleStatus`, `BookingResource`
  - Query: `Booking::query()->where('lifecycle_status', LifecycleStatus::CustomerReview->value)->count()`
  - `$sort = 40`, color `'warning'`/`'success'`, icon `heroicon-o-clock`
  - URL: `BookingResource::getUrl('index') . '?tableFilters[lifecycle_status][value]=customer_review'`
  - `canView()`: `auth()->user()?->can('view_any_booking') ?? false`

- [X] T017 [P] [US1] Create `FailedPaymentsWidget` in Payments module.

  File: `app/Modules/Payments/Filament/Widgets/FailedPaymentsWidget.php`

  - Namespace: `App\Modules\Payments\Filament\Widgets`
  - Import `App\Modules\Payments\Domain\Enums\PaymentStatus` (NOT Booking's PaymentStatus â€” namespace conflict)
  - Import `App\Modules\Payments\Filament\Resources\PaymentResource`
  - Query:
    ```php
    Payment::query()
        ->where('status', PaymentStatus::Failed->value)
        ->where('created_at', '>=', now()->subHours(48))
        ->count()
    ```
  - `$sort = 50`, color `'danger'`/`'success'`, icon `heroicon-o-credit-card`
  - URL: `PaymentResource::getUrl('index') . '?tableFilters[status][value]=failed'`
  - `canView()`: `auth()->user()?->can('view_any_payment') ?? false`

- [X] T018 [P] [US1] Create `FailedNotificationDispatchesWidget` in Communication module.

  File: `app/Modules/Communication/Filament/Widgets/FailedNotificationDispatchesWidget.php`

  - Imports: `NotificationDispatch`, `DispatchStatus`, `NotificationDispatchResource`
  - Query:
    ```php
    NotificationDispatch::query()
        ->whereIn('status', [DispatchStatus::Failed->value, DispatchStatus::Bounced->value])
        ->where('created_at', '>=', now()->subHours(24))
        ->count()
    ```
  - `$sort = 60`, color `'warning'`/`'success'`, icon `heroicon-o-bell-slash`
  - URL: `NotificationDispatchResource::getUrl('index') . '?tableFilters[status][value]=failed'`
  - `canView()`: `auth()->user()?->can('view_any_notification_dispatch') ?? false`

- [X] T019 [P] [US1] Create `PendingWithdrawalsWidget` in Settlement module.

  File: `app/Modules/Settlement/Filament/Widgets/PendingWithdrawalsWidget.php`

  - Imports: `Withdrawal`, `WithdrawalStatus`, `WithdrawalsQueueResource`
  - Query: `Withdrawal::query()->where('status', WithdrawalStatus::Pending->value)->count()`
  - `$sort = 70`, color `'warning'`/`'success'`, icon `heroicon-o-banknotes`
  - URL: `WithdrawalsQueueResource::getUrl('index')`
  - `canView()`: `auth()->user()?->can('view_any_withdrawals_queue') ?? false`

- [X] T020 [P] [US1] Create `ExcelImportsWithErrorsWidget` in Catalog module.

  File: `app/Modules/Catalog/Filament/Widgets/ExcelImportsWithErrorsWidget.php`

  - Imports: `ExcelImport`, `ExcelImportResource`
  - Query (note: `status` is a plain string on `ExcelImport`, no enum):
    ```php
    ExcelImport::query()
        ->where(function ($q) {
            $q->where('status', 'failed')->orWhere('error_rows', '>', 0);
        })
        ->where('created_at', '>=', now()->subDays(7))
        ->count()
    ```
  - `$sort = 80`, color `'danger'`/`'success'`, icon `heroicon-o-document-chart-bar`
  - URL: `ExcelImportResource::getUrl('index')`
  - `canView()`: `auth()->user()?->can('view_any_excel_import') ?? false`

- [X] T021 [P] [US1] Create `CriticalAdminInboxWidget` in Communication module.

  File: `app/Modules/Communication/Filament/Widgets/CriticalAdminInboxWidget.php`

  - Imports: `AdminInboxItem`, `AdminInboxSeverity`, `AdminInboxStatus`, `AdminInboxResource`
  - Query:
    ```php
    AdminInboxItem::query()
        ->where('severity', AdminInboxSeverity::Critical->value)
        ->whereIn('status', [AdminInboxStatus::Unread->value, AdminInboxStatus::Read->value])
        ->count()
    ```
  - `$sort = 90`, color `'danger'`/`'success'`, icon `heroicon-o-exclamation-triangle`
  - URL: `AdminInboxResource::getUrl('index') . '?tableFilters[severity][value]=critical'`
  - `canView()`: `auth()->user()?->can('view_any_admin_inbox_item') ?? false`

**Checkpoint**: Load `/admin` in browser. Confirm 9 widget headings appear in the dashboard. All counts show (some may be 0 if no seed data).

---

## Phase 4: User Story 1 Tests â€” Count Accuracy & Time-Window Assertions

**Goal**: Pest tests verify each widget's count logic, zero-state, and time window exclusion rules.

**Independent Test**: `./vendor/bin/pest --group=widgets` all green.

- [X] T022 [P] [US1] Write Pest test for `PendingVendorApprovalsWidget` â€” file: `tests/Feature/Modules/Identity/Filament/Widgets/PendingVendorApprovalsWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'identity')`):
  1. Seeds 2 pending + 1 approved vendor â†’ widget count = 2
  2. Seeds only approved vendors â†’ widget count = 0
  3. `canView()` returns true for admin with `view_any_vendor_profile`
  4. `canView()` returns false for user without that permission

- [X] T023 [P] [US1] Write Pest test for `PendingServiceModerationWidget` â€” file: `tests/Feature/Modules/Catalog/Filament/Widgets/PendingServiceModerationWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'catalog', 'rental', 'sale', 'digital')`):
  1. Seeds 1 rental + 1 digital pending_review â†’ Rental stat = 1, Sale stat = 0, Digital stat = 1
  2. Seeds no pending_review services â†’ all three stats = 0
  3. `canView()` returns true with `view_any_rental_service`

- [X] T024 [P] [US1] Write Pest test for `LateVendorResponsesWidget` â€” file: `tests/Feature/Modules/Booking/Filament/Widgets/LateVendorResponsesWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'booking')`):
  1. Seeds 1 overdue vendor (response_deadline in past, sub_status=pending) â†’ count = 1
  2. Seeds vendor with response_deadline in future â†’ count = 0
  3. Seeds vendor with response_deadline = NULL â†’ count = 0 (critical edge case from spec)
  4. Seeds vendor with sub_status=accepted â†’ count = 0

- [X] T025 [P] [US1] Write Pest test for `BookingsWaitingCustomerApprovalWidget` â€” file: `tests/Feature/Modules/Booking/Filament/Widgets/BookingsWaitingCustomerApprovalWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'booking')`):
  1. Seeds 1 booking with lifecycle_status=customer_review â†’ count = 1
  2. Seeds booking with lifecycle_status=confirmed â†’ count = 0

- [X] T026 [P] [US1] Write Pest test for `FailedPaymentsWidget` â€” file: `tests/Feature/Modules/Payments/Filament/Widgets/FailedPaymentsWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'payments')`):
  1. Seeds 1 failed payment created 1 hour ago â†’ count = 1
  2. Seeds 1 failed payment created 49 hours ago â†’ count = 0 (outside 48 h window)
  3. Seeds 1 captured payment created 1 hour ago â†’ count = 0

- [X] T027 [P] [US1] Write Pest test for `FailedNotificationDispatchesWidget` â€” file: `tests/Feature/Modules/Communication/Filament/Widgets/FailedNotificationDispatchesWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'communication')`):
  1. Seeds 1 failed dispatch created 10 minutes ago â†’ count = 1
  2. Seeds 1 bounced dispatch created 10 minutes ago â†’ count = 1 (both statuses counted)
  3. Seeds 1 failed dispatch created 25 hours ago â†’ count = 0 (outside 24 h window)

- [X] T028 [P] [US1] Write Pest test for `PendingWithdrawalsWidget` â€” file: `tests/Feature/Modules/Settlement/Filament/Widgets/PendingWithdrawalsWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'settlement')`):
  1. Seeds 1 withdrawal with status=pending â†’ count = 1
  2. Seeds 1 withdrawal with status=approved â†’ count = 0

- [X] T029 [P] [US1] Write Pest test for `ExcelImportsWithErrorsWidget` â€” file: `tests/Feature/Modules/Catalog/Filament/Widgets/ExcelImportsWithErrorsWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'catalog')`):
  1. Seeds 1 import with status='failed' and error_rows=0, created today â†’ count = 1 (status gate)
  2. Seeds 1 import with status='processing' and error_rows=3, created today â†’ count = 1 (error_rows gate)
  3. Seeds 1 import with status='failed' created 8 days ago â†’ count = 0 (7-day window)
  4. Seeds 1 import with status='completed' and error_rows=0 â†’ count = 0

- [X] T030 [P] [US1] Write Pest test for `CriticalAdminInboxWidget` â€” file: `tests/Feature/Modules/Communication/Filament/Widgets/CriticalAdminInboxWidgetTest.php`

  Test cases (use `->group('widgets', 'admin', 'communication')`):
  1. Seeds 1 AdminInboxItem with severity=critical, status=unread â†’ count = 1
  2. Seeds 1 AdminInboxItem with severity=critical, status=resolved â†’ count = 0
  3. Seeds 1 AdminInboxItem with severity=warning, status=unread â†’ count = 0

**Checkpoint**: `./vendor/bin/pest --group=widgets` all green.

---

## Phase 5: User Story 2 â€” Pre-filtered Navigation Links

**Goal**: Four widgets that navigate to filtered resource views have their URL assertions validated by Pest.

**Independent Test**: Assert `url()` output for the four filtered widgets contains the expected query-string filter.

- [X] T031 [P] [US2] Pest: assert `BookingsWaitingCustomerApprovalWidget` url() output contains `lifecycle_status` filter string â€” add to existing `BookingsWaitingCustomerApprovalWidgetTest.php`

- [X] T032 [P] [US2] Pest: assert `FailedPaymentsWidget` url() output contains `status` filter string â€” add to existing `FailedPaymentsWidgetTest.php`

- [X] T033 [P] [US2] Pest: assert `FailedNotificationDispatchesWidget` url() output contains `status` filter string â€” add to existing `FailedNotificationDispatchesWidgetTest.php`

- [X] T034 [P] [US2] Pest: assert `CriticalAdminInboxWidget` url() output contains `severity=critical` filter string â€” add to existing `CriticalAdminInboxWidgetTest.php`

**Checkpoint**: All 4 URL assertions pass.

---

## Phase 6: User Story 3 â€” Authorization Gates

**Goal**: Widgets are hidden from admins who lack the relevant permission gate.

**Independent Test**: Create admin user with only `view_any_payment` permission; assert `PendingVendorApprovalsWidget::canView()` returns false and `FailedPaymentsWidget::canView()` returns true.

- [X] T035 [US3] Write Pest authorization test covering all 9 widgets â€” file: `tests/Feature/Modules/Shared/Filament/Widgets/AdminOpsWidgetAuthorizationTest.php`

  Test cases (use `->group('widgets', 'admin', 'authorization')`):
  1. User with all permissions â†’ all 9 `canView()` return true
  2. User with zero permissions â†’ all 9 `canView()` return false
  3. User with only `view_any_payment` â†’ only `FailedPaymentsWidget::canView()` = true, rest = false
  4. super_admin role â†’ all 9 `canView()` return true (Shield super-admin bypass)

**Checkpoint**: Authorization test passes.

---

## Phase 7: User Story 4 â€” Arabic (AR) Translation Keys

**Goal**: All 9 widget headings render in Arabic when the Filament locale is `ar`.

**Independent Test**: Load dashboard with `app()->setLocale('ar')`; assert each widget heading is non-empty Arabic text (not the translation key string).

- [X] T036 [P] [US4] Create AR translation file for Identity widgets â€” file: `app/Modules/Identity/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'pending_vendor_approvals_heading'     => 'Ù…ÙˆØ±Ø¯ÙˆÙ† ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ø§Ø¹ØªÙ…Ø§Ø¯',
      'pending_vendor_approvals_description' => 'Ù…ÙˆØ±Ø¯ ÙˆØ§Ø­Ø¯ ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©|:count Ù…ÙˆØ±Ø¯ÙŠÙ† ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©',
  ];
  ```

- [X] T037 [P] [US4] Create AR translation file for Catalog widgets â€” file: `app/Modules/Catalog/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'pending_service_moderation_rental_heading'  => 'Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø¥ÙŠØ¬Ø§Ø± ØªÙ†ØªØ¸Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©',
      'pending_service_moderation_sale_heading'    => 'Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø¨ÙŠØ¹ ØªÙ†ØªØ¸Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©',
      'pending_service_moderation_digital_heading' => 'Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø±Ù‚Ù…ÙŠØ© ØªÙ†ØªØ¸Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©',
      'pending_service_moderation_description'     => 'Ø®Ø¯Ù…Ø© ÙˆØ§Ø­Ø¯Ø© ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©|:count Ø®Ø¯Ù…Ø§Øª ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…Ø±Ø§Ø¬Ø¹Ø©',
      'excel_imports_with_errors_heading'          => 'Ø§Ø³ØªÙŠØ±Ø§Ø¯Ø§Øª Excel Ø¨Ù‡Ø§ Ø£Ø®Ø·Ø§Ø¡',
      'excel_imports_with_errors_description'      => 'Ø§Ø³ØªÙŠØ±Ø§Ø¯ ÙˆØ§Ø­Ø¯ Ø¨Ù‡ Ø£Ø®Ø·Ø§Ø¡|:count Ø§Ø³ØªÙŠØ±Ø§Ø¯Ø§Øª Ø¨Ù‡Ø§ Ø£Ø®Ø·Ø§Ø¡',
  ];
  ```

- [X] T038 [P] [US4] Create AR translation file for Booking widgets â€” file: `app/Modules/Booking/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'late_vendor_responses_heading'                  => 'Ø±Ø¯ÙˆØ¯ Ù…ÙˆØ±Ø¯ÙŠÙ† Ù…ØªØ£Ø®Ø±Ø©',
      'late_vendor_responses_description'              => 'Ù…ÙˆØ±Ø¯ ÙˆØ§Ø­Ø¯ Ù…ØªØ£Ø®Ø±|:count Ù…ÙˆØ±Ø¯ÙŠÙ† Ù…ØªØ£Ø®Ø±ÙŠÙ†',
      'bookings_waiting_customer_approval_heading'     => 'Ø·Ù„Ø¨Ø§Øª ØªÙ†ØªØ¸Ø± Ù…ÙˆØ§ÙÙ‚Ø© Ø§Ù„Ø¹Ù…ÙŠÙ„',
      'bookings_waiting_customer_approval_description' => 'Ø·Ù„Ø¨ ÙˆØ§Ø­Ø¯ ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…ÙˆØ§ÙÙ‚Ø©|:count Ø·Ù„Ø¨Ø§Øª ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ù…ÙˆØ§ÙÙ‚Ø©',
  ];
  ```

- [X] T039 [P] [US4] Create AR translation file for Payments widgets â€” file: `app/Modules/Payments/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'failed_payments_heading'     => 'Ù…Ø¯ÙÙˆØ¹Ø§Øª ÙØ§Ø´Ù„Ø© (Ù¤Ù¨ Ø³Ø§Ø¹Ø©)',
      'failed_payments_description' => 'Ø¯ÙØ¹Ø© ÙØ§Ø´Ù„Ø© ÙˆØ§Ø­Ø¯Ø©|:count Ø¯ÙØ¹Ø§Øª ÙØ§Ø´Ù„Ø©',
  ];
  ```

- [X] T040 [P] [US4] Create AR translation file for Communication widgets â€” file: `app/Modules/Communication/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'failed_notification_dispatches_heading'     => 'Ø¥Ø´Ø¹Ø§Ø±Ø§Øª ÙØ§Ø´Ù„Ø© (Ù¢Ù¤ Ø³Ø§Ø¹Ø©)',
      'failed_notification_dispatches_description' => 'Ø¥Ø´Ø¹Ø§Ø± ÙØ§Ø´Ù„ ÙˆØ§Ø­Ø¯|:count Ø¥Ø´Ø¹Ø§Ø±Ø§Øª ÙØ§Ø´Ù„Ø©',
      'critical_admin_inbox_heading'               => 'Ø¨Ù†ÙˆØ¯ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„ÙˆØ§Ø±Ø¯ Ø§Ù„Ø­Ø±Ø¬Ø©',
      'critical_admin_inbox_description'           => 'Ø¨Ù†Ø¯ Ø­Ø±Ø¬ ÙˆØ§Ø­Ø¯ ØºÙŠØ± Ù…Ø­Ù„ÙˆÙ„|:count Ø¨Ù†ÙˆØ¯ Ø­Ø±Ø¬Ø© ØºÙŠØ± Ù…Ø­Ù„ÙˆÙ„Ø©',
  ];
  ```

- [X] T041 [P] [US4] Create AR translation file for Settlement widgets â€” file: `app/Modules/Settlement/Resources/lang/ar/widgets.php`

  ```php
  <?php
  return [
      'pending_withdrawals_heading'     => 'Ø·Ù„Ø¨Ø§Øª Ø³Ø­Ø¨ Ù…Ø¹Ù„Ù‚Ø©',
      'pending_withdrawals_description' => 'Ø·Ù„Ø¨ Ø³Ø­Ø¨ ÙˆØ§Ø­Ø¯ ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ø§Ø¹ØªÙ…Ø§Ø¯|:count Ø·Ù„Ø¨Ø§Øª Ø³Ø­Ø¨ ÙÙŠ Ø§Ù†ØªØ¸Ø§Ø± Ø§Ù„Ø§Ø¹ØªÙ…Ø§Ø¯',
  ];
  ```

- [X] T042 [US4] Write Pest locale test â€” file: `tests/Feature/Modules/Shared/Filament/Widgets/AdminOpsWidgetLocaleTest.php`

  Test cases (use `->group('widgets', 'admin', 'locale')`):
  1. Set locale to `ar` â†’ assert `__('identity::widgets.pending_vendor_approvals_heading')` is non-empty Arabic string, not the translation key
  2. Set locale to `en` â†’ assert the same key returns the English string
  3. Each of the 9 widget heading keys tested in AR â†’ not equal to the raw key string

**Checkpoint**: `./vendor/bin/pest --group=locale` passes.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Code quality gates and final verification.

- [X] T043 Run `php artisan shield:generate --all` to regenerate Shield permissions â€” verify no "undefined permission" exceptions when loading any widget on the dashboard

- [X] T044 [P] Run `./vendor/bin/pint` â€” fix any code style violations in all 9 new widget files and test files

- [X] T045 [P] Run `./vendor/bin/phpstan analyse app/Modules/Identity/Filament/Widgets app/Modules/Catalog/Filament/Widgets app/Modules/Booking/Filament/Widgets app/Modules/Payments/Filament/Widgets app/Modules/Communication/Filament/Widgets app/Modules/Settlement/Filament/Widgets` â€” fix any static analysis errors (focus on missing `use` imports and mismatched enum namespaces)

- [X] T046 Run `./vendor/bin/pest --group=widgets --bail` â€” all tests green before marking done

- [X] T047 [P] Verify `/admin` dashboard loads in AR locale (switch via Filament language switcher) â€” all 9 widget headings display Arabic strings, no raw translation keys visible

- [X] T048 [P] Run `php artisan filament:cache-components` â€” verify no discovery errors

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies â€” start immediately
- **Phase 2 (Foundational)**: Can start immediately in parallel with Phase 1; T001 (AdminPanelProvider) must be done before Phase 3 widgets are testable in browser
- **Phase 3 (US1 widgets)**: Requires Phase 1 (directories exist) and Phase 2 (translations exist, migration applied)
- **Phase 4 (US1 tests)**: Requires Phase 3 (widget classes exist to be tested)
- **Phase 5 (US2 tests)**: Requires Phase 3 (url() methods defined in widgets)
- **Phase 6 (US3 auth tests)**: Requires Phase 3 (canView() methods defined in widgets)
- **Phase 7 (US4 AR)**: Requires Phase 3 (widget classes use translation keys that need AR values)
- **Phase 8 (Polish)**: Requires all previous phases complete

### User Story Dependencies

- **US1**: Foundational phase complete â†’ 9 widgets independent of each other [P]
- **US2**: US1 complete â†’ url() assertions are add-ons to existing test files [P]
- **US3**: US1 complete â†’ canView() is already defined; just needs test coverage [P]
- **US4**: US1 complete (widget classes use i18n keys) â†’ AR files added [P]

### Within Phase 3: All Widget Tasks are Parallel

T013â€“T021 each touch different files. All 9 can be created simultaneously. No widget depends on another widget.

### Within Phase 4: All Test Tasks are Parallel

T022â€“T030 each touch different test files. All 9 can be written simultaneously.

---

## Parallel Execution Examples

### All 9 widgets simultaneously (Phase 3):
```
Task: T013 â€” PendingVendorApprovalsWidget (Identity)
Task: T014 â€” PendingServiceModerationWidget (Catalog)
Task: T015 â€” LateVendorResponsesWidget (Booking)
Task: T016 â€” BookingsWaitingCustomerApprovalWidget (Booking â€” different file)
Task: T017 â€” FailedPaymentsWidget (Payments)
Task: T018 â€” FailedNotificationDispatchesWidget (Communication)
Task: T019 â€” PendingWithdrawalsWidget (Settlement)
Task: T020 â€” ExcelImportsWithErrorsWidget (Catalog â€” different file)
Task: T021 â€” CriticalAdminInboxWidget (Communication â€” different file)
```

### All 9 widget tests simultaneously (Phase 4):
```
Task: T022 â€” PendingVendorApprovalsWidgetTest
Task: T023 â€” PendingServiceModerationWidgetTest
Task: T024 â€” LateVendorResponsesWidgetTest
Task: T025 â€” BookingsWaitingCustomerApprovalWidgetTest
Task: T026 â€” FailedPaymentsWidgetTest
Task: T027 â€” FailedNotificationDispatchesWidgetTest
Task: T028 â€” PendingWithdrawalsWidgetTest
Task: T029 â€” ExcelImportsWithErrorsWidgetTest
Task: T030 â€” CriticalAdminInboxWidgetTest
```

### All 6 AR translation files simultaneously (Phase 7):
```
Task: T036 â€” ar/widgets.php (Identity)
Task: T037 â€” ar/widgets.php (Catalog)
Task: T038 â€” ar/widgets.php (Booking)
Task: T039 â€” ar/widgets.php (Payments)
Task: T040 â€” ar/widgets.php (Communication)
Task: T041 â€” ar/widgets.php (Settlement)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 + Phase 2 (setup + migration + EN translations)
2. Complete Phase 3 (all 9 widgets with counts, icons, URLs, canView)
3. **STOP and VALIDATE**: Load `/admin`. Confirm all 9 widget headings appear. Seed data manually and verify counts update.
4. Optionally deploy or demo.

### Incremental Delivery

1. Phase 1 + 2 â†’ infrastructure ready
2. Phase 3 â†’ all 9 widgets live â†’ **MVP (US1 complete)**
3. Phase 4 â†’ tests green â†’ **US1 verified**
4. Phase 5 + 6 + 7 â†’ link, auth, locale tests â†’ **US2â€“US4 complete**
5. Phase 8 â†’ code quality â†’ **Ready to merge**

---

## Notes

- All widget queries use enum `->value` (not `->whereState()`) â€” see research.md R-01
- `ExcelImport.status` is a plain string cast â€” use raw `'failed'` string literal
- `FailedPaymentsWidget` must import `App\Modules\Payments\Domain\Enums\PaymentStatus` â€” see research.md R-07
- `PendingServiceModerationWidget` is the only multi-stat widget; returns 3 `Stat` objects using foreach + match
- After T043 (`shield:generate`), verify the admin user's role still has access to all resources (Shield regeneration can occasionally reset manually-assigned permissions)
- `[P]` tasks touch different files â€” safe to run in parallel with a multi-agent Claude Code workflow

