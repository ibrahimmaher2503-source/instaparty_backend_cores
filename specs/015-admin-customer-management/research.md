# Research: Admin Customer Management

**Feature**: Phase 6.3 — Admin Customer Management
**Date**: 2026-05-03
**Status**: Complete — no NEEDS CLARIFICATION items remain

---

## Decision 1: New CustomerResource vs. Enhancing Existing Resources

**Decision**: Create a new `CustomerResource` in `app/Modules/Identity/Filament/Resources/` that uses `User` as its model with a `scopeCustomers()` constraint. Retire (or keep as-is) the existing stub `CustomerProfileResource`.

**Rationale**: The existing `CustomerProfileResource` is a read-only stub scoped to `CustomerProfile` — it cannot do suspension, force-logout, or show the multi-tab footprint view. A purpose-built `CustomerResource` scoped to `User::scopeCustomers()` gives us:
- Correct model for token revocation and status changes
- Clean separation from `UserResource` (which likely covers all user types)
- Natural home for admin actions

**Alternatives considered**:
- Extending `UserResource` with conditional tabs: rejected — tabs would need to hide/show based on role type, creating fragile conditional logic.
- Enhancing `CustomerProfileResource`: rejected — model is `CustomerProfile`, making token revocation and status changes awkward (would require `->user` relationship jump everywhere).

---

## Decision 2: Where to Enforce Suspension at Authentication Layer

**Decision**: Two-layer enforcement:
1. **`LoginAction`**: Check `$user->status === 'suspended'` before issuing a Sanctum token. Throw a `ValidationException` with a bilingual message.
2. **Middleware `EnsureAccountIsActive`**: On every authenticated request, check `auth()->user()->status === 'suspended'` and return `403` with a bilingual error. Registered on all `sanctum` middleware-guarded routes.

**Rationale**: Defense in depth. A suspended user with an already-issued token (before suspension) must also be blocked, so middleware is required. Blocking at login prevents new tokens too.

**Alternatives considered**:
- Only blocking in `LoginAction`: insufficient — existing tokens still work post-suspension.
- Sanctum token `abilities`: overcomplicated for a simple binary active/suspended state.

---

## Decision 3: Suspension Atomicity (Status Update + Token Revocation)

**Decision**: `SuspendCustomerAction` runs inside a `DB::transaction`. Within the transaction it:
1. Updates `users.status` to `'suspended'`
2. Calls `$user->tokens()->delete()` to revoke all Sanctum personal access tokens

Both operations are in the same transaction. `CustomerSuspended` domain event fires `DB::afterCommit`.

**Rationale**: A partial suspension (status changed but tokens still valid, or tokens deleted but status still `active`) would be a security hole. Atomic transaction prevents both.

**Alternatives considered**:
- Dispatch a job to revoke tokens: rejected — async gap means suspended user could act in the window before the job runs.

---

## Decision 4: Activity Log (Reading audit_logs)

**Decision**: The Activity tab in `CustomerResource` will query `spatie/laravel-activitylog`'s `Activity` model (backed by the `audit_logs` table) where `subject_type = User::class` AND `subject_id = $customerId`. Ordered `created_at DESC`, paginated.

**Rationale**: `spatie/laravel-activitylog` is already installed (in `10_Package_List.md` and used in `SuspendVendorAction`). Its `Activity` model has `subject()` morphTo, `causer()` morphTo, and `properties` JSON — exactly what the Activity tab needs.

**Pattern in use**: `activity()->on($user)->causedBy(auth()->user())->withProperties([...])->log('customer.suspended')` — same pattern as `SuspendVendorAction`.

---

## Decision 5: Loyalty Balance Display (Read-only Derived Value)

**Decision**: The Wallet tab displays loyalty balances as a `RepeatableEntry` / `TableWidget`-style view. Data is derived via a query: `loyalty_ledger` grouped by `loyalty_program_id` for the given `user_id`, joined with `loyalty_programs` and vendor name.

**Rationale**: No write path needed from this feature. This is purely a read-only aggregate view. Using `DB::select` or a scoped Eloquent query within the Filament `Infolist` is sufficient.

**Alternatives considered**:
- A dedicated `LoyaltyBalanceRepository::forCustomer(User $user)`: cleaner, but adds cross-module coupling. Since this is admin-only display, inline query within the Filament resource (or a read-model in Identity) is acceptable for Phase 1.
- Contract-based cross-module access: preferred in production but out of Phase 1 scope for this display-only use.

---

## Decision 6: Admin Cannot Suspend Themselves — Enforcement Point

**Decision**: Guard in `SuspendCustomerAction::execute()`: if `$user->id === auth()->id()`, throw `\DomainException('admin_cannot_self_suspend')`. Filament action button also hides itself when `$record->id === auth()->id()`.

**Rationale**: Both layers (backend Action + UI visibility) ensure the invariant is unbreakable.

---

## Summary: What Needs Building

| Component | Status | Notes |
|---|---|---|
| `CustomerResource.php` (new) | Build | Replaces stub; User model + scopeCustomers |
| `SuspendCustomerAction` | Build | Status update + token revocation + audit |
| `UnsuspendCustomerAction` | Build | Status revert + audit |
| `ForceLogoutCustomerAction` | Build | Token revocation only + audit |
| `AdminUpdateCustomerProfileAction` | Build | Wraps existing `UpdateCustomerProfileAction` + audit log |
| `EnsureAccountIsActive` middleware | Build | 403 on suspended accounts |
| `LoginAction` patch | Patch | Add suspension check |
| `CustomerSuspended` domain event | Build | Fires `DB::afterCommit` |
| `CustomerUnsuspended` domain event | Build | Fires `DB::afterCommit` |
| Shield permissions | Config | `view_customer`, `update_customer_profile`, `suspend_customer`, `force_logout_customer` |
