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

# Implementation Plan: Lifecycle State Machine Architecture

**Branch**: `026-lifecycle-state-machines` | **Date**: 2026-05-15 | **Spec**: [spec.md](./spec.md)

## Summary

Migrate 8 enum-based status fields to `spatie/laravel-model-states` state machine classes, extend
the existing polymorphic `booking_state_transitions` table (renaming to `state_transitions` with
`reason` + `trace_id` columns), replace 44 unsafe direct mutation call sites, and add
PHPStan + Pest architecture test enforcement with a shared `StateTransitionObserver` auto-logging
all transitions.

**Package**: `spatie/laravel-model-states` — already installed and active for 3 existing state
machines (`RentalItemStatus`, `SaleItemStatus`, `DigitalItemStatus`, `SubscriptionState`).
**No new package required.**

---

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12  
**Primary Dependencies**: `spatie/laravel-model-states` (^2.x, already installed), `spatie/laravel-activitylog` (already installed for dual-write on admin overrides)  
**Storage**: MySQL 8 — one `RENAME TABLE` migration + two `ADD COLUMN` on `booking_state_transitions`  
**Testing**: Pest + PestPHP Laravel plugin — existing test suite  
**Target Platform**: `app/Modules/` modular monolith  
**Project Type**: Backend refactoring — no new API endpoints, no Filament resources  
**Performance Goals**: Transition logging adds ≤1 extra DB INSERT per transition; acceptable under 200ms p95 target  
**Constraints**: Backwards compatibility — no DB column value changes; only cast type changes on models  
**Scale/Scope**: 8 state machines × (6–8 states + 4–12 transitions) = ~60 state classes, ~80 transition classes, 34 files with unsafe mutations to update

---

## Constitution Check

*GATE: All principles passed. No violations.*

| Principle | Verdict | Evidence |
|---|---|---|
| I — Modular monolith, no cross-module model imports | PASS | Each state machine lives in its owning module's `Domain/States/`. Shared infrastructure (`StateTransitionObserver`, `SetRequestTraceIdMiddleware`) lives in `Shared` module. |
| II — Three product types, `match($enum)` | PASS | Per-item `item_status` states remain per-type (RentalItemStatus, SaleItemStatus, DigitalItemStatus) and are unchanged. No if/elseif introduced. |
| III — Money discipline | N/A | No money columns modified. |
| IV — Bilingual EN+AR | N/A | No user-facing translatable strings. Error messages for `TransitionNotAllowedException` are English-only (internal dev exception). |
| V — Append-only `state_transitions` | PASS | `state_transitions` (renamed from `booking_state_transitions`) remains append-only. `reason` and `trace_id` are new nullable columns added via `ADD COLUMN` migration — no row updates. |
| VI — Spec-driven development (ADR before code) | PASS | Spec accepted; no new module introduced. No ADR required for within-module refactoring. |
| VII — Test-first for critical paths | PASS | State machines are critical paths. 80%+ coverage on Transition classes required. Architecture test blocks regressions. |
| VIII — Idempotency for state-changing endpoints | PASS | Existing idempotency key checks in Actions are preserved and moved inside Transition `handle()` methods where applicable. |
| IX — Domain events fire `DB::afterCommit` | PASS | Transition side effects remain inside `handle()` with `DB::afterCommit()` for events. |
| X — Vendor approval two-step gate | PASS | `VendorApprovalState` guards `Pending → Approved` to admin actors only; profile approval remains gated before type approval. |
| XI — Document storage | N/A | No media/storage changes. |

---

## Project Structure

### Documentation (this feature)

```text
specs/026-lifecycle-state-machines/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── state-transition-logger.md
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code Layout

```text
# Shared infrastructure (new)
app/Modules/Shared/
├── Domain/
│   ├── Contracts/
│   │   └── StateTransitionLogger.php          # Interface
│   └── States/
│       └── Concerns/
│           └── LogsStateTransitions.php       # Trait used by base State classes
├── Http/Middleware/
│   └── SetRequestTraceIdMiddleware.php        # Injects X-Trace-Id into request + Context
├── Infrastructure/
│   └── Listeners/
│       └── StateTransitionObserver.php        # Listens to spatie StateChanged event
└── Domain/Models/
    └── StateTransition.php                    # Renamed from BookingStateTransition

