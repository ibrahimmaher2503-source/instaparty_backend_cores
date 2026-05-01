<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\HoldServiceInventoryAction;
use App\Modules\Catalog\Application\Actions\ReleaseExpiredReservationsAction;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Exceptions\InventoryNotAvailableException;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\Carbon;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
});

// =========================================================================
// Helpers
// =========================================================================

function makeVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    return VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
}

function makeRentalService(VendorProfile $vendor): Service
{
    $category = Category::factory()->create(['allowed_product_types' => ['rental']]);
    $service = Service::factory()->rental()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);
    return $service->refresh();
}

function makeSaleService(VendorProfile $vendor, ?int $stock = 5): Service
{
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $service = Service::factory()->sale()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);
    ServiceSaleDetail::factory()->create([
        'service_id'    => $service->id,
        'stock_quantity' => $stock,
    ]);
    return $service->refresh();
}

function makeDigitalService(VendorProfile $vendor): Service
{
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);
    $service = Service::factory()->digital()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);
    ServiceDigitalDetail::factory()->create(['service_id' => $service->id]);
    return $service->refresh();
}

// =========================================================================
// RENTAL inventory holds
// =========================================================================

it('rental cart hold is created and expires_at is ~15 minutes from now', function () {
    $vendor  = makeVendor();
    $service = makeRentalService($vendor);
    $user    = User::factory()->create();

    $startsAt = Carbon::now()->addDay();
    $endsAt   = $startsAt->copy()->addHours(4);

    $reservation = app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        startsAt: $startsAt,
        endsAt:   $endsAt,
    );

    expect($reservation->status)->toBe(ReservationStatus::Held);
    expect($reservation->product_type)->toBe(ProductType::Rental);

    // expires_at should be within 1 second of now + 15 minutes
    expect($reservation->expires_at->timestamp)
        ->toBeBetween(
            now()->addMinutes(14)->timestamp,
            now()->addMinutes(16)->timestamp
        );
})->group('catalog', 'inventory');

it('rental payment hold expires_at is ~24 hours from now', function () {
    $vendor  = makeVendor();
    $service = makeRentalService($vendor);
    $user    = User::factory()->create();

    $startsAt = Carbon::now()->addDay();
    $endsAt   = $startsAt->copy()->addHours(4);

    $reservation = app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Payment,
        userId:   $user->id,
        startsAt: $startsAt,
        endsAt:   $endsAt,
    );

    expect($reservation->expires_at->timestamp)
        ->toBeBetween(
            now()->addMinutes(1439)->timestamp,
            now()->addMinutes(1441)->timestamp
        );
})->group('catalog', 'inventory');

it('rental overlap detection throws InventoryNotAvailableException', function () {
    $vendor  = makeVendor();
    $service = makeRentalService($vendor);
    $user    = User::factory()->create();

    $startsAt = Carbon::now()->addDay();
    $endsAt   = $startsAt->copy()->addHours(4);

    // First hold goes through
    app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        startsAt: $startsAt,
        endsAt:   $endsAt,
    );

    // Second hold with overlapping window should throw
    app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        startsAt: $startsAt->copy()->addHours(2),
        endsAt:   $endsAt->copy()->addHours(2),
    );
})->throws(InventoryNotAvailableException::class)->group('catalog', 'inventory');

// =========================================================================
// SALE inventory holds
// =========================================================================

it('sale hold is created and quantity is tracked vs stock', function () {
    $vendor  = makeVendor();
    $service = makeSaleService($vendor, 10);
    $user    = User::factory()->create();

    $reservation = app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        quantity: 3,
    );

    expect($reservation->status)->toBe(ReservationStatus::Held);
    expect($reservation->quantity)->toBe(3);
    expect($reservation->product_type)->toBe(ProductType::Sale);
})->group('catalog', 'inventory');

it('sale hold fails when stock is exhausted', function () {
    $vendor  = makeVendor();
    $service = makeSaleService($vendor, 1);  // only 1 in stock
    $user    = User::factory()->create();

    // Hold the single available unit
    app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        quantity: 1,
    );

    // Trying to hold another unit should fail
    app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
        quantity: 1,
    );
})->throws(InventoryNotAvailableException::class)->group('catalog', 'inventory');

// =========================================================================
// DIGITAL inventory holds
// =========================================================================

it('digital hold always succeeds (no constraints)', function () {
    $vendor  = makeVendor();
    $service = makeDigitalService($vendor);
    $user    = User::factory()->create();

    // Create multiple holds — all should succeed
    $holdA = app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
    );
    $holdB = app(HoldServiceInventoryAction::class)->execute(
        service:  $service,
        holdType: HoldType::Cart,
        userId:   $user->id,
    );

    expect($holdA->status)->toBe(ReservationStatus::Held);
    expect($holdB->status)->toBe(ReservationStatus::Held);
    expect(ServiceInventoryReservation::count())->toBe(2);
})->group('catalog', 'inventory');

// =========================================================================
// Cleanup: ReleaseExpiredReservationsAction
// =========================================================================

it('ReleaseExpiredReservationsAction marks expired holds as expired', function () {
    $vendor  = makeVendor();
    $service = makeDigitalService($vendor);
    $user    = User::factory()->create();

    // Create a held reservation that has already passed its TTL
    ServiceInventoryReservation::factory()->create([
        'service_id'   => $service->id,
        'user_id'      => $user->id,
        'product_type' => ProductType::Digital,
        'hold_type'    => HoldType::Cart,
        'status'       => ReservationStatus::Held,
        'quantity'     => 1,
        'expires_at'   => now()->subMinutes(5),
    ]);

    // Also create one that is NOT expired yet
    ServiceInventoryReservation::factory()->create([
        'service_id'   => $service->id,
        'user_id'      => $user->id,
        'product_type' => ProductType::Digital,
        'hold_type'    => HoldType::Cart,
        'status'       => ReservationStatus::Held,
        'quantity'     => 1,
        'expires_at'   => now()->addMinutes(10),
    ]);

    $released = app(ReleaseExpiredReservationsAction::class)->execute();

    expect($released)->toBe(1);

    $this->assertDatabaseHas('service_inventory_reservations', [
        'status' => ReservationStatus::Held->value,
    ]);
    $this->assertDatabaseHas('service_inventory_reservations', [
        'status' => ReservationStatus::Expired->value,
    ]);
})->group('catalog', 'inventory');
