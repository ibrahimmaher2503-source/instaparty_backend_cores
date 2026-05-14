---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. specs/001-dashboard-widgets/spec.md
4. specs/001-dashboard-widgets/plan.md
---

# Tasks: Operational Dashboard Widgets (Full Set)

**Feature**: `specs/001-dashboard-widgets/`
**Phase**: 8.1 — Admin Active Ops Dashboard & Queues
**PRD Coverage**: FR-29, FR-EXT-001..FR-EXT-014

**Organization**: Tasks follow the 4 user stories from spec.md in priority order.
Tests are included because the spec's exit criteria require Pest green.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies)
- **[Story]**: US1 = operational stats, US2 = charts, US3 = subscriptions, US4 = AR locale

---

## Phase 1: Setup (Blocking Prerequisites)

**Purpose**: Remove the 3-stat skeleton, wire up discovery, create directories.

**⚠️ CRITICAL**: Must complete before any widget is written.

- [ ] T001 Delete `app/Modules/Booking/Filament/Widgets/BookingStatsWidget.php` — this is the 3-stat skeleton being replaced
- [ ] T002 Add 4 `discoverWidgets` calls to `app/Providers/Filament/AdminPanelProvider.php`:
  - `->discoverWidgets(in: app_path('Modules/Identity/Filament/Widgets'), for: 'App\\Modules\\Identity\\Filament\\Widgets')`
  - `->discoverWidgets(in: app_path('Modules/Catalog/Filament/Widgets'), for: 'App\\Modules\\Catalog\\Filament\\Widgets')`
  - `->discoverWidgets(in: app_path('Modules/Settlement/Filament/Widgets'), for: 'App\\Modules\\Settlement\\Filament\\Widgets')`
  - `->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\\Modules\\Communication\\Filament\\Widgets')`
- [ ] T003 [P] Create widget directory `app/Modules/Identity/Filament/Widgets/` (create a `.gitkeep` if empty, or create it by writing the first widget file in T010)
- [ ] T004 [P] Create widget directory `app/Modules/Catalog/Filament/Widgets/`
- [ ] T005 [P] Create widget directory `app/Modules/Settlement/Filament/Widgets/`
- [ ] T006 [P] Create widget directory `app/Modules/Communication/Filament/Widgets/`

**Checkpoint**: `BookingStatsWidget` is gone. `AdminPanelProvider` discovers 6 widget paths.

---

## Phase 2: Foundational (Translation Files)

**Purpose**: All widget headings need EN+AR translation keys before any widget can render in both locales. These files are pre-requisites for all user stories.

**⚠️ CRITICAL**: Create both EN and AR files before implementing any widget.

- [ ] T007 [P] Create `app/Modules/Identity/Resources/lang/en/widgets.php`:
  ```php
  <?php
  return [
      'vendors_awaiting_approval' => 'Vendors Awaiting Approval',
      'vendors_awaiting_approval_description' => 'Pending vendor profiles',
  ];
  ```

- [ ] T008 [P] Create `app/Modules/Identity/Resources/lang/ar/widgets.php`:
  ```php
  <?php
  return [
      'vendors_awaiting_approval' => 'البائعون في انتظار الموافقة',
      'vendors_awaiting_approval_description' => 'ملفات البائعين المعلقة',
  ];
  ```

- [ ] T009 [P] Create `app/Modules/Catalog/Resources/lang/en/widgets.php`:
  ```php
  <?php
  return [
      'pending_moderation_by_type' => 'Services Pending Moderation',
      'pending_rental'  => 'Rental Services',
      'pending_sale'    => 'Sale Services',
      'pending_digital' => 'Digital Services',
  ];
  ```

- [ ] T010 [P] Create `app/Modules/Catalog/Resources/lang/ar/widgets.php`:
  ```php
  <?php
  return [
      'pending_moderation_by_type' => 'خدمات تنتظر المراجعة',
      'pending_rental'  => 'خدمات الإيجار',
      'pending_sale'    => 'خدمات البيع',
      'pending_digital' => 'الخدمات الرقمية',
  ];
  ```

- [ ] T011 [P] Create `app/Modules/Booking/Resources/lang/en/widgets.php`:
  ```php
  <?php
  return [
      'overdue_bookings'             => 'Overdue Vendor Responses',
      'overdue_bookings_description' => 'Past response deadline',
      'revenue_by_type_heading'      => 'Revenue by Product Type (Last 30 Days)',
      'bookings_by_type_heading'     => 'Bookings by Product Type (Last 7 Days)',
  ];
  ```

