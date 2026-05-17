# Feature Specification: Replacement Vendor Guard (Admin Cannot Assign)

**Feature Branch**: `032-replacement-vendor-guard`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Add a hard system guard that prevents admin-selected replacement vendors. Admin can monitor, facilitate, suggest alternatives, and communicate, but admin must never assign or enforce a replacement vendor on behalf of the customer."

---

## Traceability

- **PRD requirements**: cites **FR-17** (admin may communicate and facilitate, but Phase 1 must not enforce admin-selected replacement vendors) and **FR-18** (customer remains final selector of alternatives) from `docs/specs/01_PRD.md` §FR; **BR-4** (admin can monitor and facilitate but does not choose vendor alternatives on behalf of the customer in Phase 1) from `docs/specs/01_PRD.md` §BR.
- **New local requirements**: introduces **FR-EXT-021** (named, gate-addressable hard refusal) extending the previously defined **FR-EXT-011** (no `AssignReplacementVendor` symbol exists) and **FR-EXT-012** (vendor proposal interventions must store `proposed_vendor_id = NULL`) from feature 029. No PRD backfill needed — guard scope is fully covered by FR-17/FR-18/BR-4.
- **Phase**: Phase 1.6 (Admin booking facilitation hardening) per `docs/specs/09_Phasing_Plan.md`. Slots alongside completed feature **029-admin-booking-intervention** and concurrent **031-vendor-booking-modification**.
- **Schema traceability**: uses existing tables `audit_logs`, `bookings`, `booking_vendors`, `booking_admin_interventions`, `permissions` (Shield) from `docs/specs/11_DB_Schema.md`. **No schema changes.**
- **Package list**: no new packages required. Uses `spatie/laravel-permission` (already locked in `docs/specs/10_Package_List.md`).

---

## Code Path Survey (pre-spec investigation)

Per the task instruction "Search for replacement, assign vendor, swap vendor, alternative vendor, BookingAdminIntervention, and any BookingVendor update actions. Report every existing code path that could violate FR-17/BR-4." Findings as of branch `032-replacement-vendor-guard`:

### A. Existing guards already in place (do NOT regress)

| Guard | Location | What it enforces |
|---|---|---|
| `BookingAdminInterventionPolicy::create()` rejects `VendorProposal` with non-null `proposed_vendor_id` | `app/Modules/Booking/Domain/Policies/BookingAdminInterventionPolicy.php:27-44` | FR-EXT-012. Hard refusal at policy layer. |
| `SuggestAlternativeVendorsAction` forces `proposed_vendor_id = null` and stores only a list of `suggested_vendor_ids` in `after_state` JSON | `app/Modules/Booking/Application/Actions/SuggestAlternativeVendorsAction.php:44` | FR-EXT-012. Suggestion-only write path. |
| Architecture test bans any `AssignReplacementVendor` / `assignReplacementVendor` symbol under Booking and Discovery modules | `tests/Architecture/AdminCannotAssignReplacementVendorTest.php:7-24` | FR-EXT-011. Prevents accidental future re-introduction. |
| Architecture test bans `assign_replacement_vendor` Filament action filename | `tests/Architecture/AdminCannotAssignReplacementVendorTest.php:39-50` | FR-EXT-011 (UI). |
| Architecture test bans `%assign_replacement%` Shield permission | `tests/Architecture/AdminCannotAssignReplacementVendorTest.php:26-37` | FR-EXT-011 (permissions table). |
| Architecture test verifies the policy returns `false` for `VendorProposal` with non-null `proposed_vendor_id` | `tests/Architecture/VendorProposalInterventionHasNullProposedVendorTest.php` | FR-EXT-012. |
| `InterventionType::VendorProposal` is the ONLY enum value related to vendor alternatives; no `vendor_replaced` / `vendor_reassigned` case exists | `app/Modules/Booking/Domain/Enums/InterventionType.php` | FR-17. |

### B. Code paths that touch `booking_vendors` but do NOT violate the rule

