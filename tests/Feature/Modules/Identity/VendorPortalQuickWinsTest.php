<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * Vendor-portal Phase 3 quick wins: business-hours GET (8.1), coverage CRUD
 * (9.1/9.3/9.4/9.5), submit-review + clone routes (4.10/4.12), import
 * status (5.3).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('reads the weekly business hours', function (): void {
    VendorBusinessHour::create([
        'vendor_profile_id' => $this->vendor->id,
        'day_of_week' => 1,
        'opens_at' => '09:00:00',
        'closes_at' => '17:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/business-hours')
        ->assertStatus(200);

    expect($response->json('data.hours.0'))->toBe(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00']);
})->group('identity', 'vendor-portal');

it('lists, updates and removes coverage areas — own only', function (): void {
    $city = City::factory()->create();
    VendorCoverageArea::create([
        'vendor_profile_id' => $this->vendor->id,
        'city_id' => $city->id,
        'delivery_fee_minor' => 3000,
        'delivery_fee_currency' => 'EGP',
    ]);

    $this->actingAs($this->user)->getJson('/api/v1/vendor/coverage-areas')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/vendor/coverage-areas/{$city->id}", ['delivery_fee_minor' => 4500])
        ->assertStatus(200);

    expect((int) VendorCoverageArea::query()->where('city_id', $city->id)->value('delivery_fee_minor'))->toBe(4500);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/coverage-areas/{$city->id}")
        ->assertStatus(200);

    expect(VendorCoverageArea::query()->where('vendor_profile_id', $this->vendor->id)->count())->toBe(0);
})->group('identity', 'vendor-portal', 'coverage');

it('cannot update another vendor coverage area — 404', function (): void {
    $other = VendorProfile::factory()->approved()->create();
    $city = City::factory()->create();
    VendorCoverageArea::create([
        'vendor_profile_id' => $other->id,
        'city_id' => $city->id,
        'delivery_fee_minor' => 1000,
        'delivery_fee_currency' => 'EGP',
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/vendor/coverage-areas/{$city->id}", ['delivery_fee_minor' => 1])
        ->assertStatus(404);
})->group('identity', 'vendor-portal', 'coverage', 'isolation');

it('available-cities excludes already covered ones', function (): void {
    $covered = City::factory()->create(['is_active' => true]);
    $free = City::factory()->create(['is_active' => true]);
    VendorCoverageArea::create([
        'vendor_profile_id' => $this->vendor->id,
        'city_id' => $covered->id,
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/coverage-areas/available-cities')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids)->toContain($free->public_id)->not->toContain($covered->public_id);
})->group('identity', 'vendor-portal', 'coverage');

it('submits a draft service for review via REST', function (): void {
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $this->vendor->id]); // draft

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/services/{$service->public_id}/submit-review")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'pending_review');
})->group('catalog', 'vendor-portal');

it('clones an own service via REST', function (): void {
    $service = Service::factory()->rental()->published()->create(['vendor_profile_id' => $this->vendor->id]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/services/{$service->public_id}/clone")
        ->assertStatus(201);

    expect($response->json('data.public_id'))->not->toBe($service->public_id);
})->group('catalog', 'vendor-portal');

it('cannot submit or clone another vendor service — 404', function (): void {
    $foreign = Service::factory()->rental()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/services/{$foreign->public_id}/submit-review")
        ->assertStatus(404);

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/services/{$foreign->public_id}/clone")
        ->assertStatus(404);
})->group('catalog', 'vendor-portal', 'isolation');

it('polls import status scoped to the owning vendor', function (): void {
    $import = ExcelImport::query()->create([
        'public_id' => (string) Str::ulid(),
        'vendor_profile_id' => $this->vendor->id,
        'product_type' => ProductType::Rental,
        'status' => 'completed',
        'original_filename' => 'rentals.xlsx',
        'stored_path' => 'imports/rentals.xlsx',
        'total_rows' => 10,
        'imported_rows' => 10,
        'error_rows' => 0,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/catalog/import/{$import->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 10);

    $otherUser = User::factory()->phoneVerified()->asVendor()->create();
    VendorProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->getJson("/api/v1/vendor/catalog/import/{$import->public_id}")
        ->assertStatus(404);
})->group('catalog', 'vendor-portal', 'isolation');