- [ ] T012 [P] Create `app/Modules/Booking/Resources/lang/ar/widgets.php`:
  ```php
  <?php
  return [
      'overdue_bookings'             => 'ردود البائعين المتأخرة',
      'overdue_bookings_description' => 'تجاوز الموعد النهائي للرد',
      'revenue_by_type_heading'      => 'الإيرادات حسب نوع المنتج (آخر 30 يومًا)',
      'bookings_by_type_heading'     => 'الحجوزات حسب نوع المنتج (آخر 7 أيام)',
  ];
  ```

- [ ] T013 [P] Create `app/Modules/Settlement/Resources/lang/en/widgets.php`:
  ```php
  <?php
  return [
      'pending_withdrawals'             => 'Pending Withdrawals',
      'pending_withdrawals_description' => 'Awaiting admin approval',
  ];
  ```

- [ ] T014 [P] Create `app/Modules/Settlement/Resources/lang/ar/widgets.php`:
  ```php
  <?php
  return [
      'pending_withdrawals'             => 'طلبات السحب المعلقة',
      'pending_withdrawals_description' => 'في انتظار موافقة المشرف',
  ];
  ```

- [ ] T015 [P] Create `app/Modules/Communication/Resources/lang/en/widgets.php`:
  ```php
  <?php
  return [
      'open_chat_flags'             => 'Open Chat Compliance Flags',
      'open_chat_flags_description' => 'Unresolved chat moderation items',
  ];
  ```

- [ ] T016 [P] Create `app/Modules/Communication/Resources/lang/ar/widgets.php`:
  ```php
  <?php
  return [
      'open_chat_flags'             => 'تنبيهات الامتثال في الدردشة',
      'open_chat_flags_description' => 'عناصر الإشراف غير المحلولة',
  ];
  ```

- [ ] T017 Verify that each module's ServiceProvider has a `loadTranslationsFrom` call pointing to its `Resources/lang` directory with the correct namespace. For each module below, open the ServiceProvider and confirm (or add) this pattern:
  - `app/Modules/Identity/Providers/IdentityServiceProvider.php` → namespace `'identity'`
  - `app/Modules/Catalog/Providers/CatalogServiceProvider.php` → namespace `'catalog'`
  - `app/Modules/Booking/Providers/BookingServiceProvider.php` → namespace `'booking'`
  - `app/Modules/Settlement/Providers/SettlementServiceProvider.php` → namespace `'settlement'`
  - `app/Modules/Communication/Providers/CommunicationServiceProvider.php` → namespace `'communication'`

**Checkpoint**: `php artisan lang:publish` not needed. Run `php artisan route:clear && php artisan config:clear`. Translation keys are now available.

---

## Phase 3: User Story 1 — Operational Stats Widgets (Priority: P1) 🎯 MVP

**Goal**: Admin opens `/admin` and sees 5 stat widgets showing live operational counts.

**Independent Test**: Seed one row in each of the 5 queried tables. Load the dashboard. Confirm all 5 widgets show count = 1.

### Implementation — US1

- [ ] T018 [P] [US1] Create `app/Modules/Identity/Filament/Widgets/VendorsAwaitingApprovalWidget.php`:
  - Extends `Filament\Widgets\StatsOverviewWidget`
  - `protected static ?int $sort = 1;`
  - `getStats()` returns one `Stat` built from:
    - `VendorProfile::where('approval_status', ApprovalStatus::Pending)->count()`
    - `->color('warning')->icon('heroicon-m-clock')`
    - `->url(VendorApprovalQueueResource::getUrl('index'))`
  - Heading: `__('identity::widgets.vendors_awaiting_approval')`
  - Description: `__('identity::widgets.vendors_awaiting_approval_description')`
  - Imports: `App\Modules\Identity\Domain\Enums\ApprovalStatus`, `App\Modules\Identity\Domain\Models\VendorProfile`, `App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource`

