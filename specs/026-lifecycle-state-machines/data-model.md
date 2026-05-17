# Data Model: Lifecycle State Machine Architecture

**Feature**: `026-lifecycle-state-machines`
**Date**: 2026-05-15

---

## Schema Changes

### 1. `state_transitions` (renamed from `booking_state_transitions`)

**Change type**: RENAME + ADD COLUMNS  
**Existing table**: `booking_state_transitions` (already in `docs/specs/11_DB_Schema.md`)  
⚠️ SCHEMA BACKFILL NEEDED: update `11_DB_Schema.md` to reflect rename and new columns.

| Column | Type | Nullable | Change | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | NO | unchanged | PK |
| `transitionable_type` | VARCHAR(120) | NO | unchanged | polymorphic model class |
| `transitionable_id` | BIGINT UNSIGNED | NO | unchanged | polymorphic FK |
| `from_state` | VARCHAR(40) | YES | unchanged | null on initial → first state |
| `to_state` | VARCHAR(40) | NO | unchanged | state DB value (e.g., `'pending_review'`) |
| `triggered_by` | BIGINT UNSIGNED | YES | unchanged | FK → `users.id` (nullOnDelete) |
| `trigger_kind` | ENUM | NO | **MODIFIED** | adds `'admin_override'` to enum values |
| `reason` | TEXT | YES | **NEW** | human-readable explanation; mandatory for `admin_override` |
| `trace_id` | CHAR(36) | YES | **NEW** | UUID from originating HTTP request or job |
| `context` | JSON | YES | unchanged | structured metadata (gateway refs, batch IDs, etc.) |
| `created_at` | TIMESTAMP | NO | unchanged | `useCurrent()`, append-only |

**Indexes**:
- Existing: `bst_transitionable_created_idx` ON `(transitionable_type, transitionable_id, created_at)` — kept as-is
- **New**: `st_trace_id_idx` ON `(trace_id)` — for trace-based debugging queries

**Constraints**:
- Append-only — no `updated_at`, no soft deletes, no UPDATEs ever
- `trigger_kind` = `'admin_override'` requires `reason IS NOT NULL` (enforced at application layer, not DB constraint)

---

## Model Changes (cast updates only — no column changes)

### `Service` (`services.status`)

```diff
- protected $casts = [
-     'status' => ServiceStatus::class,
+ protected $casts = [
+     'status' => ServiceState::class,
  ];
```

Add `use HasStates;`

Remove `'status'` from `$fillable` (protected field — only state machine may set it).

Update scopes:
```diff
- public function scopePublished(Builder $query): Builder
- {
-     return $query->where('status', ServiceStatus::Published);
- }
+ public function scopePublished(Builder $query): Builder
+ {
+     return $query->whereState('status', PublishedState::class);
+ }
```

### `Booking` (`bookings.lifecycle_status`, `bookings.payment_status`)

```diff
- protected $casts = [
-     'lifecycle_status' => LifecycleStatus::class,
-     'payment_status'   => PaymentStatus::class,   // Booking\Domain\Enums\PaymentStatus
+ protected $casts = [
+     'lifecycle_status' => BookingLifecycleState::class,
+     'payment_status'   => BookingPaymentState::class,
  ];
```

Add `use HasStates;`

Remove `lifecycle_status` and `payment_status` from `$fillable`.

### `Payment` (`payments.status`)

```diff
- protected $casts = [
-     'status' => PaymentStatus::class,   // Payments\Domain\Enums\PaymentStatus
+ protected $casts = [
+     'status' => PaymentState::class,
  ];
```

Add `use HasStates;`

Remove `status` from `$fillable`.

### `VendorProfile` (`vendor_profiles.approval_status`)

```diff
- protected $casts = [
-     'approval_status' => ApprovalStatus::class,
+ protected $casts = [
+     'approval_status' => VendorApprovalState::class,
  ];
```

Add `use HasStates;`

Remove `approval_status` from `$fillable`.

### `Withdrawal` (`withdrawals.status`)

```diff
- protected $casts = [
-     'status' => WithdrawalStatus::class,
+ protected $casts = [
+     'status' => WithdrawalState::class,
  ];
```

Add `use HasStates;`

Remove `status` from `$fillable`.

### `Commission` (`commissions.status`)

```diff
- protected $casts = [
-     'status' => CommissionStatus::class,
+ protected $casts = [
+     'status' => CommissionState::class,
  ];
```

Add `use HasStates;`

