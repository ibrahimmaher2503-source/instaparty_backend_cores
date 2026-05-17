---
description: "Task list for Replacement Vendor Guard (Admin Cannot Assign)"
---

# Tasks: Replacement Vendor Guard (Admin Cannot Assign)

**Input**: Design documents in `/specs/032-replacement-vendor-guard/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/policy.md ✓, quickstart.md ✓

**Tests**: Required — spec FR-EXT-021g mandates Pest coverage; Constitution Principle VII (Test-First) requires tests written same-day for booking/auth flows.

**Organization**: One phase per user story. Each story is independently testable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User-story label — `[US1]`, `[US2]`, `[US3]`. Setup/Foundational/Polish phases carry no story label.
- **File paths**: absolute or repo-rooted. All paths use forward slashes.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify pre-conditions and create scaffolding directories. No code yet.

- [x] T001 Verify branch is `032-replacement-vendor-guard` and working tree is clean (`git status`); if not, stash unrelated work before proceeding.
- [x] T002 Confirm prerequisite Pest test files from feature 029 still pass: `./vendor/bin/pest tests/Architecture/AdminCannotAssignReplacementVendorTest.php tests/Architecture/VendorProposalInterventionHasNullProposedVendorTest.php` — abort if red.
- [x] T003 [P] Create the test directory `tests/Feature/Modules/Booking/Policies/` (placeholder `.gitkeep` if empty) — anchor for US1/US2 test files.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Confirm the policy mapping infrastructure exists. No user-story tasks may begin until this phase passes.

**⚠️ CRITICAL**: No user-story work begins until T004–T005 are verified.

- [x] T004 Inspect `app/Providers/AuthServiceProvider.php` for any `Gate::before(...)` callback. If one exists and could grant `super_admin` blanket `true`, document the path in a comment block at the top of `app/Modules/Booking/Domain/Policies/BookingPolicy.php` referencing research.md §R-6. If none exists (verified during survey), proceed.
- [x] T005 Verify `app/Modules/Booking/Providers/BookingServiceProvider.php` registers `BookingPolicy::class` for `Booking::class` (via `Gate::policy(...)` or `protected $policies = [...]` in `AuthServiceProvider`). If absent, add the mapping in `BookingServiceProvider::boot()`.

**Checkpoint**: Foundation ready — proceed to user-story implementation in parallel.

---

## Phase 3: User Story 1 — Named hard refusal exists (Priority: P1) 🎯 MVP

**Goal**: Add the gate-addressable `BookingPolicy::assignReplacementVendor(User, Booking): false` method, with audit-log tripwire. Every role's `$user->can('assignReplacementVendor', $booking)` returns `false` and writes one `audit_logs` row.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/Policies/AssignReplacementVendorPolicyTest.php` — covers super_admin, admin, ops_admin, support_admin, vendor, customer, guest; asserts `false` + one `audit_logs` row per attempt.

### Tests for User Story 1 (write first; expect them to FAIL)

- [x] T006 [P] [US1] Author `tests/Feature/Modules/Booking/Policies/AssignReplacementVendorPolicyTest.php` with: (a) role matrix loop (`super_admin`, `admin`, `ops_admin`, `support_admin`, `vendor`, `customer`); (b) guest case (`Gate::forUser(null)`); (c) `lifecycle_status` invariance loop; (d) `product_type` invariance loop covering `rental`/`sale`/`digital`; (e) assertion that `audit_logs.action = 'booking.replacement_vendor_assignment_blocked'` rowcount equals the number of attempts. Use `RefreshDatabase` trait. Run `./vendor/bin/pest --filter=AssignReplacementVendorPolicy` — confirm all cases FAIL (method does not exist yet).
- [x] T007 [P] [US1] Author `tests/Architecture/BookingPolicyExposesNamedReplacementGuardTest.php` using reflection: assert `BookingPolicy` has a public method `assignReplacementVendor`, that its return type is the literal `false`, and that invoking it returns `false`. Run — confirm FAIL.

### Implementation for User Story 1

- [x] T008 [US1] In `app/Modules/Booking/Domain/Policies/BookingPolicy.php`: add public method `assignReplacementVendor(User $user, Booking $booking): false` exactly as specified in `contracts/policy.md`. Include the PHPDoc citing FR-17/FR-18/BR-4/FR-EXT-021. Add a private helper `private function logBlockedReplacementAttempt(?User $user, Booking $booking): void` that writes the `audit_logs` row per `research.md` §R-4 (using `DB::table('audit_logs')->insert([...])` wrapped in `try/catch (\Throwable)` to never throw from the policy itself). Add the necessary `use` imports for `DB`, `Str`, `Booking`, `User`.
- [x] T009 [US1] Re-run `./vendor/bin/pest --filter=AssignReplacementVendorPolicy` and `--filter=BookingPolicyExposesNamedReplacementGuard` — confirm both now PASS.
- [x] T010 [US1] Run the existing architecture test that bans the symbol elsewhere: `./vendor/bin/pest tests/Architecture/AdminCannotAssignReplacementVendorTest.php` — must remain GREEN. (The new method declaration on `BookingPolicy` is the single allowed mention; if the existing test now fails because it forbids the substring globally, narrow its scope to exclude `app/Modules/Booking/Domain/Policies/BookingPolicy.php` — but only by file path, never by reducing the rule.)