| Path | Why it's safe |
|---|---|
| `BookingVendor` writes for `sub_status` lifecycle (`responded`, `timed_out`, etc.) in vendor-decision actions | Only mutates per-vendor sub-status; never changes `vendor_profile_id`. |
| `ForceCancelBookingAction` writes `sub_status` to `cancelled` | Cancellation, not replacement. |
| Booking creation Actions (`SubmitBookingAction`, etc.) insert new `booking_vendors` rows | Customer-initiated, not admin-initiated. |

### C. Paths flagged for review — to harden in this spec

1. **`BookingPolicy` lacks a named hard refusal.** `app/Modules/Booking/Domain/Policies/BookingPolicy.php` currently has only Shield-generated CRUD methods. There is no explicit, gate-addressable method named `assignReplacementVendor` — meaning a future contributor cannot defensively call `Gate::denies('assignReplacementVendor', $booking)` and rely on a named refusal. This spec adds it.
2. **No HTTP route currently exists for replacement assignment** — confirmed by grep over `app/Modules/Booking/Routes/*.php`. If one is ever added (intentionally or in error), there is no centralised audit-log + denial path. This spec mandates the failure handler.
3. **No `Booking::class` policy method on a vendor-replacement intent** — the existing guards are spread across architecture tests and a sibling policy (`BookingAdminInterventionPolicy`). A single named method on `BookingPolicy` is the canonical hook.

**Conclusion of survey**: No active violation of FR-17/BR-4 exists in code today. This spec converts the implicit, distributed guard into a single named, discoverable policy method and adds positive tests asserting the refusal for every admin role.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Named hard refusal exists for replacement assignment (Priority: P1)

A future admin-side feature or a developer accidentally wiring a UI action attempts to authorize `assignReplacementVendor` on a booking. The system MUST return `false` unconditionally — for every role, including super_admin — and the attempt MUST be auditable if it travelled through any HTTP/Action path.

**Why this priority**: This is the entire point of the feature. Without a named, gate-addressable refusal, the rule is enforced only by absence-of-code (FR-EXT-011 architecture test). A named `false` policy method makes the rule discoverable, callable, and testable as a positive contract rather than an "everything you cannot find" negative contract.

**Independent Test**: A Pest test calls `Gate::forUser($superAdmin)->allows('assignReplacementVendor', $booking)` and expects `false`. Repeated for `admin`, `vendor`, `customer`, `guest`. Delivers compliance with FR-17/BR-4 as a single observable assertion.

**Acceptance Scenarios**:

1. **Given** a booking in any `lifecycle_status`, **When** a user holding the `super_admin` role calls the `assignReplacementVendor` gate on that booking, **Then** the gate MUST return `false`.
2. **Given** a booking in any `lifecycle_status`, **When** a user holding the `admin` role (or any of its variants — `ops_admin`, `support_admin`) calls the `assignReplacementVendor` gate, **Then** the gate MUST return `false`.
3. **Given** the codebase at any commit on `main`, **When** a developer searches for the symbol `AssignReplacementVendor` or `assignReplacementVendor` outside of `BookingPolicy::assignReplacementVendor` and architecture tests, **Then** zero hits are found.
4. **Given** the `permissions` table after `php artisan shield:generate --all` and seeders run, **When** querying for any permission name matching `%assign_replacement%`, **Then** zero rows are returned.

---

### User Story 2 — Admin can still suggest alternatives (Priority: P1)

Admin retains the existing ability to propose alternative vendors as a suggestion (record-only, no assignment). The customer remains the sole decider.

**Why this priority**: The guard must NOT regress feature 029's `SuggestAlternativeVendorsAction`. The rule is "admin cannot **assign**", not "admin cannot **suggest**".

**Independent Test**: Admin user invokes `SuggestAlternativeVendorsAction::execute($booking, $dto)` — succeeds, returns a `BookingAdminIntervention` row with `intervention_type = vendor_proposal`, `proposed_vendor_id = NULL`, and `after_state.suggested_vendor_ids = [...]`. Customer then sees these suggestions and selects one through customer-facing flow.

**Acceptance Scenarios**:

