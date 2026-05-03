<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('can create a region with translatable name', function () {
    $governorate = Governorate::factory()->create();

    $region = Region::create([
        'public_id' => (string) Str::ulid(),
        'governorate_id' => $governorate->id,
        'name' => ['en' => 'East Cairo', 'ar' => 'شرق القاهرة'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($region->getTranslation('name', 'en'))->toBe('East Cairo')
        ->and($region->getTranslation('name', 'ar'))->toBe('شرق القاهرة');
})->group('geography', 'migrations');

it('belongs to a governorate', function () {
    $governorate = Governorate::factory()->create();
    $region = Region::factory()->for($governorate)->create();

    expect($region->governorate->id)->toBe($governorate->id);
})->group('geography');

it('has a public_id ULID that is 26 characters', function () {
    $region = Region::factory()->create();

    expect($region->public_id)->toHaveLength(26);
})->group('geography');

it('scopeActive filters out inactive regions', function () {
    $active = Region::factory()->create(['is_active' => true]);
    $inactive = Region::factory()->inactive()->create();

    expect(Region::active()->whereKey($active->id)->exists())->toBeTrue()
        ->and(Region::active()->whereKey($inactive->id)->exists())->toBeFalse();
})->group('geography');

it('scopeForGovernorate returns only matching regions', function () {
    $g1 = Governorate::factory()->create();
    $g2 = Governorate::factory()->create();
    Region::factory()->for($g1)->count(2)->create();
    Region::factory()->for($g2)->create();

    expect(Region::forGovernorate($g1->id)->get())->toHaveCount(2);
})->group('geography');

it('route key is public_id', function () {
    $region = Region::factory()->create();

    expect($region->getRouteKeyName())->toBe('public_id');
})->group('geography');

it('throws QueryException when deleting a governorate with regions (restrictOnDelete)', function () {
    $governorate = Governorate::factory()->create();
    Region::factory()->for($governorate)->create();

    $governorate->delete();
})->throws(QueryException::class)->group('geography', 'migrations');
