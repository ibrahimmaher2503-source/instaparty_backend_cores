<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('can create a governorate with translatable name', function () {
    $country = Country::factory()->create();

    $governorate = Governorate::create([
        'public_id' => (string) Str::ulid(),
        'country_id' => $country->id,
        'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
        'code' => 'EG-CAI',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($governorate->getTranslation('name', 'en'))->toBe('Cairo')
        ->and($governorate->getTranslation('name', 'ar'))->toBe('القاهرة');
})->group('geography', 'migrations');

it('has a public_id ULID that is 26 characters', function () {
    $governorate = Governorate::factory()->create();

    expect($governorate->public_id)->toHaveLength(26);
})->group('geography');

it('belongs to a country', function () {
    $country = Country::factory()->create();
    $governorate = Governorate::factory()->for($country)->create();

    expect($governorate->country->id)->toBe($country->id);
})->group('geography');

it('has many regions', function () {
    $governorate = Governorate::factory()->create();
    Region::factory()->for($governorate)->count(3)->create();

    expect($governorate->regions)->toHaveCount(3);
})->group('geography');

it('scopeActive filters out inactive governorates', function () {
    Governorate::factory()->create(['is_active' => true]);
    Governorate::factory()->inactive()->create();

    expect(Governorate::active()->get())->toHaveCount(1);
})->group('geography');

it('route key is public_id', function () {
    $governorate = Governorate::factory()->create();

    expect($governorate->getRouteKeyName())->toBe('public_id');
})->group('geography');

it('throws QueryException when deleting a country with governorates (restrictOnDelete)', function () {
    $country = Country::factory()->create();
    Governorate::factory()->for($country)->create();

    $country->delete();
})->throws(QueryException::class)->group('geography', 'migrations');