# Catalog module
app/Modules/Catalog/Domain/States/ServiceStatus/
├── ServiceState.php                           # abstract, extends State, config() defines transitions
├── DraftState.php
├── PendingReviewState.php
├── ChangesRequestedState.php
├── PublishedState.php
├── RejectedState.php
├── ArchivedState.php
└── Transitions/
    ├── SubmitForReviewTransition.php
    ├── ApproveServiceTransition.php
    ├── RejectServiceTransition.php
    ├── RequestServiceChangesTransition.php
    ├── ResubmitAfterChangesTransition.php
    ├── ArchiveServiceTransition.php
    ├── UnarchiveServiceTransition.php
    └── AdminOverrideServiceStatusTransition.php

# Booking module — lifecycle
app/Modules/Booking/Domain/States/BookingLifecycleStatus/
├── BookingLifecycleState.php
├── DraftState.php
├── SubmittedState.php
├── VendorReviewState.php
├── CustomerReviewState.php
├── ConfirmedState.php
├── ActiveState.php
├── CompletedState.php
├── CancelledState.php
└── Transitions/
    ├── SubmitBookingTransition.php
    ├── SendToVendorReviewTransition.php
    ├── MoveToCustomerReviewTransition.php
    ├── ConfirmBookingTransition.php
    ├── ActivateBookingTransition.php
    ├── CompleteBookingTransition.php
    ├── CancelBookingTransition.php
    └── AdminOverrideLifecycleTransition.php

# Booking module — payment status
app/Modules/Booking/Domain/States/BookingPaymentStatus/
├── BookingPaymentState.php
├── UnpaidState.php
├── PartialState.php
├── PaidState.php
├── RefundPendingState.php
├── PartiallyRefundedState.php
├── RefundedState.php
└── Transitions/
    ├── RecordPartialPaymentTransition.php
    ├── MarkBookingPaidTransition.php
    ├── InitiateRefundTransition.php
    ├── RecordPartialRefundTransition.php
    ├── CompleteRefundTransition.php
    └── AdminOverridePaymentStatusTransition.php

# Payments module
app/Modules/Payments/Domain/States/PaymentStatus/
├── PaymentState.php
├── PendingState.php
├── AuthorizedState.php
├── CapturedState.php
├── FailedState.php
├── RefundedState.php
├── PartiallyRefundedState.php
├── VoidedState.php
├── AbandonedState.php
└── Transitions/
    ├── AuthorizePaymentTransition.php
    ├── CapturePaymentTransition.php
    ├── FailPaymentTransition.php
    ├── VoidPaymentTransition.php
    ├── RefundPaymentTransition.php
    ├── PartiallyRefundPaymentTransition.php
    ├── AbandonPaymentTransition.php
    └── AdminOverridePaymentStatusTransition.php

# Identity module
app/Modules/Identity/Domain/States/VendorApprovalStatus/
├── VendorApprovalState.php
├── PendingState.php
├── ApprovedState.php
├── RejectedState.php
├── SuspendedState.php
├── ChangesRequestedState.php
└── Transitions/
    ├── ApproveVendorTransition.php
    ├── RejectVendorTransition.php
    ├── RequestVendorChangesTransition.php
    ├── VendorResubmitTransition.php
    ├── SuspendVendorTransition.php
    ├── UnsuspendVendorTransition.php
    └── AdminOverrideVendorApprovalTransition.php

# Settlement module — withdrawals
app/Modules/Settlement/Domain/States/WithdrawalStatus/
├── WithdrawalState.php
├── PendingState.php
├── ApprovedState.php
├── PaidState.php
├── RejectedState.php
└── Transitions/
    ├── ApproveWithdrawalTransition.php
    ├── RejectWithdrawalTransition.php
    ├── MarkWithdrawalPaidTransition.php
    └── AdminOverrideWithdrawalTransition.php

# Settlement module — commissions
app/Modules/Settlement/Domain/States/CommissionStatus/
├── CommissionState.php
├── CalculatedState.php
├── PartiallyReversedState.php
├── ReversedState.php
└── Transitions/
    ├── PartiallyReverseCommissionTransition.php
    ├── FullyReverseCommissionTransition.php
    └── AdminOverrideCommissionTransition.php

# Subscriptions module (existing states, adding transition classes)
app/Modules/Subscriptions/Domain/States/Transitions/
    ├── ExpireSubscriptionTransition.php
    ├── CancelSubscriptionTransition.php
    ├── RenewSubscriptionTransition.php
    ├── MarkPastDueTransition.php
    ├── SupersedeSubscriptionTransition.php
    └── AdminOverrideSubscriptionTransition.php

