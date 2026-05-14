<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\PendingDigitalServicesPage;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\PendingRentalServicesPage;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\PendingSaleServicesPage;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('catalog', 'service-moderation');

beforeEach(function (): void {
    $this->vendorProfile = VendorProfile::factory()->approved()->create();
});

it('scopes each pending queue to its product type and pending review status', function (string $pageClass, ProductType $productType): void {
    $pending = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => $productType,
            'status' => ServiceStatus::PendingReview,
        ]);

    Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => $productType,
            'status' => ServiceStatus::Published,
        ]);

    foreach (ProductType::cases() as $otherType) {
        if ($otherType === $productType) {
            continue;
        }

        Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => $otherType,
                'status' => ServiceStatus::PendingReview,
            ]);
    }

    $records = pendingQueueQuery($pageClass)->get();

    expect($records->contains(fn (Service $service): bool => $service->is($pending)))->toBeTrue()
        ->and($records->every(fn (Service $service): bool => $service->product_type === $productType))->toBeTrue()
        ->and($records->every(fn (Service $service): bool => $service->status === ServiceStatus::PendingReview))->toBeTrue();
})->with([
    'rental' => [PendingRentalServicesPage::class, ProductType::Rental],
    'sale' => [PendingSaleServicesPage::class, ProductType::Sale],
    'digital' => [PendingDigitalServicesPage::class, ProductType::Digital],
]);

it('registers pending queue pages under each service resource', function (): void {
    expect(array_keys(RentalServiceResource::getPages()))->toContain('pending')
        ->and(array_keys(SaleServiceResource::getPages()))->toContain('pending')
        ->and(array_keys(DigitalServiceResource::getPages()))->toContain('pending');
});

it('does not expose the removable status filter on pending queue pages', function (string $pageClass): void {
    $filters = pendingQueueFilters($pageClass);

    expect(array_key_exists('status', $filters))->toBeFalse();
})->with([
    'rental' => PendingRentalServicesPage::class,
    'sale' => PendingSaleServicesPage::class,
    'digital' => PendingDigitalServicesPage::class,
]);

function pendingQueueQuery(string $pageClass): Builder
{
    $page = app($pageClass);
    $method = new ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);

    return $method->invoke($page);
}

/**
 * @return array<mixed>
 */
function pendingQueueFilters(string $pageClass): array
{
    $page = app($pageClass);
    $method = new ReflectionMethod($page, 'getTableFilters');
    $method->setAccessible(true);

    return $method->invoke($page);
}