- [ ] T019 [P] [US1] Create `app/Modules/Catalog/Filament/Widgets/ServicesPendingModerationByTypeWidget.php`:
  - Extends `Filament\Widgets\StatsOverviewWidget`
  - `protected static ?int $sort = 2;`
  - `getStats()` returns **3 Stat objects** (one per product type):
    - Rental: `Service::where('status', ServiceStatus::PendingReview)->where('product_type', ProductType::Rental)->count()` → color `'warning'`, icon `'heroicon-m-wrench'`, url `RentalServiceResource::getUrl('index')`
    - Sale: same pattern with `ProductType::Sale` → color `'success'`, icon `'heroicon-m-shopping-bag'`, url `SaleServiceResource::getUrl('index')`
    - Digital: same with `ProductType::Digital` → color `'info'`, icon `'heroicon-m-bolt'`, url `DigitalServiceResource::getUrl('index')`
  - Heading keys: `catalog::widgets.pending_rental`, `catalog::widgets.pending_sale`, `catalog::widgets.pending_digital`
  - Imports: `App\Modules\Catalog\Domain\Enums\{ServiceStatus, ProductType}`, `App\Modules\Catalog\Domain\Models\Service`, `App\Modules\Catalog\Filament\Resources\{RentalServiceResource, SaleServiceResource, DigitalServiceResource}`

- [ ] T020 [P] [US1] Create `app/Modules/Booking/Filament/Widgets/OverdueBookingsWidget.php`:
  - Extends `Filament\Widgets\StatsOverviewWidget`
  - `protected static ?int $sort = 3;`
  - `getStats()` returns one Stat:
    - `BookingVendor::where('sub_status', VendorSubStatus::Pending)->where('response_deadline', '<', now())->count()`
    - color `'danger'`, icon `'heroicon-m-exclamation-circle'`
    - url `BookingsMonitorResource::getUrl('index')`
  - Heading: `__('booking::widgets.overdue_bookings')`
  - Description: `__('booking::widgets.overdue_bookings_description')`
  - Imports: `App\Modules\Booking\Domain\Enums\VendorSubStatus`, `App\Modules\Booking\Domain\Models\BookingVendor`, `App\Modules\Booking\Filament\Resources\BookingsMonitorResource`

- [ ] T021 [P] [US1] Create `app/Modules/Settlement/Filament/Widgets/WithdrawalsQueueWidget.php`:
  - Extends `Filament\Widgets\StatsOverviewWidget`
  - `protected static ?int $sort = 4;`
  - `getStats()` returns one Stat:
    - `Withdrawal::where('status', WithdrawalStatus::Pending)->count()`
    - color `'warning'`, icon `'heroicon-m-banknotes'`
    - url `WithdrawalsQueueResource::getUrl('index')`
  - Heading: `__('settlement::widgets.pending_withdrawals')`
  - Description: `__('settlement::widgets.pending_withdrawals_description')`
  - Imports: `App\Modules\Settlement\Domain\Enums\WithdrawalStatus`, `App\Modules\Settlement\Domain\Models\Withdrawal`, `App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource`

- [ ] T022 [P] [US1] Create `app/Modules/Communication/Filament/Widgets/OpenChatFlagsStatWidget.php`:
  - Extends `Filament\Widgets\StatsOverviewWidget`
  - `protected static ?int $sort = 5;`
  - `getStats()` returns one Stat:
    - Count: use `Schema::hasTable('chat_moderation_flags') ? DB::table('chat_moderation_flags')->whereNull('resolved_at')->count() : 0`
    - color `'danger'`, icon `'heroicon-m-flag'`
    - No url (Communication Filament resource not yet built in Phase 1)
  - Heading: `__('communication::widgets.open_chat_flags')`
  - Description: `__('communication::widgets.open_chat_flags_description')`
  - Imports: `Illuminate\Support\Facades\{DB, Schema}`

### Tests — US1

- [ ] T023 [P] [US1] Create `tests/Feature/Modules/Identity/Filament/VendorsAwaitingApprovalWidgetTest.php`:
  ```php
  it('shows correct count of pending vendors', function () {
      VendorProfile::factory()->count(3)->create(['approval_status' => ApprovalStatus::Pending]);
      VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);
      // assert widget stat = 3
  })->group('dashboard-widgets');

  it('links to VendorApprovalQueueResource', function () {
      // assert url() returns non-empty string
  })->group('dashboard-widgets');
  ```

