<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Phase 3 D4 — public catalog + geography gaps: occasion detail (5.2),
 * category detail (5.4), field schemas (5.5/FR-20), service themes (5.6),
 * regions drill-down (18.6 reshaped to the LOCKED hierarchy).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('shows an occasion by slug', function (): void {
    $occasion = Occasion::factory()->create(['is_active' => true]);

    $this->getJson("/api/v1/customer/occasions/{$occasion->code}")
        ->assertStatus(200)
        ->assertJsonPath('data.public_id', $occasion->public_id);
})->group('catalog');

it('shows a category by public id and 404s inactive ones', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $inactive = Category::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/customer/categories/{$category->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.public_id', $category->public_id);

    $this->getJson("/api/v1/customer/categories/{$inactive->public_id}")
        ->assertStatus(404);
})->group('catalog');

it('returns filterable field schemas per product type', function (): void {
    $category = Category::factory()->create(['is_active' => true]);

    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id,
        'product_type' => ProductType::Rental,
        'field_key' => 'requires_electricity',
        'field_type' => 'boolean',
        'is_filterable' => true,
    ]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id,
        'product_type' => ProductType::Sale,
        'field_key' => 'flavor',
        'is_filterable' => false, // hidden from the public filter sheet
    ]);

    $response = $this->getJson("/api/v1/customer/categories/{$category->public_id}/field-schemas?type=rental")
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.field_key'))->toBe('requires_electricity')
        ->and($response->json('data.0.applies_to_product_types'))->toBe(['rental']);
})->group('catalog', 'rental', 'sale', 'digital');

it('lists active service themes only', function (): void {
    $active = ServiceTheme::factory()->create(['is_active' => true, 'name' => ['en' => 'Unicorn', 'ar' => 'يونيكورن']]);
    $inactive = ServiceTheme::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/customer/service-themes')
        ->assertStatus(200);

    // Dev seeders add demo themes — assert membership, not global counts.
    $ids = collect($response->json('data'))->pluck('public_id');

    expect($ids)->toContain($active->public_id)
        ->and($ids)->not->toContain($inactive->public_id)
        ->and(collect($response->json('data'))->firstWhere('public_id', $active->public_id)['name'])->toBe('Unicorn');
})->group('catalog');

it('drills down governorate → regions → cities', function (): void {
    $governorate = Governorate::factory()->create(['is_active' => true]);
    $region = Region::factory()->create([
        'governorate_id' => $governorate->id,
        'is_active' => true,
        'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
    ]);
    $city = City::factory()->create([
        'governorate_id' => $governorate->id,
        'region_id' => $region->id,
        'is_active' => true,
    ]);

    $regions = $this->getJson("/api/v1/customer/governorates/{$governorate->public_id}/regions")
        ->assertStatus(200);

    expect($regions->json('data.0.public_id'))->toBe($region->public_id)
        ->and($regions->json('data.0.name'))->toBe('Downtown');

    $cities = $this->getJson("/api/v1/customer/regions/{$region->public_id}/cities")
        ->assertStatus(200);

    expect(collect($cities->json('data'))->pluck('public_id'))->toContain($city->public_id);
})->group('geography');
