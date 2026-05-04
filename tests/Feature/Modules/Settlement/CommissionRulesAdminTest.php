<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource;
use Carbon\Carbon;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────
// T610 — CommissionRulesResource basic query
// ─────────────────────────────────────────────────────

it('CommissionRulesResource query returns all commission rates', function (): void {
    CommissionRate::factory()->count(3)->create();

    $results = CommissionRulesResource::getEloquentQuery()->get();

    expect($results)->toHaveCount(3);
})->group('settlement', 'admin', 'filament', 'T610');

// ─────────────────────────────────────────────────────
// T610 — New rate applies on next PaymentCaptured
// ─────────────────────────────────────────────────────

it('a newly created commission rate is used for subsequent commission calculations', function (): void {
    // Default global rate: 1500 bps (15%)
    CommissionRate::factory()->globalDefault()->create();

    // Create a more-specific rate: 2000 bps for sale items
    CommissionRate::factory()->create([
        'category_id' => null,
        'product_type' => ProductType::Sale,
        'commission_bps' => 2000,
    ]);

    $vp = VendorProfile::factory()->create();
    $grossMinor = 100000; // 1000 EGP

    $itemDto = new BookingItemSnapshotDto(
        id: 1,
        publicId: (string) Str::ulid(),
        bookingId: 1,
        vendorProfileId: $vp->id,
        categoryId: null,
        productType: ProductType::Sale,
        totalMinor: $grossMinor,
        totalCurrency: 'EGP',
        commissionBps: null, // resolver will be used
    );

    $paymentDto = new PaymentSnapshotDto(
        id: 1,
        publicId: (string) Str::ulid(),
        bookingId: 1,
        amountMinor: $grossMinor,
        currency: 'EGP',
        status: PaymentStatus::Captured,
        capturedAt: Carbon::now(),
    );

    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);

    // Should use the more-specific sale rate (2000 bps), NOT the global default (1500 bps)
    expect($commission->commission_bps)->toBe(2000)
        ->and($commission->commission_minor)->toBe((int) round($grossMinor * 2000 / 10000));
})->group('settlement', 'admin', 'filament', 'T610');

// ─────────────────────────────────────────────────────
// T610 — Existing commissions are not retroactively changed
// ─────────────────────────────────────────────────────

it('editing a commission rate does not retroactively alter existing commissions', function (): void {
    $rate = CommissionRate::factory()->globalDefault()->create(['commission_bps' => 1500]);

    $vp = VendorProfile::factory()->create();
    $grossMinor = 100000;

    $itemDto = new BookingItemSnapshotDto(
        id: 2,
        publicId: (string) Str::ulid(),
        bookingId: 2,
        vendorProfileId: $vp->id,
        categoryId: null,
        productType: ProductType::Rental,
        totalMinor: $grossMinor,
        totalCurrency: 'EGP',
        commissionBps: null,
    );

    $paymentDto = new PaymentSnapshotDto(
        id: 2,
        publicId: (string) Str::ulid(),
        bookingId: 2,
        amountMinor: $grossMinor,
        currency: 'EGP',
        status: PaymentStatus::Captured,
        capturedAt: Carbon::now(),
    );

    // Record commission at 1500 bps
    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);
    expect($commission->commission_bps)->toBe(1500);

    // Now admin updates the rate to 3000 bps
    $rate->update(['commission_bps' => 3000]);

    // The existing commission row must NOT change
    $commission->refresh();
    expect($commission->commission_bps)->toBe(1500)
        ->and(Commission::count())->toBe(1);
})->group('settlement', 'admin', 'filament', 'T610');

// ─────────────────────────────────────────────────────
// T610 — Permission: vendor cannot manage commission rates
// ─────────────────────────────────────────────────────

it('a vendor user does not have the manage_commission_rates permission', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    expect($vp->user->can('manage_commission_rates'))->toBeFalse();
})->group('settlement', 'admin', 'filament', 'T610');

it('an admin user has the manage_commission_rates permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->can('manage_commission_rates'))->toBeTrue();
})->group('settlement', 'admin', 'filament', 'T610');
