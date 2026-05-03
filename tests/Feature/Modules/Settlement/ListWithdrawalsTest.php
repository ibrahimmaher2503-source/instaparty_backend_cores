<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────
// No shared helper needed — each test creates its own data.
// ─────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────
// T320 — GET /api/v1/vendor/withdrawals
// ─────────────────────────────────────────────────────

it('returns withdrawals for the authenticated vendor', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
    ]);
    Withdrawal::factory()->paid()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/withdrawals');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['public_id', 'status', 'requested_amount_minor']],
            'meta' => ['pagination'],
        ]);

    expect(count($response->json('data')))->toBe(2);
})->group('settlement', 'withdrawal', 'list');

it('returns 401 when listing withdrawals without a token', function (): void {
    $this->getJson('/api/v1/vendor/withdrawals')->assertStatus(401);
})->group('settlement', 'withdrawal', 'list');

it('returns 403 when a customer tries to list withdrawals', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/withdrawals')
        ->assertStatus(403);
})->group('settlement', 'withdrawal', 'list');

// ────────────────────────────────────────��────────────
// Vendor isolation
// ─────────────────────────────────────────────────────

it('vendor1 cannot see vendor2 withdrawals', function (): void {
    $vp1 = VendorProfile::factory()->create();
    $vp1->user->assignRole('vendor');
    $token1 = $vp1->user->createToken('test')->plainTextToken;

    $vp2 = VendorProfile::factory()->create();
    $vp2->user->assignRole('vendor');

    // Create withdrawal for vendor2
    Withdrawal::factory()->paid()->create([
        'vendor_profile_id' => $vp2->id,
        'requested_by_user_id' => $vp2->user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson('/api/v1/vendor/withdrawals');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
})->group('settlement', 'withdrawal', 'list', 'isolation');

// ─────────────────────────────────────────────────────
// Status filter
// ─────────────────────────────────────────────────────

it('filters withdrawals by status=paid', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
    ]);
    Withdrawal::factory()->paid()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/withdrawals?status=paid');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(1)
        ->and($response->json('data.0.status'))->toBe('paid');
})->group('settlement', 'withdrawal', 'list');

// ─────────────────────────────────────────────────────
// GET /api/v1/vendor/withdrawals/{public_id}
// ─────────────────────────────────────────────────────

it('returns a single withdrawal by public_id', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $withdrawal = Withdrawal::factory()->paid()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vendor/withdrawals/{$withdrawal->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.public_id', $withdrawal->public_id);
})->group('settlement', 'withdrawal', 'list');

it('returns 404 when vendor tries to view another vendor withdrawal by public_id', function (): void {
    $vp1 = VendorProfile::factory()->create();
    $vp1->user->assignRole('vendor');
    $token1 = $vp1->user->createToken('test')->plainTextToken;

    $vp2 = VendorProfile::factory()->create();
    $vp2->user->assignRole('vendor');

    $withdrawal = Withdrawal::factory()->paid()->create([
        'vendor_profile_id' => $vp2->id,
        'requested_by_user_id' => $vp2->user->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson("/api/v1/vendor/withdrawals/{$withdrawal->public_id}")
        ->assertStatus(404);
})->group('settlement', 'withdrawal', 'list', 'isolation');
