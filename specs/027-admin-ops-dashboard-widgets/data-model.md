# Data Model: Admin Operations Dashboard Widgets

**Feature**: `027-admin-ops-dashboard-widgets`
**Phase**: Phase 1 Design
**Date**: 2026-05-15

---

## Overview

No new tables. No schema mutations. Nine read-only aggregation queries against existing tables.

---

## Entities Queried

### 1. VendorProfile (`vendor_profiles`)

| Column | Type | Widget use |
|---|---|---|
| `approval_status` | ENUM → `ApprovalStatus` | `= ApprovalStatus::Pending->value` |

**Query**:
```sql
SELECT COUNT(*) FROM vendor_profiles WHERE approval_status = 'pending' AND deleted_at IS NULL
```
Note: Model uses `SoftDeletes` — Eloquent scope excludes soft-deleted rows automatically.

---

### 2. Service (`services`)

| Column | Type | Widget use |
|---|---|---|
| `status` | ENUM → `ServiceStatus` | `= ServiceStatus::PendingReview->value` |
| `product_type` | ENUM → `ProductType` | `= ProductType::Rental->value` (and Sale, Digital) |

**Query** (repeated 3× for each product type):
```sql
SELECT COUNT(*) FROM services
WHERE status = 'pending_review'
  AND product_type = '{rental|sale|digital}'
  AND deleted_at IS NULL
```
Note: Model uses `SoftDeletes`.

---

### 3. BookingVendor (`booking_vendors`)

| Column | Type | Widget use |
|---|---|---|
| `sub_status` | ENUM → `VendorSubStatus` | `= VendorSubStatus::Pending->value` |
| `response_deadline` | TIMESTAMP nullable | `IS NOT NULL AND < now()` |

**Query**:
```sql
SELECT COUNT(*) FROM booking_vendors
WHERE sub_status = 'pending'
  AND response_deadline IS NOT NULL
  AND response_deadline < NOW()
```
No soft deletes on this table.

---

### 4. Booking (`bookings`)

| Column | Type | Widget use |
|---|---|---|
| `lifecycle_status` | ENUM → `LifecycleStatus` | `= LifecycleStatus::CustomerReview->value` |

**Query**:
```sql
SELECT COUNT(*) FROM bookings
WHERE lifecycle_status = 'customer_review'
  AND deleted_at IS NULL
```
Note: Model uses `SoftDeletes`.

---

### 5. Payment (`payments`)

Module: `App\Modules\Payments`

| Column | Type | Widget use |
|---|---|---|
| `status` | ENUM → `Payments\Domain\Enums\PaymentStatus` | `= PaymentStatus::Failed->value` |
| `created_at` | TIMESTAMP | `>= NOW() - INTERVAL 48 HOUR` |

**Query**:
```sql
SELECT COUNT(*) FROM payments
WHERE status = 'failed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
```
`payments` is append-only — no soft deletes, no `deleted_at`.

---

### 6. NotificationDispatch (`notification_dispatches`)

| Column | Type | Widget use |
|---|---|---|
| `status` | ENUM → `DispatchStatus` | `IN ('failed', 'bounced')` |
| `created_at` | TIMESTAMP (append-only table has only `created_at`) | `>= NOW() - INTERVAL 24 HOUR` |

**Query**:
```sql
SELECT COUNT(*) FROM notification_dispatches
WHERE status IN ('failed', 'bounced')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```
Note: `NotificationDispatch` model has `public $timestamps = false` and `const CREATED_AT = 'created_at'` and `const UPDATED_AT = null`. The `created_at` field exists.

---

### 7. Withdrawal (`withdrawals`)

| Column | Type | Widget use |
|---|---|---|
| `status` | ENUM → `WithdrawalStatus` | `= WithdrawalStatus::Pending->value` |

**Query**:
```sql
SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'
```

---

### 8. ExcelImport (`excel_imports`)

| Column | Type | Widget use |
|---|---|---|
| `status` | STRING (plain cast) | `= 'failed'` |
| `error_rows` | INTEGER | `> 0` |
| `created_at` | TIMESTAMP | `>= NOW() - INTERVAL 7 DAY` |

**Query**:
```sql
SELECT COUNT(*) FROM excel_imports
WHERE (status = 'failed' OR error_rows > 0)
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
```

---

### 9. AdminInboxItem (`admin_inbox_items`)

| Column | Type | Widget use |
|---|---|---|
| `severity` | ENUM → `AdminInboxSeverity` | `= AdminInboxSeverity::Critical->value` |
| `status` | ENUM → `AdminInboxStatus` | `IN ('unread', 'read')` |

**Query**:
```sql
SELECT COUNT(*) FROM admin_inbox_items
WHERE severity = 'critical'
  AND status IN ('unread', 'read')
```

---

## Optional Index Migration

File: `app/Modules/Shared/Database/Migrations/{timestamp}_add_indexes_for_admin_ops_widgets.php`

Adds the following indexes (additive-only, no data changes):

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->index('lifecycle_status', 'bookings_lifecycle_status_idx');
});

Schema::table('payments', function (Blueprint $table) {
    $table->index(['status', 'created_at'], 'payments_status_created_at_idx');
});

Schema::table('notification_dispatches', function (Blueprint $table) {
    $table->index(['status', 'created_at'], 'notification_dispatches_status_created_at_idx');
});

Schema::table('admin_inbox_items', function (Blueprint $table) {
    $table->index(['severity', 'status'], 'admin_inbox_items_severity_status_idx');
});
```

---

## Widget → File → Namespace Map (complete)

| Widget | File | PHP Namespace |
|---|---|---|
| `PendingVendorApprovalsWidget` | `app/Modules/Identity/Filament/Widgets/PendingVendorApprovalsWidget.php` | `App\Modules\Identity\Filament\Widgets` |
| `PendingServiceModerationWidget` | `app/Modules/Catalog/Filament/Widgets/PendingServiceModerationWidget.php` | `App\Modules\Catalog\Filament\Widgets` |
| `ExcelImportsWithErrorsWidget` | `app/Modules/Catalog/Filament/Widgets/ExcelImportsWithErrorsWidget.php` | `App\Modules\Catalog\Filament\Widgets` |
| `LateVendorResponsesWidget` | `app/Modules/Booking/Filament/Widgets/LateVendorResponsesWidget.php` | `App\Modules\Booking\Filament\Widgets` |
| `BookingsWaitingCustomerApprovalWidget` | `app/Modules/Booking/Filament/Widgets/BookingsWaitingCustomerApprovalWidget.php` | `App\Modules\Booking\Filament\Widgets` |
| `FailedPaymentsWidget` | `app/Modules/Payments/Filament/Widgets/FailedPaymentsWidget.php` | `App\Modules\Payments\Filament\Widgets` |
| `FailedNotificationDispatchesWidget` | `app/Modules/Communication/Filament/Widgets/FailedNotificationDispatchesWidget.php` | `App\Modules\Communication\Filament\Widgets` |
| `CriticalAdminInboxWidget` | `app/Modules/Communication/Filament/Widgets/CriticalAdminInboxWidget.php` | `App\Modules\Communication\Filament\Widgets` |
| `PendingWithdrawalsWidget` | `app/Modules/Settlement/Filament/Widgets/PendingWithdrawalsWidget.php` | `App\Modules\Settlement\Filament\Widgets` |
