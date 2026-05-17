# Quickstart: Lifecycle State Machine Architecture

**For**: Developers implementing or extending state machines in this feature.  
**Date**: 2026-05-15

---

## Adding a New State Machine (5-step pattern)

Use this pattern for each of the 8 state fields being migrated.

### Step 1 — Create the abstract State base class

```php
// app/Modules/Catalog/Domain/States/ServiceStatus/ServiceState.php
abstract class ServiceState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->registerState(DraftState::class,          'draft')
            ->registerState(PendingReviewState::class,  'pending_review')
            ->registerState(PublishedState::class,      'published')
            // ... etc.
            ->allowTransition(DraftState::class, PendingReviewState::class, SubmitForReviewTransition::class)
            ->allowTransition(PendingReviewState::class, PublishedState::class, ApproveServiceTransition::class)
            // ... etc.
    }
}
```

### Step 2 — Create concrete state classes (empty bodies are fine)

```php
// app/Modules/Catalog/Domain/States/ServiceStatus/DraftState.php
final class DraftState extends ServiceState {}

// app/Modules/Catalog/Domain/States/ServiceStatus/PendingReviewState.php
final class PendingReviewState extends ServiceState {}
```

### Step 3 — Update the model

```php
// app/Modules/Catalog/Domain/Models/Service.php
use Spatie\ModelStates\HasStates;

class Service extends Model
{
    use HasStates;

    protected $casts = [
        'status' => ServiceState::class,  // was: ServiceStatus::class
    ];

    // Remove 'status' from $fillable
}
```

### Step 4 — Create Transition classes

```php
// app/Modules/Catalog/Domain/States/ServiceStatus/Transitions/ApproveServiceTransition.php
final class ApproveServiceTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Service
    {
        // Populate Laravel Context — observer reads these for the log row
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);
        Context::add('transition_reason', 'Admin approved service');

        // Guard: check permission
        abort_unless(
            auth()->user()?->can('publish_service'),
            403,
            'Insufficient permissions to approve service'
        );

        // The actual transition (spatie fires StateChanged → observer logs it)
        $this->model->status->transitionTo(PublishedState::class);

        // Side effects — fire after DB commit
        DB::afterCommit(fn () => event(new ServicePublished($this->model)));

        return $this->model;
    }
}
```

### Step 5 — Update the calling Action

```php
// Before (unsafe):
$service->update(['status' => ServiceStatus::Published]);

// After (safe):
$service = Service::query()->lockForUpdate()->findOrFail($serviceId);
(new ApproveServiceTransition($service, auth()->id()))->handle();
```

---

## Admin Override Pattern

Every state machine needs one `AdminOverride...Transition` class:

```php
final class AdminOverrideServiceStatusTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly string $targetState,  // DB value string
        private readonly string $reason,
        private readonly int $actorId,
    ) {
        if (empty($reason)) {
            throw new \InvalidArgumentException('Reason is required for admin overrides');
        }
    }

    public function handle(): Service
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::AdminOverride->value);
        Context::add('transition_reason', $this->reason);

        // Force-transition to any state (bypasses allowTransition guard)
        $targetStateClass = ServiceState::resolveStateClass($this->targetState);
        $this->model->status->transitionTo($targetStateClass, $this->model);

        DB::afterCommit(fn () => event(new AdminOverrideApplied($this->model)));

        return $this->model;
    }
}
```

> Note: To bypass `allowTransition()` restrictions, use `forceTransitionTo()` instead of
> `transitionTo()`. Confirm the spatie version supports this — see
> `\Spatie\ModelStates\State::forceTransitionTo()`.

---

## Query Patterns (replacing raw string comparisons)

```php
// Before (unsafe raw value):
Service::where('status', ServiceStatus::Published)->get();
Service::where('status', 'pending_review')->get();

// After (state-aware):
Service::whereState('status', PublishedState::class)->get();
Service::whereState('status', PendingReviewState::class)->get();

// Multiple states:
Service::whereStateIn('status', [PublishedState::class, PendingReviewState::class])->get();
```

---

## Transition Log Query Patterns

```php
// All transitions for a specific booking:
StateTransition::where('transitionable_type', Booking::class)
    ->where('transitionable_id', $booking->id)
    ->orderBy('created_at')
    ->get();

// All admin overrides today:
StateTransition::where('trigger_kind', 'admin_override')
    ->whereDate('created_at', today())
    ->get();

// Trace a request across all its transitions:
StateTransition::where('trace_id', $traceId)->get();
```

---

## Testing a State Machine

```php
// Valid transition test
it('publishes a service after admin approval', function () {
    $service = Service::factory()->pendingReview()->create();
    $admin = User::factory()->admin()->create();

    actingAs($admin);
    (new ApproveServiceTransition($service, $admin->id))->handle();

    expect($service->fresh()->status)->toBeInstanceOf(PublishedState::class);
    expect(StateTransition::where('transitionable_type', Service::class)
        ->where('to_state', 'published')->count())->toBe(1);
})->group('catalog', 'state-machines');

// Invalid transition test
it('cannot publish directly from draft', function () {
    $service = Service::factory()->draft()->create();
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    expect(fn () => (new ApproveServiceTransition($service, $admin->id))->handle())
        ->toThrow(\Spatie\ModelStates\Exceptions\TransitionNotAllowedException::class);

    expect($service->fresh()->status)->toBeInstanceOf(DraftState::class);
})->group('catalog', 'state-machines');

// Admin override test
it('admin override writes admin_override trigger_kind', function () {
    $service = Service::factory()->archived()->create();
    $admin = User::factory()->admin()->create();

    actingAs($admin);
    (new AdminOverrideServiceStatusTransition($service, 'published', 'Emergency restore', $admin->id))->handle();

    $log = StateTransition::where('transitionable_type', Service::class)->latest()->first();
    expect($log->trigger_kind)->toBe('admin_override');
    expect($log->reason)->toBe('Emergency restore');
})->group('catalog', 'state-machines');
```

---

## `Context` Values Reference

| Key | Set by | Read by | Type |
|---|---|---|---|
| `trace_id` | `SetRequestTraceIdMiddleware` or job `handle()` | `StateTransitionObserver` | `string` (UUID) |
| `actor_id` | Transition class `handle()` | `StateTransitionObserver` | `int\|null` |
| `trigger_kind` | Transition class `handle()` | `StateTransitionObserver` | `string` (TriggerKind value) |
| `transition_reason` | Transition class `handle()` | `StateTransitionObserver` | `string\|null` |
| `transition_metadata` | Transition class `handle()` (optional) | `StateTransitionObserver` | `array\|null` |

Always clear `transition_reason` and `transition_metadata` at the end of `handle()` if the
Context is shared across multiple transitions in a single request (e.g., batch operations):
```php
Context::forget('transition_reason');
Context::forget('transition_metadata');
```
