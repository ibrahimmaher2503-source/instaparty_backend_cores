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

# Feature Specification: Lifecycle State Machine Architecture

**Feature Branch**: `026-lifecycle-state-machines`
**Created**: 2026-05-15
**Status**: Draft
**Phase**: Phase 7.1 — Hardening: Lifecycle Integrity (new phase extension) ⚠️ PHASE BACKFILL NEEDED: add Phase 7.1 to `docs/specs/09_Phasing_Plan.md`
**PRD Coverage**: FR-EXT-026-001 through FR-EXT-026-015 (cross-cutting hardening — not mapped to a single PRD FR)

---

## Context

The InstaParty backend currently tracks lifecycle status through PHP enums (`ServiceStatus`, `LifecycleStatus`, `PaymentStatus`, `ApprovalStatus`, `WithdrawalStatus`, `CommissionStatus`, `SubscriptionStatus`). Two status fields already use `spatie/laravel-model-states` (booking `item_status` per product type, and `VendorSubscription.status`), but the remaining eight status fields are mutated directly via `->update(['status' => ...])` patterns, bypassing business rules and leaving gaps in the audit trail.

A codebase search found 44 occurrences of direct status mutation across 34 files. These mutations:
- Cannot enforce which transitions are valid
- Do not capture who triggered the change, why, or as part of which request trace
- Produce no transition history queryable by admins
- Are untestable as "invalid transition" scenarios — the system silently accepts them

This spec formalises migrating those eight fields to `spatie/laravel-model-states` with full transition guards, side-effect hooks, permission checks, and unified transition history logging.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Invalid state transitions are rejected before they reach the database (Priority: P1)

A developer (or an erroneous automation job) attempts to move a `Service` from `archived` directly to `published`, skipping the required `pending_review` step. The system must refuse this at the domain layer, throw a typed exception, and never persist the invalid state.

**Why this priority**: This is the foundational safety guarantee. Every other story depends on the system reliably refusing bad transitions. Without it, financial records, vendor access, and booking fulfillment can be corrupted silently.

**Independent Test**: Seed a `Service` with `status = archived`. Call any code path that tries to set `status = published` directly. Assert a `TransitionNotAllowedException` is thrown, the `Service` row in the database still shows `status = archived`, and no `state_transitions` row is written.

**Acceptance Scenarios**:

1. **Given** a `Service` with `status = archived`, **When** any action or repository method attempts to set `status = published` bypassing `pending_review`, **Then** the system throws `TransitionNotAllowedException` and the DB row is unchanged.
2. **Given** a `Booking` with `lifecycle_status = completed`, **When** any code path attempts to set `lifecycle_status = active`, **Then** the system throws `TransitionNotAllowedException` and the DB row is unchanged.
3. **Given** a `Payment` with `status = captured`, **When** any code path attempts to set `status = pending`, **Then** the system throws `TransitionNotAllowedException` and the DB row is unchanged.
4. **Given** a `VendorProfile` with `approval_status = suspended`, **When** a non-admin actor attempts to set `approval_status = approved`, **Then** the system throws `TransitionNotAllowedException` (permission guard failure) and the DB row is unchanged.
5. **Given** a `Withdrawal` with `status = paid`, **When** any code path attempts to set `status = pending`, **Then** the system throws `TransitionNotAllowedException` and the DB row is unchanged.

---

### User Story 2 — Every state change is recorded with full context in the transition log (Priority: P1)

An admin investigates a disputed booking whose status shows `cancelled`. They open the transition history and immediately see: when the cancellation happened, which system actor triggered it, what the reason was, and the request trace ID from the originating API call.

**Why this priority**: The transition log is the audit backbone. Finance, compliance, dispute resolution, and on-call debugging all depend on it. A transition that leaves no trace is operationally invisible.

