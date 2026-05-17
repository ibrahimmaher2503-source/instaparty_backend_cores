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

# Feature Specification: Admin Operations Dashboard Widgets

**Feature Branch**: `027-admin-ops-dashboard-widgets`
**Created**: 2026-05-15
**Status**: Draft
**Phase**: Phase 8.1 (Admin Active Ops Dashboard — Operations Command Centre)
**PRD Coverage**: FR-16, FR-17, FR-18, FR-19, FR-22, FR-23–FR-27, FR-28, FR-29, FR-30; Admin Journey §1–§6

---

## Widget → Requirement → Source → Destination Mapping

| Widget | PRD / Admin Journey Requirement | Source Model / Table | Destination Resource (with filter) |
|---|---|---|---|
| `PendingVendorApprovalsWidget` | FR-29; Admin Journey §2 | `vendor_profiles` WHERE `approval_status = 'pending'` | `VendorApprovalQueueResource` (no extra filter needed — queue is already filtered) |
| `PendingServiceModerationWidget` | FR-19, FR-22; Admin Journey §3 | `services` WHERE `status = 'pending_review'`, grouped by `product_type` | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (pending pages) |
| `LateVendorResponsesWidget` | FR-16, FR-17; Admin Journey §4 | `booking_vendors` WHERE `response_deadline < now()` AND `sub_status = 'pending'` | `BookingsMonitorResource` (filtered by late-response flag) |
| `BookingsWaitingCustomerApprovalWidget` | FR-14, FR-15; Admin Journey §1 (overdue bookings, customer review cycle) | `bookings` WHERE `lifecycle_status = 'customer_review'` | `BookingResource` (filtered by `lifecycle_status=customer_review`) |
| `FailedPaymentsWidget` | FR-30; Admin Journey §5 (Monitor payments) | `payments` WHERE `status = 'failed'` (last 48 h) | `PaymentResource` (filtered by `status=failed`) |
| `FailedNotificationDispatchesWidget` | FR-23–FR-27; Admin Journey §4 (Send alerts & escalations) | `notification_dispatches` WHERE `status IN ('failed', 'bounced')` (last 24 h) | `NotificationDispatchResource` (filtered by failed/bounced status) |
| `PendingWithdrawalsWidget` | FR-28, FR-29; Admin Journey §6 (Settlements) | `withdrawals` WHERE `status = 'pending'` | `WithdrawalsQueueResource` |
| `ExcelImportsWithErrorsWidget` | FR-22; Admin Journey §1 (critical states) | `excel_imports` WHERE `status = 'failed'` OR `error_rows > 0` (last 7 days) | `ExcelImportResource` (filtered by error state) |
| `CriticalAdminInboxWidget` | FR-16; Admin Journey §4 (late vendor / alerts escalation) | `admin_inbox_items` WHERE `severity = 'critical'` AND `status IN ('unread', 'read')` | `AdminInboxResource` (filtered by critical severity, unresolved) |

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin sees live operational alerts at a glance (Priority: P1)

An admin opens `/admin` and immediately sees nine operational stat widgets on the dashboard. Each widget shows a count of items requiring attention and is a live link to the correct filtered resource page. No navigation to sub-menus is required to see the current operational health of the platform.

**Why this priority**: This is the daily-ops entry point for every admin shift. Missing or incorrect counts lead to missed SLA deadlines (vendor response windows, pending withdrawals, failed payments), which directly affects vendor and customer satisfaction.

**Independent Test**: Seed one row for each of the nine data sources (one pending vendor, one pending-review service, one late booking_vendor, one customer-review booking, one failed payment, one failed notification dispatch, one pending withdrawal, one excel import with errors, one critical admin inbox item). Load `/admin`. Confirm all nine widgets show exactly 1.

**Acceptance Scenarios**:

