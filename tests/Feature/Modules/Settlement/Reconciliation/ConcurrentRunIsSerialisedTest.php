<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns wasIdempotentReplay=true when a run with the same scope is already in progress', function (): void {
    $scopeHash = md5(serialize(['all', []]));
    $lockKey   = "lock:reconcile:{$scopeHash}";

    // Simulate an in-progress run: acquire the Redis lock manually and insert a running row
    $existingRun = ReconciliationRun::create([
        'public_id'     => (string) Str::ulid(),
        'scope_type'    => 'all',
        'scope_params'  => null,
        'status'        => ReconciliationStatus::Running->value,
        'trigger_kind'  => 'manual',
        'correlation_id' => (string) Str::ulid(),
        'created_at'    => now(),
    ]);

    // Hold the cache lock so the second call cannot acquire it
    Cache::lock($lockKey, 900)->get();

    $action = app(RunReconciliationAction::class);

    $input = new RunReconciliationInput(
        scopeType:         'all',
        scopeParams:       [],
        triggerKind:       'manual',
        triggeredByUserId: null,
        idempotencyKey:    null,
        correlationId:     (string) Str::ulid(),
    );

    $result = $action->execute($input);

    expect($result->wasIdempotentReplay)->toBeTrue();
    expect($result->runPublicId)->toBe((string) $existingRun->public_id);

    // Only the original run should exist — no second run row created
    expect(ReconciliationRun::count())->toBe(1);

    // Release the in-memory array lock so it does not leak to subsequent tests.
    Cache::lock($lockKey, 900)->forceRelease();
})->group('us6');