# Architecture tests (new)
tests/Architecture/
└── NoDirectStatusMutationTest.php

# Migration
app/Modules/Booking/Database/Migrations/
└── 2026_05_15_000001_rename_booking_state_transitions_add_columns.php
```

---

## State Machine Transition Maps

### ServiceState (`services.status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Draft | PendingReview | Vendor | vendor owns service |
| PendingReview | Published | Admin | has `publish_service` permission |
| PendingReview | Rejected | Admin | has `moderate_service` permission |
| PendingReview | ChangesRequested | Admin | has `moderate_service` permission |
| PendingReview | Draft | Admin | has `moderate_service` permission |
| ChangesRequested | PendingReview | Vendor | vendor owns service |
| ChangesRequested | Draft | Vendor | vendor owns service |
| Published | PendingReview | System | material edit detected |
| Published | Archived | Vendor or Admin | — |
| Published | Draft | Admin | has `moderate_service` permission |
| Rejected | Archived | Admin | has `moderate_service` permission |
| Archived | Draft | Vendor or Admin | — |
| Any | Any | Admin | AdminOverride: has `admin` role + reason required |

### BookingLifecycleState (`bookings.lifecycle_status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Draft | Submitted | Customer | booking has ≥1 item |
| Submitted | VendorReview | System | — |
| VendorReview | CustomerReview | System | ≥1 vendor responded |
| VendorReview | Cancelled | System | all vendors rejected |
| CustomerReview | Confirmed | Customer | — |
| CustomerReview | Cancelled | Customer | — |
| Confirmed | Active | System | payment captured |
| Active | Completed | System | event_ends_at passed + all items complete |
| Active | Cancelled | Admin | has `cancel_booking` permission |
| Any | Cancelled | Admin | AdminOverride: has `admin` role + reason |

### BookingPaymentState (`bookings.payment_status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Unpaid | Partial | System | partial payment received |
| Unpaid | Paid | System | full payment received |
| Partial | Paid | System | remaining payment received |
| Paid | RefundPending | System | refund initiated |
| RefundPending | PartiallyRefunded | System | partial refund processed |
| RefundPending | Refunded | System | full refund processed |
| PartiallyRefunded | Refunded | System | remaining refund processed |

### PaymentState (`payments.status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Pending | Authorized | System (Paymob webhook) | valid HMAC |
| Pending | Failed | System | gateway declined |
| Pending | Abandoned | System | TTL expired |
| Authorized | Captured | System (Paymob webhook) | valid HMAC |
| Authorized | Voided | Admin | has `void_payment` permission |
| Captured | PartiallyRefunded | System | partial refund processed |
| Captured | Refunded | System | full refund processed |
| PartiallyRefunded | Refunded | System | remaining refund processed |
| Failed | Pending | System | retry allowed (max 3) |

### VendorApprovalState (`vendor_profiles.approval_status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Pending | Approved | Admin | has `approve_vendor` permission |
| Pending | Rejected | Admin | has `reject_vendor` permission |
| Pending | ChangesRequested | Admin | has `moderate_vendor` permission |
| ChangesRequested | Pending | Vendor | vendor owns profile |
| Approved | Suspended | Admin | has `suspend_vendor` permission |
| Suspended | Approved | Admin | has `unsuspend_vendor` permission |
| Rejected | Pending | Admin | has `moderate_vendor` permission (admin reset) |

### WithdrawalState (`withdrawals.status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Pending | Approved | Admin | has `approve_withdrawal` permission |
| Pending | Rejected | Admin | has `reject_withdrawal` permission |
| Approved | Paid | Admin | has `mark_withdrawal_paid` permission |

### CommissionState (`commissions.status`)
| From | To | Actor | Guard |
|---|---|---|---|
| Calculated | PartiallyReversed | System | partial refund event |
| Calculated | Reversed | System | full refund event |
| PartiallyReversed | Reversed | System | remaining refund event |

### SubscriptionState (`vendor_subscriptions.status`) — already has state classes
| From | To | Actor | Guard |
|---|---|---|---|
| Active | PastDue | System | renewal failed |
| Active | Cancelled | Vendor or Admin | — |
| Active | Superseded | System | new subscription replaces |
| PastDue | Active | System | renewal success |
| PastDue | Expired | System | grace period elapsed |
| PastDue | Cancelled | Vendor or Admin | — |

