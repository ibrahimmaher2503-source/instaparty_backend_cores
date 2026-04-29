<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Events\CustomerRegistered;
use App\Modules\Identity\Domain\Events\PhoneVerified;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Cache::flush();
});

// =====================================================================
// T020 — Registration happy path + validation errors
// =====================================================================

it('registers a customer with public_id and customer_profile', function (): void {
    Event::fake([CustomerRegistered::class]);

    $payload = [
        'name' => 'Sara Test',
        'phone_e164' => '+201001234567',
        'email' => 'sara@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'preferred_locale' => 'ar',
    ];

    $response = $this->postJson('/api/v1/register/customer', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.phone_e164', '+201001234567')
        ->assertJsonPath('data.preferred_locale', 'ar')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone_e164']]);

    $user = User::where('phone_e164', '+201001234567')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('customer'))->toBeTrue()
        ->and(CustomerProfile::where('user_id', $user->id)->exists())->toBeTrue()
        ->and($user->public_id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/i');

    Event::assertDispatched(CustomerRegistered::class);
})->group('identity', 'us1');

it('rejects duplicate phone with 422', function (): void {
    User::factory()->create(['phone_e164' => '+201001234567']);

    $response = $this->postJson('/api/v1/register/customer', [
        'name' => 'X',
        'phone_e164' => '+201001234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['phone_e164']);
})->group('identity', 'us1');

it('rejects duplicate email with 422', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->postJson('/api/v1/register/customer', [
        'name' => 'X',
        'phone_e164' => '+201009999999',
        'email' => 'taken@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
})->group('identity', 'us1');

it('rejects missing password_confirmation with 422', function (): void {
    $response = $this->postJson('/api/v1/register/customer', [
        'name' => 'X',
        'phone_e164' => '+201008888888',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
})->group('identity', 'us1');

// =====================================================================
// T021 — Phone verify + OTP rate limit (429 on 4th send)
// =====================================================================

it('verifies phone with a valid OTP and fires PhoneVerified', function (): void {
    Event::fake([PhoneVerified::class]);
    $user = User::factory()->create(['phone_e164' => '+201007777777', 'phone_verified_at' => null]);

    $response = $this->postJson('/api/v1/phone/verify', [
        'phone_e164' => '+201007777777',
        'code' => '000000',
    ]);

    $response->assertStatus(200);
    expect($user->fresh()->phone_verified_at)->not->toBeNull();
    Event::assertDispatched(PhoneVerified::class);
})->group('identity', 'us1');

it('rejects an OTP that is not 6 digits with 422', function (): void {
    User::factory()->create(['phone_e164' => '+201006666666']);

    $response = $this->postJson('/api/v1/phone/verify', [
        'phone_e164' => '+201006666666',
        'code' => '123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['code']);
})->group('identity', 'us1');

it('returns 429 after 3 OTP send attempts in 10 minutes', function (): void {
    $phone = '+201005555555';

    for ($i = 1; $i <= 3; $i++) {
        $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => $phone])
            ->assertStatus(202);
    }

    $response = $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => $phone]);

    $response->assertStatus(429);
})->group('identity', 'us1');

// =====================================================================
// T022 — Login / logout flows
// =====================================================================

it('logs in with phone+password and returns a Sanctum token', function (): void {
    User::factory()->create([
        'phone_e164' => '+201004444444',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'login' => '+201004444444',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.phone_e164', '+201004444444')
        ->assertJsonStructure(['data' => ['user', 'token']]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
})->group('identity', 'us1');

it('rejects login with wrong password (422)', function (): void {
    User::factory()->create([
        'phone_e164' => '+201003333333',
        'password' => bcrypt('correct'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'login' => '+201003333333',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['login']);
})->group('identity', 'us1');

it('logs out and revokes the current token (204)', function (): void {
    $user = User::factory()->create()->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout');

    $response->assertStatus(204);
    expect($user->fresh()->tokens()->count())->toBe(0);
})->group('identity', 'us1');

it('returns 401 when accessing a protected route without a token', function (): void {
    $this->getJson('/api/v1/customer/profile')->assertStatus(401);
})->group('identity', 'us1');

// =====================================================================
// T023 — Locale via Accept-Language
// =====================================================================

it('honors Accept-Language: ar and sets app locale to ar', function (): void {
    $response = $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/register/customer', [
            'name' => 'Locale AR',
            'phone_e164' => '+201002222222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(201);
    expect(app()->getLocale())->toBe('ar');
})->group('identity', 'us1');

it('honors Accept-Language: en and sets app locale to en', function (): void {
    $response = $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/register/customer', [
            'name' => 'Locale EN',
            'phone_e164' => '+201001111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(201);
    expect(app()->getLocale())->toBe('en');
})->group('identity', 'us1');

it('defaults to en when Accept-Language header is missing', function (): void {
    $this->postJson('/api/v1/register/customer', [
        'name' => 'No Locale',
        'phone_e164' => '+201000000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    expect(app()->getLocale())->toBe('en');
})->group('identity', 'us1');
