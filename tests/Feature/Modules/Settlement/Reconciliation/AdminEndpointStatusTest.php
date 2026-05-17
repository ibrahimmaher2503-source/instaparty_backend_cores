<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('GET /admin/settlement/reconciliation/status returns null data when no run exists', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('audit.view');

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/settlement/reconciliation/status');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeNull();
})->group('us6');

it('GET /admin/settlement/reconciliation/status returns latest run summary in EN', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('audit.view');

    ReconciliationRun::create([
        'public_id'           => (string) Str::ulid(),
        'scope_type'          => 'all',
        'scope_params'        => null,
        'status'              => ReconciliationStatus::Clean->value,
        'trigger_kind'        => 'scheduled',
        'correlation_id'      => (string) Str::ulid(),
        'wallets_scanned'     => 5,
        'findings_count'      => 0,
        'auto_repaired_count' => 0,
        'manual_review_count' => 0,
        'started_at'          => now()->subMinutes(2),
        'completed_at'        => now()->subMinute(),
        'created_at'          => now()->subMinutes(2),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/settlement/reconciliation/status', [
            'Accept-Language' => 'en',
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'public_id', 'scope_type', 'status', 'wallets_scanned',
            'findings_count', 'auto_repaired_count', 'manual_review_count',
            'started_at', 'completed_at',
        ],
    ]);
    expect($response->json('data.status'))->toBe('clean');
    expect($response->json('data.wallets_scanned'))->toBe(5);
})->group('us6');

it('GET /admin/settlement/reconciliation/status returns latest run summary in AR', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('audit.view');

    ReconciliationRun::create([
        'public_id'      => (string) Str::ulid(),
        'scope_type'     => 'all',
        'scope_params'   => null,
        'status'         => ReconciliationStatus::Repaired->value,
        'trigger_kind'   => 'manual',
        'correlation_id' => (string) Str::ulid(),
        'created_at'     => now(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/settlement/reconciliation/status', [
            'Accept-Language' => 'ar',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('repaired');
})->group('us6');

it('GET /admin/settlement/reconciliation/status returns 401 for unauthenticated', function (): void {
    $this->getJson('/api/v1/admin/settlement/reconciliation/status')->assertStatus(401);
})->group('us6');
