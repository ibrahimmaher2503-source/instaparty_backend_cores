<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
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
// T221 — GET /api/v1/vendor/wallet/ledger
// ─────────────────────────────────────────────────────

it('returns paginated ledger entries for the authenticated vendor', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $wallet = Wallet::factory()->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    WalletLedgerEntry::factory()->count(3)->commissionCredit()->create(['wallet_id' => $wallet->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet/ledger');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['entry_type', 'amount_minor', 'currency']],
            'meta' => ['pagination' => ['per_page', 'next_cursor', 'prev_cursor']],
        ]);

    expect(count($response->json('data')))->toBe(3);
})->group('settlement', 'ledger', 'T221');

it('returns empty data when vendor has no wallet', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet/ledger');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
})->group('settlement', 'ledger', 'T221');

it('filters ledger entries by entry_type', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $wallet = Wallet::factory()->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    WalletLedgerEntry::factory()->count(2)->commissionCredit()->create(['wallet_id' => $wallet->id]);
    WalletLedgerEntry::factory()->count(1)->refundDebit()->create([
        'wallet_id' => $wallet->id,
        'amount_minor' => -5000,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet/ledger?entry_type=commission_credit');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(2);

    foreach ($response->json('data') as $entry) {
        expect($entry['entry_type'])->toBe('commission_credit');
    }
})->group('settlement', 'ledger', 'T221');

it('returns 401 when accessing ledger without a token', function (): void {
    $this->getJson('/api/v1/vendor/wallet/ledger')->assertStatus(401);
})->group('settlement', 'ledger', 'T221');

it('returns 403 when a customer tries to view ledger', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet/ledger')
        ->assertStatus(403);
})->group('settlement', 'ledger', 'T221');

it('respects per_page query parameter', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');
    $token = $vp->user->createToken('test')->plainTextToken;

    $wallet = Wallet::factory()->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    WalletLedgerEntry::factory()->count(10)->create(['wallet_id' => $wallet->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/wallet/ledger?per_page=3');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(3);
})->group('settlement', 'ledger', 'T221');