1. **Given** an admin with `booking.intervene.suggest_alternative_vendors` permission, **When** they suggest up to N alternative vendors (where N = `config('booking.intervention.suggest_max_candidates')`, default 5), **Then** a single `booking_admin_interventions` row is created with `intervention_type = vendor_proposal`, `proposed_vendor_id = NULL`, suggested vendor IDs stored in `after_state` JSON, and an `audit_logs` row recorded with `action = 'booking.suggest_alternatives'`.
2. **Given** a customer with access to the booking, **When** they view the booking's intervention timeline, **Then** they see the suggested alternatives presented as **suggestions**, with a UI affordance to select one — the system does not auto-apply any suggestion.
3. **Given** a booking with one or more suggested alternatives, **When** the admin's chosen vendor would be auto-assigned (e.g., timer expires, no customer action), **Then** NO assignment happens — the booking remains in its current state pending customer choice (or escalates to `customer_review` / `cancelled` per existing rules in feature 017).

---

### User Story 3 — Blocked attempts are audited (Priority: P2)

If any route, controller, or Action ever invokes the `assignReplacementVendor` gate (because a UI affordance, a webhook, or an internal mutation tried to reach the rule), the denial MUST be written to `audit_logs` with sufficient context for incident review.

**Why this priority**: Defense-in-depth. The architecture tests block static introduction of the symbol; this requirement blocks runtime side-channels. Lower priority than P1 because it is a "should never fire" path — but valuable as a tripwire if a regression slips past the architecture tests.

**Independent Test**: A test route that intentionally invokes `Gate::authorize('assignReplacementVendor', $booking)` produces a 403, AND inserts an `audit_logs` row with `action = 'booking.replacement_vendor_assignment_blocked'`. Architecture tests then re-confirm the tripwire route is dev-only / not exposed in prod routes.

**Acceptance Scenarios**:

1. **Given** any HTTP request path that authorises `assignReplacementVendor`, **When** the gate denies (which it always does), **Then** the system MUST insert an `audit_logs` row with `action = 'booking.replacement_vendor_assignment_blocked'`, `auditable_type = Booking`, `auditable_id = $booking->id`, `user_id` = the attempting user, and `changes` JSON containing the source route name and the request payload (PII-scrubbed).
2. **Given** the `BookingAdminInterventionPolicy::create()` guard already rejects `VendorProposal` interventions with non-null `proposed_vendor_id`, **When** an admin attempts that exact write (e.g., via a forged Filament form), **Then** in addition to the existing policy refusal, an `audit_logs` row with the same `action` is recorded.
3. **Given** an audit reviewer, **When** they query `audit_logs` for `action = 'booking.replacement_vendor_assignment_blocked'`, **Then** they see a complete history of attempts, who attempted them, and from where.

---

### Edge Cases

