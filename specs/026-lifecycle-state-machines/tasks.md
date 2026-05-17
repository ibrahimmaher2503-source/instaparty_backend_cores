---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. docs/specs/11_DB_Schema.md
4. docs/specs/10_Package_List.md
---

# Tasks: Lifecycle State Machine Architecture

**Input**: Design documents from `specs/026-lifecycle-state-machines/`
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)
**Phase**: Phase 7.1 â€” Hardening: Lifecycle Integrity
**Total tasks**: 56 | **Parallelizable**: 38

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable â€” different files, no in-phase dependencies
- **[US#]**: User story from spec.md (US1â€“US5)
- Tests are required per FR-EXT-026-018 (Pest; TDD order â€” write test then implement)

---

## Phase 1: Setup â€” Shared Infrastructure

**Purpose**: Rename the transition log table, create shared models, middleware, and observer.
All 8 state machine implementations depend on this phase being complete.

**âš ï¸ CRITICAL**: No state machine work can begin until this phase is complete.

- [x] T001 Write migration `app/Modules/Booking/Database/Migrations/2026_05_15_000001_rename_booking_state_transitions_add_columns.php`: rename `booking_state_transitions` â†’ `state_transitions`, ADD COLUMN `reason TEXT NULL AFTER trigger_kind`, ADD COLUMN `trace_id CHAR(36) NULL AFTER reason`, ADD INDEX `st_trace_id_idx ON (trace_id)`, and MODIFY COLUMN `trigger_kind` ENUM to add `'admin_override'` value (keeping existing four values). `down()` must reverse all changes.

- [x] T002 Create `app/Modules/Shared/Domain/Models/StateTransition.php`: Eloquent model with `$table = 'state_transitions'`, `const UPDATED_AT = null` (append-only), `$fillable` for all columns, cast `context` as `'array'`. This replaces `BookingStateTransition`.

- [x] T003 [P] Create `app/Modules/Shared/Domain/Enums/TriggerKind.php`: backed string enum with cases `System = 'system'`, `Customer = 'customer'`, `Vendor = 'vendor'`, `Admin = 'admin'`, `AdminOverride = 'admin_override'`. Add method `requiresReason(): bool` (returns true only for `AdminOverride`).

- [x] T004 [P] Create `app/Modules/Shared/Domain/Contracts/StateTransitionLogger.php`: interface with one method `record(string $transitionableType, int $transitionableId, ?string $fromState, string $toState, ?int $triggeredBy, TriggerKind $triggerKind, ?string $reason, ?string $traceId, ?array $context): void`. See `contracts/state-transition-logger.md` for full signature.

- [x] T005 [P] Create `app/Modules/Shared/Http/Middleware/SetRequestTraceIdMiddleware.php`: reads `X-Trace-Id` header from request (or generates `(string) Str::uuid()` if absent), writes value to `Context::add('trace_id', $traceId)`, passes request to `$next($request)`, sets `X-Trace-Id` on response for client correlation.

- [x] T006 Create `app/Modules/Shared/Infrastructure/Listeners/StateTransitionObserver.php`: listens to `\Spatie\ModelStates\Events\StateChanged`. In `handle(StateChanged $event)`: build a `StateTransition::create([...])` row reading `actor_id`, `trigger_kind`, `transition_reason`, `transition_metadata`, `trace_id` from `Context`. After writing, call `Context::forget(['transition_reason','transition_metadata'])` to avoid bleed between transitions.

- [x] T007 Update `app/Modules/Shared/Providers/SharedServiceProvider.php` (or create it if absent): in `register()` bind `StateTransitionLogger::class` â†’ `StateTransitionObserver::class`; in `boot()` call `Event::listen(\Spatie\ModelStates\Events\StateChanged::class, StateTransitionObserver::class)`. Update `bootstrap/app.php` to register `SetRequestTraceIdMiddleware` globally before route middleware.

- [x] T008 Search-and-replace `BookingStateTransition` â†’ `StateTransition` across all `app/Modules/` PHP files (there is exactly 1 model file and ~4 Action/Listener files that reference it). Update `use` imports to point to `App\Modules\Shared\Domain\Models\StateTransition`. Delete old `app/Modules/Booking/Domain/Models/BookingStateTransition.php` after confirming all references are updated.

**Checkpoint**: `php artisan migrate` succeeds; `StateTransitionObserver` is registered; no references to `BookingStateTransition` remain.

---

## Phase 2: Foundational â€” State Class Hierarchies

**Purpose**: Abstract state base classes and all concrete state classes for the 8 machines.
These must exist before any transition can reference them.

All tasks in this phase are parallel (different module directories).

- [x] T009 [P] Create `app/Modules/Catalog/Domain/States/ServiceStatus/ServiceState.php` (abstract, extends `Spatie\ModelStates\State`; `config()` registers all 6 DB values via `registerState()`; `allowTransition()` entries are added in Phase 3). Create 6 concrete classes in the same directory: `DraftState.php`, `PendingReviewState.php`, `ChangesRequestedState.php`, `PublishedState.php`, `RejectedState.php`, `ArchivedState.php` (each `final class XxxState extends ServiceState {}`).

- [x] T010 [P] Create `app/Modules/Booking/Domain/States/BookingLifecycleStatus/BookingLifecycleState.php` (abstract) with `registerState()` for all 8 DB values. Create 8 concrete classes: `DraftState.php`, `SubmittedState.php`, `VendorReviewState.php`, `CustomerReviewState.php`, `ConfirmedState.php`, `ActiveState.php`, `CompletedState.php`, `CancelledState.php`.

- [x] T011 [P] Create `app/Modules/Booking/Domain/States/BookingPaymentStatus/BookingPaymentState.php` (abstract) with `registerState()` for all 6 DB values. Create 6 concrete classes: `UnpaidState.php`, `PartialState.php`, `PaidState.php`, `RefundPendingState.php`, `PartiallyRefundedState.php`, `RefundedState.php`.

- [x] T012 [P] Create `app/Modules/Payments/Domain/States/PaymentStatus/PaymentState.php` (abstract) with `registerState()` for all 8 DB values. Create 8 concrete classes: `PendingState.php`, `AuthorizedState.php`, `CapturedState.php`, `FailedState.php`, `RefundedState.php`, `PartiallyRefundedState.php`, `VoidedState.php`, `AbandonedState.php`.

- [x] T013 [P] Create `app/Modules/Identity/Domain/States/VendorApprovalStatus/VendorApprovalState.php` (abstract) with `registerState()` for all 5 DB values. Create 5 concrete classes: `PendingState.php`, `ApprovedState.php`, `RejectedState.php`, `SuspendedState.php`, `ChangesRequestedState.php`.

- [x] T014 [P] Create `app/Modules/Settlement/Domain/States/WithdrawalStatus/WithdrawalState.php` (abstract) with `registerState()` for all 4 DB values. Create 4 concrete classes: `PendingState.php`, `ApprovedState.php`, `PaidState.php`, `RejectedState.php`.

- [x] T015 [P] Create `app/Modules/Settlement/Domain/States/CommissionStatus/CommissionState.php` (abstract) with `registerState()` for all 3 DB values. Create 3 concrete classes: `CalculatedState.php`, `PartiallyReversedState.php`, `ReversedState.php`.

- [x] T016 [P] Audit existing `app/Modules/Subscriptions/Domain/States/SubscriptionState.php`: verify `registerState()` is declared for all 5 DB values (`active`, `past_due`, `cancelled`, `expired`, `superseded`); add it if missing. All 5 concrete state classes already exist â€” no new files needed.

**Checkpoint**: All abstract + concrete state classes exist; `php artisan ide-helper:models` resolves them without errors.

---

## Phase 3: User Story 1 â€” Transition Enforcement (Prevent Invalid Transitions)

**Goal**: Wire `allowTransition()` in each machine's `config()` and create the normal (non-admin-override) Transition classes. Update model casts to `HasStates`. After this phase, any invalid transition throws `TransitionNotAllowedException`.

**Independent Test**: Seed a `Service` in `archived` status. Attempt `transitionTo(PublishedState::class)`. Assert `TransitionNotAllowedException` is thrown and DB row is unchanged.

### Service State Machine (Catalog module)

- [x] T017 [US1] Add all `allowTransition()` declarations to `ServiceState::config()` in `app/Modules/Catalog/Domain/States/ServiceStatus/ServiceState.php` using the full matrix from `plan.md` Â§State Machine Transition Maps. Add `use HasStates` to `app/Modules/Catalog/Domain/Models/Service.php`, change cast `'status' => ServiceState::class`, remove `'status'` from `$fillable`, update `scopePublished()` and `scopePendingReview()` to use `->whereState('status', PublishedState::class)` etc.

- [x] T018 [P] [US1] Create 7 normal Transition classes in `app/Modules/Catalog/Domain/States/ServiceStatus/Transitions/`: `SubmitForReviewTransition.php` (vendor guard), `ApproveServiceTransition.php` (admin, `publish_service` permission), `RejectServiceTransition.php` (admin), `RequestServiceChangesTransition.php` (admin), `ResubmitAfterChangesTransition.php` (vendor), `ArchiveServiceTransition.php` (vendor or admin), `UnarchiveServiceTransition.php` (vendor or admin). Each sets `Context` keys for `actor_id`, `trigger_kind`, `transition_reason`, fires `DB::afterCommit` domain event.

### Booking Lifecycle State Machine (Booking module)

- [x] T019 [US1] Add all `allowTransition()` declarations to `BookingLifecycleState::config()`. Add `use HasStates` to `app/Modules/Booking/Domain/Models/Booking.php`, change cast `'lifecycle_status' => BookingLifecycleState::class`, remove `lifecycle_status` from `$fillable`, update all booking scopes that compare `lifecycle_status` to use `->whereState()`.

- [x] T020 [P] [US1] Create 9 normal Transition classes in `app/Modules/Booking/Domain/States/BookingLifecycleStatus/Transitions/`: `SubmitBookingTransition.php`, `SendToVendorReviewTransition.php`, `MoveToCustomerReviewTransition.php`, `CancelFromVendorReviewTransition.php` (system â€” all vendors rejected), `ConfirmBookingTransition.php`, `CancelFromCustomerReviewTransition.php`, `ActivateBookingTransition.php`, `CompleteBookingTransition.php`, `CancelActiveBookingTransition.php` (admin). Each sets `Context` keys and fires appropriate domain events via `DB::afterCommit`.

### Booking Payment State Machine (Booking module)

- [x] T021 [US1] Add all `allowTransition()` declarations to `BookingPaymentState::config()`. Update `app/Modules/Booking/Domain/Models/Booking.php` cast `'payment_status' => BookingPaymentState::class`, remove `payment_status` from `$fillable`. (Depends on T019 since both touch `Booking.php`.)

- [x] T022 [P] [US1] Create 5 normal Transition classes in `app/Modules/Booking/Domain/States/BookingPaymentStatus/Transitions/`: `RecordPartialPaymentTransition.php`, `MarkBookingPaidTransition.php`, `InitiateRefundTransition.php`, `RecordPartialRefundTransition.php`, `CompleteRefundTransition.php`. All are `trigger_kind = system` (driven by payment events, not direct actor calls).

### Payment State Machine (Payments module)

- [x] T023 [US1] Add all `allowTransition()` declarations to `PaymentState::config()`. Add `use HasStates` to `app/Modules/Payments/Domain/Models/Payment.php`, change cast `'status' => PaymentState::class`, remove `status` from `$fillable`.

- [x] T024 [P] [US1] Create 8 normal Transition classes in `app/Modules/Payments/Domain/States/PaymentStatus/Transitions/`: `AuthorizePaymentTransition.php`, `CapturePaymentTransition.php`, `FailPaymentTransition.php`, `VoidPaymentTransition.php`, `RefundPaymentTransition.php`, `PartiallyRefundPaymentTransition.php`, `AbandonPaymentTransition.php`, `RetryPaymentTransition.php` (Failed â†’ Pending). All system-triggered; set `trigger_kind = TriggerKind::System`.

### Vendor Approval State Machine (Identity module)

- [x] T025 [US1] Add all `allowTransition()` declarations to `VendorApprovalState::config()`. Add `use HasStates` to `app/Modules/Identity/Domain/Models/VendorProfile.php`, change cast `'approval_status' => VendorApprovalState::class`, remove `approval_status` from `$fillable`.

- [x] T026 [P] [US1] Create 7 normal Transition classes in `app/Modules/Identity/Domain/States/VendorApprovalStatus/Transitions/`: `ApproveVendorTransition.php` (admin), `RejectVendorTransition.php` (admin), `RequestVendorChangesTransition.php` (admin), `VendorResubmitTransition.php` (vendor â€” Pending), `SuspendVendorTransition.php` (admin), `UnsuspendVendorTransition.php` (admin), `ResetVendorToPendingTransition.php` (admin). Guard: `Pending â†’ Approved` requires `auth()->user()->can('approve_vendor')`.

### Withdrawal State Machine (Settlement module)

- [x] T027 [US1] Add all `allowTransition()` declarations to `WithdrawalState::config()`. Add `use HasStates` to `app/Modules/Settlement/Domain/Models/Withdrawal.php`, change cast `'status' => WithdrawalState::class`, remove `status` from `$fillable`.

- [x] T028 [P] [US1] Create 3 normal Transition classes in `app/Modules/Settlement/Domain/States/WithdrawalStatus/Transitions/`: `ApproveWithdrawalTransition.php`, `RejectWithdrawalTransition.php`, `MarkWithdrawalPaidTransition.php`. All admin-only; check `approve_withdrawal` permission.

### Commission State Machine (Settlement module)

- [x] T029 [US1] Add all `allowTransition()` declarations to `CommissionState::config()`. Add `use HasStates` to `app/Modules/Settlement/Domain/Models/Commission.php`, change cast `'status' => CommissionState::class`, remove `status` from `$fillable`.

- [x] T030 [P] [US1] Create 2 normal Transition classes in `app/Modules/Settlement/Domain/States/CommissionStatus/Transitions/`: `PartiallyReverseCommissionTransition.php`, `FullyReverseCommissionTransition.php`. Both are system-triggered (fired from refund listener); set `trigger_kind = TriggerKind::System`.

### Subscription State Machine (Subscriptions module)

- [x] T031 [US1] Add all `allowTransition()` declarations to the existing `SubscriptionState::config()` in `app/Modules/Subscriptions/Domain/States/SubscriptionState.php` (it currently defines them; verify they are complete and match `plan.md` transition map). Update `VendorSubscription.php` scopes to use `->whereState('status', ActiveState::class)` instead of `->where('status', SubscriptionStatus::Active->value)`.

- [x] T032 [P] [US1] Create 5 normal Transition classes in `app/Modules/Subscriptions/Domain/States/Transitions/`: `MarkPastDueTransition.php`, `RenewSubscriptionTransition.php`, `ExpireSubscriptionTransition.php`, `CancelSubscriptionTransition.php`, `SupersedeSubscriptionTransition.php`. All are system-triggered except `CancelSubscriptionTransition` (vendor or admin).

### US1 Tests

- [ ] T033 [P] [US1] Write Pest tests in `tests/Feature/Modules/Catalog/States/ServiceStateTest.php`: (a) all 11 valid transitions succeed and each writes 1 `state_transitions` row; (b) all invalid transition pairs (e.g., `archived â†’ published`) throw `TransitionNotAllowedException` and leave DB unchanged; (c) permission guard test (vendor cannot approve own service â†’ `403`).

- [ ] T034 [P] [US1] Write Pest tests in `tests/Feature/Modules/Booking/States/BookingLifecycleStateTest.php` and `tests/Feature/Modules/Booking/States/BookingPaymentStateTest.php`: valid/invalid transitions + guard failures for both state machines.

- [ ] T035 [P] [US1] Write Pest tests in `tests/Feature/Modules/Payments/States/PaymentStateTest.php`: valid/invalid for all 9 transition pairs including retry flow (`failed â†’ pending`).

- [ ] T036 [P] [US1] Write Pest tests in `tests/Feature/Modules/Identity/States/VendorApprovalStateTest.php`: valid/invalid transitions; confirm `Pending â†’ Approved` fails for non-admin; confirm `Approved â†’ Suspended` fails without `suspend_vendor` permission.

- [ ] T037 [P] [US1] Write Pest tests in `tests/Feature/Modules/Settlement/States/WithdrawalStateTest.php`, `CommissionStateTest.php`, and `tests/Feature/Modules/Subscriptions/States/SubscriptionStateTest.php`: valid/invalid for each machine.

**Checkpoint**: `./vendor/bin/pest --group=state-machines` passes. Zero `TransitionNotAllowedException` escaping to the HTTP layer for valid transitions.

---

## Phase 4: User Story 2 â€” Transition History Logging

**Goal**: Every successful state transition writes a `state_transitions` row with correct `trace_id`, `actor_id`, `trigger_kind`, and `reason` within the same DB transaction.

**Independent Test**: Trigger `Pending â†’ Approved` on a `VendorProfile`. Query `state_transitions` for that model. Assert one row exists with `trigger_kind = admin`, `triggered_by = admin_user_id`, `trace_id` matching the request header, `to_state = approved`.

- [ ] T038 [US2] Confirm `StateTransitionObserver` is receiving `StateChanged` events: write a smoke test in `tests/Feature/Modules/Shared/States/StateTransitionLogTest.php` that transitions any model and asserts one `state_transitions` row is created in the same transaction. If the observer is not firing (spatie version difference), add `protected static $dispatchesEvents = ['transitioned' => StateChanged::class]` manually or use spatie's `onTransitioned()` hook.

- [ ] T039 [P] [US2] Add `trace_id` propagation to queue jobs that trigger state transitions: in `app/Modules/Booking/Application/Jobs/` and `app/Modules/Payments/Application/Jobs/`, add `Context::add('trace_id', $this->job->uuid())` at the start of `handle()`. Verify `trace_id` is set before any `transitionTo()` call.

- [ ] T040 [P] [US2] Write log-completeness tests in `tests/Feature/Modules/Shared/States/StateTransitionLogTest.php`: for each of the 8 machines, execute one representative transition and assert `state_transitions` row has non-null `trace_id`, correct `trigger_kind`, correct `from_state` / `to_state` DB values, and `triggered_by` is null for system transitions and non-null for actor transitions.

**Checkpoint**: Every transition in the test suite produces exactly 1 `state_transitions` row with trace_id; `SC-002` and `SC-007` from spec pass.

---

## Phase 5: User Story 3 â€” Unsafe Mutation Elimination

**Goal**: Replace all 44 direct `->update(['status'])` / `->forceFill(['status'])` call sites with Transition class calls. Add the architecture test that enforces this permanently.

**Independent Test**: Run `tests/Architecture/NoDirectStatusMutationTest.php` â€” expects 0 violations.

- [ ] T041 [US3] Refactor Catalog Actions (7 files): replace `->update(['status' => ServiceStatus::...])` patterns in `app/Modules/Catalog/Application/Actions/SubmitServiceForReviewAction.php`, `ApproveRentalServiceAction.php`, `ApproveSaleServiceAction.php`, `ApproveDigitalServiceAction.php`, `RejectServiceAction.php`, `RequestRentalServiceChangesAction.php`, `RequestSaleServiceChangesAction.php`, `RequestDigitalServiceChangesAction.php`, `ArchiveServiceAction.php`, `ServiceResubmitAfterChangesAction.php`, `MarkServicePendingReviewForMaterialEditAction.php`. Pattern: `$service = Service::lockForUpdate()->findOrFail($id); (new ApproveServiceTransition($service, auth()->id()))->handle();`

- [ ] T042 [P] [US3] Refactor Identity Actions (5 files): replace `$profile->update(['approval_status' => ...])` in `app/Modules/Identity/Application/Actions/VendorResubmitAfterChangesAction.php`, `RequestVendorChangesAction.php`, `SuspendVendorAction.php` (if exists), `UnsuspendCustomerAction.php`, `SuspendCustomerAction.php`, `UnsuspendVendorAction.php` (if exists). Also update `VendorResubmitAfterChangesAction` to remove the 4 direct `update()` calls found in codebase scan.

- [ ] T043 [P] [US3] Refactor Booking Actions and Listeners (5 files): replace `$booking->update(['lifecycle_status' => ...])` and manual `BookingStateTransition::create([...])` calls in `app/Modules/Booking/Application/Actions/VendorRejectBookingAction.php`, `VendorAcceptBookingAction.php`, `CustomerConfirmModifiedBookingAction.php`, `VendorModifyBookingAction.php`, `MarkBookingItemStateAction.php`, and `app/Modules/Booking/Application/Listeners/UpdateBookingPaymentStatusListener.php`. Remove all `BookingStateTransition::create([...])` calls (now handled by observer automatically).

- [ ] T044 [P] [US3] Refactor Payments Actions/Repositories (4 files): replace `->update(['status' => PaymentStatus::...])` in `app/Modules/Payments/Infrastructure/Repositories/EloquentPaymentRepository.php`, `EloquentRefundRepository.php`, `app/Modules/Payments/Application/Actions/ProcessRefundAction.php`, `VoidStuckAuthorizationAction.php`, `MarkPaymentAbandonedAction.php`.

- [ ] T045 [P] [US3] Refactor Settlement Actions (3 files): replace `->update(['status' => ...])` in `app/Modules/Settlement/Application/Actions/` withdrawal and commission action files. Update `ArchiveServicesOnTypeRevokedListener.php` in Catalog module.

- [ ] T046 [P] [US3] Refactor Subscriptions Actions (2 files): replace `->update(['status' => ...])` in `app/Modules/Subscriptions/Application/Actions/ApplyAdminTierOverrideAction.php`, `RevokeAdminTierOverrideAction.php`. Replace `->update(['status' => CampaignStatus::...])` calls in Communication Actions â€” **NOTE**: Communication/Advertising status fields (campaign, dispatch, ad subscription) are OUT OF SCOPE for state machine migration but their `->update(['status'])` calls must still be kept as-is (do not break them).

- [ ] T047 [US3] Create `tests/Architecture/NoDirectStatusMutationTest.php`: Pest architecture test that scans `app/Modules/` for `->update(['lifecycle_status'`, `->update(['payment_status'`, `->update(['approval_status'`, `->update(['status'` on the 8 protected models, `->forceFill` variants, and `$model->status =` direct assignments. Use `arch()` or a custom file scanner. Assert 0 matches (excluding `Database/Migrations/`, `Database/Factories/`, `Database/Seeders/`). Run and confirm green.

**Checkpoint**: `./vendor/bin/pest tests/Architecture/NoDirectStatusMutationTest.php` passes; `SC-001` satisfied.

---

## Phase 6: User Story 4 â€” Admin Override Transitions

**Goal**: Each state machine has a dedicated `AdminOverride*Transition` that bypasses `allowTransition()` guards, requires a non-empty `reason`, requires `admin` role, and records `trigger_kind = admin_override`.

**Independent Test**: Admin user calls `AdminOverrideServiceStatusTransition` to move `archived â†’ published` (normally forbidden). Assert succeeds, `state_transitions` row has `trigger_kind = admin_override` and non-null `reason`. Non-admin attempt fails with `403`.

- [ ] T048 [P] [US4] Create `app/Modules/Catalog/Domain/States/ServiceStatus/Transitions/AdminOverrideServiceStatusTransition.php`: constructor accepts `Service $model`, `string $targetStateDbValue`, `string $reason`, `int $actorId`. Validate `reason` not empty (throw `InvalidArgumentException`). Abort if actor lacks `admin` role. Set `Context` with `trigger_kind = admin_override`. Call `$this->model->status->forceTransitionTo(...)` (or use spatie's bypass mechanism). Fire `ServiceStatusAdminOverridden` domain event via `DB::afterCommit`.

- [ ] T049 [P] [US4] Create `app/Modules/Booking/Domain/States/BookingLifecycleStatus/Transitions/AdminOverrideLifecycleTransition.php` and `app/Modules/Booking/Domain/States/BookingPaymentStatus/Transitions/AdminOverrideBookingPaymentTransition.php` following the same shape as T048.

- [ ] T050 [P] [US4] Create `app/Modules/Payments/Domain/States/PaymentStatus/Transitions/AdminOverridePaymentStatusTransition.php`, `app/Modules/Identity/Domain/States/VendorApprovalStatus/Transitions/AdminOverrideVendorApprovalTransition.php`, `app/Modules/Settlement/Domain/States/WithdrawalStatus/Transitions/AdminOverrideWithdrawalTransition.php`, `app/Modules/Settlement/Domain/States/CommissionStatus/Transitions/AdminOverrideCommissionTransition.php`, `app/Modules/Subscriptions/Domain/States/Transitions/AdminOverrideSubscriptionTransition.php`.

- [ ] T051 [US4] Write Pest tests in `tests/Feature/Modules/*/States/*AdminOverrideTest.php` for each machine: (a) admin with reason succeeds â†’ `trigger_kind = admin_override`, `reason` non-null in `state_transitions`; (b) admin without reason â†’ `InvalidArgumentException`; (c) non-admin â†’ `403`; (d) verify `audit_logs` entry written for the same override (dual-write via `spatie/laravel-activitylog` or direct insert).

**Checkpoint**: All admin override tests pass; `SC-004` satisfied; dual-write in `audit_logs` confirmed.

---

## Phase 7: User Story 5 â€” Race Condition Protection

**Goal**: Concurrent duplicate transition attempts on the same model result in exactly one success and one conflict error. No split-brain state or duplicate `state_transitions` rows.

**Independent Test**: Two simultaneous HTTP calls attempt `submitted â†’ vendor_review` on the same Booking. Assert exactly one `200`, one `409`, and one `state_transitions` row.

- [ ] T052 [US5] Audit all Action classes that now call Transition classes: verify every one calls `Model::query()->lockForUpdate()->findOrFail($id)` before instantiating the Transition. If any Action skips `lockForUpdate()`, add it. Document the pattern in `quickstart.md` (add a "Locking" section if absent).

- [ ] T053 [P] [US5] Write concurrent transition tests in `tests/Feature/Modules/Booking/States/ConcurrentTransitionTest.php`: use PHP `pcntl_fork()` or `Fiber`/`Process::run()` to fire two simultaneous `SubmitBookingTransition` calls for the same Booking. Assert exactly one succeeds, one throws `LockTimeoutException` or returns conflict, and DB has exactly one `state_transitions` row for this pair. Add similar test for `VendorApprovalState` in `tests/Feature/Modules/Identity/States/ConcurrentVendorApprovalTest.php`.

**Checkpoint**: Race condition tests pass; `SC-005` satisfied.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T054 Run `./vendor/bin/pest --bail` across the full test suite; fix any regressions introduced by model cast changes (e.g., code that did `$model->status instanceof ServiceStatus` must be updated to `$model->status instanceof PublishedState` or equivalent state class check).

- [ ] T055 [P] Run `./vendor/bin/pint` on all new and modified PHP files; run `./vendor/bin/phpstan analyse` on all new `Domain/States/` directories at level 8. Fix any reported issues.

- [ ] T056 [P] Update `docs/specs/11_DB_Schema.md`: rename `booking_state_transitions` entry to `state_transitions`, document new `reason` and `trace_id` columns, update `trigger_kind` enum values. Update `CLAUDE.md` coding convention Â§15 to replace `booking_state_transitions` reference with `state_transitions`.

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
  â””â”€â–º Phase 2 (State Classes) â€” all 8 modules parallel
        â””â”€â–º Phase 3 (US1 â€” Transitions + Model Wiring)
              â”œâ”€â–º Phase 4 (US2 â€” Log Wiring)     â”€â”
              â”œâ”€â–º Phase 5 (US3 â€” Action Refactor)  â”œâ”€â–º Phase 7 (US5 â€” Race)
              â””â”€â–º Phase 6 (US4 â€” Admin Override)  â”€â”˜
                                                       â””â”€â–º Phase 8 (Polish)
```

### User Story Dependencies

| Story | Depends on | Can run in parallel with |
|---|---|---|
| US1 (P1) | Phase 1 + Phase 2 complete | â€” |
| US2 (P1) | US1 (needs transitions to exist) | US3 partial |
| US3 (P2) | US1 (needs state classes to exist) | US4 |
| US4 (P2) | US1 (needs state machines) | US3 |
| US5 (P3) | US1 + US3 (needs lockForUpdate in Actions) | â€” |

### Within Phase 3 (US1)

- T017â€“T018 (Service): parallel after T009
- T019, T021 (Booking.php): sequential â€” T019 must complete before T021 (same file)
- T020, T022 (Booking transitions): parallel after T019/T021
- T023â€“T024 (Payment): parallel after T012
- T025â€“T026 (VendorApproval): parallel after T013
- T027â€“T028 (Withdrawal) + T029â€“T030 (Commission): parallel after T014/T015
- T031â€“T032 (Subscription): parallel after T016
- T033â€“T037 (Tests): all parallel after T017â€“T032

---

## Parallel Execution Examples

### Phase 2 (all parallel)

```text
Run simultaneously:
  T009 â€” ServiceState hierarchy (Catalog)
  T010 â€” BookingLifecycleState hierarchy (Booking)
  T011 â€” BookingPaymentState hierarchy (Booking â€” different directory)
  T012 â€” PaymentState hierarchy (Payments)
  T013 â€” VendorApprovalState hierarchy (Identity)
  T014 â€” WithdrawalState hierarchy (Settlement)
  T015 â€” CommissionState hierarchy (Settlement â€” different directory)
  T016 â€” SubscriptionState audit (Subscriptions)
```

### Phase 3 (most tasks parallel after Booking.php is resolved)

```text
Sequential (same file):
  T019 â†’ T021 (both modify Booking.php)

Parallel after T019+T021:
  T020, T022 (Booking transition classes)

All parallel independently:
  T017+T018 (Catalog), T023+T024 (Payments), T025+T026 (Identity),
  T027+T028 (Withdrawal), T029+T030 (Commission), T031+T032 (Subscriptions)
  T033â€“T037 (tests, after all transition classes exist)
```

### Phase 5 (all module refactors parallel)

```text
Run simultaneously:
  T041 â€” Catalog Actions
  T042 â€” Identity Actions
  T043 â€” Booking Actions + Listeners
  T044 â€” Payments Actions/Repositories
  T045 â€” Settlement Actions
  T046 â€” Subscriptions Actions
Then sequential:
  T047 â€” Architecture test (verify all above complete)
```

---

## Implementation Strategy

### MVP (User Stories 1 + 2 only)

1. Phase 1: Setup
2. Phase 2: Foundational (state class hierarchies)
3. Phase 3: US1 â€” invalid transitions blocked
4. Phase 4: US2 â€” every transition logged
5. **VALIDATE**: Run `pest --group=state-machines` â€” all green
6. **STOP** â€” deliver: the system now rejects bad transitions and logs all transitions

### Full Delivery (all 5 stories)

Continue with Phase 5 (unsafe mutation sweep), Phase 6 (admin override), Phase 7 (race conditions), Phase 8 (polish).

---

## Notes

- `[P]` tasks have no dependencies on incomplete tasks in the same phase
- `[US#]` maps to the user story in `spec.md`
- Each Transition class follows the canonical shape in `quickstart.md`
- All transitions use `DB::afterCommit` for domain events â€” never fire events inside the transaction
- `lockForUpdate()` is the caller's responsibility (Action), not the Transition class's
- `ServiceStatus::canTransitionTo()` method on the enum must be removed/deprecated (tracked in T054/T055) once the state machine is authoritative