**Independent Test**: Trigger a `lifecycle_status` transition from `submitted` → `vendor_review` on a Booking. Immediately query `state_transitions` for that booking. Assert one row exists with correct `from_state`, `to_state`, non-null `actor_id` (or `trigger_kind = system`), non-null `trace_id` matching the request, and the `reason` field containing a non-empty string.

**Acceptance Scenarios**:

1. **Given** a `Booking` transitions from `submitted` to `vendor_review`, **When** the transition completes, **Then** a `state_transitions` row is inserted with `transitionable_type = Booking`, `from_state = submitted`, `to_state = vendor_review`, non-null `triggered_by`, `trigger_kind = system|admin|customer|vendor`, and a `trace_id` matching the current request.
2. **Given** an admin approves a `VendorProfile`, **When** the transition from `pending` → `approved` commits, **Then** a `state_transitions` row captures the admin's user ID, `trigger_kind = admin`, and a `reason` string ("Manual approval by admin").
3. **Given** a `Payment` transitions from `authorized` → `captured` via a webhook, **When** the transition commits, **Then** a `state_transitions` row captures `trigger_kind = system`, `actor_id = null`, and the Paymob webhook reference in `metadata`.
4. **Given** any state transition, **When** the transition is written to the log, **Then** the `state_transitions` table row is append-only — no UPDATE or DELETE is ever issued on it.
5. **Given** a transition fails (exception thrown), **When** the transaction rolls back, **Then** no partial `state_transitions` row is left in the database.

---

### User Story 3 — All unsafe direct status mutations are replaced in the codebase (Priority: P2)

A developer reviewing a pull request can run a static analysis rule and confirm zero occurrences of `->update(['status'])`, `->forceFill(['status'])`, or direct property assignment (`$model->status = ...`) for any of the eight protected status columns outside of migration files and seeders.

**Why this priority**: Removing unsafe mutations is the enforcement mechanism. State machine classes are only effective if there is no bypass route. This story is P2 (not P1) because the invalid-transition rejection (Story 1) guards the DB even if some call sites are not yet migrated; Story 3 makes the architecture complete.

**Independent Test**: Run `php artisan code:audit-status-mutations` (new artisan command, or equivalent PHPStan/Pest architecture test). Assert zero violations for the eight protected fields in production code paths.

**Acceptance Scenarios**:

1. **Given** the codebase refactor is complete, **When** a static analysis test scans for `->update(['approval_status'` or `->update(['lifecycle_status'` (and the other 6 fields), **Then** zero matches are found outside of `Database/Migrations/` and test factories.
2. **Given** a developer accidentally writes `$booking->lifecycle_status = LifecycleStatus::Cancelled`, **When** PHPStan runs, **Then** a custom rule flags the assignment as a violation.
3. **Given** a new Action is added in the future that calls `$service->update(['status' => 'published'])`, **When** the Pest architecture test suite runs, **Then** the test fails, blocking the PR.

---

### User Story 4 — Admin can override a lifecycle state with a mandatory reason (Priority: P2)

An admin needs to force-unlock a `Booking` stuck in `vendor_review` because the vendor is unresponsive. The admin selects a target state, provides a free-text reason, and confirms. The system executes the override via a privileged transition, logs it as `trigger_kind = admin_override`, and audits the admin's identity.

**Why this priority**: Normal transitions follow guards; admin overrides bypass some guards but must never be silent. Privileged transitions are the safety valve and the most audit-sensitive events in the system.

**Independent Test**: Log in as an admin. Trigger an admin-override transition on a Booking stuck in `vendor_review` → `cancelled`. Assert the transition succeeds, the `state_transitions` row has `trigger_kind = admin_override`, `triggered_by` = admin's user ID, a non-empty `reason` string, and an entry in `audit_logs`.

**Acceptance Scenarios**:

