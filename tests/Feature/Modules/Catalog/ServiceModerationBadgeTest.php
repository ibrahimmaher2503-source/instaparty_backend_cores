<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('catalog', 'service-moderation');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->asAdmin()->create();
    $this->vendorProfile = VendorProfile::factory()->approved()->create();
});

it('reports pending review navigation badge counts per product type', function (string $resourceClass, ProductType $productType): void {
    $baseCount = Service::query()
        ->forType($productType)
        ->pendingReview()
        ->count();

    Service::factory()
        ->count(3)
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

    expect($resourceClass::getNavigationBadge())->toBe((string) ($baseCount + 3))
        ->and($resourceClass::getNavigationBadgeColor())->toBe('warning');
})->with([
    'rental' => [RentalServiceResource::class, ProductType::Rental],
    'sale' => [SaleServiceResource::class, ProductType::Sale],
    'digital' => [DigitalServiceResource::class, ProductType::Digital],
]);

it('updates the navigation badge after a pending service is approved', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->sale()
        ->pendingReview()
        ->create();

    $before = (int) SaleServiceResource::getNavigationBadge();

    app(PublishServiceAction::class)->execute($service, $this->admin);

    $after = SaleServiceResource::getNavigationBadge();

    expect((int) $after)->toBe($before - 1);
});