Remove `status` from `$fillable`.

### `VendorSubscription` (`vendor_subscriptions.status`) — already uses `SubscriptionState`

Cast unchanged. Add transition classes and log integration only.

Update scopes to use `->whereState()`:
```diff
- public function scopeActive(Builder $query): Builder
- {
-     return $query->where('status', SubscriptionStatus::Active->value);
- }
+ public function scopeActive(Builder $query): Builder
+ {
+     return $query->whereState('status', ActiveState::class);
+ }
```

---

## New File Inventory

### `app/Modules/Shared/Domain/Models/StateTransition.php`
Renamed from `app/Modules/Booking/Domain/Models/BookingStateTransition.php`.

```php
class StateTransition extends Model
{
    protected $table = 'state_transitions';

    const UPDATED_AT = null;  // append-only

    protected $fillable = [
        'transitionable_type', 'transitionable_id',
        'from_state', 'to_state',
        'triggered_by', 'trigger_kind',
        'reason', 'trace_id', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
```

### `app/Modules/Shared/Infrastructure/Listeners/StateTransitionObserver.php`

Registered in `SharedServiceProvider::boot()`:
```php
Event::listen(\Spatie\ModelStates\Events\StateChanged::class, StateTransitionObserver::class);
```

### `app/Modules/Shared/Http/Middleware/SetRequestTraceIdMiddleware.php`

Registered globally in `bootstrap/app.php` before route middleware.

### `app/Modules/Shared/Domain/Contracts/StateTransitionLogger.php`

Interface — see `contracts/state-transition-logger.md`.

---

## State Value → State Class Mapping (All 8 Machines)

| DB Value | State Class |
|---|---|
| **ServiceStatus** | |
| `draft` | `DraftState` |
| `pending_review` | `PendingReviewState` |
| `changes_requested` | `ChangesRequestedState` |
| `published` | `PublishedState` |
| `rejected` | `RejectedState` |
| `archived` | `ArchivedState` |
| **BookingLifecycleStatus** | |
| `draft` | `DraftState` |
| `submitted` | `SubmittedState` |
| `vendor_review` | `VendorReviewState` |
| `customer_review` | `CustomerReviewState` |
| `confirmed` | `ConfirmedState` |
| `active` | `ActiveState` |
| `completed` | `CompletedState` |
| `cancelled` | `CancelledState` |
| **BookingPaymentStatus** | |
| `unpaid` | `UnpaidState` |
| `partial` | `PartialState` |
| `paid` | `PaidState` |
| `refund_pending` | `RefundPendingState` |
| `partially_refunded` | `PartiallyRefundedState` |
| `refunded` | `RefundedState` |
| **PaymentStatus** | |
| `pending` | `PendingState` |
| `authorized` | `AuthorizedState` |
| `captured` | `CapturedState` |
| `failed` | `FailedState` |
| `refunded` | `RefundedState` |
| `partially_refunded` | `PartiallyRefundedState` |
| `voided` | `VoidedState` |
| `abandoned` | `AbandonedState` |
| **VendorApprovalStatus** | |
| `pending` | `PendingState` |
| `approved` | `ApprovedState` |
| `rejected` | `RejectedState` |
| `suspended` | `SuspendedState` |
| `changes_requested` | `ChangesRequestedState` |
| **WithdrawalStatus** | |
| `pending` | `PendingState` |
| `approved` | `ApprovedState` |
| `paid` | `PaidState` |
| `rejected` | `RejectedState` |
| **CommissionStatus** | |
| `calculated` | `CalculatedState` |
| `partially_reversed` | `PartiallyReversedState` |
| `reversed` | `ReversedState` |
| **SubscriptionStatus** (already mapped) | |
| `active` | `ActiveState` |
| `past_due` | `PastDueState` |
| `cancelled` | `CancelledState` |
| `expired` | `ExpiredState` |
| `superseded` | `SupersededState` |

---

## Backwards Compatibility

No DB column value changes. All existing enum string values are preserved via `registerState()`.
Existing API Resources that call `$model->status->value` (or `->name`) will continue to work
because the state class implements `__toString()` returning the DB value string.

However, any code that type-checks `$model->status instanceof ServiceStatus` (enum) will break
after the cast changes to `ServiceState`. All such checks must be replaced with:
```php
$model->status instanceof PublishedState
// or
$model->status->equals(PublishedState::class)
```
These call sites are included in the 34 files with unsafe mutations and will be updated.