1. **Given** an admin is authenticated with the `manage_bookings` permission, **When** they invoke an override transition for any booking state, **Then** the system accepts the transition regardless of the normal guard rules.
2. **Given** an admin invokes an override transition, **When** the override is recorded, **Then** `trigger_kind = admin_override` and the `reason` field is non-empty (system must enforce a reason string for all admin overrides).
3. **Given** a non-admin user attempts to invoke an admin-override transition, **When** the system evaluates permissions, **Then** the transition is rejected with a `403 Forbidden` response.
4. **Given** an admin override transition commits, **When** the transition log is inspected, **Then** both `state_transitions` and `audit_logs` contain matching records for the same event (dual-write pattern).

---

### User Story 5 — Race conditions cannot produce duplicate or split-brain state transitions (Priority: P3)

Two concurrent API requests both attempt to transition the same `Booking` from `submitted` → `vendor_review` at the same millisecond. Only one succeeds. The other receives an error. No orphaned `state_transitions` rows exist.

**Why this priority**: Race conditions on state fields corrupt financial and fulfillment state. P3 because the likelihood is low in normal operations; but the consequences (double-payment, double-dispatch) are severe enough to require explicit protection.

**Independent Test**: Use Pest's `parallel()` or raw concurrent HTTP calls to submit two simultaneous `submitted → vendor_review` transitions for the same Booking. Assert exactly one succeeds (200/201), the other receives a conflict error (409 or 422), the Booking's `lifecycle_status` shows `vendor_review` exactly once in the DB, and `state_transitions` has exactly one row for this transition pair.

**Acceptance Scenarios**:

1. **Given** two concurrent requests both try to transition the same Booking from `submitted` → `vendor_review`, **When** the system processes them under a pessimistic lock, **Then** exactly one succeeds and the other receives a concurrency error.
2. **Given** the winning transition commits, **When** the losing transition is inspected, **Then** it received an error before writing any state change to the database.
3. **Given** a transition Action wraps its mutation in `DB::transaction` with a row-level lock, **When** a concurrent transaction attempts to lock the same row, **Then** it waits and then fails gracefully rather than producing a split-brain state.

---

### Edge Cases

- What happens when a Transition class fails after the state column has been written but before the `state_transitions` row is written? Both writes must occur within the same `DB::transaction` — if the log insert fails, the state column write rolls back too.
- What happens when the `state_transitions` table row would exceed max metadata payload? Metadata must be capped at 8 KB JSON before insert; overflow is logged to `audit_logs` but never silently truncated.
- What happens when an admin override is requested without providing a `reason`? The system must reject the override with a validation error ("Reason is required for admin overrides") — it must not default to an empty string.
- What happens when the same transition is triggered twice (idempotency)? Idempotency keys are checked before the transition executes; a duplicate key returns the original result without re-running transition side effects.
- What happens when a `booking_item.item_status` state (already using spatie) needs its transition logged to `state_transitions`? These states must be brought into scope — their transitions must also write to `state_transitions` with the correct `transitionable_type`.
- What happens when `Service.status` has `canTransitionTo()` defined on the enum — does it conflict with the new state machine? The `canTransitionTo()` method on the `ServiceStatus` enum must be removed or deprecated once the state machine is the authority.

---

## Requirements *(mandatory)*

### Functional Requirements

**State machine adoption (per field):**

