<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
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
// Helpers
// ─────────────────────────────────────────────────────

function withdrawalPayload(array $overrides = []): array
{
    return array_merge([
        'amount_minor' => 50000,
        'currency' => 'EGP',
        'bank_account' => [
            'account_holder' => 'Test Vendor',
            'iban' => 'EG380019000500000000263180002',
            'bank_name' => 'CIB',
            'swift_bic' => 'CIBEEGCX',
        ],
    ], $overrides);
}

function makeVendorWithWallet(int $balanceMinor = 200000): array
{
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    $wallet = Wallet::factory()->withBalance($balanceMinor)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
    ]);

    $token = $vp->user->createToken('test')->plainTextToken;

    return [$vp, $wallet, $token];
}

// ─────────────────────────────────────────────────────
// T320 — Happy path
// ─────────────────────────────────────────────────────

it('creates a withdrawal and returns 201', function (): void {
    [, , $token] = makeVendorWithWallet(200000);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'withdraw-test-001')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.requested_amount_minor', 50000)
        ->assertJsonStructure(['data' => [
            'public_id',
            'status',
            'requested_amount_minor',
            'requested_amount_formatted',
            'requested_amount_currency',
            'bank_account',
            'requested_at',
        ]]);

    expect(Withdrawal::count())->toBe(1);
})->group('settlement', 'withdrawal', 'T320');

// ─────────────────────────────────────────────────────
// T320 — Auth / Authz
// ─────────────────────────────────────────────────────

it('returns 401 when requesting withdrawal without a token', function (): void {
    $this->withHeader('Idempotency-Key', 'no-auth-test')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload())
        ->assertStatus(401);
})->group('settlement', 'withdrawal', 'T320');

it('returns 403 when a customer tries to request a withdrawal', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'customer-withdraw-test')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload())
        ->assertStatus(403);
})->group('settlement', 'withdrawal', 'T320');

// ─────────────────────────────────────────────────────
// T321 — Validation
// ─────────────────────────────────────────────────────

it('returns 422 when amount_minor is missing', function (): void {
    [, , $token] = makeVendorWithWallet();
    $payload = withdrawalPayload();
    unset($payload['amount_minor']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'missing-amount')
        ->postJson('/api/v1/vendor/withdrawals', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount_minor']);
})->group('settlement', 'withdrawal', 'T321');

it('returns 422 when bank_account.iban is invalid', function (): void {
    [, , $token] = makeVendorWithWallet();

    // Use an IBAN with correct country format but wrong checksum so validateIban() returns false
    $payload = [
        'amount_minor' => 50000,
        'currency' => 'EGP',
        'bank_account' => [
            'account_holder' => 'Test',
            'iban' => 'EG000000000000000000000000000', // wrong checksum
            'bank_name' => 'CIB',
            'swift_bic' => 'CIBEEGCX',
        ],
    ];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'bad-iban')
        ->postJson('/api/v1/vendor/withdrawals', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_account.iban']);
})->group('settlement', 'withdrawal', 'T321');

it('returns 422 when currency is missing', function (): void {
    [, , $token] = makeVendorWithWallet();
    $payload = withdrawalPayload();
    unset($payload['currency']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'missing-currency')
        ->postJson('/api/v1/vendor/withdrawals', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['currency']);
})->group('settlement', 'withdrawal', 'T321');

// ─────────────────────────────────────────────────────
// T322 — Business-rule errors
// ─────────────────────────────────────────────────────

it('returns 422 when balance is zero and a withdrawal is requested', function (): void {
    [, , $token] = makeVendorWithWallet(balanceMinor: 0);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'zero-balance')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload(['amount_minor' => 10000]))
        ->assertStatus(422);
})->group('settlement', 'withdrawal', 'T322');

it('returns 422 when amount is below the minimum (10 000 piastres = 100 EGP)', function (): void {
    [, , $token] = makeVendorWithWallet(200000);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'below-minimum')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload(['amount_minor' => 100]))
        ->assertStatus(422);
})->group('settlement', 'withdrawal', 'T322');

it('returns 422 when vendor already has a pending withdrawal', function (): void {
    [$vp, , $token] = makeVendorWithWallet(500000);

    // First withdrawal
    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'first-withdrawal')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload(['amount_minor' => 10000]))
        ->assertStatus(201);

    // Second withdrawal — should be blocked by pending_lock UNIQUE constraint
    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'second-withdrawal')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload(['amount_minor' => 10000]))
        ->assertStatus(422);
})->group('settlement', 'withdrawal', 'T322');

it('returns 422 when requested amount exceeds available balance', function (): void {
    [, , $token] = makeVendorWithWallet(balanceMinor: 20000); // only 200 EGP

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'over-balance')
        ->postJson('/api/v1/vendor/withdrawals', withdrawalPayload(['amount_minor' => 50000]))
        ->assertStatus(422);
})->group('settlement', 'withdrawal', 'T322');
