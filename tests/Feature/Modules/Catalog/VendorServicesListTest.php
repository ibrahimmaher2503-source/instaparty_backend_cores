<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ListVendorServicesAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeListVendorWithType(ProductType $type): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    VendorApprovedProductTypeFactory::new()->forType($type)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    return $vendor;
}

// ─────────────────────────────────────────────────────────────────────────────
// All three product types appear in results
// ─────────────────────────────────────────────────────────────────────────────

it('returns services across all three product types for a vendor', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    foreach ([ProductType::Rental, ProductType::Sale, ProductType::Digital] as $type) {
        VendorApprovedProductTypeFactory::new()->forType($type)->create([
            'vendor_profile_id' => $vendor->id,
        ]);
        $state = match ($type) {
            ProductType::Rental => 'rental',
            ProductType::Sale => 'sale',
            ProductType::Digital => 'digital',
        };
        Service::factory()->{$state}()->create(['vendor_profile_id' => $vendor->id]);
    }

    $result = app(ListVendorServicesAction::class)->execute($vendor);

    expect($result->total())->toBe(3);

    $types = $result->getCollection()->pluck('product_type')->map(fn ($t) => $t->value)->sort()->values();
    expect($types->all())->toBe(['digital', 'rental', 'sale']);
})->group('catalog', 'services-list');

// ─────────────────────────────────────────────────────────────────────────────
// Filter by product type
// ─────────────────────────────────────────────────────────────────────────────

it('filters services by rental type', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    Service::factory()->rental()->count(2)->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->sale()->create(['vendor_profile_id' => $vendor->id]);

    $result = app(ListVendorServicesAction::class)->execute($vendor, type: ProductType::Rental);

    expect($result->total())->toBe(2);
    expect($result->getCollection()->every(fn ($s) => $s->product_type === ProductType::Rental))->toBeTrue();
})->group('catalog', 'services-list', 'rental');

it('filters services by sale type', function (): void {
    $vendor = makeListVendorWithType(ProductType::Sale);

    Service::factory()->sale()->count(2)->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id]);

    $result = app(ListVendorServicesAction::class)->execute($vendor, type: ProductType::Sale);

    expect($result->total())->toBe(2);
    expect($result->getCollection()->every(fn ($s) => $s->product_type === ProductType::Sale))->toBeTrue();
})->group('catalog', 'services-list', 'sale');

it('filters services by digital type', function (): void {
    $vendor = makeListVendorWithType(ProductType::Digital);

    Service::factory()->digital()->count(3)->create(['vendor_profile_id' => $vendor->id]);
    Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id]);

    $result = app(ListVendorServicesAction::class)->execute($vendor, type: ProductType::Digital);

    expect($result->total())->toBe(3);
    expect($result->getCollection()->every(fn ($s) => $s->product_type === ProductType::Digital))->toBeTrue();
})->group('catalog', 'services-list', 'digital');

// ─────────────────────────────────────────────────────────────────────────────
// Filter by status
// ─────────────────────────────────────────────────────────────────────────────

it('filters services by status', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    Service::factory()->rental()->count(2)->create([
        'vendor_profile_id' => $vendor->id,
        'status' => ServiceStatus::Draft,
    ]);
    Service::factory()->rental()->create([
        'vendor_profile_id' => $vendor->id,
        'status' => ServiceStatus::PendingReview,
    ]);

    $drafts = app(ListVendorServicesAction::class)->execute($vendor, status: ServiceStatus::Draft);
    $pending = app(ListVendorServicesAction::class)->execute($vendor, status: ServiceStatus::PendingReview);

    expect($drafts->total())->toBe(2);
    expect($pending->total())->toBe(1);
})->group('catalog', 'services-list');

// ─────────────────────────────────────────────────────────────────────────────
// Cross-vendor leakage prevention
// ─────────────────────────────────────────────────────────────────────────────

it('does not return services belonging to another vendor', function (): void {
    $vendorA = makeListVendorWithType(ProductType::Rental);
    $vendorB = makeListVendorWithType(ProductType::Rental);

    Service::factory()->rental()->count(3)->create(['vendor_profile_id' => $vendorA->id]);
    Service::factory()->rental()->count(2)->create(['vendor_profile_id' => $vendorB->id]);

    $resultA = app(ListVendorServicesAction::class)->execute($vendorA);
    $resultB = app(ListVendorServicesAction::class)->execute($vendorB);

    expect($resultA->total())->toBe(3);
    expect($resultB->total())->toBe(2);

    $resultA->getCollection()->each(fn ($s) => expect($s->vendor_profile_id)->toBe($vendorA->id));
    $resultB->getCollection()->each(fn ($s) => expect($s->vendor_profile_id)->toBe($vendorB->id));
})->group('catalog', 'services-list', 'auth');

it('returns empty paginator when vendor has no services', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    $result = app(ListVendorServicesAction::class)->execute($vendor);

    expect($result->total())->toBe(0);
    expect($result->getCollection())->toBeEmpty();
})->group('catalog', 'services-list');

// ─────────────────────────────────────────────────────────────────────────────
// Bulk submit for review
// ─────────────────────────────────────────────────────────────────────────────

it('submits multiple draft services for review', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    $services = Service::factory()->rental()->count(3)->create([
        'vendor_profile_id' => $vendor->id,
        'status' => ServiceStatus::Draft,
    ]);

    foreach ($services as $service) {
        app(SubmitServiceForReviewAction::class)->execute($service, $vendor);
    }

    $submitted = app(ListVendorServicesAction::class)->execute($vendor, status: ServiceStatus::PendingReview);

    expect($submitted->total())->toBe(3);
})->group('catalog', 'services-list', 'submit-review');

it('throws ValidationException when submitting a published service for review', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    $service = Service::factory()->rental()->create([
        'vendor_profile_id' => $vendor->id,
        'status' => ServiceStatus::Published,
    ]);

    expect(fn () => app(SubmitServiceForReviewAction::class)->execute($service, $vendor))
        ->toThrow(ValidationException::class);
})->group('catalog', 'services-list', 'submit-review');

it('throws ValidationException when another vendor tries to submit for review', function (): void {
    $vendorA = makeListVendorWithType(ProductType::Rental);
    $vendorB = makeListVendorWithType(ProductType::Rental);

    $service = Service::factory()->rental()->create([
        'vendor_profile_id' => $vendorA->id,
        'status' => ServiceStatus::Draft,
    ]);

    expect(fn () => app(SubmitServiceForReviewAction::class)->execute($service, $vendorB))
        ->toThrow(ValidationException::class);
})->group('catalog', 'services-list', 'submit-review', 'auth');

it('allows submitting a changes_requested service for review', function (): void {
    $vendor = makeListVendorWithType(ProductType::Rental);

    $service = Service::factory()->rental()->create([
        'vendor_profile_id' => $vendor->id,
        'status' => ServiceStatus::ChangesRequested,
    ]);

    $updated = app(SubmitServiceForReviewAction::class)->execute($service, $vendor);

    expect($updated->status)->toBe(ServiceStatus::PendingReview);
})->group('catalog', 'services-list', 'submit-review');