- [ ] T024 [P] [US1] Create `tests/Feature/Modules/Catalog/Filament/ServicesPendingModerationByTypeWidgetTest.php`:
  ```php
  it('shows 3 stats, one per product type', function () {
      Service::factory()->rental()->create(['status' => ServiceStatus::PendingReview]);
      Service::factory()->digital()->create(['status' => ServiceStatus::PendingReview]);
      // assert rental stat = 1, sale stat = 0, digital stat = 1
  })->group('dashboard-widgets');
  ```

- [ ] T025 [P] [US1] Create `tests/Feature/Modules/Booking/Filament/OverdueBookingsWidgetTest.php`:
  ```php
  it('counts booking_vendors past deadline with pending sub_status', function () {
      BookingVendor::factory()->create([
          'sub_status' => VendorSubStatus::Pending,
          'response_deadline' => now()->subHour(),
      ]);
      BookingVendor::factory()->create([
          'sub_status' => VendorSubStatus::Pending,
          'response_deadline' => now()->addDay(),  // not overdue
      ]);
      BookingVendor::factory()->create([
          'sub_status' => VendorSubStatus::Accepted,
          'response_deadline' => now()->subHour(),  // accepted, not pending
      ]);
      // assert stat count = 1
  })->group('dashboard-widgets');

  it('excludes rows with null response_deadline', function () {
      BookingVendor::factory()->create(['sub_status' => VendorSubStatus::Pending, 'response_deadline' => null]);
      // assert stat count = 0
  })->group('dashboard-widgets');
  ```

- [ ] T026 [P] [US1] Create `tests/Feature/Modules/Settlement/Filament/WithdrawalsQueueWidgetTest.php`:
  ```php
  it('counts pending withdrawals', function () {
      Withdrawal::factory()->count(2)->create(['status' => WithdrawalStatus::Pending]);
      Withdrawal::factory()->create(['status' => WithdrawalStatus::Paid]);
      // assert stat = 2
  })->group('dashboard-widgets');
  ```

- [ ] T027 [P] [US1] Create `tests/Feature/Modules/Communication/Filament/OpenChatFlagsStatWidgetTest.php`:
  ```php
  it('returns 0 gracefully when chat_moderation_flags table does not exist', function () {
      // No migration for chat_moderation_flags exists — widget must not throw
      expect(fn () => (new OpenChatFlagsStatWidget)->getStats())->not->toThrow(Exception::class);
      // stat value = 0
  })->group('dashboard-widgets');
  ```

**Checkpoint**: Run `./vendor/bin/pest --group=dashboard-widgets` — all US1 tests green. Dashboard shows 5 stat widgets.

---

## Phase 4: User Story 2 — Revenue & Booking Charts (Priority: P2)

**Goal**: Dashboard shows two stacked bar charts — revenue by type (30 days) and bookings by type (7 days).

**Independent Test**: Seed 3 bookings with items of each type on distinct dates. Load dashboard. Verify both charts have 3 labelled datasets (Rental, Sale, Digital) with at least one non-zero bar.

### Implementation — US2

- [ ] T028 [US2] Create `app/Modules/Booking/Filament/Widgets/RevenueByProductTypeChart.php`:
  - Extends `Filament\Widgets\ChartWidget`
  - `protected static ?int $sort = 6;`
  - `protected function getHeading(): string { return __('booking::widgets.revenue_by_type_heading'); }`
  - `protected function getType(): string { return 'bar'; }`
  - `getData()` builds 3 datasets (Rental, Sale, Digital) by:
    - Generating a date array for last 30 days: `$dates = collect(range(0, 29))->map(fn($d) => now()->subDays($d)->toDateString())->reverse()->values()`
    - One query joining `booking_items → booking_vendors → bookings` where `bookings.payment_status = 'paid'` and `bookings.deleted_at IS NULL` and `bookings.created_at >= now()->subDays(30)`, grouped by `DATE(bookings.created_at)` and `booking_items.product_type`, selecting `SUM(booking_items.line_total_minor) as total`
    - Map results into 3 datasets keyed by product type, filling missing dates with 0
  - Returns standard ChartWidget data shape:
    ```php
    return [
        'datasets' => [
            ['label' => 'Rental',  'data' => $rentalData,  'backgroundColor' => '#F59E0B'],
            ['label' => 'Sale',    'data' => $saleData,    'backgroundColor' => '#10B981'],
            ['label' => 'Digital', 'data' => $digitalData, 'backgroundColor' => '#0EA5E9'],
        ],
        'labels' => $dates->toArray(),
    ];
    ```
  - Imports: `App\Modules\Booking\Domain\Enums\{PaymentStatus, ProductType (from Catalog)}` — wait, `ProductType` is in Catalog. Since `BookingItem` casts `product_type` to `ProductType` (Catalog enum), and this widget is in the Booking module, it **may import** `App\Modules\Catalog\Domain\Enums\ProductType` as the canonical enum. This is acceptable: the widget reads from `booking_items.product_type` string values directly in the raw SQL groupBy, and uses the enum only for building dataset labels. **No cross-module Model import occurs** — only the enum import.
  - Alternative to avoid the enum import: use raw strings `'rental'`, `'sale'`, `'digital'` for dataset keys. Use this approach to stay strictly within module boundaries.