**Checkpoint**: User Story 1 is functional. The named gate refusal works for every role; audit tripwire fires.

---

## Phase 4: User Story 2 — Admin can still suggest alternatives (Priority: P1)

**Goal**: Verify the existing `SuggestAlternativeVendorsAction` continues to work — the guard must not regress feature 029.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/Policies/AdminCanStillSuggestAlternativesTest.php` — confirms admin with the right permission creates a `vendor_proposal` intervention with `proposed_vendor_id = NULL`, plus the existing `booking.suggest_alternatives` audit row, AND no `booking.replacement_vendor_assignment_blocked` row.

### Tests for User Story 2 (write first; expect them to FAIL until US1 lands)

- [x] T011 [P] [US2] Author `tests/Feature/Modules/Booking/Policies/AdminCanStillSuggestAlternativesTest.php`: (a) seed an admin with `booking.intervene.suggest_alternative_vendors` permission; (b) invoke `app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto)`; (c) assert `BookingAdminIntervention` row created with `intervention_type = vendor_proposal`, `proposed_vendor_id = NULL`, `after_state.suggested_vendor_ids` matches input; (d) assert one `audit_logs` row with `action = 'booking.suggest_alternatives'`; (e) assert ZERO `audit_logs` rows with `action = 'booking.replacement_vendor_assignment_blocked'` (the suggestion path must not trip the new tripwire). Use `RefreshDatabase`. Run — confirm fails or passes depending on whether T008 is merged.

### Implementation for User Story 2

- [x] T012 [US2] No new implementation required — this story is a regression guard. Verify the existing `app/Modules/Booking/Application/Actions/SuggestAlternativeVendorsAction.php` is unchanged. Re-run `./vendor/bin/pest tests/Feature/Modules/Booking/AdminIntervention/SuggestAlternativeVendorsActionTest.php` — must remain GREEN.
- [x] T013 [US2] Run `./vendor/bin/pest --filter=AdminCanStillSuggestAlternatives` — must PASS.

**Checkpoint**: User Story 2 is verified. Admin's allowed suggestion path is intact.

---

## Phase 5: User Story 3 — Blocked attempts are audited (Priority: P2)

**Goal**: Verify the audit-log tripwire captures sufficient context: `user_id`, `auditable_id`, `action`, `changes` JSON with `attempted_at`, `role`, `source`, `ip`, `user_agent`. Add a smoke test exercising the HTTP 403 path via a one-off dev-only route.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/Policies/AuditTripwireShapeTest.php` — invokes the gate; reads the latest `audit_logs` row; asserts every expected JSON key exists.

### Tests for User Story 3 (write first; expect them to FAIL)

- [x] T014 [P] [US3] Author `tests/Feature/Modules/Booking/Policies/AuditTripwireShapeTest.php`: (a) authenticate as a super_admin; (b) call `Gate::forUser($user)->allows('assignReplacementVendor', $booking)`; (c) read the most recent `audit_logs` row where `action = 'booking.replacement_vendor_assignment_blocked'`; (d) assert `user_id`, `auditable_type`, `auditable_id` match; (e) decode `changes` JSON and assert keys `attempted_at`, `role`, `source`, `ip`, `user_agent` present; (f) authenticate as guest, repeat, assert `user_id` is `NULL` and `role` is `[]`. Use `RefreshDatabase`. Run — confirm initially fails (no audit row).

### Implementation for User Story 3

- [x] T015 [US3] If T008's private helper does not populate all five `changes` JSON keys (`attempted_at`, `role`, `source`, `ip`, `user_agent`), update `BookingPolicy::logBlockedReplacementAttempt()` to match the shape in `contracts/policy.md`. Re-run `./vendor/bin/pest --filter=AuditTripwireShape` — must PASS.
- [x] T016 [US3] Update `.specify/memory/api-registry.md` to add `booking.replacement_vendor_assignment_blocked` to the audit-action catalogue (per `research.md` §R-7). Append a single line under the audit-actions section.

**Checkpoint**: User Story 3 is verified. Audit-log tripwire captures full context.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Static analysis, formatting, documentation cross-link, and quickstart validation.