- **What happens when a vendor self-withdraws and the customer's response timer is short?** No change — the customer (not admin) gets the next-step prompt. Admin may suggest alternatives via the existing intervention flow; admin cannot apply them.
- **What if the original vendor's account is fully deleted (rare, soft-delete only)?** The booking's `booking_vendors.vendor_profile_id` is preserved (FK with `restrictOnDelete()` per migration rules); admin cannot mutate it. Resolution path is customer-driven cancel/re-book.
- **What if an admin clicks a stale UI link to a removed Filament action?** The route returns 404 / 403; the `assignReplacementVendor` audit-log tripwire fires only if the gate was reached (i.e., if a route existed). No-route case is silent.
- **What if a future feature (e.g., AI-driven matchmaking) wants to auto-suggest a single vendor with high confidence?** Out of Phase 1 scope. If approved later, it MUST still create a suggestion intervention with `proposed_vendor_id = NULL` and rely on customer click-through. Any deviation is a spec-amendment conversation.
- **What if the customer is unreachable for a long period and the event date passes?** Existing escalation in feature 017 (`ForceCancelBookingAction`) governs cancellation. Admin still cannot pick a replacement; admin can only cancel.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-021**: System MUST expose a policy method `BookingPolicy::assignReplacementVendor(User $user, Booking $booking): bool` that returns `false` for every user, role, permission combination, and booking state — without exception. It MUST be addressable through the standard authorization gate (`$user->can('assignReplacementVendor', $booking)`, `Gate::forUser($user)->allows('assignReplacementVendor', $booking)`, and `$this->authorize('assignReplacementVendor', $booking)` in controllers).
- **FR-EXT-021a** (continuation of FR-EXT-011): No HTTP route, controller action, Form Request, Action class, Filament action, console command, queued job, listener, or scheduled task may exist whose declared intent is to mutate `booking_vendors.vendor_profile_id` after the booking has been created. The architecture test in `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` MUST continue to pass.
- **FR-EXT-021b** (continuation of FR-EXT-012): The `BookingAdminInterventionPolicy::create()` guard against `VendorProposal` interventions with non-null `proposed_vendor_id` MUST remain in place. The `SuggestAlternativeVendorsAction` MUST continue to write `proposed_vendor_id = NULL` only.
- **FR-EXT-021c**: If any code path reaches the `assignReplacementVendor` gate at runtime (regardless of caller), the system MUST insert an `audit_logs` row with `action = 'booking.replacement_vendor_assignment_blocked'` capturing the attempting user, booking, source (controller / Action class name), and PII-scrubbed payload — and MUST then return / raise a 403 to the caller.
- **FR-EXT-021d**: No Shield permission whose name contains the substrings `assign_replacement`, `replacement_vendor`, `swap_vendor`, `reassign_vendor`, or `force_replace` may exist in the `permissions` table after `php artisan shield:generate --all` and module seeders run.
- **FR-EXT-021e**: Admin-facing Filament Resources for `Booking` and `BookingAdminIntervention` MUST NOT expose any row action, header action, or bulk action whose label or method name implies replacement assignment. The existing "Suggest alternative vendors" action remains permitted and visible.
- **FR-EXT-021f**: The customer-facing API for the booking detail endpoint MUST continue to surface admin-suggested alternatives (from `vendor_proposal` interventions with `proposed_vendor_id = NULL`) as a non-binding suggestion list that requires explicit customer selection to take effect.
- **FR-EXT-021g**: Pest test coverage MUST include (a) super_admin denied, (b) every admin role variant denied, (c) admin allowed to invoke `SuggestAlternativeVendorsAction` and produce a suggestion-only record, (d) customer selects from suggestions and the booking proceeds, (e) audit-log row exists when the gate is reached.

### Non-Functional Requirements

- **NFR-021-A**: The hard refusal MUST be a single named symbol (`BookingPolicy::assignReplacementVendor`) — discoverable via IDE jump-to-symbol, not buried inside a `match` statement.
- **NFR-021-B**: Architecture tests (under `tests/Architecture/`) MUST run as part of the default `pest` invocation, not behind an opt-in group, so accidental regressions break CI.
- **NFR-021-C**: The audit-log writer for blocked attempts MUST be transactional-safe — if invoked inside a request that fails for unrelated reasons, the audit row should not be rolled back. (Standard `audit_logs` append-only semantics already provide this; just confirm via test.)

### Key Entities

- **BookingPolicy** — Policy class at `app/Modules/Booking/Domain/Policies/BookingPolicy.php`. Gains one new method `assignReplacementVendor(User, Booking): false`. No state.
- **audit_logs** — Existing append-only polymorphic table. Gains a new `action` value: `booking.replacement_vendor_assignment_blocked`. Schema unchanged.
- **BookingAdminIntervention** — Existing model with `intervention_type = vendor_proposal`. Unchanged; its `proposed_vendor_id` MUST stay `NULL` per FR-EXT-012.
- **No new tables, no new columns, no new ENUM values.**

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-021-01**: 100% of Pest tests covering `assignReplacementVendor` (super_admin, admin, ops_admin, support_admin) assert `false` and pass on `main`.
- **SC-021-02**: A static `grep` for `AssignReplacementVendor` / `assignReplacementVendor` over `app/` returns exactly one hit — the `BookingPolicy::assignReplacementVendor` method declaration — plus existing architecture-test references under `tests/`. Zero hits in production controllers, actions, routes, Filament resources, or migrations.
- **SC-021-03**: The `permissions` table after a fresh `php artisan migrate --seed && php artisan shield:generate --all` contains zero rows whose name matches `%assign_replacement%`, `%replacement_vendor%`, `%swap_vendor%`, `%reassign_vendor%`, or `%force_replace%`.
- **SC-021-04**: The existing `SuggestAlternativeVendorsAction` Pest test (`tests/Feature/Modules/Booking/AdminIntervention/SuggestAlternativeVendorsActionTest.php`) continues to pass — no regression in admin's allowed suggestion path.
- **SC-021-05**: A focused security review (manual or via `/security-review`) of branch `032-replacement-vendor-guard` reports zero unintended write paths to `booking_vendors.vendor_profile_id` introduced after Phase 1 baseline.
- **SC-021-06**: When the policy method is invoked through any future HTTP request (intentionally injected for a smoke test), the response is HTTP 403 within < 50 ms AND an `audit_logs` row with `action = 'booking.replacement_vendor_assignment_blocked'` is persisted before the response is returned.

