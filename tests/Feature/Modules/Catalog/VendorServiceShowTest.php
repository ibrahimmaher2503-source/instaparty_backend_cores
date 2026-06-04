<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeShowVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();

    return VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/services/{publicId} — show (G10)
// ─────────────────────────────────────────────────────────────────────────────

it('returns the vendor service detail for any product type', function (ProductType $productType): void {
    $vendor = makeShowVendor();
    $state = $productType->value; // factory state names match enum values

    $service = Service::factory()->{$state}()->create(['vendor_profile_id' => $vendor->id]);

    $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/services/{$service->public_id}")
        ->assertOk()
        ->assertJsonPath('data.public_id', $service->public_id)
        ->assertJsonPath('data.product_type', $productType->value);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
])->group('catalog', 'service-show', 'rental', 'sale', 'digital');

it('returns 404 when another vendor requests the service', function (): void {
    $owner = makeShowVendor();
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $owner->id]);

    $other = makeShowVendor();

    $this->actingAs($other->user)
        ->getJson("/api/v1/vendor/services/{$service->public_id}")
        ->assertStatus(404);
})->group('catalog', 'service-show', 'auth');

it('returns 404 for an unknown service public id', function (): void {
    $vendor = makeShowVendor();

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/services/'.Str::ulid()->toBase32())
        ->assertStatus(404);
})->group('catalog', 'service-show');

it('returns 401 when unauthenticated on service show', function (): void {
    $this->getJson('/api/v1/vendor/services/'.Str::ulid()->toBase32())
        ->assertStatus(401);
})->group('catalog', 'service-show', 'auth');