1. **Given** one `vendor_profiles` row with `approval_status=pending` exists, **When** the admin loads the dashboard, **Then** `PendingVendorApprovalsWidget` shows 1 and links to `VendorApprovalQueueResource`.
2. **Given** one rental and one digital service with `status=pending_review` exist, **When** the admin loads the dashboard, **Then** `PendingServiceModerationWidget` shows three sub-stats: Rental=1, Sale=0, Digital=1 (total 2).
3. **Given** one `booking_vendors` row with `response_deadline < now()` AND `sub_status=pending` exists, **When** the admin loads the dashboard, **Then** `LateVendorResponsesWidget` shows 1.
4. **Given** one `bookings` row with `lifecycle_status=customer_review` exists, **When** the admin loads the dashboard, **Then** `BookingsWaitingCustomerApprovalWidget` shows 1.
5. **Given** one `payments` row with `status=failed` within the last 48 hours exists, **When** the admin loads the dashboard, **Then** `FailedPaymentsWidget` shows 1.
6. **Given** one `notification_dispatches` row with `status=failed` within the last 24 hours exists, **When** the admin loads the dashboard, **Then** `FailedNotificationDispatchesWidget` shows 1.
7. **Given** one `withdrawals` row with `status=pending` exists, **When** the admin loads the dashboard, **Then** `PendingWithdrawalsWidget` shows 1.
8. **Given** one `excel_imports` row with `error_rows > 0` created within the last 7 days exists, **When** the admin loads the dashboard, **Then** `ExcelImportsWithErrorsWidget` shows 1.
9. **Given** one `admin_inbox_items` row with `severity=critical` AND `status=unread` exists, **When** the admin loads the dashboard, **Then** `CriticalAdminInboxWidget` shows 1.

---

### User Story 2 — Admin navigates directly from a widget to the relevant resource with filters pre-applied (Priority: P1)

Clicking a stat number or the widget's action link opens the correct Filament resource list page with the appropriate filters already applied so the admin does not need to manually set them.

**Why this priority**: A widget that shows "3 pending vendors" but whose link goes to an unfiltered list defeats the purpose of the alert. Pre-applied filters reduce time-to-action from seconds to clicks.

**Independent Test**: Seed two pending vendor profiles and one approved vendor profile. Click `PendingVendorApprovalsWidget`'s stat. Verify the navigated page shows exactly 2 rows, not 3.

**Acceptance Scenarios**:

1. **Given** `PendingVendorApprovalsWidget` shows a count, **When** the admin clicks it, **Then** the page loads `VendorApprovalQueueResource` list — which is already pre-filtered to `approval_status=pending` by the resource itself.
2. **Given** `LateVendorResponsesWidget` shows a count, **When** the admin clicks it, **Then** the page loads `BookingsMonitorResource` list filtered to show only bookings with at least one late vendor response.
3. **Given** `FailedPaymentsWidget` shows a count, **When** the admin clicks it, **Then** the page loads `PaymentResource` list with `status=failed` pre-applied.
4. **Given** `CriticalAdminInboxWidget` shows a count, **When** the admin clicks it, **Then** the page loads `AdminInboxResource` list with `severity=critical` AND unresolved status pre-applied.

---

### User Story 3 — Admin-role authorization gates widget visibility (Priority: P2)

Widgets are only visible to users who have the correct Filament Shield permission for the underlying resource. An admin sub-role without `view_any_vendor_profile` permission does not see `PendingVendorApprovalsWidget`.

**Why this priority**: Least-privilege is a non-functional requirement (NFR auditability). Showing operation counts to unauthorised sub-roles leaks business intelligence.

**Independent Test**: Create a test admin user who has `view_any_payment` but not `view_any_vendor_profile`. Load the dashboard. Confirm `PendingVendorApprovalsWidget` is absent and `FailedPaymentsWidget` is visible.

**Acceptance Scenarios**:

1. **Given** a user lacks `view_any_vendor_profile`, **When** the dashboard loads, **Then** `PendingVendorApprovalsWidget` is not rendered.
2. **Given** a user has the `super_admin` role, **When** the dashboard loads, **Then** all nine widgets render.
3. **Given** a user has permission for some but not all nine resources, **When** the dashboard loads, **Then** only the widgets for which they have permission are rendered.

---

### User Story 4 — Widget labels render in both Arabic and English (Priority: P2)

Switching the Filament admin locale to Arabic renders all widget headings and stat descriptions in Arabic script. Switching to English renders them in English. No raw translation keys are visible in either locale.

**Why this priority**: The admin panel supports EN+AR (Tech Decisions §6). Arabic-speaking admins must see a fully localised dashboard, not English fallbacks or translation key strings.

**Independent Test**: Load `/admin?lang=ar`. Verify each of the nine widget headings is non-empty Arabic text (not the translation key pattern `admin_ops.*`).

