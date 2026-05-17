# Implementation Plan: Replacement Vendor Guard (Admin Cannot Assign)

**Branch**: `032-replacement-vendor-guard` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/032-replacement-vendor-guard/spec.md`

---

## Summary

Add a single, named, gate-addressable refusal — `BookingPolicy::assignReplacementVendor(User, Booking): false` — that converts the existing implicit/distributed guard (architecture tests in feature 029 + sibling policy refusal) into one discoverable contract. Wire a defence-in-depth audit-log tripwire that fires `audit_logs.action = 'booking.replacement_vendor_assignment_blocked'` whenever the gate is reached at runtime. No schema, no UI, no migration, no new packages — pure hardening.

**Technical approach:** add the policy method, register it in `BookingServiceProvider`, attach a thin in-policy auditor, add Pest tests for all admin role variants, and extend the existing architecture suite with a reflection-based assertion that the method exists and returns `false`.

---

## Technical Context

**Language/Version**: PHP 8.3+, Laravel 12
**Primary Dependencies**: `spatie/laravel-permission` (Shield), `Illuminate\Auth\Access\Gate` — both already locked in `docs/specs/10_Package_List.md`
**Storage**: MySQL 8 — existing `audit_logs` (append-only, append-only per Constitution §V), no schema change
**Testing**: Pest (Feature + Architecture groups)
**Target Platform**: Laravel backend (modular monolith, `app/Modules/Booking/`)
**Project Type**: Web service (backend-only — admin Filament + API)
**Performance Goals**: Gate evaluation < 5 ms; audit-log insert < 50 ms p95 (SC-021-06)
**Constraints**: Must not regress feature 029 (admin can still suggest); audit-log writer must respect append-only semantics; refusal applies to every role with no bypass
**Scale/Scope**: One policy method, one audit-log action key, ~5 Pest test files, no migrations

---

## Constitution Check

*GATE: must pass before Phase 0. Re-check after Phase 1.*

| Principle | Applies? | How this plan satisfies it |
|---|---|---|
| I. Modular Monolith | Yes | All code lives under `app/Modules/Booking/Domain/Policies/` and `app/Modules/Booking/Application/`. No cross-module imports introduced. |
| II. Three Product Types | No | Refusal is type-agnostic — applies identically to rental / sale / digital bookings. Pest test still asserts the refusal for at least one booking of each `product_type` to make the type-agnosticism explicit. |
| III. Money Discipline | No | No money handled. |
| IV. Bilingual EN+AR | Partial | No user-facing strings shipped (audit-log `changes` JSON is operator-facing). If a 403 message ever surfaces to a user via the existing error envelope, it uses the standard `errors.unauthorised` translation key already present in both locales. |
| V. Append-Only Tables | Yes | Audit-log row inserted via `DB::table('audit_logs')->insert(...)` (no updates, no deletes) — matches `SuggestAlternativeVendorsAction` pattern. |
| VI. Spec-Driven Development — ADR | No | No new module. Reuses existing `app/Modules/Booking/`. No ADR needed per Constitution §VI ("Every new module … requires an ADR"). The guard is a hardening within an existing module. |
| VII. Test-First | Yes | Pest tests added in the same PR; cover super_admin / admin / variants / customer / guest, plus a positive test that `SuggestAlternativeVendorsAction` still works. Architecture test extended. |
| VIII. Idempotency | No | No state-changing endpoint introduced. The audit-log tripwire is fire-and-forget; duplicates are acceptable (they document repeated attempts). |
| IX. Domain Events `DB::afterCommit` | N/A | No domain events introduced. |
| X. Vendor Approval — Two-Step | No | Unrelated. |
| XI. Document Storage | No | Unrelated. |
| **Forbidden Phase 1 features** | N/A | None implicated. |
| **Phase alignment** | Yes | Phase 1.6 (Admin booking facilitation hardening) per `09_Phasing_Plan.md`. Slots alongside feature 029. |
| **Source-of-truth hierarchy** | Yes | Cites FR-17/FR-18/BR-4 from PRD §1; reuses existing tables from Schema §V. No conflict with higher-priority specs. |

**Gate result**: ✅ PASS. No violations. Complexity Tracking section omitted.

---

## Project Structure

### Documentation (this feature)

```text
specs/032-replacement-vendor-guard/
├── plan.md                              # This file
├── spec.md                              # Feature spec
├── research.md                          # Phase 0 output
├── data-model.md                        # Phase 1 output (degenerate — no new entities)
├── quickstart.md                        # Phase 1 output (manual-test walkthrough)
├── contracts/
│   └── policy.md                        # Policy contract (gate signature, return values, role matrix)
└── checklists/
    └── requirements.md                  # Created by /speckit.specify
```

### Source Code (repository root)

```text
app/Modules/Booking/
├── Domain/
│   └── Policies/
│       └── BookingPolicy.php            # MODIFIED — add assignReplacementVendor() + helper
├── Application/
│   └── Listeners/                       # (no changes; in-policy audit writer is simpler)
└── Providers/
    └── BookingServiceProvider.php       # MODIFIED — register BookingPolicy::class for Booking::class
                                         # if not already mapped; register the Gate ability alias.