---

## Shared Infrastructure Design

### `StateTransitionObserver`

Listens to `spatie/laravel-model-states`' built-in `StateChanged` event (fired automatically
after every `transitionTo()` call). Writes one row to `state_transitions` inside the
**same** DB transaction as the model update:

```php
// Spatie fires this event on every successful transitionTo()
class StateTransitionObserver
{
    public function handle(StateChanged $event): void
    {
        StateTransition::create([
            'transitionable_type' => get_class($event->model),
            'transitionable_id'   => $event->model->getKey(),
            'from_state'          => $event->initialState ? class_basename($event->initialState) : null,
            'to_state'            => class_basename($event->finalState),
            'triggered_by'        => Context::get('actor_id'),
            'trigger_kind'        => Context::get('trigger_kind', 'system'),
            'reason'              => Context::get('transition_reason'),
            'trace_id'            => Context::get('trace_id'),
            'context'             => Context::get('transition_metadata'),
        ]);
    }
}
```

Key design decisions:
- Uses Laravel `Context` (Laravel 11+) to propagate `actor_id`, `trigger_kind`, `reason`, `trace_id`, and `metadata` without threading them through every method signature
- `SetRequestTraceIdMiddleware` populates `Context::add('trace_id', ...)` from `X-Trace-Id` header (or generates a UUID if absent)
- Transition classes call `Context::add('transition_reason', $this->reason)` before executing
- Admin override Transitions call `Context::add('trigger_kind', 'admin_override')`

### `SetRequestTraceIdMiddleware`

```php
class SetRequestTraceIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id') ?? (string) Str::uuid();
        Context::add('trace_id', $traceId);
        return $next($request);
    }
}
```

Registered globally in `bootstrap/app.php` middleware pipeline.

### Transition class shape (canonical example)

```php
// app/Modules/Identity/Domain/States/VendorApprovalStatus/Transitions/ApproveVendorTransition.php
final class ApproveVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
    ) {}

    public function handle(): VendorProfile
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', 'admin');
        Context::add('transition_reason', 'Manual approval by admin');

        $this->model->status->transitionTo(ApprovedState::class);

        DB::afterCommit(fn () => event(new VendorApproved($this->model)));

        return $this->model;
    }
}
```

Then in the `ApproveVendorProfileAction`:

```php
// Before (unsafe):
$profile->update(['approval_status' => ApprovalStatus::Approved->value]);

// After (safe):
(new ApproveVendorTransition($profile, auth()->id()))->handle();
```

### Naming reconciliation (enum value ↔ state class name)

`spatie/laravel-model-states` maps state class names to DB column values via `$name` property or
the class basename. Current enum string values (e.g., `'pending_review'`) must map to the
`PendingReviewState` class. The `ServiceState` config registers the mapping:

```php
abstract class ServiceState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->registerState(DraftState::class, 'draft')
            ->registerState(PendingReviewState::class, 'pending_review')
            ->registerState(ChangesRequestedState::class, 'changes_requested')
            ->registerState(PublishedState::class, 'published')
            ->registerState(RejectedState::class, 'rejected')
            ->registerState(ArchivedState::class, 'archived')
            ->allowTransition(DraftState::class, PendingReviewState::class)
            // ... etc.
    }
}
```

This preserves existing DB values exactly — **zero data migration needed**.

---

## Migration Plan

### Step 1 — Rename table + add columns
```php
// 2026_05_15_000001_rename_booking_state_transitions_add_columns.php
Schema::rename('booking_state_transitions', 'state_transitions');
Schema::table('state_transitions', function (Blueprint $table) {
    $table->text('reason')->nullable()->after('trigger_kind');
    $table->char('trace_id', 36)->nullable()->after('reason');
    $table->index('trace_id', 'st_trace_id_idx');
});
```

### Step 2 — Rename model file
`BookingStateTransition` → `StateTransition` (in `app/Modules/Shared/Domain/Models/`)
Update `$table = 'state_transitions'` and fix all 34 call sites.

### Step 3 — Add `HasStates` + update casts per model
For each of the 8 models: add `use HasStates`, change cast from `EnumClass::class` to
`AbstractStateClass::class`, remove from `$fillable` (state fields must not be mass-assignable
after migration — only state machine can set them).

### Step 4 — Implement state + transition classes (per module, see tree above)

### Step 5 — Register `StateTransitionObserver` in `SharedServiceProvider`
```php
Event::listen(StateChanged::class, StateTransitionObserver::class);
```

