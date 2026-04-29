<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

function vendorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Vendor Owner',
        'phone_e164' => '+201001112222',
        'email' => 'vendor@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => ['en' => 'Happy Balloons', 'ar' => 'بالونات سعيدة'],
        'business_type' => 'individual',
        'primary_governorate_id' => 1,
        'primary_city_id' => 1,
    ], $overrides);
}

it('registers a vendor with approval_status=pending and slug', function (): void {
    Event::fake([VendorRegistered::class]);

    $response = $this->postJson('/api/v1/register/vendor', vendorPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.approval_status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'business_name', 'slug', 'approval_status']]);

    expect($response->json('data.slug'))->toStartWith('happy-balloons');

    $vp = VendorProfile::query()->whereHas('user', fn ($q) => $q->where('phone_e164', '+201001112222'))->first();
    expect($vp)->not->toBeNull()
        ->and($vp->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($vp->user->hasRole('vendor'))->toBeTrue();

    Event::assertDispatched(VendorRegistered::class);
})->group('identity', 'us2');

it('rejects vendor registration missing business_name.en (422)', function (): void {
    $payload = vendorPayload();
    unset($payload['business_name']['en']);

    $this->postJson('/api/v1/register/vendor', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['business_name.en']);
})->group('identity', 'us2');

it('rejects vendor registration with duplicate phone (422)', function (): void {
    User::factory()->create(['phone_e164' => '+201001112222']);

    $this->postJson('/api/v1/register/vendor', vendorPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone_e164']);
})->group('identity', 'us2');

it('rejects vendor registration with bad city_id (422)', function (): void {
    $this->postJson('/api/v1/register/vendor', vendorPayload(['primary_city_id' => 99999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['primary_city_id']);
})->group('identity', 'us2');

it('returns vendor profile in Arabic when Accept-Language: ar', function (): void {
    $vp = VendorProfile::factory()->create([
        'business_name' => ['en' => 'Hello Co', 'ar' => 'شركة مرحبا'],
    ]);
    $token = $vp->user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/vendor/profile');

    $response->assertStatus(200)
        ->assertJsonPath('data.business_name', 'شركة مرحبا');
})->group('identity', 'us2');

it('returns vendor profile in English when Accept-Language: en', function (): void {
    $vp = VendorProfile::factory()->create([
        'business_name' => ['en' => 'Hello Co', 'ar' => 'شركة مرحبا'],
    ]);
    $token = $vp->user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Accept-Language', 'en')
        ->getJson('/api/v1/vendor/profile');

    $response->assertStatus(200)
        ->assertJsonPath('data.business_name', 'Hello Co');
})->group('identity', 'us2');

it('returns 401 when fetching vendor profile without a token', function (): void {
    $this->getJson('/api/v1/vendor/profile')->assertStatus(401);
})->group('identity', 'us2');

it('returns 403 when a customer tries to fetch vendor profile', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/vendor/profile')
        ->assertStatus(403);
})->group('identity', 'us2');
