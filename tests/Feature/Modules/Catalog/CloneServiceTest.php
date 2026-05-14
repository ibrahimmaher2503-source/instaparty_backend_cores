<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeApprovedVendorForClone(ProductType $type): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    VendorApprovedProductTypeFactory::new()->forType($type)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    return compact('user', 'vendor');
}

// ─────────────────────────────────────────────────────────────────────────────
// Rental
// ─────────────────────────────────────────────────────────────────────────────

it('clones a rental service with (Copy) name suffix and Draft status', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Rental);

    $service = Service::factory()
        ->rental()
        ->create([
            'vendor_profile_id' => $vendor->id,
            'name' => ['en' => 'Bouncy Castle', 'ar' => 'قلعة نطاط'],
        ]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    expect($clone->getTranslation('name', 'en'))->toBe('Bouncy Castle (Copy)');
    expect($clone->getTranslation('name', 'ar'))->toBe('قلعة نطاط (نسخة)');
    expect($clone->status)->toBe(ServiceStatus::Draft);
    expect($clone->product_type)->toBe(ProductType::Rental);
    expect($clone->id)->not->toBe($service->id);
    expect($clone->public_id)->not->toBe($service->public_id);
})->group('catalog', 'rental', 'clone');

it('copies rental detail fields to the clone', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Rental);

    $service = Service::factory()
        ->rental()
        ->create(['vendor_profile_id' => $vendor->id]);
    ServiceRentalDetail::factory()->create([
        'service_id' => $service->id,
        'requires_electricity' => true,
        'setup_time_minutes' => 90,
        'security_deposit_minor' => 25000,
    ]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    $cloneDetail = $clone->rentalDetail;
    expect($cloneDetail)->not->toBeNull();
    expect($cloneDetail->requires_electricity)->toBeTrue();
    expect($cloneDetail->setup_time_minutes)->toBe(90);
    expect($cloneDetail->security_deposit_minor)->toBe(25000);
})->group('catalog', 'rental', 'clone');

// ─────────────────────────────────────────────────────────────────────────────
// Sale
// ─────────────────────────────────────────────────────────────────────────────

it('clones a sale service with correct name suffix and Draft status', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Sale);

    $service = Service::factory()
        ->sale()
        ->create([
            'vendor_profile_id' => $vendor->id,
            'name' => ['en' => 'Birthday Cake', 'ar' => 'كيكة عيد ميلاد'],
        ]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    expect($clone->getTranslation('name', 'en'))->toBe('Birthday Cake (Copy)');
    expect($clone->getTranslation('name', 'ar'))->toBe('كيكة عيد ميلاد (نسخة)');
    expect($clone->status)->toBe(ServiceStatus::Draft);
    expect($clone->product_type)->toBe(ProductType::Sale);
})->group('catalog', 'sale', 'clone');

it('copies sale detail fields to the clone', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Sale);

    $service = Service::factory()
        ->sale()
        ->create(['vendor_profile_id' => $vendor->id]);
    ServiceSaleDetail::factory()->create([
        'service_id' => $service->id,
        'is_perishable' => true,
        'lead_time_hours' => 48,
        'stock_quantity' => 20,
    ]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    $detail = $clone->saleDetail;
    expect($detail)->not->toBeNull();
    expect($detail->is_perishable)->toBeTrue();
    expect($detail->lead_time_hours)->toBe(48);
    expect($detail->stock_quantity)->toBe(20);
})->group('catalog', 'sale', 'clone');

// ─────────────────────────────────────────────────────────────────────────────
// Digital
// ─────────────────────────────────────────────────────────────────────────────

it('clones a digital service with correct name suffix', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Digital);

    $service = Service::factory()
        ->digital()
        ->create([
            'vendor_profile_id' => $vendor->id,
            'name' => ['en' => 'E-Invitation', 'ar' => 'دعوة إلكترونية'],
        ]);
    ServiceDigitalDetail::factory()->create(['service_id' => $service->id]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    expect($clone->getTranslation('name', 'en'))->toBe('E-Invitation (Copy)');
    expect($clone->getTranslation('name', 'ar'))->toBe('دعوة إلكترونية (نسخة)');
    expect($clone->product_type)->toBe(ProductType::Digital);
    expect($clone->status)->toBe(ServiceStatus::Draft);
})->group('catalog', 'digital', 'clone');

it('copies digital detail fields to the clone', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Digital);

    $service = Service::factory()
        ->digital()
        ->create(['vendor_profile_id' => $vendor->id]);
    ServiceDigitalDetail::factory()->create([
        'service_id' => $service->id,
        'has_expiry' => true,
        'expiry_days_after_purchase' => 30,
    ]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    $detail = $clone->digitalDetail;
    expect($detail)->not->toBeNull();
    expect($detail->has_expiry)->toBeTrue();
    expect($detail->expiry_days_after_purchase)->toBe(30);
})->group('catalog', 'digital', 'clone');

// ─────────────────────────────────────────────────────────────────────────────
// Ownership guard
// ─────────────────────────────────────────────────────────────────────────────

it('throws ValidationException when vendor does not own the service', function (): void {
    ['vendor' => $ownerVendor] = makeApprovedVendorForClone(ProductType::Rental);
    ['vendor' => $otherVendor] = makeApprovedVendorForClone(ProductType::Rental);

    $service = Service::factory()
        ->rental()
        ->create(['vendor_profile_id' => $ownerVendor->id]);

    expect(fn () => app(CloneServiceAction::class)->execute($service, $otherVendor))
        ->toThrow(ValidationException::class);
})->group('catalog', 'clone', 'auth');

// ─────────────────────────────────────────────────────────────────────────────
// Type permission guard
// ─────────────────────────────────────────────────────────────────────────────

it('throws ValidationException when vendor is not approved for the service type', function (): void {
    // Vendor approved only for Sale, but service is Rental
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Sale);

    $service = Service::factory()
        ->rental()
        ->create(['vendor_profile_id' => $vendor->id]);

    expect(fn () => app(CloneServiceAction::class)->execute($service, $vendor))
        ->toThrow(ValidationException::class);
})->group('catalog', 'clone', 'auth');

// ─────────────────────────────────────────────────────────────────────────────
// What is NOT cloned
// ─────────────────────────────────────────────────────────────────────────────

it('does not copy availability blocks to the clone', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Rental);

    $service = Service::factory()
        ->rental()
        ->create(['vendor_profile_id' => $vendor->id]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);

    ServiceAvailabilityBlock::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $service->id,
        'starts_at' => today(),
        'ends_at' => today()->addDays(1),
        'reason' => ['en' => 'Holiday', 'ar' => 'عطلة'],
    ]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    expect($clone->availabilityBlocks()->count())->toBe(0);
    expect($service->availabilityBlocks()->count())->toBe(1);
})->group('catalog', 'clone');

it('generates a unique public_id and slug for the clone', function (): void {
    ['vendor' => $vendor] = makeApprovedVendorForClone(ProductType::Sale);

    $service = Service::factory()
        ->sale()
        ->create(['vendor_profile_id' => $vendor->id]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id]);

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);

    expect($clone->public_id)->not->toBe($service->public_id);
    expect($clone->slug)->not->toBe($service->slug);
})->group('catalog', 'clone');