**Acceptance Scenarios**:

1. **Given** Filament locale is Arabic, **When** the dashboard loads, **Then** all nine widget headings display Arabic-language strings.
2. **Given** Filament locale is English, **When** the dashboard loads, **Then** all nine widget headings display English-language strings.
3. **Given** a translation key is missing from the AR file, **When** the dashboard loads in AR, **Then** the widget falls back to the EN string, not the raw key.

---

### Edge Cases

- What happens when `booking_vendors.response_deadline` is NULL? That row must be excluded from the `LateVendorResponsesWidget` count.
- What happens when a `payments` row with `status=failed` is older than 48 hours? It must be excluded from `FailedPaymentsWidget` to avoid counting stale failures that are already known.
- What happens when `excel_imports.error_rows` is 0 but `status = 'failed'`? The import must still appear in `ExcelImportsWithErrorsWidget` (failed status is the primary gate; error_rows > 0 is the secondary gate).
- What happens if `admin_inbox_items` has no `critical` rows? `CriticalAdminInboxWidget` must show 0 without error.
- What happens when two admins load the dashboard simultaneously? All counts are computed fresh per page load (no persistent cache shared between requests that could return stale state to another admin).
- What happens if the `notification_dispatches` table is very large? `FailedNotificationDispatchesWidget` must query only within the last 24 h window (indexed by `created_at`) to avoid a slow full-table scan.
- What happens when `PendingServiceModerationWidget` shows three sub-stats but all three counts are 0? The widget must render all three sub-stats (Rental: 0, Sale: 0, Digital: 0) — not an empty widget or hidden panel.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-101**: System MUST display `PendingVendorApprovalsWidget` as a stat showing count of `vendor_profiles` where `approval_status = pending`. Clicking links to `VendorApprovalQueueResource`.
  _PRD trace: FR-29; Admin Journey §2._

- **FR-EXT-102**: System MUST display `PendingServiceModerationWidget` as a `StatsOverviewWidget` with three `Stat` objects — one per product type (Rental / Sale / Digital) — each showing count of `services` where `status = pending_review` for that type. Clicking each stat links to the matching per-type resource pending page.
  _PRD trace: FR-19, FR-22; Admin Journey §3._

- **FR-EXT-103**: System MUST display `LateVendorResponsesWidget` as a stat showing count of `booking_vendors` where `response_deadline < now()` AND `sub_status = pending`. Response deadline NULL rows are excluded. Clicking links to `BookingsMonitorResource`.
  _PRD trace: FR-16, FR-17; Admin Journey §4._

- **FR-EXT-104**: System MUST display `BookingsWaitingCustomerApprovalWidget` as a stat showing count of `bookings` where `lifecycle_status = customer_review`. Clicking links to `BookingResource` filtered to that status.
  _PRD trace: FR-14, FR-15; Admin Journey §1._

- **FR-EXT-105**: System MUST display `FailedPaymentsWidget` as a stat showing count of `payments` where `status = failed` AND `created_at >= now() - 48h`. Clicking links to `PaymentResource` filtered to `status=failed`.
  _PRD trace: FR-30; Admin Journey §5._

- **FR-EXT-106**: System MUST display `FailedNotificationDispatchesWidget` as a stat showing count of `notification_dispatches` where `status IN (failed, bounced)` AND `created_at >= now() - 24h`. Clicking links to `NotificationDispatchResource` filtered to failed/bounced status.
  _PRD trace: FR-23–FR-27; Admin Journey §4._

- **FR-EXT-107**: System MUST display `PendingWithdrawalsWidget` as a stat showing count of `withdrawals` where `status = pending`. Clicking links to `WithdrawalsQueueResource`.
  _PRD trace: FR-28, FR-29; Admin Journey §6._

- **FR-EXT-108**: System MUST display `ExcelImportsWithErrorsWidget` as a stat showing count of `excel_imports` where (`status = 'failed'` OR `error_rows > 0`) AND `created_at >= now() - 7 days`. Clicking links to `ExcelImportResource`.
  _PRD trace: FR-22; Admin Journey §1 (critical states)._

