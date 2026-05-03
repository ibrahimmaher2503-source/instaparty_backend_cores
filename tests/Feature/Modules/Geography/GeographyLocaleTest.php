<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns governorate names for the active app locale', function (): void {
    $governorate = Governorate::factory()->create([
        'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
    ]);

    app()->setLocale('ar');
    expect($governorate->fresh()->name)->toBe('القاهرة');

    app()->setLocale('en');
    expect($governorate->fresh()->name)->toBe('Cairo');
})->group('geography', 'locale');

it('returns region names for the active app locale', function (): void {
    $region = Region::factory()->create([
        'name' => ['en' => 'East Cairo', 'ar' => 'شرق القاهرة'],
    ]);

    app()->setLocale('ar');
    expect($region->fresh()->name)->toBe('شرق القاهرة');

    app()->setLocale('en');
    expect($region->fresh()->name)->toBe('East Cairo');
})->group('geography', 'locale');

it('returns city names for the active app locale', function (): void {
    $city = City::factory()->create([
        'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
    ]);

    app()->setLocale('ar');
    expect($city->fresh()->name)->toBe('مدينة نصر');

    app()->setLocale('en');
    expect($city->fresh()->name)->toBe('Nasr City');
})->group('geography', 'locale');