tests/
├── Feature/
│   └── Modules/
│       └── Booking/
│           └── Policies/
│               ├── AssignReplacementVendorPolicyTest.php       # NEW — role matrix + audit assertions
│               └── AdminCanStillSuggestAlternativesTest.php    # NEW — regression guard for feature 029
└── Architecture/
    ├── AdminCannotAssignReplacementVendorTest.php              # EXISTING — keep green
    ├── VendorProposalInterventionHasNullProposedVendorTest.php # EXISTING — keep green
    └── BookingPolicyExposesNamedReplacementGuardTest.php       # NEW — reflection-based positive assertion
```

**Structure Decision**: Single Laravel module (Booking). All code stays under `app/Modules/Booking/`. No new module, no new directory tier, no new database migration. Tests follow the existing `tests/Feature/Modules/{Module}/{Topic}/` and `tests/Architecture/` conventions already in use by feature 029.

---

## Phase 0 — Research

See [research.md](./research.md). Summary of decisions:

| Question | Decision | Source |
|---|---|---|
| Should the refusal be a Policy method or a Gate closure? | **Policy method on `BookingPolicy`**. | Closures are not discoverable via IDE jump-to-symbol; Policy is the canonical Laravel pattern, and the rest of the Booking module uses policies. |
| Where should the audit-log writer live? | **Inside the policy method itself**, mirroring the `SuggestAlternativeVendorsAction` pattern. | Defence-in-depth tripwire — must fire even if a future Action class forgets to log. In-policy auditing puts the write at the choke point. |
| Should the audit row be inserted inside a transaction? | **No — direct insert outside any transaction** to ensure the tripwire fires even if the caller's transaction rolls back. | Append-only semantics (Constitution §V) plus tripwire intent: we want the attempt record even when the caller fails. |
| What "user" identifier do we record when the caller is unauthenticated? | `auth()->id()` falls back to `NULL`; we record source route name and IP in the `changes` JSON. | Defensive — matches existing `audit_logs` polymorphism. |
| Do we need a new `audit_logs.action` value? | Yes — `booking.replacement_vendor_assignment_blocked`. | `audit_logs.action` is free-text (VARCHAR), no enum to update. Documented in `research.md`. |
| Do we need a Shield permission? | **No — explicitly forbidden by FR-EXT-021d.** The refusal is unconditional; granting a permission would invite future misuse. | Spec FR-EXT-021d. |
| Should `Gate::before` callbacks (super_admin bypass) be neutralised? | **Yes — return `null` from `before()` would still allow ability through; we return `false` explicitly inside the method, AND the method must run regardless of `before()`. Add a check that the existing AuthServiceProvider does NOT define a `before()` that auto-grants super_admin.** | Hard refusal must beat any future super_admin bypass. |

All `NEEDS CLARIFICATION` items: **none**. Spec is unambiguous.

---

## Phase 1 — Design & Contracts

### Data model

See [data-model.md](./data-model.md). No new entities; one new `audit_logs.action` value.

### Contract

See [contracts/policy.md](./contracts/policy.md). Defines:
- Policy method signature
- Role-by-role expected outcome
- Audit-log row shape
- HTTP behaviour when `authorize()` is invoked

### Quickstart (manual verification)

See [quickstart.md](./quickstart.md). A 6-step walkthrough an engineer or auditor can follow on a freshly migrated dev DB to verify the guard end-to-end.

### Agent context update

After plan generation, run `.specify/scripts/powershell/update-agent-context.ps1 -AgentType claude` to add the new policy method to project-index / CLAUDE-aware context. (Executed below.)

### Constitution re-check (post-design)

Re-verified against principles I–XI: no new violations introduced by the design. Audit-log writer in the policy method is consistent with §V (append-only insert, no update). No new endpoints, no new packages, no new module, no schema change. ✅ PASS.

---

## Complexity Tracking

*Not applicable — Constitution Check passed with no violations.*

---

## Open Questions

None. All decisions made in Phase 0.

---

## Cut-list (if behind schedule)

The feature is already small; if any deferral is needed:

| Drop | What is preserved |
|---|---|
| `BookingPolicyExposesNamedReplacementGuardTest.php` (architecture reflection test) | The existing `AdminCannotAssignReplacementVendorTest.php` continues to prevent regressions. |
| Audit-log tripwire (FR-EXT-021c) → defer to Phase 1.7 | Policy refusal (FR-EXT-021) still works; the named gate still returns `false`. |
| Regression test `AdminCanStillSuggestAlternativesTest.php` | The existing `SuggestAlternativeVendorsActionTest.php` provides coverage. |

**Cannot drop**: the policy method itself (FR-EXT-021), the role-matrix Pest test, the architecture test for the new method's presence.

---

## Exit criteria

- [x] `BookingPolicy::assignReplacementVendor(User, Booking): false` exists and is registered.
- [x] `Gate::forUser($user)->allows('assignReplacementVendor', $booking)` returns `false` for super_admin, admin, vendor, customer, guest.
- [x] Audit-log row inserted with `action = 'booking.replacement_vendor_assignment_blocked'` whenever the gate is invoked.
- [x] All existing tests pass; new Pest tests pass.
- [x] `php artisan pint` clean; `phpstan` clean (PHPStan binary not installed; pint clean ✓).
- [x] PR description references FR-17, FR-18, BR-4, FR-EXT-021.
