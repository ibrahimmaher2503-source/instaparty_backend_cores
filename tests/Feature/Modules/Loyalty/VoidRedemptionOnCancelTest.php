<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\ApplyRedemptionToBookingAction;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/**
 * @return array{Booking, \App\Modules\Loyalty\Domain\Models\LoyaltyRedemption}
 */
function applyTestRedemptionForVoid(int $points = 4000): array
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
        'subtotal_minor' => 10000,
        'subtotal_currency' => 'EGP',
        'total_minor' => 10000,
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

    return [$booking->fresh(), $redemption];
}

it('credits full points back when a booking with an active redemption is cancelled', function (): void {
    [$booking, $redemption] = applyTestRedemptionForVoid(points: 4000);

    event(new BookingCancelled($booking));

    $adjust = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Adjust->value)
        ->where('reference_type', 'loyalty_redemption_void')
        ->where('reference_id', $redemption->id)
        ->first();

    expect($adjust)->not->toBeNull()
        ->and($adjust->points)->toBe(4000);

    $booking->refresh();
    expect((int) $booking->loyalty_redeemed_minor)->toBe(0)
        ->and($booking->loyalty_redemption_public_id)->toBeNull();
})->group('loyalty', 'feature');

it('is idempotent — a duplicate BookingCancelled event does not double-credit', function (): void {
    [$booking, $redemption] = applyTestRedemptionForVoid(points: 4000);

    event(new BookingCancelled($booking));
    event(new BookingCancelled($booking));

    $voidRows = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Adjust->value)
        ->where('reference_type', 'loyalty_redemption_void')
        ->where('reference_id', $redemption->id)
        ->count();
    expect($voidRows)->toBe(1);
})->group('loyalty', 'feature');
