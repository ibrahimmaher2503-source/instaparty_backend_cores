# Research: Lifecycle State Machine Architecture

**Feature**: `026-lifecycle-state-machines`
**Date**: 2026-05-15

---

## Research Area 1 — `spatie/laravel-model-states` Auto-Logging Pattern

### Decision
Use the `StateChanged` event emitted by `spatie/laravel-model-states` after every successful
`transitionTo()` call. Register a single `StateTransitionObserver` listener to write the
`state_transitions` row — no per-Transition boilerplate.

### Rationale
- `StateChanged` is a first-class spatie event that fires synchronously within the DB transaction
  when using `DB::transaction()` + `transitionTo()`. This guarantees the log row is always
  committed together with the model state column — or both roll back.
- Alternative A: Override a `handle()` base method in a `BaseTransition` abstract class.
  Rejected — requires all 80+ Transition classes to extend the base, or the log is silently missed.
- Alternative B: Eloquent model observers (`updating` / `updated` hooks). Rejected — fires on
  any `save()`, including factories and migrations; cannot distinguish trigger_kind or reason.

### How spatie fires `StateChanged`
```php
// spatie/laravel-model-states src/HasStates.php (simplified):
protected function performStateTransition(State $state, Transition $transition): void
{
    $oldState = $this->{$state->getField()};
    // ... do the transition ...
    event(new StateChanged($oldState, $state, $transition, $this));
}
```
The event carries: `initialState`, `finalState`, `transition` (the Transition class instance),
and `model`. This is all we need for the log row.

### Context propagation via Laravel `Context`
Laravel 11 ships `Illuminate\Support\Facades\Context` — a request-scoped key-value store
that survives across method calls within the same PHP request/job. This replaces needing to
thread `$actorId`, `$reason`, `$traceId` through every method signature.

Each Transition class sets:
```php
Context::add('actor_id', $this->actorId);        // int|null
Context::add('trigger_kind', 'admin');            // 'system'|'customer'|'vendor'|'admin'|'admin_override'
Context::add('transition_reason', $this->reason); // string|null
Context::add('transition_metadata', $this->meta); // array|null
```

The `StateTransitionObserver` reads these from Context when building the log row.
The `SetRequestTraceIdMiddleware` populates `trace_id` in Context for all HTTP requests.
Queue jobs set `trace_id` from job UUID in the job `handle()` method.

---

## Research Area 2 — DB Column Value Preservation (No Data Migration)

### Decision
Use `spatie/laravel-model-states` `registerState()` to map each concrete state class to the
existing DB string value. Zero data migration required.

### Rationale
All 8 status fields currently store values like `'pending_review'`, `'vendor_review'`,
`'captured'`, etc. If state classes are named `PendingReviewState`, `VendorReviewState`,
`CapturedState`, the default spatie serialization would produce `PendingReviewState` as the DB
value (fully-qualified or class basename depending on config). This would break existing rows.

The fix is explicit `registerState()` calls in `StateConfig::config()`:
```php
->registerState(PendingReviewState::class, 'pending_review')  // DB value stays 'pending_review'
```

This produces no migration cost and zero downtime risk.

- Alternative: Rename DB values (e.g., `'pending_review'` → `'PendingReviewState'`). Rejected —
  requires data migration + risk of production data corruption.

---

## Research Area 3 — `RENAME TABLE` Migration Safety

### Decision
Use a single migration: `Schema::rename('booking_state_transitions', 'state_transitions')`
followed by `Schema::table('state_transitions', ...)` to add the two new columns.
Run in one migration file, wrapped in a DB transaction where supported.

### Rationale
MySQL 8 `RENAME TABLE` is an atomic metadata-only operation — no row copying, instant for
tables of any size. It is safe under concurrent reads/writes (acquires metadata lock briefly).

`state_transitions` has no FK references from other tables (no other table has
`booking_state_transitions_id` or references the table name). Only application code references
the table name — all call sites will be updated in the same PR.

Rollback: `down()` method reverses the rename and drops the two new columns.

---

## Research Area 4 — PHPStan / Pest Architecture Test for Unsafe Mutations

### Decision
Use a Pest architecture test (`tests/Architecture/NoDirectStatusMutationTest.php`) rather than a
custom PHPStan rule. Pest's `arch()` assertions cover the requirement with less setup.

### Rationale
A custom PHPStan rule that parses array keys in `->update([...])` calls requires writing a
PHPStan extension in PHP + registering it in `phpstan.neon`. That's significant overhead for a
one-time enforcement task.

Pest `arch()` can detect method calls and argument patterns:
```php
arch('no direct lifecycle_status mutation')
    ->expect('App\Modules')
    ->not->toUseMethod('update')
    ->withArguments(fn ($args) => isset($args[0]['lifecycle_status']));
```

If the Pest `arch()` argument-inspection API is insufficient for this pattern (it varies by
version), fall back to a Pest test using `Symfony\Component\Finder` to scan PHP files via regex.
Either approach is runtime-free and CI-enforced.

- Alternative: `PHPStan\Rules\Rule` custom extension. Deferred — valid for Phase 2 if Pest arch
  approach proves insufficient in practice.

---

## Research Area 5 — Pessimistic Locking for Race Condition Prevention

### Decision
Use `lockForUpdate()` on the model row before executing the transition. `spatie/laravel-model-states`
does not add row locking by default; locking must be explicit in the calling Action.

### Rationale
The existing `VendorRejectBookingAction` already uses `lockForUpdate()` correctly:
```php
$bookingVendor = BookingVendor::query()->lockForUpdate()->firstOrFail();
```
This pattern is proven in the codebase. Transition classes will document that the model must be
retrieved with `lockForUpdate()` by the caller (the Action) before the Transition is instantiated.

The Transition class itself does NOT add `lockForUpdate()` — it receives an already-locked model
from the Action. This is the correct separation of concerns.

For concurrent identical transitions (idempotency), the existing `idempotency_keys` table check
in the calling Action prevents duplicate execution even when both requests pass the lock.

---

## Research Area 6 — `trigger_kind` Extended to Include `admin_override`

### Decision
Extend the `trigger_kind` enum column from `['system', 'customer', 'vendor', 'admin']` to
`['system', 'customer', 'vendor', 'admin', 'admin_override']`.

### Rationale
The existing migration defines `trigger_kind` as:
```php
$table->enum('trigger_kind', ['system', 'customer', 'vendor', 'admin']);
```
Admin overrides need a distinct value for auditing — they bypass normal guards and must be
queryable separately. Adding `'admin_override'` to the enum is the minimal change.

The new `trigger_kind` migration uses MySQL `ALTER TABLE ... MODIFY COLUMN`:
```php
DB::statement("ALTER TABLE state_transitions MODIFY COLUMN trigger_kind
    ENUM('system','customer','vendor','admin','admin_override') NOT NULL");
```

---

## Summary of All Decisions

| Area | Decision | Alternatives Rejected |
|---|---|---|
| Log auto-writing | `StateChanged` event → single observer | Per-Transition base class; Eloquent `updated` observer |
| Context propagation | Laravel `Context` facade | Method parameter threading; singleton service |
| DB value preservation | `registerState()` in `StateConfig` | Data migration renaming values |
| Table rename | `Schema::rename()` in single migration | Drop+recreate (data loss risk) |
| Unsafe mutation enforcement | Pest architecture test | Custom PHPStan rule |
| Race condition protection | `lockForUpdate()` in calling Action | DB unique constraint on transitions; Redis mutex |
| Admin override distinction | New `admin_override` enum value in `trigger_kind` | Boolean flag column; separate table |
