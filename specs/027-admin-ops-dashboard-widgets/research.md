# Research: Admin Operations Dashboard Widgets

**Feature**: `027-admin-ops-dashboard-widgets`
**Phase**: Phase 0 Research
**Date**: 2026-05-15

All NEEDS CLARIFICATION items from the spec have been resolved below.

---

## R-01: Status Query Strategy

**Question**: Should widget queries use raw string comparisons, enum `->value`, or spatie/laravel-model-states `->whereState()`?

**Decision**: Use enum backed `->value` property in all `where()` calls.

**Rationale**:
- Inspected existing models (`VendorProfile`, `Booking`, `BookingVendor`, `Service`, `Withdrawal`, `Payment`, `NotificationDispatch`, `AdminInboxItem`). All status fields are declared as backed PHP 8.1 enum casts in `protected function casts(): array` — NOT as spatie/laravel-model-states `AbstractState` subclasses.
- `ExcelImport.status` is a plain `'string'` cast. Raw string literal `'failed'` is correct.
- Using `->whereState()` on a non-model-states column throws `InvalidArgumentException`. Enum `->value` produces identical SQL and is type-safe.
- Spec note from `001-dashboard-widgets` recommending `->whereState()` was based on an assumption that model-states is used universally — that assumption does not hold for these models.

**How applied**: Each widget uses `EnumClass::Case->value` in its `where()` predicate.

---

## R-02: Authorization Mechanism

**Question**: How does each widget gate itself to authorised admins only?

**Decision**: Override `public static function canView(): bool` in each widget class, checking `auth()->user()?->can('{gate}')`.

**Rationale**: Filament v3's `Widget` base class has a `canView()` static hook. Filament Shield generates `view_any_*` gates from resource policies. Using the same policy gates as the linked resource is the correct pattern — it means: "if you can see the resource list, you can see the widget count for that resource."

**Gate mapping confirmed** (see plan.md §R-02 table).

---

## R-03: Resource URL Generation

**Question**: How should widget `url()` values be constructed?

**Decision**: Use `ResourceClass::getUrl('index')` for resources where the index page IS the filtered view (e.g., `VendorApprovalQueueResource` — already only shows pending vendors). Append `?tableFilters[field][value]=val` query string for resources where the index shows all records and a filter must be pre-applied.

**Confirmed from codebase**:
- `VendorApprovalQueueResource` slug: `vendor-approval-queue` → URL `/admin/vendor-approval-queue`
- `WithdrawalsQueueResource` slug: `settlement-withdrawals` → URL `/admin/settlement-withdrawals`
- All other resources use auto-generated slugs from class name (e.g., `PaymentResource` → `payments`)

**Pre-filtered URL pattern** (Filament v3 table filter query string):
```
/admin/payments?tableFilters[status][value]=failed
/admin/bookings?tableFilters[lifecycle_status][value]=customer_review
/admin/notification-dispatches?tableFilters[status][value]=failed
/admin/admin-inbox-items?tableFilters[severity][value]=critical
```

---

## R-04: Widget Discovery Registration

**Question**: Which modules need new `discoverWidgets()` calls in `AdminPanelProvider`?

**Currently registered** (from `AdminPanelProvider.php` inspection):
- `app/Modules/Booking/Filament/Widgets`
- `app/Modules/Subscriptions/Filament/Widgets`
- `app/Modules/Shared/Filament/Widgets`
- `app/Modules/Reporting/Filament/Widgets`
- `app/Modules/Advertising/Filament/Widgets`
- `app/Modules/Settlement/Filament/Widgets`

**Not yet registered** (need to add):
- `app/Modules/Identity/Filament/Widgets`
- `app/Modules/Catalog/Filament/Widgets`
- `app/Modules/Payments/Filament/Widgets`
- `app/Modules/Communication/Filament/Widgets`

**Decision**: Add all four missing calls. No risk — auto-discovery only picks up classes that exist and extend a Filament widget base.

---

## R-05: Translation File Convention

**Question**: Where do widget translation strings live and how are they keyed?

**Decision**: Each module's `Resources/lang/{en,ar}/widgets.php` holds that module's widget strings. Module ServiceProviders already call `loadTranslationsFrom(...)` — the widget keys load automatically if the file exists.

**Key convention**:
```php
// Flat, descriptive keys — no nested arrays
'pending_vendor_approvals_heading' => '...'
'pending_vendor_approvals_description' => '...'
```

The module's translation namespace (e.g., `identity::`, `catalog::`, `booking::`) is the prefix used in `__('identity::widgets.pending_vendor_approvals_heading')`.

---

## R-06: Index Adequacy

**Question**: Are existing database indexes sufficient for widget query performance?

**Assessment per widget**:

| Widget | Query | Index status |
|---|---|---|
| PendingVendorApprovals | `WHERE approval_status = 'pending'` | Likely full-scan on vendor_profiles; acceptable at expected scale (<10k vendors) |
| PendingServiceModeration | `WHERE status = 'pending_review' AND product_type = '...'` | Covered by `(category_id, product_type, status)` index — leftmost prefix scan on `product_type, status` |
| LateVendorResponses | `WHERE response_deadline < now() AND sub_status = 'pending'` | `(vendor_id, sub_status, response_deadline)` covers `sub_status` + `response_deadline` range |
| BookingsWaitingCustomerApproval | `WHERE lifecycle_status = 'customer_review'` | May need standalone index on `lifecycle_status` |
| FailedPayments | `WHERE status = 'failed' AND created_at >= now()-48h` | May need composite `(status, created_at)` index |
| FailedNotificationDispatches | `WHERE status IN (...) AND created_at >= now()-24h` | May need composite `(status, created_at)` index |
| PendingWithdrawals | `WHERE status = 'pending'` | Index on `status` likely exists |
| ExcelImportsWithErrors | `WHERE (status='failed' OR error_rows>0) AND created_at >= now()-7d` | Index on `created_at` |
| CriticalAdminInbox | `WHERE severity='critical' AND status IN (...)` | Composite `(severity, status)` may be needed |

**Decision**: Create one optional `add_indexes_for_admin_ops_widgets` migration that adds missing indexes. The migration is additive-only and safe to run at any time.

Missing indexes to add:
1. `bookings` — `(lifecycle_status)` or `(lifecycle_status, created_at)`
2. `payments` — `(status, created_at)`
3. `notification_dispatches` — `(status, created_at)`
4. `admin_inbox_items` — `(severity, status)`

---

## R-07: `PaymentStatus` Namespace Conflict

**Question**: There are two `PaymentStatus` enums — `Booking\Domain\Enums\PaymentStatus` and `Payments\Domain\Enums\PaymentStatus`. Which does `FailedPaymentsWidget` use?

**Decision**: `FailedPaymentsWidget` queries the `payments` table, owned by the Payments module. It must use `App\Modules\Payments\Domain\Enums\PaymentStatus`.

The `Booking\Domain\Enums\PaymentStatus` covers booking-level payment state (a different concept). The widget's `use` import must explicitly reference the Payments module enum.

---

## All NEEDS CLARIFICATION items resolved

No outstanding clarifications. Plan is ready for tasks generation.
