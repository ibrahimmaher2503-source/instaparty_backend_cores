<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/me (G2)
// ─────────────────────────────────────────────────────────────────────────────

it('returns the vendor identity payload with roles and approved types', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/vendor/me')
        ->assertOk()
        ->assertJsonPath('data.public_id', $user->public_id)
        ->assertJsonPath('data.vendor_profile.public_id', $vendor->public_id)
        ->assertJsonPath('data.vendor_profile.approval_status', 'approved')
        ->assertJsonPath('data.vendor_profile.approved_product_types.0', 'rental')
        ->assertJsonStructure(['data' => [
            'public_id', 'name', 'email', 'phone_e164', 'preferred_locale', 'timezone', 'roles',
            'vendor_profile' => ['public_id', 'business_name', 'slug', 'approval_status', 'approved_product_types'],
        ]]);

    expect(collect($this->getJson('/api/v1/vendor/me')->json('data.roles')))->toContain('vendor');
})->group('identity', 'vendor-me');

it('does not leak commission or compliance internals', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $payload = json_encode($this->actingAs($user)->getJson('/api/v1/vendor/me')->json());

    expect($payload)->not->toContain('commission')
        ->and($payload)->not->toContain('bank_iban');
})->group('identity', 'vendor-me', 'privacy');

it('returns 401 when unauthenticated on /vendor/me', function (): void {
    $this->getJson('/api/v1/vendor/me')->assertStatus(401);
})->group('identity', 'vendor-me', 'auth');

it('returns 403 when a customer calls /vendor/me', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/me')
        ->assertStatus(403);
})->group('identity', 'vendor-me', 'auth');
