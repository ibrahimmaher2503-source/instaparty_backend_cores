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

# Feature Specification: Operational Dashboard Widgets (Full Set)

**Feature Branch**: `001-dashboard-widgets`
**Created**: 2026-05-04
**Status**: Draft
**Phase**: Phase 8.1 (Admin Active Ops Dashboard & Queues) — widget completeness work
**PRD Coverage**: FR-29 (admin reviews/approves), Admin Journey §6.3 steps 1–7

---

## Clarifications

### Session 2026-05-15

- Q: Should widget status queries use raw string comparisons or spatie/laravel-model-states `->whereState()` API? → A: Use `->whereState('status', StateClass::class)` throughout all widgets — state-machine-aware queries, no raw string comparisons.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin sees live operational state at a glance (Priority: P1)

An admin opens `/admin` and immediately sees the count of vendors awaiting approval, services in moderation queue, overdue bookings, pending withdrawals, and open chat compliance flags — all in one dashboard, without navigating to individual resource pages.

**Why this priority**: This is the daily-ops entry point. Every admin shift starts here. If these counts are wrong or absent, the admin has no situational awareness and will miss SLA-critical actions.

**Independent Test**: Seed a vendor with `approval_status=pending`, a service with `status=pending_review`, a booking_vendor row with `response_deadline < now() AND sub_status=pending`, a withdrawal with `status=pending`, and a chat_moderation_flags row. Load `/admin`. Confirm five stat widgets display non-zero counts that match the seed.

**Acceptance Scenarios**:

1. **Given** one vendor with `approval_status=pending` exists, **When** the admin loads the dashboard, **Then** the `VendorsAwaitingApprovalWidget` stat shows exactly 1 and is clickable to the filtered VendorApprovalQueueResource list.
2. **Given** one rental service and one digital service with `status=pending_review` exist, **When** the admin loads the dashboard, **Then** `ServicesPendingModerationByTypeWidget` shows 1 rental, 0 sale, 1 digital in three sub-stats.
3. **Given** a `booking_vendors` row with `response_deadline < now()` and `sub_status=pending` exists, **When** the admin loads the dashboard, **Then** `OverdueBookingsWidget` stat shows 1.
4. **Given** a `withdrawals` row with `status=pending` exists, **When** the admin loads the dashboard, **Then** `WithdrawalsQueueWidget` stat shows 1.
5. **Given** a `chat_moderation_flags` row with unresolved status exists, **When** the admin loads the dashboard, **Then** `OpenChatFlagsStatWidget` stat shows 1.

---

### User Story 2 — Admin reviews revenue and booking trends by product type (Priority: P2)

An admin opens the dashboard and sees two charts: a line/bar chart showing revenue by product type for the last 30 days, and a stacked bar chart showing booking counts by type for the last 7 days. Each data point corresponds to a real aggregated DB row.

**Why this priority**: Trend visibility informs operational decisions (e.g., heavy rental demand on weekends). Without it, the admin relies on static reports buried in menu navigation.

**Independent Test**: Seed 3 bookings (one per type) on distinct dates. Load the dashboard. Verify `RevenueByProductTypeChart` has a data point for each type and `BookingsByTypeChart` shows one booking per type on their respective dates.

**Acceptance Scenarios**:

1. **Given** bookings with `payment_status=paid` exist for all three product types across the last 30 days, **When** the admin loads the dashboard, **Then** `RevenueByProductTypeChart` renders three labelled datasets (Rental, Sale, Digital) with daily totals summed from `bookings.total_minor`.
2. **Given** bookings exist in the last 7 days, **When** the admin loads the dashboard, **Then** `BookingsByTypeChart` renders a stacked bar where each bar is a day and each segment is a product type count.
3. **Given** no bookings exist for a specific type in a date range, **When** that type is rendered in a chart, **Then** that dataset shows zero — no missing data points or gaps that would break the chart.

---

### User Story 3 — Admin monitors subscription health (Priority: P3)

An admin sees at a glance how many vendors have past-due subscriptions and how the vendor fleet is distributed across tiers (Free / Silver / Gold / Premium). Both stats are read-only with click-through to filtered vendor lists.

**Why this priority**: Subscription health affects revenue. Past-due vendors may have their service limits revoked; tier distribution shows upsell opportunity. These are secondary to daily-ops but materially important within Phase 1.7.

**Independent Test**: Seed one vendor on `Silver` tier with `status=past_due` and three vendors on `Free` tier with `status=active`. Load the dashboard. Verify `PastDueSubscriptionsStatWidget` shows 1 and `VendorsByTierWidget` shows the correct tier breakdown.

**Acceptance Scenarios**:

1. **Given** two vendor subscriptions with `status=past_due` exist, **When** the admin loads the dashboard, **Then** `PastDueSubscriptionsStatWidget` shows 2 and links to a vendor list filtered by past-due subscriptions.
2. **Given** vendors on Free (3), Silver (1), Gold (2), Premium (0) tiers exist, **When** the admin loads the dashboard, **Then** `VendorsByTierWidget` shows those counts per tier.
3. **Given** Phase 1.7 subscription data does not exist (Phase 1.7 incomplete), **When** the dashboard loads, **Then** subscription widgets show 0 gracefully — no exceptions thrown.

