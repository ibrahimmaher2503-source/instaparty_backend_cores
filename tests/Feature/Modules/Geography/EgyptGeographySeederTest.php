<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds all 27 Egypt governorates with bilingual names', function (): void {
    $this->seed(EgyptGeographySeeder::class);

    $governorates = Governorate::query()->withCount(['regions', 'cities'])->get();

    expect(Country::query()->where('iso2', 'EG')->count())->toBe(1)
        ->and($governorates)->toHaveCount(27)
        ->and($governorates->pluck('code')->unique())->toHaveCount(27);

    foreach ($governorates as $governorate) {
        expect($governorate->public_id)->toHaveLength(26)
            ->and($governorate->getTranslation('name', 'en'))->not->toBe('')
            ->and($governorate->getTranslation('name', 'ar'))->not->toBe('')
            ->and($governorate->regions_count)->toBeGreaterThan(0)
            ->and($governorate->cities_count)->toBeGreaterThan(0);
    }

    foreach (City::query()->get() as $city) {
        expect($city->governorate_id)->toBe($city->region->governorate_id)
            ->and($city->getTranslation('name', 'en'))->not->toBe('')
            ->and($city->getTranslation('name', 'ar'))->not->toBe('');
    }
})->group('geography', 'seeders');

it('can run EgyptGeographySeeder twice without duplicates or public_id churn', function (): void {
    $this->seed(EgyptGeographySeeder::class);

    $counts = [
        'countries' => Country::query()->count(),
        'governorates' => Governorate::query()->count(),
        'regions' => Region::query()->count(),
        'cities' => City::query()->count(),
    ];
    $publicIds = Governorate::query()
        ->orderBy('code')
        ->pluck('public_id', 'code')
        ->all();

    $this->seed(EgyptGeographySeeder::class);

    expect(Country::query()->count())->toBe($counts['countries'])
        ->and(Governorate::query()->count())->toBe($counts['governorates'])
        ->and(Region::query()->count())->toBe($counts['regions'])
        ->and(City::query()->count())->toBe($counts['cities'])
        ->and(Governorate::query()->orderBy('code')->pluck('public_id', 'code')->all())->toBe($publicIds);
})->group('geography', 'seeders');
