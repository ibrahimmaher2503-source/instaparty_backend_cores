# Phase 0 Research — Replacement Vendor Guard

**Feature**: `032-replacement-vendor-guard`
**Date**: 2026-05-16
**Status**: Complete — zero `NEEDS CLARIFICATION` markers remain.

---

## R-1. Policy method vs. standalone Gate closure

**Decision**: Implement as a **Policy method** on `App\Modules\Booking\Domain\Policies\BookingPolicy`.

**Rationale**:
- The rest of the Booking module already exposes policies (`BookingPolicy`, `BookingAdminInterventionPolicy`, `BookingStateTransitionPolicy`, `BookingModificationPolicy`). Adding the refusal as a closure in `AuthServiceProvider::boot()` would break that consistency.
- Policy methods are discoverable via IDE jump-to-symbol (`BookingPolicy::assignReplacementVendor`) and via `php artisan route:list --policies` style introspection. Closures registered in `Gate::define(...)` are not.
- Laravel resolves `$user->can('assignReplacementVendor', $booking)` to a policy method when a `Booking` instance is passed and `BookingPolicy::class` is registered for `Booking::class` — exactly the ergonomics the spec requires.

**Alternatives considered**:
- *Gate closure in `AuthServiceProvider`*: rejected — invisible to IDE, harder to override or extend in a sibling module if needed.
- *Standalone "GuardService" class*: rejected — duplicates Laravel's authorization plumbing.

---

## R-2. Where to write the audit-log row

**Decision**: Write the `audit_logs` row **inside the policy method**, just before returning `false`.

**Rationale**:
- The tripwire (FR-EXT-021c) must fire regardless of caller. Putting the insert in a listener that hooks `Illuminate\Auth\Access\Events\GateEvaluated` would couple to an event Laravel fires for *every* gate check, requiring a filter — fragile.
- Putting the insert in a controller / Action would require every future caller to remember the contract — exactly what we are trying to prevent.
- The in-policy insert follows the same pattern as `SuggestAlternativeVendorsAction` (direct `DB::table('audit_logs')->insert(...)`).

**Alternatives considered**:
- *Listener on a custom `ReplacementAssignmentAttempted` event*: rejected — events fire `DB::afterCommit`, but we want the log even if the caller's transaction rolls back. Synchronous insert at the choke point is simpler.
- *Database trigger on a phantom `booking_replacement_attempts` table*: rejected — schema overhead for zero benefit.

---

## R-3. Transactional behaviour of the audit-log insert

**Decision**: Insert the audit row **outside any enclosing transaction**, using `DB::connection()->getPdo()->exec(...)`-style independence is overkill — instead, the policy method just calls `DB::table('audit_logs')->insert(...)` directly. If a parent transaction exists, the insert will be part of it; we accept this trade-off because:

1. In the only intended runtime path (a controller invoking `$this->authorize(...)`), there is no enclosing transaction at gate-check time.
2. If a future Action wraps the authorization in a transaction and then aborts, the audit-row loss is acceptable — the architecture tests catch the static introduction of the symbol first.

**Rationale**: Simplicity over premature defence. The architecture test (`AdminCannotAssignReplacementVendorTest`) is the primary line of defence; the audit log is the secondary tripwire for routes that somehow reach the gate.

**Alternatives considered**:
- *Use a separate database connection for audit-log writes*: rejected — premature complexity, and would conflict with existing `audit_logs` writer patterns.

---

## R-4. Recorded fields for the audit row

**Decision**:

```php
DB::table('audit_logs')->insert([
    'public_id'      => Str::ulid()->toBase32(),
    'auditable_type' => Booking::class,
    'auditable_id'   => $booking->id,
    'user_id'        => $user?->id,                            // may be NULL for guests
    'action'         => 'booking.replacement_vendor_assignment_blocked',
    'changes'        => json_encode([
        'attempted_at'  => now()->toIso8601String(),
        'role'          => $user?->getRoleNames()->toArray() ?? [],
        'source'        => request()?->route()?->getName() ?? 'cli',
        'ip'            => request()?->ip(),
        'user_agent'    => request()?->userAgent(),
    ]),
    'created_at'     => now(),
]);
```

**Rationale**: matches existing `audit_logs` shape (per Schema §Cross-cutting). `changes` JSON keeps the row append-only and avoids new columns.

**Alternatives considered**:
- *Add new columns (`source_route`, `user_role`, `attempt_ip`)*: rejected — `audit_logs` is polymorphic-by-design; new columns would violate that.

---

## R-5. New Shield permission?

**Decision**: **No permission** is to be created.

**Rationale**: FR-EXT-021d explicitly bans permissions matching `%assign_replacement%` and similar patterns. The refusal is unconditional. A permission existing in the table — even if unbound — invites future misuse via Filament's auto-discovery.

**Alternatives considered**: *Create a permission named `cannot_assign_replacement_vendor` for symmetry*: rejected — counterintuitive name and still appears in the table.

---

## R-6. Interaction with `Gate::before()` super_admin bypass

**Decision**: Inspect `app/Providers/AuthServiceProvider.php` for any `Gate::before(...)` registration. If one exists and grants super_admin a blanket `true`, the refusal MUST be enforced by:

1. Returning `false` explicitly from the policy method (which already happens regardless of `before()`).
2. Adding a Pest test that explicitly creates a super_admin and asserts the refusal — this catches any future `before()` callback that returns `true` for super_admin.

**Rationale**: Laravel's `Gate::before()` returns `null` (don't decide) or `true|false` (decide). If a callback returns `true` for super_admin, it short-circuits the policy. The spec FR-EXT-021 says the refusal is unconditional, so the super_admin Pest test is the safety net.

**Action**: at implementation time, grep `app/Providers/AuthServiceProvider.php` for `Gate::before` and ensure no callback returns `true` for super_admin **for this specific ability**. If a global `before()` exists, register an override via `Gate::define('assignReplacementVendor', ...)` that hard-returns `false` — but this is only needed if the project has such a `before()`. Today's codebase has none (verified during survey).

**Alternatives considered**:
- *Always register a `Gate::define` override*: rejected as premature — the explicit policy method already returns `false`, and a future `before()` is the only risk we cannot pre-empt.

---

## R-7. New `audit_logs.action` value catalogue entry

**Decision**: Add `booking.replacement_vendor_assignment_blocked` to the audit-action catalogue in `.specify/memory/api-registry.md` (or wherever the project tracks action keys). No enum change because `audit_logs.action` is a free-text column per Schema §Cross-cutting.

**Rationale**: Keeps the spec-kit memory aware of the new operator-facing action. Helps future `/security-review` and `/review` skills.

---

## R-8. Test data — does every Pest test need to migrate the DB?

**Decision**: Yes — use `RefreshDatabase` trait per existing convention in `tests/Feature/Modules/Booking/`. The audit-log assertions require an actual `audit_logs` table.

**Rationale**: Matches `SuggestAlternativeVendorsActionTest.php` and feature 029's other Booking feature tests.

---

## Summary of unresolved questions

**None.** Plan can proceed to Phase 1 design artefacts.