---

### User Story 4 — Arabic locale renders widget headings correctly (Priority: P2)

When the admin switches the Filament locale to Arabic, all nine widget headings render their Arabic translations rather than falling back to English strings or raw translation keys.

**Why this priority**: The AR locale issue (ar-01) was explicitly flagged in the feature input. It is a regression risk on every dashboard visit. Users who operate in Arabic see broken UI if untranslated.

**Independent Test**: Load `/admin` with `Accept-Language: ar` (or switch locale in Filament). Verify each of the 9 widget headings is a non-empty Arabic string and not the translation key (e.g., not `widgets.vendors_awaiting_approval`).

**Acceptance Scenarios**:

1. **Given** the Filament admin locale is set to Arabic, **When** the dashboard loads, **Then** all 9 widget headings appear in Arabic script.
2. **Given** the Filament admin locale is English, **When** the dashboard loads, **Then** all 9 widget headings appear in English.
3. **Given** a translation key is missing from either locale file, **When** the dashboard loads, **Then** the widget falls back gracefully (shows the EN string) rather than showing the raw key.

---

### Edge Cases

- What happens when a database table referenced by a widget does not yet exist (e.g., `vendor_subscriptions` before Phase 1.7 runs migrations)? Widget must catch `QueryException` or use `Schema::hasTable()` guard and return 0.
- What happens when a chart date range returns no rows for any type? Chart must render an empty dataset with all date labels present, not an error or empty screen.
- What happens when `booking_vendors.response_deadline` is NULL? Row must be excluded from the overdue count.
- What happens when two admins view the dashboard simultaneously? Counts are computed fresh on each page load — no caching that could return stale data beyond a short TTL.
- What happens when `BookingsByTypeChart` is loaded for the last 7 days and today has no bookings? Today's bar still renders, with height 0.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-001**: System MUST display a `VendorsAwaitingApprovalWidget` stat showing count of `vendor_profiles` with `approval_status=pending`. ⚠️ BACKFILL NEEDED: add to 01_PRD.md §7.3 admin journey FRs.
- **FR-EXT-002**: System MUST display a `ServicesPendingModerationByTypeWidget` showing three sub-stats: pending_review count per `product_type` (rental / sale / digital) from the `services` table.
- **FR-EXT-003**: System MUST display an `OverdueBookingsWidget` stat showing count of `booking_vendors` rows where `response_deadline < now()` AND `sub_status=pending`.
- **FR-EXT-004**: System MUST display a `WithdrawalsQueueWidget` stat showing count of `withdrawals` rows with `status=pending`.
- **FR-EXT-005**: System MUST display an `OpenChatFlagsStatWidget` stat showing count of unresolved `chat_moderation_flags` rows.
- **FR-EXT-006**: System MUST display a `RevenueByProductTypeChart` line or bar chart aggregating `bookings.total_minor` (paid bookings) grouped by `product_type` over the last 30 days.
- **FR-EXT-007**: System MUST display a `BookingsByTypeChart` stacked bar chart showing booking counts per day per `product_type` over the last 7 days.
- **FR-EXT-008**: System MUST display a `PastDueSubscriptionsStatWidget` stat showing count of `vendor_subscriptions` with `status=past_due`. Widget MUST degrade gracefully if the `vendor_subscriptions` table does not exist.
- **FR-EXT-009**: System MUST display a `VendorsByTierWidget` stat (or grouped stat) showing vendor count per subscription tier. Widget MUST degrade gracefully if the `vendor_subscriptions` table does not exist.
- **FR-EXT-010**: Every stat widget MUST include a `url()` method that links to the matching filtered list resource page.
- **FR-EXT-011**: Widgets MUST be registered in `AdminPanelProvider::widgets()` with deterministic sort order via `getSort()`.
- **FR-EXT-012**: EN+AR translation keys for all widget labels MUST be present in both `lang/en/widgets.php` and `lang/ar/widgets.php` files in each owning module.
- **FR-EXT-013**: The system MUST expose 9 widgets total on the Filament dashboard (5 stat + 2 chart + 2 subscription-tier stat).
- **FR-EXT-014**: Each widget MUST extend the appropriate Filament base class (`StatsOverviewWidget` for stat groups, `ChartWidget` for charts) per `.claude/rules/filament-components.md` §5.

⚠️ PHASE BACKFILL NEEDED: Phase 8.1 in `09_Phasing_Plan.md` already covers the dashboard concept but the widget set listed there omits `ServicesPendingModerationByTypeWidget` (3 sub-stats) and the subscription widgets from Phase 1.7. This spec formalises the full deliverable.

### Key Entities *(include if feature involves data)*

