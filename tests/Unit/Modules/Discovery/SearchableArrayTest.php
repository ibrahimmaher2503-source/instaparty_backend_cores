<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;

it('toSearchableArray contains required bilingual fields', function (): void {
    $service = Service::factory()->make([
        'name' => ['en' => 'Bouncy Castle', 'ar' => 'نطاطة'],
        'short_description' => ['en' => 'Fun castle', 'ar' => 'نطاطة ممتعة'],
        'status' => ServiceStatus::Published,
        'product_type' => ProductType::Rental,
        'base_price_minor' => 150000,
        'base_price_currency' => 'EGP',
    ]);

    $array = $service->toSearchableArray();

    expect($array)->toHaveKeys([
        'id', 'public_id', 'name_en', 'name_ar',
        'short_description_en', 'short_description_ar',
        'product_type', 'is_active', 'occasion_ids',
        'price_minor', 'vendor_rating', 'rating_avg',
    ]);
    expect($array['name_en'])->toBe('Bouncy Castle');
    expect($array['name_ar'])->toBe('نطاطة');
    expect($array['is_active'])->toBeTrue();
    expect($array['occasion_ids'])->toBeArray();
})->group('discovery', 'search');

it('shouldBeSearchable returns false for non-published service', function (): void {
    $draft = Service::factory()->make(['status' => ServiceStatus::Draft]);
    expect($draft->shouldBeSearchable())->toBeFalse();
})->group('discovery', 'search');

it('shouldBeSearchable returns true for published service', function (): void {
    $published = Service::factory()->make(['status' => ServiceStatus::Published]);
    expect($published->shouldBeSearchable())->toBeTrue();
})->group('discovery', 'search');
