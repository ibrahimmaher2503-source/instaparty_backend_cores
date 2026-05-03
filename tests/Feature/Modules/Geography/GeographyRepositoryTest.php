<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Infrastructure\Repositories\EloquentGeographyRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves GeographyRepository from the container', function () {
    $repo = app(GeographyRepository::class);

    expect($repo)->toBeInstanceOf(EloquentGeographyRepository::class);
})->group('geography');

it('findCityById returns the city', function () {
    $city = City::factory()->create();

    expect(app(GeographyRepository::class)->findCityById($city->id)->id)->toBe($city->id);
})->group('geography');

it('findCityById returns null when not found', function () {
    expect(app(GeographyRepository::class)->findCityById(999999))->toBeNull();
})->group('geography');

it('findCityByPublicId returns the city', function () {
    $city = City::factory()->create();

    expect(app(GeographyRepository::class)->findCityByPublicId($city->public_id)->id)->toBe($city->id);
})->group('geography');

it('findCityByPublicId returns null when not found', function () {
    expect(app(GeographyRepository::class)->findCityByPublicId('00000000000000000000000000'))->toBeNull();
})->group('geography');

it('findGovernorateByPublicId returns the governorate', function () {
    $gov = Governorate::factory()->create();

    expect(app(GeographyRepository::class)->findGovernorateByPublicId($gov->public_id)->id)->toBe($gov->id);
})->group('geography');

it('listCitiesByGovernorate returns only active cities for the governorate', function () {
    $gov = Governorate::factory()->create();
    $region = Region::factory()->for($gov)->create();
    City::factory()->count(2)->create(['region_id' => $region->id, 'governorate_id' => $gov->id, 'is_active' => true]);
    City::factory()->create(['region_id' => $region->id, 'governorate_id' => $gov->id, 'is_active' => false]);
    City::factory()->create();

    expect(app(GeographyRepository::class)->listCitiesByGovernorate($gov->id))->toHaveCount(2);
})->group('geography');

it('searchCities matches by EN name', function () {
    $region = Region::factory()->create();
    $city = City::factory()->create([
        'region_id' => $region->id,
        'governorate_id' => $region->governorate_id,
        'name' => ['en' => 'Saffron Heights', 'ar' => 'التلال الزعفرانية'],
    ]);

    $results = app(GeographyRepository::class)->searchCities('Saffron');

    expect($results->contains('id', $city->id))->toBeTrue();
})->group('geography');

it('searchCities matches by AR name', function () {
    $region = Region::factory()->create();
    $city = City::factory()->create([
        'region_id' => $region->id,
        'governorate_id' => $region->governorate_id,
        'name' => ['en' => 'Saffron Heights', 'ar' => 'التلال الزعفرانية'],
    ]);

    $results = app(GeographyRepository::class)->searchCities('زعفرانية');

    expect($results->contains('id', $city->id))->toBeTrue();
})->group('geography');

it('searchCities returns empty collection on no match', function () {
    City::factory()->create();

    expect(app(GeographyRepository::class)->searchCities('nonexistent-xyz-123'))->toHaveCount(0);
})->group('geography');

it('activeGovernorates returns only active ones ordered by sort_order', function () {
    Governorate::factory()->inactive()->create(['sort_order' => 1]);
    $g2 = Governorate::factory()->create(['sort_order' => 2]);
    $g1 = Governorate::factory()->create(['sort_order' => 1]);

    $results = app(GeographyRepository::class)->activeGovernorates();

    $orderedIds = $results->pluck('id')->all();
    $g1Position = array_search($g1->id, $orderedIds, true);
    $g2Position = array_search($g2->id, $orderedIds, true);

    expect($g1Position)->not->toBeFalse()
        ->and($g2Position)->not->toBeFalse()
        ->and($g1Position)->toBeLessThan($g2Position);
})->group('geography');