- [ ] T029 [US2] Create `app/Modules/Booking/Filament/Widgets/BookingsByTypeChart.php`:
  - Extends `Filament\Widgets\ChartWidget`
  - `protected static ?int $sort = 7;`
  - `protected function getHeading(): string { return __('booking::widgets.bookings_by_type_heading'); }`
  - `protected function getType(): string { return 'bar'; }`
  - `getData()` builds 3 datasets for last 7 days:
    - `$dates = collect(range(0, 6))->map(fn($d) => now()->subDays($d)->toDateString())->reverse()->values()`
    - Query `booking_items → booking_vendors → bookings` where `bookings.deleted_at IS NULL` and `bookings.created_at >= now()->subDays(7)`, grouped by `DATE(bookings.created_at)` and `booking_items.product_type`, selecting `COUNT(*) as count`
    - Map into 3 datasets, fill missing dates with 0
  - Returns ChartWidget data shape (same pattern as T028, count instead of sum)
  - Use raw type strings `'rental'`, `'sale'`, `'digital'` for dataset keys (avoids cross-module enum import)

### Tests — US2

- [ ] T030 [P] [US2] Create `tests/Feature/Modules/Booking/Filament/RevenueByProductTypeChartTest.php`:
  ```php
  it('returns 3 datasets labelled by product type', function () {
      // Seed a paid booking with one rental item and one digital item
      // Assert getData() returns datasets with keys 'Rental', 'Sale', 'Digital'
      // Assert rental dataset contains non-zero value on seeded date
      // Assert sale dataset is all zeros
  })->group('dashboard-widgets');

  it('returns zero-filled arrays when no paid bookings exist', function () {
      // Assert all 3 datasets have 30 values, all 0
      // Assert labels has 30 entries
  })->group('dashboard-widgets');
  ```

- [ ] T031 [P] [US2] Create `tests/Feature/Modules/Booking/Filament/BookingsByTypeChartTest.php`:
  ```php
  it('returns 3 datasets with 7-day labels', function () {
      // Seed booking items per type on today's date
      // Assert labels has 7 entries
      // Assert each type dataset has 7 values
      // Assert today's count matches seeded item count per type
  })->group('dashboard-widgets');

  it('includes today even when no bookings exist today', function () {
      // Assert today's date is in labels
      // Assert today's value is 0 for all types
  })->group('dashboard-widgets');
  ```

**Checkpoint**: Run `./vendor/bin/pest --group=dashboard-widgets` — US2 tests green. Dashboard now shows 7 widgets (5 stats + 2 charts).

---

## Phase 5: User Story 3 — Subscription Health Widgets (Priority: P3)

**Goal**: Dashboard shows 2 subscription widgets (already implemented). Update sort order and ensure heading is translated.

**Independent Test**: Confirm `PastDueSubscriptionsStatWidget` and `VendorsByTierWidget` appear on the dashboard with `$sort` 8 and 9 respectively. `VendorsByTierWidget` heading renders in AR.

### Implementation — US3

- [ ] T032 [US3] Update `app/Modules/Subscriptions/Filament/Widgets/PastDueSubscriptionsStatWidget.php`:
  - Change `protected static ?int $sort = 2;` → `protected static ?int $sort = 8;`
  - No other changes needed (widget logic is correct per Phase 1.7 implementation)

