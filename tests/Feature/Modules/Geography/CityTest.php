<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('can create a city with translatable name', function () {
    $governorate = Governorate::factory()->create();
    $region = Region::factory()->for($governorate)->create();

    $city = City::create([
        'public_id' => (string) Str::ulid(),
        'region_id' => $region->id,
        'governorate_id' => $governorate->id,
        'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($city->getTranslation('name', 'en'))->toBe('Nasr City')
        ->and($city->getTranslation('name', 'ar'))->toBe('مدينة نصر');
})->group('geography', 'migrations');

it('belongs to a region and a governorate', function () {
    $city = City::factory()->create();

    expect($city->region)->not->toBeNull()
        ->and($city->governorate)->not->toBeNull()
        ->and($city->governorate_id)->toBe($city->region->governorate_id);
})->group('geography');

it('has a public_id ULID that is 26 characters', function () {
    $city = City::factory()->create();

    expect($city->public_id)->toHaveLength(26);
})->group('geography');

it('denorm governorate_id matches region governorate_id when factory derives it', function () {
    $city = City::factory()->create();

    expect($city->governorate_id)->toBe($city->region->governorate_id);
})->group('geography');

it('scopeActive filters out inactive cities', function () {
    City::factory()->create(['is_active' => true]);
    City::factory()->inactive()->create();

    expect(City::active()->get())->toHaveCount(1);
})->group('geography');

it('scopeForGovernorate returns only matching cities', function () {
    $g1 = Governorate::factory()->create();
    $g2 = Governorate::factory()->create();
    $r1 = Region::factory()->for($g1)->create();
    $r2 = Region::factory()->for($g2)->create();

    City::factory()->count(2)->create(['region_id' => $r1->id, 'governorate_id' => $g1->id]);
    City::factory()->create(['region_id' => $r2->id, 'governorate_id' => $g2->id]);

    expect(City::forGovernorate($g1->id)->get())->toHaveCount(2);
})->group('geography');

it('scopeForRegion returns only matching cities', function () {
    $region = Region::factory()->create();
    City::factory()->count(3)->create([
        'region_id' => $region->id,
        'governorate_id' => $region->governorate_id,
    ]);
    City::factory()->create();

    expect(City::forRegion($region->id)->get())->toHaveCount(3);
})->group('geography');

it('route key is public_id', function () {
    $city = City::factory()->create();

    expect($city->getRouteKeyName())->toBe('public_id');
})->group('geography');

it('throws QueryException when deleting a region with cities (restrictOnDelete)', function () {
    $region = Region::factory()->create();
    City::factory()->create([
        'region_id' => $region->id,
        'governorate_id' => $region->governorate_id,
    ]);

    $region->delete();
})->throws(QueryException::class)->group('geography', 'migrations');

it('throws QueryException when deleting a governorate with cities (restrictOnDelete)', function () {
    $governorate = Governorate::factory()->create();
    $region = Region::factory()->for($governorate)->create();
    City::factory()->create([
        'region_id' => $region->id,
        'governorate_id' => $governorate->id,
    ]);

    // Region first would also fail (FK to gov). We must drop the region's cities first to isolate gov FK.
    // Cities still reference governorate_id directly, so deleting governorate must throw.
    $governorate->delete();
})->throws(QueryException::class)->group('geography', 'migrations');
