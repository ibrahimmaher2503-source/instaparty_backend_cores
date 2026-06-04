<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// G1 — vendor auth flows. The OTP + login endpoints are shared (role-agnostic
// by design); these tests prove the full vendor mobile login journeys work
// end-to-end: credential → Sanctum token → /vendor/me.
// ─────────────────────────────────────────────────────────────────────────────

it('vendor can log in via phone OTP and reach /vendor/me with the issued token', function (): void {
    $user = User::factory()->asVendor()->create([
        'phone_e164' => '+201009990001',
        'phone_verified_at' => null,
    ]);
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    // Step 1 — request the OTP
    $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => '+201009990001'])
        ->assertStatus(202);

    // Step 2 — verify and receive a Sanctum token
    $verify = $this->postJson('/api/v1/phone/verify', [
        'phone_e164' => '+201009990001',
        'code' => '000000',
    ])->assertOk();

    $token = $verify->json('data.token');
    expect($token)->not->toBeNull();

    // Step 3 — the token reaches the vendor surface
    $this->flushHeaders();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/me')
        ->assertOk()
        ->assertJsonPath('data.phone_e164', '+201009990001');
})->group('identity', 'vendor-auth', 'otp');

it('vendor can log in via email + password and reach /vendor/me', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create([
        'email' => 'vendor-auth@test.local',
        'password' => Hash::make('secret-password-1'),
    ]);
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $login = $this->postJson('/api/v1/login', [
        'login' => 'vendor-auth@test.local',
        'password' => 'secret-password-1',
        'device_name' => 'vendor-app',
    ])->assertOk();

    $token = $login->json('data.token');
    expect($token)->not->toBeNull();

    $this->flushHeaders();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'vendor-auth@test.local');
})->group('identity', 'vendor-auth');

it('vendor OTP requests are rate limited like customer ones', function (): void {
    $user = User::factory()->asVendor()->create(['phone_e164' => '+201009990002']);
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => '+201009990002'])
            ->assertStatus(202);
    }

    $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => '+201009990002'])
        ->assertStatus(429);
})->group('identity', 'vendor-auth', 'otp', 'rate-limit');

it('a customer token cannot reach /vendor/me even after OTP login', function (): void {
    User::factory()->asCustomer()->create([
        'phone_e164' => '+201009990003',
        'phone_verified_at' => null,
    ]);

    $verify = $this->postJson('/api/v1/phone/verify', [
        'phone_e164' => '+201009990003',
        'code' => '000000',
    ])->assertOk();

    $this->flushHeaders();
    $this->withHeader('Authorization', "Bearer {$verify->json('data.token')}")
        ->getJson('/api/v1/vendor/me')
        ->assertStatus(403);
})->group('identity', 'vendor-auth', 'auth');

it('vendor can log out and the token is revoked', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $token = $user->createToken('vendor-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertStatus(204);

    // Reset the cached guard so the revoked token is re-evaluated.
    $this->flushHeaders();
    $this->app->get('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/me')
        ->assertStatus(401);
})->group('identity', 'vendor-auth');
