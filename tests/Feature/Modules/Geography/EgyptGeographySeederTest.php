<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('seeds all 27 Egypt governorates with bilingual names', function (): void {
    $this->seed(EgyptGeographySeeder::class);

    $governorates = Governorate::query()->withCount(['regions', 'cities'])->get();

    expect(Country::query()->where('iso2', 'EG')->count())->toBe(1)
        ->and(Str::isUlid((string) Country::query()->where('iso2', 'EG')->value('public_id')))->toBeTrue()
        ->and($governorates)->toHaveCount(27)
        ->and($governorates->pluck('code')->unique())->toHaveCount(27);

    foreach ($governorates as $governorate) {
        expect($governorate->public_id)->toHaveLength(26)
            ->and(Str::isUlid($governorate->public_id))->toBeTrue()
            ->and($governorate->getTranslation('name', 'en'))->not->toBe('')
            ->and($governorate->getTranslation('name', 'ar'))->not->toBe('')
            ->and($governorate->regions_count)->toBeGreaterThan(0)
            ->and($governorate->cities_count)->toBeGreaterThan(0);
    }

    foreach (Region::query()->get(['public_id']) as $region) {
        expect(Str::isUlid($region->public_id))->toBeTrue();
    }

    foreach (City::query()->get() as $city) {
        expect($city->governorate_id)->toBe($city->region->governorate_id)
            ->and(Str::isUlid($city->public_id))->toBeTrue()
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

it('repairs legacy deterministic public ids that fail ULID route constraints', function (): void {
    $this->seed(EgyptGeographySeeder::class);

    $country = Country::query()->where('iso2', 'EG')->firstOrFail();
    $governorate = Governorate::query()->where('code', 'EG-CAI')->firstOrFail();
    $region = Region::query()
        ->where('governorate_id', $governorate->id)
        ->where('name->en', 'East Cairo')
        ->firstOrFail();
    $city = City::query()
        ->where('region_id', $region->id)
        ->where('name->en', 'Nasr City')
        ->firstOrFail();

    $country->forceFill(['public_id' => 'HZ5J93E7HNKMFYQHWHKRQWQPSF'])->save();
    $governorate->forceFill(['public_id' => 'HZ5J93E7HNKMFYQHWHKRQWQPSF'])->save();
    $region->forceFill(['public_id' => 'XJSNP601AG5NS29PX5B530XZME'])->save();
    $city->forceFill(['public_id' => 'YWPSFY5KZ2G9XENPGZDXKJBCES'])->save();

    expect(Str::isUlid((string) $country->refresh()->public_id))->toBeFalse()
        ->and(Str::isUlid((string) $governorate->refresh()->public_id))->toBeFalse()
        ->and(Str::isUlid((string) $region->refresh()->public_id))->toBeFalse()
        ->and(Str::isUlid((string) $city->refresh()->public_id))->toBeFalse();

    $this->seed(EgyptGeographySeeder::class);

    expect(Str::isUlid((string) $country->refresh()->public_id))->toBeTrue()
        ->and(Str::isUlid((string) $governorate->refresh()->public_id))->toBeTrue()
        ->and(Str::isUlid((string) $region->refresh()->public_id))->toBeTrue()
        ->and(Str::isUlid((string) $city->refresh()->public_id))->toBeTrue()
        ->and(Governorate::query()->where('code', 'EG-CAI')->count())->toBe(1)
        ->and(Region::query()->where('governorate_id', $governorate->id)->where('name->en', 'East Cairo')->count())->toBe(1)
        ->and(City::query()->where('region_id', $region->id)->where('name->en', 'Nasr City')->count())->toBe(1);
})->group('geography', 'seeders');