- **FR-EXT-109**: System MUST display `CriticalAdminInboxWidget` as a stat showing count of `admin_inbox_items` where `severity = critical` AND `status IN (unread, read)`. Clicking links to `AdminInboxResource` filtered to critical/unresolved.
  _PRD trace: FR-16; Admin Journey §4 (alerts & escalations)._

- **FR-EXT-110**: Every widget MUST implement a `canView()` / `static::canView()` method that checks the authenticated user's Filament Shield permission for the underlying resource (e.g., `view_any_vendor_profile` for `PendingVendorApprovalsWidget`).
  _PRD trace: NFR (clean permission model)._

- **FR-EXT-111**: Widget modules MUST be added to `AdminPanelProvider`'s `discoverWidgets` list if not already covered, so that auto-discovery picks up the new widget classes.
  _PRD trace: CLAUDE.md §Module Layout._

- **FR-EXT-112**: EN+AR translation keys for all widget headings and stat descriptions MUST be present in both `lang/en/` and `lang/ar/` under each owning module's `Resources/lang/` directory.
  _PRD trace: Tech Decisions §7 (EN+AR required everywhere)._

- **FR-EXT-113**: Each widget MUST extend the correct Filament v3 base class: `Filament\Widgets\StatsOverviewWidget` for stat widgets, as defined in `.claude/rules/filament-components.md` §5.
  _PRD trace: Tech Decisions §13 (Filament v3)._

- **FR-EXT-114**: `PendingServiceModerationWidget` MUST produce three `Stat` objects from a single `StatsOverviewWidget` class — not three separate widget classes. The `match($productType)` pattern MUST be used for the per-type URL routing.
  _PRD trace: CLAUDE.md §Coding Conventions rule 8._

- **FR-EXT-115**: Widget queries MUST NOT use raw string comparisons on enum-backed status columns. Use the enum `value` property or query scopes on the model where available.
  _PRD trace: CLAUDE.md §Coding Conventions._

⚠️ PHASE BACKFILL NEEDED: `09_Phasing_Plan.md` Phase 8.1 covers "Admin Active Ops Dashboard" conceptually. Add these nine widgets to the Phase 8.1 deliverable list.

### Key Entities

- **VendorProfile** (`vendor_profiles`): `approval_status` ENUM, queried for `pending` count.
- **Service** (`services`): `status` ENUM, `product_type` ENUM, queried for `pending_review` count per type.
- **BookingVendor** (`booking_vendors`): `sub_status` ENUM, `response_deadline` TIMESTAMP, queried for late-response count.
- **Booking** (`bookings`): `lifecycle_status` ENUM, queried for `customer_review` count.
- **Payment** (`payments`): `status` ENUM, `created_at`, queried for `failed` in last 48 h.
- **NotificationDispatch** (`notification_dispatches`): `status` ENUM, `created_at`, queried for `failed`/`bounced` in last 24 h.
- **Withdrawal** (`withdrawals`): `status` ENUM, queried for `pending` count.
- **ExcelImport** (`excel_imports`): `status` STRING, `error_rows` INT, `created_at`, queried for error state in last 7 days.
- **AdminInboxItem** (`admin_inbox_items`): `severity` ENUM, `status` ENUM, queried for critical unresolved count.

---

## Schema Traceability

All tables are from `docs/specs/11_DB_Schema.md`. No new tables are introduced.

| Table | Owning Module | Widget |
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

## Widget → Module → File Location

| Widget | Module | File Path |
|---|---|---|
| `PendingVendorApprovalsWidget` | Identity | `app/Modules/Identity/Filament/Widgets/PendingVendorApprovalsWidget.php` |
| `PendingServiceModerationWidget` | Catalog | `app/Modules/Catalog/Filament/Widgets/PendingServiceModerationWidget.php` |
| `LateVendorResponsesWidget` | Booking | `app/Modules/Booking/Filament/Widgets/LateVendorResponsesWidget.php` |
| `BookingsWaitingCustomerApprovalWidget` | Booking | `app/Modules/Booking/Filament/Widgets/BookingsWaitingCustomerApprovalWidget.php` |
| `FailedPaymentsWidget` | Payments | `app/Modules/Payments/Filament/Widgets/FailedPaymentsWidget.php` |
| `FailedNotificationDispatchesWidget` | Communication | `app/Modules/Communication/Filament/Widgets/FailedNotificationDispatchesWidget.php` |
| `PendingWithdrawalsWidget` | Settlement | `app/Modules/Settlement/Filament/Widgets/PendingWithdrawalsWidget.php` |
| `ExcelImportsWithErrorsWidget` | Catalog | `app/Modules/Catalog/Filament/Widgets/ExcelImportsWithErrorsWidget.php` |
| `CriticalAdminInboxWidget` | Communication | `app/Modules/Communication/Filament/Widgets/CriticalAdminInboxWidget.php` |