- [x] T017 [P] Run `./vendor/bin/pint` — formatter must be clean. Commit if files were reformatted.
- [x] T018 [P] Run `./vendor/bin/phpstan analyse` — must be clean. The `false` literal return type may require PHPStan baseline update only if your `phpstan.neon` level forbids literal returns; if so, **do not** widen the type — adjust the baseline instead. (PHPStan binary not installed in this project — skipped.)
- [x] T019 [P] Run the full Pest suite: `./vendor/bin/pest` — all tests including feature 029's existing suite must remain green.
- [x] T020 [P] Update `.specify/memory/project-index.md` to add a one-line entry under the Booking module noting `BookingPolicy::assignReplacementVendor` as a hard refusal gate (link to `specs/032-replacement-vendor-guard/`).
- [ ] T021 Execute the manual walkthrough in `specs/032-replacement-vendor-guard/quickstart.md` end-to-end on a fresh `migrate:fresh --seed` database. All 6 steps must produce expected outputs.
- [x] T022 Verify exit criteria in `plan.md` (six checkboxes) and tick them off.
- [x] T023 Draft the PR description: cite FR-17, FR-18, BR-4, FR-EXT-021 (and sub-requirements a–g); link to `specs/032-replacement-vendor-guard/spec.md`; include a one-paragraph survey summary from spec.md §Code Path Survey; list the new files touched.

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately.
- **Phase 2 (Foundational)**: Depends on Phase 1. Blocks all user stories.
- **Phase 3 (US1)**: Depends on Phase 2. Required for US3 (audit tests depend on the policy method existing).
- **Phase 4 (US2)**: Depends on Phase 2. Independent of US1 except for the regression assertion that US1's tripwire did not accidentally fire for the suggestion path — therefore US2 should run AFTER US1 to assert non-interference.
- **Phase 5 (US3)**: Depends on US1 (Phase 3) — the policy method must exist for the audit-shape tests to run.
- **Phase 6 (Polish)**: Depends on US1, US2, US3 complete.

### User-story dependencies

- **US1 (P1)**: Foundational only.
- **US2 (P1)**: Foundational + US1 (recommended order — US2 verifies non-regression of US1's tripwire).
- **US3 (P2)**: Foundational + US1 (audit shape requires the helper from US1).

### Within each user story

- Tests written FIRST (they MUST fail before implementation begins).
- Implementation is one file (`BookingPolicy.php`) for US1; US2 has no implementation (regression check only); US3 may refine US1's helper.
- Each story closes with a green test run.

### Parallel opportunities

- T003 (mkdir) is `[P]` — trivially parallel with T001/T002.
- T006 and T007 (`[P] [US1]`) are different test files — author in parallel.
- T011 (`[P] [US2]`) and T014 (`[P] [US3]`) are different test files — author in parallel with US1 tests, even though they will not pass until US1 lands.
- T017, T018, T019, T020 are independent quality-gate commands — run in parallel.

---

## Parallel Example: User Story 1

```powershell
# Author the two US1 test files in parallel (different files, no shared state):
# Terminal A:
code tests/Feature/Modules/Booking/Policies/AssignReplacementVendorPolicyTest.php
# Terminal B:
code tests/Architecture/BookingPolicyExposesNamedReplacementGuardTest.php

# Then run both filters in parallel:
./vendor/bin/pest --filter=AssignReplacementVendorPolicy --filter=BookingPolicyExposesNamedReplacementGuard
```

---

## Parallel Example: Polish phase

```powershell
# All four quality gates run independently:
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest
# (project-index.md edit done in parallel by the dev)
```

---

## Implementation Strategy

### MVP first (User Story 1 only)

1. Complete Phase 1 (Setup) → Phase 2 (Foundational).
2. Complete Phase 3 (US1) — named refusal + audit tripwire.
3. **STOP and VALIDATE**: run quickstart Steps 1–3, confirm `refused` and audit row.
4. If pressed for time and US2/US3 must defer, merge here — feature 029's existing tests still cover the suggestion path and the architecture tests still cover the symbol ban. (See plan.md cut-list.)

### Incremental delivery

1. Setup + Foundational → US1 → demo MVP.
2. Add US2 → demo (non-regression of suggestion path confirmed).
3. Add US3 → demo (audit tripwire shape verified).
4. Polish → ship.

### Single-developer strategy

This is a one-day feature for a single developer. All user stories can be implemented in one PR. The phase split is for review-clarity and rollback granularity, not parallelism.

---

## Notes

- `[P]` tasks touch different files and have no in-phase dependencies.
- `[Story]` label maps to the spec.md user stories — `US1`, `US2`, `US3`.
- The policy method's return type MUST be the literal `false` (PHP 8.3 `false` pseudo-type) — do not soften to `bool` for "future flexibility."
- The audit-log writer MUST swallow exceptions (`try/catch (\Throwable)`); the refusal must not depend on DB availability.
- Do NOT introduce a Shield permission for this guard — explicitly banned by FR-EXT-021d.
- Do NOT add a Filament action, route, or controller. The gate is invoked from existing controllers via `$this->authorize(...)`; no UI affordance is correct.
- Commit per phase, conventional format: `feat(Booking): add hard refusal gate assignReplacementVendor (FR-EXT-021)` for US1; `test(Booking): regression guard for suggestion path` for US2; etc.
