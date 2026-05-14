<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\MarkServicePendingReviewForMaterialEditAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceReturnedToReview;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class)->group('catalog', 'service-moderation');

beforeEach(function (): void {
    $this->vendorProfile = VendorProfile::factory()->approved()->create();
});

it('returns published services to pending review when material fields change', function (ProductType $productType): void {
    Event::fake([ServiceReturnedToReview::class]);

    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => $productType,
            'status' => ServiceStatus::Published,
        ]);

    $service->update([
        'base_price_minor' => $service->base_price_minor + 1000,
    ]);

    app(MarkServicePendingReviewForMaterialEditAction::class)->execute($service);

    $service->refresh();

    expect($service->status)->toBe(ServiceStatus::PendingReview);

    Event::assertDispatched(
        ServiceReturnedToReview::class,
        fn (ServiceReturnedToReview $event): bool => $event->service->is($service),
    );
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('returns published services to pending review when category changes', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create();

    $category = Category::factory()->create([
        'allowed_product_types' => [$service->product_type->value],
    ]);

    $service->update(['category_id' => $category->id]);

    app(MarkServicePendingReviewForMaterialEditAction::class)->execute($service);

    expect($service->refresh()->status)->toBe(ServiceStatus::PendingReview);
});

it('returns published services to pending review when translated content changes', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create();

    $service->update([
        'name' => ['en' => 'Updated title', 'ar' => 'Updated title'],
    ]);

    app(MarkServicePendingReviewForMaterialEditAction::class)->execute($service);

    expect($service->refresh()->status)->toBe(ServiceStatus::PendingReview);
});

it('returns published services to pending review when core media changes', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create();

    app(MarkServicePendingReviewForMaterialEditAction::class)->execute($service, coreMediaChanged: true);

    expect($service->refresh()->status)->toBe(ServiceStatus::PendingReview);
});

it('does not return published services to review for non-material edits', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create();

    $service->update(['is_featured' => true]);

    app(MarkServicePendingReviewForMaterialEditAction::class)->execute($service);

    expect($service->refresh()->status)->toBe(ServiceStatus::Published);
});
