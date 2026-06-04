<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 C3 — POST /customer/discovery/recommendations (ruling #1:
 * service scoring replaces packages; occasion 40 / city 30 / price 10;
 * age accepted-but-unscored in Phase 1).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('ranks occasion+city+budget matches above partial matches', function (): void {
    $occasion = Occasion::factory()->create();
    $city = City::factory()->create();

    $vendorInCity = VendorProfile::factory()->approved()->create();
    VendorCoverageArea::create([
        'vendor_profile_id' => $vendorInCity->id,
        'city_id' => $city->id,
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
    ]);

    $fullMatch = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $vendorInCity->id,
        'base_price_minor' => 40000,
    ]);
    DB::table('occasion_category')->insert([
        'occasion_id' => $occasion->id,
        'category_id' => $fullMatch->category_id,
    ]);

    // Over budget + wrong occasion + uncovered city.
    Service::factory()->sale()->published()->create(['base_price_minor' => 999999]);

    $response = $this->postJson('/api/v1/customer/discovery/recommendations', [
        'occasion_public_id' => $occasion->public_id,
        'city_public_id' => $city->public_id,
        'budget_minor' => 50000,
        'child_age' => 6,
    ])->assertStatus(200);

    expect($response->json('data.0.public_id'))->toBe($fullMatch->public_id)
        ->and($response->json('meta.age_scored'))->toBeFalse();
})->group('discovery', 'recommendations');

it('works for guests with no context at all', function (): void {
    Service::factory()->digital()->published()->create();

    $response = $this->postJson('/api/v1/customer/discovery/recommendations', [])
        ->assertStatus(200);

    expect($response->json('data'))->not->toBeEmpty();
})->group('discovery', 'recommendations');

it('only recommends published services', function (): void {
    $draft = Service::factory()->rental()->create(); // draft

    $response = $this->postJson('/api/v1/customer/discovery/recommendations', [])
        ->assertStatus(200);

    expect(collect($response->json('data'))->pluck('public_id'))->not->toContain($draft->public_id);
})->group('discovery', 'recommendations');

it('validates limit and budget bounds', function (): void {
    $this->postJson('/api/v1/customer/discovery/recommendations', ['limit' => 100])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);

    $this->postJson('/api/v1/customer/discovery/recommendations', ['budget_minor' => -5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['budget_minor']);
})->group('discovery', 'recommendations');
