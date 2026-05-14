<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function vendorWithUser(): User
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $user->load('vendorProfile');

    return $user->fresh(['vendorProfile']);
}

function validProgramPayload(): array
{
    return [
        'name' => ['en' => 'Birthday Points', 'ar' => 'نقاط أعياد الميلاد'],
        'terms' => ['en' => 'Earn and redeem.', 'ar' => 'اكسب واستبدل.'],
        'is_active' => true,
        'points_per_currency_unit' => 1.0,
        'points_value_minor' => 100,
        'points_value_currency' => 'EGP',
        'min_points_to_redeem' => 100,
        'max_redeem_pct' => 50,
        'points_expire_after_days' => 365,
    ];
}

it('vendor can create a loyalty program', function (): void {
    $vendor = vendorWithUser();

    $response = $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->postJson('/api/v1/vendor/loyalty/program', validProgramPayload());

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['public_id', 'name', 'is_active', 'points_value']])
        ->assertJsonPath('data.is_active', true);

    expect(LoyaltyProgram::query()->where('vendor_profile_id', $vendor->vendorProfile->id)->exists())
        ->toBeTrue();
})->group('loyalty', 'feature');

it('returns 401 when unauthenticated', function (): void {
    $this->postJson('/api/v1/vendor/loyalty/program', validProgramPayload())
        ->assertStatus(401);
})->group('loyalty', 'feature');

it('returns 403 for users without a vendor profile', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->postJson('/api/v1/vendor/loyalty/program', validProgramPayload())
        ->assertStatus(403);
})->group('loyalty', 'feature');

it('returns 422 when name is missing', function (): void {
    $vendor = vendorWithUser();
    $payload = validProgramPayload();
    unset($payload['name']);

    $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->postJson('/api/v1/vendor/loyalty/program', $payload)
        ->assertStatus(422);
})->group('loyalty', 'feature');

it('returns English name when Accept-Language is en', function (): void {
    $vendor = vendorWithUser();

    $response = $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/vendor/loyalty/program', validProgramPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Birthday Points');
})->group('loyalty', 'feature', 'locale');

it('returns Arabic name when Accept-Language is ar', function (): void {
    $vendor = vendorWithUser();

    $response = $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/vendor/loyalty/program', validProgramPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'نقاط أعياد الميلاد');
})->group('loyalty', 'feature', 'locale');

it('PUT updates an existing program', function (): void {
    $vendor = vendorWithUser();

    $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->postJson('/api/v1/vendor/loyalty/program', validProgramPayload())
        ->assertStatus(201);

    $updated = $this->actingAs($vendor)
        ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
        ->putJson('/api/v1/vendor/loyalty/program', [
            'name' => ['en' => 'Updated', 'ar' => 'محدّث'],
            'is_active' => false,
            'points_per_currency_unit' => 2.0,
            'points_value_minor' => 50,
            'min_points_to_redeem' => 200,
            'max_redeem_pct' => 30,
        ]);

    $updated->assertStatus(200)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.name', 'Updated');
})->group('loyalty', 'feature');