- [ ] T033 [US3] Update `app/Modules/Subscriptions/Filament/Widgets/VendorsByTierWidget.php`:
  - Change `protected static ?int $sort = 1;` → `protected static ?int $sort = 9;`
  - Change `protected static ?string $heading = 'Vendors by Subscription Tier';` → `protected static ?string $heading = null;` and add:
    ```php
    protected function getHeading(): ?string
    {
        return __('subscription.widgets_vendors_by_tier');
    }
    ```
  - Add translation key `widgets_vendors_by_tier` to `app/Modules/Subscriptions/Resources/lang/en/subscription.php` and `ar/subscription.php` (confirm namespace and file name by checking the Subscriptions ServiceProvider)

### Tests — US3

- [ ] T034 [P] [US3] Create `tests/Feature/Modules/Subscriptions/Filament/PastDueSubscriptionsStatWidgetTest.php`:
  ```php
  it('has sort 8', function () {
      expect(PastDueSubscriptionsStatWidget::$sort)->toBe(8);
  })->group('dashboard-widgets');

  it('shows 0 gracefully when no past-due invoices exist', function () {
      $stats = (new PastDueSubscriptionsStatWidget)->getStats();
      expect($stats[0]->getValue())->toBe(0);
  })->group('dashboard-widgets');
  ```

- [ ] T035 [P] [US3] Create `tests/Feature/Modules/Subscriptions/Filament/VendorsByTierWidgetTest.php`:
  ```php
  it('has sort 9', function () {
      expect(VendorsByTierWidget::$sort)->toBe(9);
  })->group('dashboard-widgets');

  it('returns empty chart data gracefully when no subscriptions exist', function () {
      $data = (new VendorsByTierWidget)->getData();
      expect($data['datasets'][0]['data'])->toBeEmpty();
  })->group('dashboard-widgets');
  ```

**Checkpoint**: Dashboard now shows all 9 widgets in correct order.

---

## Phase 6: User Story 4 — Arabic Locale Widget Headings (Priority: P2)

**Goal**: All 9 widget headings render Arabic strings when Filament locale is `ar`.

**Independent Test**: Call each widget's `getHeading()` / `getStats()` with `App::setLocale('ar')`. Assert returned heading string contains Arabic characters (non-ASCII, right-to-left script).

### Implementation — US4

> Implementation is complete from Phases 2–5 (all widgets use translation keys). This phase adds the locale test coverage.

### Tests — US4

- [ ] T036 [US4] Create `tests/Feature/Modules/Dashboard/LocaleWidgetHeadingsTest.php` — one test file covering all 9 widgets:
  ```php
  use App;

  beforeEach(fn () => App::setLocale('ar'));
  afterEach(fn () => App::setLocale('en'));

  it('VendorsAwaitingApprovalWidget heading is Arabic', function () {
      $stats = (new VendorsAwaitingApprovalWidget)->getStats();
      expect($stats[0]->getLabel())->toMatch('/\p{Arabic}/u');
  })->group('dashboard-widgets', 'locale');

  it('ServicesPendingModerationByTypeWidget headings are Arabic', function () {
      $stats = (new ServicesPendingModerationByTypeWidget)->getStats();
      foreach ($stats as $stat) {
          expect($stat->getLabel())->toMatch('/\p{Arabic}/u');
      }
  })->group('dashboard-widgets', 'locale');

  it('OverdueBookingsWidget heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');
  it('WithdrawalsQueueWidget heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');
  it('OpenChatFlagsStatWidget heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');

  it('RevenueByProductTypeChart heading is Arabic', function () {
      $widget = new RevenueByProductTypeChart;
      expect($widget->getHeading())->toMatch('/\p{Arabic}/u');
  })->group('dashboard-widgets', 'locale');

  it('BookingsByTypeChart heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');
  it('PastDueSubscriptionsStatWidget heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');
  it('VendorsByTierWidget heading is Arabic', function () { ... })->group('dashboard-widgets', 'locale');
  ```

**Checkpoint**: `./vendor/bin/pest --group=locale` all green. No raw key strings in AR mode.

---

## Phase 7: Polish & Validation

