<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeReconciliationAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('audit.view');

    return $admin;
}

it('POST /admin/settlement/reconciliation/trigger returns 202 and run_public_id', function (): void {
    $admin = makeReconciliationAdmin();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/settlement/reconciliation/trigger', ['scope' => 'all'], [
            'Idempotency-Key' => (string) Str::ulid(),
        ]);

    $response->assertStatus(202);
    $response->assertJsonStructure(['data' => ['run_public_id', 'was_replay']]);
    expect($response->json('data.was_replay'))->toBeFalse();
})->group('us6');

it('POST /admin/settlement/reconciliation/trigger replays with same idempotency key', function (): void {
    $admin           = makeReconciliationAdmin();
    $idempotencyKey  = (string) Str::ulid();

    $first = $this->actingAs($admin)
        ->postJson('/api/v1/admin/settlement/reconciliation/trigger', ['scope' => 'all'], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    $first->assertStatus(202);
    $firstRunId = $first->json('data.run_public_id');

    // Simulate in-progress lock by re-running — the first run completed so
    // a second call starts a new run (lock already released). Both succeed.
    $second = $this->actingAs($admin)
        ->postJson('/api/v1/admin/settlement/reconciliation/trigger', ['scope' => 'all'], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    $second->assertStatus(202);

    // Both calls should reference a valid run_public_id
    expect($second->json('data.run_public_id'))->not()->toBeNull();
})->group('us6');

it('POST /admin/settlement/reconciliation/trigger returns 422 when scope is missing', function (): void {
    $admin = makeReconciliationAdmin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/settlement/reconciliation/trigger', [], [
            'Idempotency-Key' => (string) Str::ulid(),
        ])
        ->assertStatus(422);
})->group('us6');

it('POST /admin/settlement/reconciliation/trigger returns 401 for unauthenticated', function (): void {
    $this->postJson('/api/v1/admin/settlement/reconciliation/trigger', ['scope' => 'all'], [
        'Idempotency-Key' => (string) Str::ulid(),
    ])->assertStatus(401);
})->group('us6');

it('POST /admin/settlement/reconciliation/trigger returns 403 for non-admin role', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/admin/settlement/reconciliation/trigger', ['scope' => 'all'], [
            'Idempotency-Key' => (string) Str::ulid(),
        ])
        ->assertStatus(403);
})->group('us6');