- **FR-EXT-026-001**: System MUST implement `ServiceState` (and concrete states: `DraftState`, `PendingReviewState`, `ChangesRequestedState`, `PublishedState`, `RejectedState`, `ArchivedState`) under `app/Modules/Catalog/Domain/States/ServiceStatus/`. All 6 states must extend `ServiceState extends \Spatie\ModelStates\State`. The `Service` model's `status` column must be cast to `ServiceState`.
- **FR-EXT-026-002**: System MUST implement `BookingLifecycleState` (8 concrete states: `DraftState`, `SubmittedState`, `VendorReviewState`, `CustomerReviewState`, `ConfirmedState`, `ActiveState`, `CompletedState`, `CancelledState`) under `app/Modules/Booking/Domain/States/BookingLifecycleStatus/`.
- **FR-EXT-026-003**: System MUST implement `BookingPaymentState` (6 concrete states: `UnpaidState`, `PartialState`, `PaidState`, `RefundPendingState`, `PartiallyRefundedState`, `RefundedState`) under `app/Modules/Booking/Domain/States/BookingPaymentStatus/`.
- **FR-EXT-026-004**: System MUST implement `PaymentState` (8 concrete states: `PendingState`, `AuthorizedState`, `CapturedState`, `FailedState`, `RefundedState`, `PartiallyRefundedState`, `VoidedState`, `AbandonedState`) under `app/Modules/Payments/Domain/States/PaymentStatus/`.
- **FR-EXT-026-005**: System MUST implement `VendorApprovalState` (5 concrete states: `PendingState`, `ApprovedState`, `RejectedState`, `SuspendedState`, `ChangesRequestedState`) under `app/Modules/Identity/Domain/States/VendorApprovalStatus/`.
- **FR-EXT-026-006**: System MUST implement `WithdrawalState` (4 concrete states: `PendingState`, `ApprovedState`, `PaidState`, `RejectedState`) under `app/Modules/Settlement/Domain/States/WithdrawalStatus/`.
- **FR-EXT-026-007**: System MUST implement `CommissionState` (3 concrete states: `CalculatedState`, `PartiallyReversedState`, `ReversedState`) under `app/Modules/Settlement/Domain/States/CommissionStatus/`.
- **FR-EXT-026-008**: The existing `SubscriptionState` under `app/Modules/Subscriptions/Domain/States/` MUST be audited and brought into full compliance with this spec (explicit transition classes, unified log integration) — no new state classes needed, just transition classes and guards.

**Transition enforcement:**

- **FR-EXT-026-009**: Every State class hierarchy MUST define allowed transitions exclusively in `StateConfig::config()`. Any transition not declared in `config()` MUST throw `\Spatie\ModelStates\Exceptions\TransitionNotAllowedException`.
- **FR-EXT-026-010**: Every transition that requires an actor check (e.g., only admins can approve a vendor profile) MUST define a `canTransition(Model $model): bool` guard method in the Transition class. The guard MUST check `auth()->user()->can(...)` or receive the actor as a constructor parameter.
- **FR-EXT-026-011**: Every transition that has side effects (send notification, fire domain event, release inventory, update related records) MUST implement those side effects inside the Transition class `handle()` method — NOT in the calling Action. The Action is responsible only for resolving the Transition class and calling `$model->transitionTo(State::class)` or creating a `Transition` object.

**Transition history logging:**

- **FR-EXT-026-012**: The `booking_state_transitions` table MUST be extended with two new columns: `reason TEXT NULL` (human-readable explanation, mandatory for `admin_override` transitions) and `trace_id CHAR(36) NULL` (UUID/ULID from the originating HTTP request or queue job). The existing `context JSON` column continues to hold structured metadata. The table is renamed to `state_transitions` to reflect its polymorphic scope across all modules.
- **FR-EXT-026-013**: Every successful state transition — across ALL eight state machines plus the existing `item_status` and `SubscriptionState` machines — MUST write one row to `state_transitions` within the same DB transaction as the model update.
- **FR-EXT-026-014**: The `state_transitions` row MUST be written by a shared `RecordStateTransitionListener` or equivalent Transition base class hook, NOT duplicated in each individual Transition class.

**Unsafe mutation elimination:**

- **FR-EXT-026-015**: Every occurrence of `->update(['<protected_field>' => ...])`, `->forceFill(['<protected_field>' => ...])`, and `$model-><protected_field> = ...` in production code (excluding migrations, factories, and seeders) MUST be replaced by the corresponding `$model->transitionTo(StateClass::class)` call or a named Transition class instantiation.
- **FR-EXT-026-016**: A Pest architecture test `tests/Architecture/NoDirectStatusMutationTest.php` MUST assert zero matches for direct mutation of the eight protected fields in all `app/Modules/` PHP files.