**`AdminPanelProvider` additions required** (new `discoverWidgets` calls):
- `app/Modules/Identity/Filament/Widgets` → `App\Modules\Identity\Filament\Widgets`
- `app/Modules/Catalog/Filament/Widgets` → `App\Modules\Catalog\Filament\Widgets`
- `app/Modules/Payments/Filament/Widgets` → `App\Modules\Payments\Filament\Widgets`
- `app/Modules/Communication/Filament/Widgets` → `App\Modules\Communication\Filament\Widgets`
- Booking and Settlement already registered.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All nine widgets render on the Filament dashboard landing page without any additional navigation, as verified by a Pest feature test that loads `/admin` and asserts each widget's heading is present in the response.
- **SC-002**: Each widget's stat count matches the seeded database state — 100% accuracy verified by Pest assertions that compare widget count output to direct DB query results.
- **SC-003**: Each widget's `url()` value resolves to the correct Filament resource URL (verified by Pest string assertions on the generated URL).
- **SC-004**: A user lacking the relevant permission does not see the corresponding widget — verified by Pest authorization test.
- **SC-005**: All nine widget headings render in Arabic when the locale is `ar` and in English when the locale is `en` — verified by Pest locale assertions with non-empty, non-key strings.
- **SC-006**: `LateVendorResponsesWidget` excludes rows where `response_deadline IS NULL` — verified by seeding a NULL-deadline row and asserting count remains 0.
- **SC-007**: `FailedPaymentsWidget` excludes `failed` payments older than 48 hours — verified by seeding an old failed payment and asserting count is 0.
- **SC-008**: `ExcelImportsWithErrorsWidget` includes imports where `status = 'failed'` even when `error_rows = 0` — verified by seeding such a row and asserting count is 1.

---

## Assumptions

- The existing `VendorApprovalQueueResource` already filters to `approval_status=pending` internally; the widget URL need only point to the resource index page.
- Per-type pending service pages (`PendingRentalServicesPage`, `PendingSaleServicesPage`, `PendingDigitalServicesPage`) already exist in the codebase (confirmed by file search: `PendingRentalServicesPage.php`, etc.); the widget will link to those pages directly.
- `BookingVendor.sub_status` is the field holding the vendor response state (`pending`, `accepted`, etc.) per the `VendorSubStatus` enum.
- `Booking.lifecycle_status` holds the `customer_review` value when a booking is waiting for the customer to review a vendor modification, per the `LifecycleStatus` enum.
- `Payment.status` uses the `Payments\Domain\Enums\PaymentStatus` enum; `PaymentStatus::Failed` is the relevant case.
- `NotificationDispatch.status` uses `Communication\Domain\Enums\DispatchStatus`; `DispatchStatus::Failed` and `DispatchStatus::Bounced` are both counted.
- `Withdrawal.status` uses the `Settlement\Domain\Enums\WithdrawalStatus` enum; `WithdrawalStatus::Pending` is the relevant case.
- `ExcelImport.status` is a plain string field (not an enum-backed cast) based on the model inspection; raw string comparison `'failed'` is used for this field.
- `AdminInboxItem.severity` uses `Communication\Domain\Enums\AdminInboxSeverity::Critical`; `AdminInboxItem.status` uses `Communication\Domain\Enums\AdminInboxStatus`; the widget counts rows in `unread` or `read` states (active, not resolved).
- No new packages are required — all widgets use Filament v3's built-in `StatsOverviewWidget` already in the approved package list.
- Translation files live in each owning module's `Resources/lang/{en,ar}/` and a key namespace like `admin_ops.widgets.*` is used to avoid collision with existing keys.
- `php artisan shield:generate --all` must be run after any new Filament class that requires permissions.
- Tests live under `tests/Feature/Modules/{Module}/Filament/Widgets/`.
