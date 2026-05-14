<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\GetBookingCommissionBreakdownAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeBookingVendorWithItems(int $subtotalMinor = 100000, int $commissionMinor = 10000): array
{
    $data = makeSubmittedBookingWithVendor();
    $bookingVendor = $data['bookingVendor'];

    $bookingVendor->update([
        'sub_status' => VendorSubStatus::Accepted,
        'subtotal_minor' => $subtotalMinor,
        'commission_minor' => $commissionMinor,
        'vendor_payout_minor' => $subtotalMinor - $commissionMinor,
    ]);

    return [
        'vendor' => $data['vendor'],
        'bookingVendor' => $bookingVendor->fresh(['items']),
        'item' => $data['item'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Happy path
// ─────────────────────────────────────────────────────────────────────────────

it('returns commission breakdown for the owning vendor', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeBookingVendorWithItems(
        subtotalMinor: 80000,
        commissionMinor: 8000
    );

    $result = app(GetBookingCommissionBreakdownAction::class)->execute($bv, $vendor);

    expect($result['gross_total_minor'])->toBe(80000);
    expect($result['commission_total_minor'])->toBe(8000);
    expect($result['net_total_minor'])->toBe(72000);
    expect($result['currency'])->toBe('EGP');
    expect($result['items'])->toBeInstanceOf(Collection::class);
})->group('booking', 'commission', 'detail');

it('includes item-level breakdown in the result', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeBookingVendorWithItems();

    $item->update(['commission_bps' => 1000, 'commission_minor' => 5000]);

    $result = app(GetBookingCommissionBreakdownAction::class)->execute($bv->fresh(), $vendor);

    expect($result['items'])->not->toBeEmpty();
    expect($result['items']->first()->commission_bps)->toBe(1000);
})->group('booking', 'commission', 'detail');

it('returns delivery_fee_minor in breakdown', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeBookingVendorWithItems();

    $bv->update(['delivery_fee_minor' => 2000, 'delivery_fee_currency' => 'EGP']);

    $result = app(GetBookingCommissionBreakdownAction::class)->execute($bv->fresh(), $vendor);

    expect($result['delivery_fee_minor'])->toBe(2000);
})->group('booking', 'commission', 'detail');

// ─────────────────────────────────────────────────────────────────────────────
// Ownership guard — cross-vendor leakage prevention
// ─────────────────────────────────────────────────────────────────────────────

it('throws when a different vendor tries to access commission breakdown', function (): void {
    ['bookingVendor' => $bv] = makeBookingVendorWithItems();

    $otherVendor = VendorProfile::factory()->approved()->create();

    expect(fn () => app(GetBookingCommissionBreakdownAction::class)->execute($bv, $otherVendor))
        ->toThrow(ValidationException::class);
})->group('booking', 'commission', 'auth');

it('throws even when other vendor has a valid profile', function (): void {
    ['bookingVendor' => $bv] = makeBookingVendorWithItems();

    $other = VendorProfile::factory()->approved()->create();

    $this->expectException(ValidationException::class);
    app(GetBookingCommissionBreakdownAction::class)->execute($bv, $other);
})->group('booking', 'commission', 'auth');