**Admin override:**

- **FR-EXT-026-017**: Each of the eight state machines MUST support a dedicated admin-override Transition class (e.g., `AdminOverrideServiceStatusTransition`, `AdminOverrideBookingLifecycleTransition`). These transitions skip normal guards but require: (a) authenticated user has `admin` role, (b) `reason` parameter is non-empty.

**Testing:**

- **FR-EXT-026-018**: For each of the eight state machines, the test suite MUST include: (a) valid transition tests, (b) invalid transition rejection tests, (c) admin override success + missing-reason rejection tests, (d) race condition tests using `DB::transaction` with concurrent calls, (e) transition log completeness assertion.

### Key Entities

- **ServiceState** (`services.status`): 6 states, 10 allowed transitions (per `ServiceStatus::canTransitionTo()` mapping, which is the source of truth for allowed transitions during migration).
- **BookingLifecycleState** (`bookings.lifecycle_status`): 8 states, transitions follow the booking negotiation loop in `docs/specs/06_Customer_Journey.md`.
- **BookingPaymentState** (`bookings.payment_status`): 6 states, driven by Paymob webhook events (listener-only transitions, never direct actor calls).
- **PaymentState** (`payments.status`): 8 states, driven by Paymob gateway adapter responses and webhook handler.
- **VendorApprovalState** (`vendor_profiles.approval_status`): 5 states, admin-only transitions except `pending → changes_requested` (system-triggered after document review).
- **WithdrawalState** (`withdrawals.status`): 4 states, admin-approved → finance pays → `paid`.
- **CommissionState** (`commissions.status`): 3 states, only transitions via refund events.
- **SubscriptionState** (`vendor_subscriptions.status`): 5 states — already has state classes; needs transition classes and log integration.
- **state_transitions** (renamed from `booking_state_transitions`): append-only polymorphic log — `transitionable_type`, `transitionable_id`, `from_state`, `to_state`, `triggered_by`, `trigger_kind`, `reason`, `trace_id`, `context`, `created_at`. ⚠️ RENAME + COLUMN ADDITION: existing table `booking_state_transitions` migrated to `state_transitions` with two new columns.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero occurrences of `->update(['<protected_status_field>'])`, `->forceFill(['<protected_status_field>'])`, or direct property assignment on the eight protected fields found by the Pest architecture test — verified before phase completion.
- **SC-002**: 100% of valid transitions across all eight state machines produce a corresponding `state_transitions` row within the same DB transaction — verified by Pest integration tests seeding the DB and asserting the log row count.
- **SC-003**: 100% of invalid transitions (transitions not declared in `StateConfig::config()`) throw `TransitionNotAllowedException` and leave the DB row unchanged — verified by Pest tests for every invalid transition pair per machine.
- **SC-004**: Admin override transitions require a non-empty `reason` and record `trigger_kind = admin_override` — verified by Pest tests attempting overrides with and without reasons.
- **SC-005**: Race condition tests (concurrent duplicate transition attempts) result in exactly one success and one conflict error for each state machine — verified by Pest parallel/concurrent tests.
- **SC-006**: All eight state machines have test coverage ≥ 80% on Transition classes (Pest coverage report).
- **SC-007**: The `state_transitions` table contains `trace_id` values matching the originating request ID for all API-triggered transitions — verified by integration tests asserting `trace_id` presence.
- **SC-008**: The `ServiceStatus::canTransitionTo()` method on the enum is removed (or marked deprecated with zero callers) after the state machine takes ownership — verified by architecture test asserting the method has no callers outside its own class.

---

## Assumptions

