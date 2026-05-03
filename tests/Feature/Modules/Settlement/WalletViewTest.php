<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Models\Wallet;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────
// T220 — GET /api/v1/vendor/wallet
// ─────────────────────────────────────────────────────

it('returns wallet data for an authenticated vendor', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    // Seed a wallet for this vendor
    Wallet::factory()->withBalance(50000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('data.balance_minor', 50000)
        ->assertJsonPath('data.currency', 'EGP')
        ->assertJsonStructure(['data' => [
            'balance_minor',
            'balance_formatted',
            'pending_withdrawal_minor',
            'available_minor',
            'is_negative',
            'totals',
        ]]);
})->group('settlement', 'wallet', 'T220');

it('returns zero-balance data when vendor has no wallet yet', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('data.balance_minor', 0)
        ->assertJsonPath('data.is_negative', false);
})->group('settlement', 'wallet', 'T220');

it('returns 401 when accessing wallet without a token', function (): void {
    $this->getJson('/api/v1/vendor/wallet')->assertStatus(401);
})->group('settlement', 'wallet', 'T220');

it('returns 403 when a customer tries to view wallet', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet')
        ->assertStatus(403);
})->group('settlement', 'wallet', 'T220');

it('returns balance_formatted in English locale format', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    Wallet::factory()->withBalance(100000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/v1/vendor/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('data.balance_formatted', '1,000.00 EGP');
})->group('settlement', 'wallet', 'T220');

it('returns correct wallet data with Accept-Language ar header', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    Wallet::factory()->withBalance(75000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    // The WalletResource formats money without locale-sensitive number format,
    // so the structure is identical for AR — we just verify the HTTP response is 200.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/vendor/wallet')
        ->assertStatus(200)
        ->assertJsonPath('data.balance_minor', 75000);
})->group('settlement', 'wallet', 'T220');