---

## Assumptions

- The existing Phase 1 guards from feature 029 (`BookingAdminInterventionPolicy::create()` refusal, `SuggestAlternativeVendorsAction` with `proposed_vendor_id = NULL`, both architecture tests) remain in place and are NOT in scope to remove or rewrite — this spec extends them with a named policy method.
- No HTTP route or controller currently allows a replacement assignment. If one is discovered during implementation (e.g., a leftover Filament `Action::make('reassign')` in a module not yet reviewed), it is to be removed in the same PR and called out in the PR description.
- Admin roles in scope: `super_admin`, `admin`, plus any variants registered via `spatie/laravel-permission` (`ops_admin`, `support_admin`, etc.). The refusal applies uniformly — there is no role allowed to bypass.
- "Replacement vendor" means changing `booking_vendors.vendor_profile_id` of an existing `booking_vendors` row, or inserting a new `booking_vendors` row in lieu of an existing one, after the customer has committed to a vendor. It does NOT mean appending a new vendor to a multi-vendor booking that the customer themselves initiated.
- The customer-facing alternative-selection UI (Phase 1.x for customer journey) is the consuming surface for suggested alternatives. Backend already exposes the data via existing booking detail / intervention timeline endpoints.
- The audit-log tripwire (FR-EXT-021c) is a defence-in-depth measure. In practice it should never fire in production. Its primary value is to break CI / page on-call if a regression introduces a callable path.

---

## Out of Scope

- Automated vendor matchmaking / AI suggestions — Phase 2.
- Admin-initiated vendor changes WITH explicit customer e-sign confirmation — Phase 2 dispute resolution module.
- Changing the meaning of "replacement" for multi-vendor bookings where customer adds/removes vendors themselves — that is `BookingModification`, governed by feature 031.
- Soft-deleted vendor recovery / re-attachment — handled by Identity module.

---

## Dependencies

- **Feature 029 (admin-booking-intervention)** — provides `BookingAdminInterventionPolicy`, `SuggestAlternativeVendorsAction`, `InterventionType::VendorProposal`, and the two existing architecture tests. Status: implemented.
- **Feature 017 (admin-booking-override)** — provides `ForceCancelBookingAction` and the vendor-timeout escalation path that this spec must not regress. Status: implemented.
- **Feature 031 (vendor-booking-modification)** — concurrent; touches `booking_modifications` flow. This spec's guard MUST NOT block customer-driven modifications. Status: in progress.
- **Constitution (`CLAUDE.md`) §15** — `audit_logs` is append-only. The tripwire writer must respect that contract.

---

## Implementation Hints *(non-binding, for `/speckit.plan`)*

The spec is intentionally implementation-light. The plan phase should consider:

- Add `BookingPolicy::assignReplacementVendor(User, Booking): bool { return false; }` — three lines, plus a `/** @return false */` PHPDoc.
- Register the policy mapping for `Booking::class` in `AuthServiceProvider` (or the module ServiceProvider) if not already.
- Create a small `BlockedReplacementAttemptAuditor` listener or in-policy helper that writes to `audit_logs` whenever the method is invoked (using `DB::table('audit_logs')->insert(...)` per existing pattern in `SuggestAlternativeVendorsAction`).
- Pest tests live under `tests/Feature/Modules/Booking/Policies/AssignReplacementVendorPolicyTest.php`.
- A separate architecture test extension can verify the method exists and returns `false` via reflection — complementing the existing "no symbol" architecture test.
- No migration, no Shield re-run, no new package.
