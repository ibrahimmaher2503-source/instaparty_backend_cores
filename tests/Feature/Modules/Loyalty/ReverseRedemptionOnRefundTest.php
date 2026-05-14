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
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/**
 * Apply a redemption (using the real Action) so we have a booking with an
 * active loyalty discount and a Redeem ledger row.
 *
 * @return array{Booking, User, VendorProfile, LoyaltyProgram, \App\Modules\Loyalty\Domain\Models\LoyaltyRedemption}
 */
function applyTestRedemption(int $subtotalMinor = 10000, int $points = 4000): array
{
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'is_active' => true,
        'points_value_minor' => 1,
        'min_points_to_redeem' => 100,
        'max_redeem_pct' => 50,
    ]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'subtotal_minor' => $subtotalMinor,
        'subtotal_currency' => 'EGP',
        'total_minor' => $subtotalMinor,
    ]);
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $vendor->id,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => $points * 2,
        'balance_after' => $points * 2,
    ]);

    $redemption = app(ApplyRedemptionToBookingAction::class)
        ->execute($customer->id, $booking->public_id, $points);

    return [$booking->fresh(), $customer, $vendor, $program, $redemption];
}

it('credits full points back on a full refund and clears the booking discount', function (): void {
    [$booking, , , , $redemption] = applyTestRedemption(subtotalMinor: 10000, points: 4000);

    expect((int) $booking->loyalty_redeemed_minor)->toBe(4000)
        ->and($booking->loyalty_redemption_public_id)->toBe($redemption->public_id);

    // Full refund of the redemption: amountMinor == redemption->amount_minor.
    // The listener uses the redemption's amount as the denominator for the
    // proportional restore; a refund equal to (or exceeding) that amount
    // restores the full points magnitude and clears the booking discount.
    event(new RefundCompleted(
        refundId: 1,
        paymentId: 1,
        bookingId: $booking->id,
        amountMinor: (int) $redemption->amount_minor,
        amountCurrency: 'EGP',
        reasonCode: 'requested',
    ));

    $adjust = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Adjust->value)
        ->where('reference_type', 'loyalty_redemption_reversal')
        ->where('reference_id', $redemption->id)
        ->first();
    expect($adjust)->not->toBeNull()
        ->and($adjust->points)->toBe(4000);

    $booking->refresh();
    expect((int) $booking->loyalty_redeemed_minor)->toBe(0)
        ->and($booking->loyalty_redemption_public_id)->toBeNull();
})->group('loyalty', 'feature');

it('credits proportional points on a partial refund and leaves the discount in place', function (): void {
    [$booking, , , , $redemption] = applyTestRedemption(subtotalMinor: 10000, points: 4000);

    // Half-refund of the redemption amount (4000 minor → 2000 minor).
    event(new RefundCompleted(
        refundId: 2,
        paymentId: 2,
        bookingId: $booking->id,
        amountMinor: 2000,
        amountCurrency: 'EGP',
        reasonCode: 'partial',
    ));

    $adjust = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Adjust->value)
        ->where('reference_type', 'loyalty_redemption_reversal')
        ->where('reference_id', $redemption->id)
        ->first();
    expect($adjust)->not->toBeNull()
        ->and($adjust->points)->toBe(2000); // proportional to refund

    // Booking discount still applied (partial refund).
    $booking->refresh();
    expect($booking->loyalty_redemption_public_id)->toBe($redemption->public_id);
})->group('loyalty', 'feature');

it('is idempotent — a duplicate RefundCompleted event does not double-credit', function (): void {
    [$booking, , , , $redemption] = applyTestRedemption(subtotalMinor: 10000, points: 4000);

    $payload = [
        'refundId' => 3,
        'paymentId' => 3,
        'bookingId' => $booking->id,
        'amountMinor' => (int) $redemption->amount_minor,
        'amountCurrency' => 'EGP',
        'reasonCode' => 'requested',
    ];
    event(new RefundCompleted(...$payload));
    event(new RefundCompleted(...$payload));

    $adjustRows = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Adjust->value)
        ->where('reference_type', 'loyalty_redemption_reversal')
        ->where('reference_id', $redemption->id)
        ->count();
    expect($adjustRows)->toBe(1);
})->group('loyalty', 'feature');
