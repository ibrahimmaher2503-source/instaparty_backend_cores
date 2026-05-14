<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\ApplyRedemptionToBookingAction;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/**
 * Build a draft Booking + BookingVendor + LoyaltyProgram + seeded balance.
 *
 * @return array{Booking, User, VendorProfile, LoyaltyProgram}
 */
function setupRedeemableBooking(int $subtotalMinor = 10000, int $availableBalance = 5000, bool $activeProgram = true, array $programOverrides = []): array
{
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    $program = LoyaltyProgram::factory()->create(array_merge([
        'vendor_profile_id' => $vendor->id,
        'is_active' => $activeProgram,
        'points_value_minor' => 1,    // 1 point = 1 minor
        'min_points_to_redeem' => 100,
        'max_redeem_pct' => 50,
    ], $programOverrides));

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'subtotal_minor' => $subtotalMinor,
        'subtotal_currency' => 'EGP',
        'total_minor' => $subtotalMinor,
        'total_currency' => 'EGP',
    ]);

    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    if ($availableBalance > 0) {
        LoyaltyLedgerEntry::factory()->create([
            'user_id' => $customer->id,
            'vendor_profile_id' => $vendor->id,
            'loyalty_program_id' => $program->id,
            'direction' => LedgerDirection::Earn,
            'points' => $availableBalance,
            'balance_after' => $availableBalance,
        ]);
    }

    return [$booking, $customer, $vendor, $program];
}

it('applies a valid redemption: writes ledger row, redemption, and updates booking', function (): void {
    [$booking, $customer, $vendor, $program] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 5000,
    );

    $redemption = app(ApplyRedemptionToBookingAction::class)
        ->execute($customer->id, $booking->public_id, 4000);

    expect($redemption)->toBeInstanceOf(LoyaltyRedemption::class)
        ->and($redemption->points_redeemed)->toBe(4000)
        ->and($redemption->amount_minor)->toBe(4000) // points_value_minor = 1
        ->and($redemption->user_id)->toBe($customer->id)
        ->and($redemption->vendor_profile_id)->toBe($vendor->id)
        ->and($redemption->booking_id)->toBe($booking->id);

    // Booking is updated with the discount.
    $booking->refresh();
    expect((int) $booking->loyalty_redeemed_minor)->toBe(4000)
        ->and($booking->loyalty_redemption_public_id)->toBe($redemption->public_id);

    // A redeem ledger row exists.
    $redeem = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Redeem->value)
        ->where('reference_type', 'loyalty_redemption')
        ->where('reference_id', $redemption->id)
        ->first();
    expect($redeem)->not->toBeNull()
        ->and($redeem->points)->toBe(4000)
        ->and($redeem->balance_after)->toBe(1000); // 5000 - 4000
})->group('loyalty', 'feature');

it('refuses redemptions below min_points_to_redeem (422-equivalent DomainException)', function (): void {
    [$booking, $customer] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 5000,
        programOverrides: ['min_points_to_redeem' => 500],
    );

    $action = app(ApplyRedemptionToBookingAction::class);

    expect(fn () => $action->execute($customer->id, $booking->public_id, 100))
        ->toThrow(DomainException::class, 'loyalty.below_min_threshold');
})->group('loyalty', 'feature');

it('refuses redemptions that exceed the user balance', function (): void {
    [$booking, $customer] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 200,
    );

    $action = app(ApplyRedemptionToBookingAction::class);

    expect(fn () => $action->execute($customer->id, $booking->public_id, 500))
        ->toThrow(DomainException::class, 'loyalty.insufficient_balance');
})->group('loyalty', 'feature');

it('refuses redemptions that exceed max_redeem_pct of the booking subtotal', function (): void {
    // subtotal 10000, max_redeem_pct = 50% → maxAllowed = 5000 minor. Asking for 6000
    // points × 1 minor/pt = 6000 minor discount, which exceeds the cap.
    [$booking, $customer] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 10000,
        programOverrides: ['max_redeem_pct' => 50, 'points_value_minor' => 1],
    );

    $action = app(ApplyRedemptionToBookingAction::class);

    expect(fn () => $action->execute($customer->id, $booking->public_id, 6000))
        ->toThrow(DomainException::class, 'loyalty.exceeds_max_redeem_pct');
})->group('loyalty', 'feature');

it('refuses a second redemption on the same booking (UNIQUE booking_id)', function (): void {
    [$booking, $customer] = setupRedeemableBooking(
        subtotalMinor: 100000,
        availableBalance: 50000,
    );

    $action = app(ApplyRedemptionToBookingAction::class);
    $action->execute($customer->id, $booking->public_id, 4000);

    expect(fn () => $action->execute($customer->id, $booking->public_id, 1000))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect(LoyaltyRedemption::query()->where('booking_id', $booking->id)->count())->toBe(1);
})->group('loyalty', 'feature');

it("refuses to redeem on another customer's booking", function (): void {
    [$booking] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 5000,
    );
    $stranger = User::factory()->asCustomer()->create();

    $action = app(ApplyRedemptionToBookingAction::class);

    expect(fn () => $action->execute($stranger->id, $booking->public_id, 1000))
        ->toThrow(AuthorizationException::class);
})->group('loyalty', 'feature');

it('refuses redemption when the program is inactive', function (): void {
    [$booking, $customer] = setupRedeemableBooking(
        subtotalMinor: 10000,
        availableBalance: 5000,
        activeProgram: false,
    );

    $action = app(ApplyRedemptionToBookingAction::class);

    expect(fn () => $action->execute($customer->id, $booking->public_id, 1000))
        ->toThrow(DomainException::class, 'loyalty.program_inactive');
})->group('loyalty', 'feature');