- [ ] T037 Run `./vendor/bin/pint` on all changed files and fix any formatting issues
- [ ] T038 Run `./vendor/bin/phpstan analyse app/Modules/Identity/Filament/Widgets app/Modules/Catalog/Filament/Widgets app/Modules/Booking/Filament/Widgets app/Modules/Settlement/Filament/Widgets app/Modules/Communication/Filament/Widgets app/Modules/Subscriptions/Filament/Widgets` — zero errors
- [ ] T039 Run `./vendor/bin/pest --group=dashboard-widgets` — all tests green
- [ ] T040 Boot the Filament admin and visually confirm:
  - `/admin` dashboard shows exactly 9 widgets (no BookingStatsWidget, no FilamentInfoWidget mixed in)
  - Widgets appear in correct sort order (1→9 top-to-bottom/left-to-right)
  - Each stat widget is clickable and navigates to the correct resource list
  - Switch locale to AR via the language switcher — all headings render in Arabic
- [ ] T041 [P] Update `specs/001-dashboard-widgets/spec.md` to mark phase status as `Implemented`
- [ ] T042 [P] Update `.specify/memory/project-index.md` §Current Phase to note dashboard widgets complete

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    └── Phase 2 (Translations)
            ├── Phase 3 (US1 — Stat widgets)    ← MVP
            ├── Phase 4 (US2 — Chart widgets)
            ├── Phase 5 (US3 — Subscription widgets — sort update only)
            └── Phase 6 (US4 — AR locale tests)
                    └── Phase 7 (Polish)
```

- **Phase 1**: Start immediately — delete file, edit AdminPanelProvider, create dirs
- **Phase 2**: Start after T002 (AdminPanelProvider must know about widget paths)
- **Phases 3–6**: All unblock after Phase 2 completes; can be done in any order
- **Phase 7**: After all widget phases complete

### User Story Dependencies

- **US1 (P1)**: Independent — queries 5 separate tables in 5 separate modules
- **US2 (P2)**: Independent — queries Booking module only; no dependency on US1
- **US3 (P3)**: Independent — only `$sort` update; already implemented in Phase 1.7
- **US4 (P2)**: Depends on T007–T016 (translation files must exist before locale tests pass)

### Within Each Story

- Translation files (Phase 2) → Widget class creation (Phases 3–5) → Tests (same phase) → Locale tests (Phase 6)
- Widget classes within the same phase: all [P] — different files, no shared dependencies

---

## Parallel Opportunities

### Phase 2 (Translations — All parallelizable)
```
T007 ─┐
T008 ─┤
T009 ─┤
T010 ─┤ All run in parallel (10 separate files)
T011 ─┤
T012 ─┤
T013 ─┤
T014 ─┤
T015 ─┤
T016 ─┘
```

### Phase 3 (US1 — Widget classes parallelizable, tests parallelizable)
```
T018, T019, T020, T021, T022  ← all parallel (5 separate files)
T023, T024, T025, T026, T027  ← all parallel (5 separate test files)
```

### Phase 4 (US2)
```
T028, T029  ← parallel (2 separate files)
T030, T031  ← parallel (2 separate test files)
```

---

## Implementation Strategy

### MVP First (US1 Only — 5 stat widgets)

1. Phase 1: Setup (T001–T006)
2. Phase 2: Translation files for Identity, Catalog, Booking, Settlement, Communication (T007–T017)
3. Phase 3: US1 widgets + tests (T018–T027)
4. **STOP and VALIDATE**: `./vendor/bin/pest --group=dashboard-widgets` + visual check of 5 stats on `/admin`
5. Proceed to Phase 4 (charts) → Phase 5 (subscriptions) → Phase 6 (locale)

### Full Delivery

Phases 1 → 2 → 3 → 4 → 5 → 6 → 7 in order. Single developer, ~1 day.

---

## Notes

- `[P]` tasks = different files, no shared dependencies — safe to run simultaneously
- `BookingStatsWidget` deletion (T001) is **irreversible** — commit T001 before starting widget creation
- `OpenChatFlagsStatWidget` (T022) will always show 0 until Phase 8.2 creates `chat_moderation_flags`
- Charts (T028–T029) use raw type strings `'rental'`, `'sale'`, `'digital'` rather than importing the `ProductType` enum from Catalog — avoids cross-module model import per Constitution Principle I
- If `VendorsByTierWidget` heading key `subscription.widgets_vendors_by_tier` is missing, find the correct key by inspecting `app/Modules/Subscriptions/Resources/lang/en/*.php` before T033
- Run `php artisan filament:cache-components` after T002 if widget discovery caches are stale