- **VendorProfile** (`vendor_profiles`): queried for `approval_status=pending` count.
- **Service** (`services`): queried for `status=pending_review`, grouped by `product_type`.
- **BookingVendor** (`booking_vendors`): queried for `response_deadline < now()` AND `sub_status=pending`.
- **Withdrawal** (`withdrawals`): queried for `status=pending`.
- **ChatModerationFlag** (`chat_moderation_flags`): queried for unresolved flags.
- **Booking** (`bookings`): queried for revenue chart (sum of `total_minor` where `payment_status=paid`, grouped by `product_type` and date).
- **VendorSubscription** (`vendor_subscriptions`, Phase 1.7): queried for `status=past_due` and tier distribution. Guard with table existence check.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All 9 widgets are visible on the Filament dashboard landing page without any additional navigation.
- **SC-002**: Each stat widget's count matches the underlying seeded DB data — 100% accuracy verified by Pest assertions.
- **SC-003**: Each stat widget is clickable and navigates to the correctly filtered list resource (verified by the `url()` output in tests).
- **SC-004**: Chart data points for `RevenueByProductTypeChart` and `BookingsByTypeChart` match aggregated DB rows — verified by Pest chart data assertions.
- **SC-005**: All 9 widget headings render correctly in both English and Arabic locales (verified by Pest locale tests — zero raw translation keys visible).
- **SC-006**: Subscription widgets (`PastDueSubscriptionsStatWidget`, `VendorsByTierWidget`) return 0 gracefully when `vendor_subscriptions` table is absent — no unhandled exceptions.
- **SC-007**: Filament dashboard page loads with all 9 widgets in under 3 seconds under normal test-database load (Pest assertion on response time or absence of timeout).
- **SC-008**: Widget sort order is deterministic — the same widget order is produced across fresh page loads (verified by snapshot or ordered assertion in Pest).

---

## Assumptions

- The existing `BookingStatsWidget` (confirmed to exist in the repo) will be **replaced or absorbed** into the new widget set, not preserved alongside it. If it overlaps with any new widget, it is deduped.
- `chat_moderation_flags.status` has a value or set of values that means "unresolved" — assumed to be any row where `resolved_at IS NULL` or `status != 'resolved'`. Implementation must confirm against the locked schema in `docs/specs/11_DB_Schema.md` before coding.
- `booking_vendors.sub_status` column holds a value `pending` for vendor responses not yet received; the field name and values are confirmed against the locked schema.
- Phase 1.7 (`vendor_subscriptions`) may or may not be migrated when this widget set is deployed. Subscription widgets must use a runtime guard (check table existence via `Schema::hasTable`) and return 0 if the table is absent.
- Filament's `StatsOverviewWidget` is the correct base for all stat widgets; `ServicesPendingModerationByTypeWidget` produces three `Stat` objects (one per type) from a single widget class.
- `RevenueByProductTypeChart` uses `bookings.total_minor` (raw piastres) for chart data values — display formatting (÷100, currency symbol) is handled by the chart label/tooltip layer, not the data layer.
- Drag-and-drop dashboard customization and per-admin layouts are explicitly deferred to Phase 1.5 (per cut-list in feature input).
- Widget file locations follow the owning-module pattern from `CLAUDE.md` §Module Layout:
  - Identity widgets → `app/Modules/Identity/Filament/Widgets/`
  - Catalog widgets → `app/Modules/Catalog/Filament/Widgets/`
  - Booking widgets → `app/Modules/Booking/Filament/Widgets/`
  - Settlement widgets → `app/Modules/Settlement/Filament/Widgets/`
  - Communication widgets → `app/Modules/Communication/Filament/Widgets/`
  - Subscription widgets → `app/Modules/Subscriptions/Filament/Widgets/`
- Translation files are placed in each owning module's `Resources/lang/{en,ar}/widgets.php`.
- No new packages are required — Filament's built-in `StatsOverviewWidget` and `ChartWidget` (Chart.js, already in stack via Filament v3) cover all widget types.
- **All widget queries MUST use `->whereState('status', StateClass::class)` (spatie/laravel-model-states API) instead of raw string comparisons.** Raw comparisons (`WHERE status = 'pending'`) are forbidden — they bypass transition guards and break when state class names diverge from string values. Each queried state field (e.g., `approval_status`, `sub_status`, `status`) must reference the corresponding `AbstractState` subclass.

---

## Schema Traceability

All tables referenced are existing tables from `docs/specs/11_DB_Schema.md`:

| Table | Module | Widget |
|---|---|---|
| `vendor_profiles` | Identity | VendorsAwaitingApprovalWidget |
| `services` | Catalog | ServicesPendingModerationByTypeWidget |
| `booking_vendors` | Booking | OverdueBookingsWidget |
| `bookings` | Booking | RevenueByProductTypeChart, BookingsByTypeChart |
| `withdrawals` | Settlement | WithdrawalsQueueWidget |
| `chat_moderation_flags` | Communication | OpenChatFlagsStatWidget |
| `vendor_subscriptions` | Subscriptions (Phase 1.7) | PastDueSubscriptionsStatWidget, VendorsByTierWidget |

No new tables are introduced. All queries are read-only aggregates.