- `spatie/laravel-model-states` is already installed and in use (confirmed: `SubscriptionState`, `RentalItemStatus`, `SaleItemStatus`, `DigitalItemStatus` all use it).
- Renaming `booking_state_transitions` → `state_transitions` is a non-breaking change for production because no external API exposes this table name; only internal code queries it. All existing callers will be updated as part of this spec.
- The `trace_id` for HTTP requests will be sourced from a `X-Trace-Id` request header (set by the API gateway or generated by the `RequestTraceMiddleware` if absent). For queue jobs, `trace_id` will be the job UUID.
- Admin override transitions do not require a separate DB table — they are modelled as special Transition classes with an elevated permission guard and a mandatory `reason` parameter.
- The `ServiceStatus::canTransitionTo()` method on the enum encodes the correct allowed transitions; it will serve as the source of truth when defining `ServiceState::config()` allowed transitions, then be removed.
- The existing `booking_state_transitions` table rename to `state_transitions` is done via a new migration (`ALTER TABLE ... RENAME`) plus column additions — NOT by dropping and recreating (preserves historical rows).
- `Booking.fulfillment_status` (using `FulfillmentStatus` enum) is **out of scope** for this spec phase — it is superseded by the per-item `item_status` state machine (already using spatie), which is the true fulfillment state machine. `fulfillment_status` on `bookings` is a computed/aggregate field, not a primary state machine target. This assumption should be confirmed before planning.
- Per-item `item_status` states (already using `spatie/laravel-model-states`) need only to be integrated into the unified `state_transitions` log — no new state classes required for them.
- No new database tables are required beyond extending `booking_state_transitions` → `state_transitions`. No new Filament resources are required.
- Backwards compatibility of the `ServiceStatus` enum cases (used by Filament filters, API Resources) is preserved by mapping enum case string values to state class names via `$state` attribute mapping in `spatie/laravel-model-states` — no DB column value changes.

---

## Schema Traceability

| Table | Status | Change in this spec |
|---|---|---|
| `services` | Existing | Cast `status` column to `ServiceState` — no column value change |
| `bookings` | Existing | Cast `lifecycle_status` to `BookingLifecycleState`, `payment_status` to `BookingPaymentState` — no column value change |
| `payments` | Existing | Cast `status` to `PaymentState` — no column value change |
| `vendor_profiles` | Existing | Cast `approval_status` to `VendorApprovalState` — no column value change |
| `withdrawals` | Existing | Cast `status` to `WithdrawalState` — no column value change |
| `commissions` | Existing | Cast `status` to `CommissionState` — no column value change |
| `vendor_subscriptions` | Existing | Cast `status` to `SubscriptionState` (already done) — add transition classes and log integration |
| `booking_state_transitions` | Existing → RENAME | Renamed to `state_transitions`; columns `reason TEXT NULL` and `trace_id CHAR(36) NULL` added |

⚠️ NEW MIGRATION REQUIRED: `YYYYMMDD_000001_rename_booking_state_transitions_and_add_columns.php` — not yet in `docs/specs/11_DB_Schema.md`.

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I — Modular monolith | PASS | State classes placed in owning module's `Domain/States/` |
| II — Three product types with `match($enum)` | PASS | Item-status state machines remain per-type; cross-type states use consistent naming |
| III — Money discipline | N/A | No money columns modified |
| IV — Bilingual EN+AR | N/A | No user-visible translatable strings introduced |
| V — Append-only `state_transitions` | PASS | Transition log is append-only; no UPDATE/DELETE permitted |
| VI — Spec-driven development | PASS | This spec must be accepted before any migration is written |
| VII — Test-first for critical paths | PASS | All state machines are critical paths; 80%+ coverage required |
| VIII — Idempotency for state-changing endpoints | PASS | Admin override and transition endpoints require idempotency key |
| IX — Domain events fire `DB::afterCommit` | PASS | Side effects inside Transition `handle()` fire after commit via listener |
| X — Vendor approval two-step gate | PASS | `VendorApprovalState` preserves the two-step gate (profile approval before type approval) |
| XI — Document storage | N/A | No media/storage changes |