### Step 6 — Refactor Action call sites (34 files)
Replace every `->update(['<status_field>' => ...])` with the appropriate Transition class.
Remove manually-created `BookingStateTransition::create([...])` calls (now handled by observer).

### Step 7 — Update model scopes
Replace `->where('status', EnumValue::Foo->value)` with `->whereState('status', FooState::class)`.

### Step 8 — Add architecture test

### Step 9 — Update existing per-item `item_status` machines to also fire through observer
These already use spatie; just ensure `StateChanged` event fires — confirm via test.

---

## Scope Boundaries

**In scope:**
- 8 status fields listed in spec
- `state_transitions` table (rename + extend `booking_state_transitions`)
- `BookingStateTransition` model rename → `StateTransition`
- 34 call sites with unsafe mutations
- Model scopes using raw `.value` comparisons (update to `->whereState()`)
- Architecture test

**Out of scope (confirmed):**
- `Booking.fulfillment_status` — superseded by per-item `item_status` state machine
- `BookingVendor.sub_status` — not in spec scope; already tracked in `state_transitions` via the existing manual `BookingStateTransition::create()` calls; add to a future spec if needed
- Filament Resource changes — state fields are internal; admin views query via scopes (no UI label change)
- API endpoint additions — no new endpoints

---

## Unsafe Mutation Inventory

Confirmed 44 occurrences in 34 files. Key clusters:

| File | Mutations | Replacement |
|---|---|---|
| `VendorRejectBookingAction` | 2 lifecycle_status, manual BST create ×2 | `CancelBookingTransition`, `MoveToCustomerReviewTransition` |
| `VendorResubmitAfterChangesAction` | 1 approval_status | `VendorResubmitTransition` |
| `CustomerConfirmModifiedBookingAction` | 3 lifecycle_status | Lifecycle transition classes |
| `UpdateBookingPaymentStatusListener` | 2 payment_status | Payment status transition classes |
| `SubmitBookingAction` | lifecycle_status | `SubmitBookingTransition` |
| Payments Actions (4 files) | payment.status | Payment state transition classes |
| Communication Actions (5 files) | campaign/dispatch status | (scoped separately — not in spec) |
| Subscriptions Actions (2 files) | subscription.status | Subscription transition classes |
| Catalog Actions (7 files) | service.status | Service state transition classes |
| Identity Actions (5 files) | approval_status | Vendor approval transition classes |
| Settlement (3 files) | withdrawal.status, commission.status | Settlement transition classes |

Communication and Advertising module status fields are **excluded** from this spec (campaign status, dispatch status, ad subscription status are not in the 8 target fields).

---

## Test Plan

### Per state machine
For each of the 8 machines, tests must cover:
1. **Valid transitions** — each allowed pair succeeds and writes one `state_transitions` row
2. **Invalid transition rejection** — each forbidden pair throws `TransitionNotAllowedException`
3. **Guard failures** — wrong actor or missing permission throws correctly
4. **Admin override** — succeeds with reason, fails without reason, fails for non-admin
5. **Race condition** — concurrent duplicate transition → exactly one succeeds
6. **Log completeness** — `trace_id`, `actor_id`, `trigger_kind`, `reason` all non-null in appropriate cases

### Architecture test
```php
// tests/Architecture/NoDirectStatusMutationTest.php
arch('no direct mutation of protected status fields')
    ->expect('App\Modules')
    ->not->toCallMethod('update')  // scoped to status field array keys
    ->ignoring(['App\Modules\*/Database/Migrations', 'App\Modules\*/Database/Factories']);
```

### Regression
- Existing Booking flow tests must pass without modification
- Subscription state machine tests pass
- Item-status state machine tests pass

---

## ADR Reference

No new module introduced — no ADR required. This refactoring is within existing modules.
Constitution Principle VI satisfied.

---

## Exit Criteria (Phase 7.1)

- [ ] All 8 state machines implemented with explicit `allowTransition()` config
- [ ] `state_transitions` table migration applied (rename + 2 columns)
- [ ] `StateTransitionObserver` registered and logging all transitions
- [ ] `SetRequestTraceIdMiddleware` active on all routes
- [ ] 0 unsafe mutation occurrences (architecture test green)
- [ ] Pest test suite green including per-machine valid/invalid/race cases
- [ ] PHPStan level 8 green on all new state/transition class files
