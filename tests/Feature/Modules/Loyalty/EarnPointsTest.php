<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\CalculateLoyaltyPointsAction;
use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
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
 * Bind a fake BookingItemNetAmountReader so we don't drag the entire Booking
 * module's factory choreography into a pure earn-points test.
 */
function bindNetReaderFake(int $vendorProfileId, int $netMinor, string $productType = 'rental'): void
{
    app()->instance(BookingItemNetAmountReader::class, new class($vendorProfileId, $netMinor, $productType) implements BookingItemNetAmountReader
    {
        public function __construct(
            private readonly int $vendorProfileId,
            private readonly int $netMinor,
            private readonly string $productType,
        ) {}

        public function netPaidMinorFor(int $bookingItemId): int
        {
            return $this->netMinor;
        }

        public function vendorProfileIdFor(int $bookingItemId): int
        {
            return $this->vendorProfileId;
        }

        public function productTypeFor(int $bookingItemId): string
        {
            return $this->productType;
        }
    });
}

it('credits points to the ledger for a paid booking item under an active program', function (): void {
    $user = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'is_active' => true,
        'points_per_currency_unit' => 1.0,
        'points_expire_after_days' => 90,
    ]);
    bindNetReaderFake($vendor->id, 1000);

    /** @var CalculateLoyaltyPointsAction $action */
    $action = app(CalculateLoyaltyPointsAction::class);
    $entry = $action->execute($user->id, bookingItemId: 555, bookingId: 999);

    expect($entry)->not->toBeNull()
        ->and($entry->direction)->toBe(LedgerDirection::Earn)
        ->and($entry->points)->toBe(10)
        ->and($entry->balance_after)->toBe(10)
        ->and($entry->reference_type)->toBe('booking_item')
        ->and($entry->reference_id)->toBe(555)
        ->and($entry->loyalty_program_id)->toBe($program->id)
        ->and($entry->expires_at)->not->toBeNull();
})->group('loyalty', 'feature');

it('is idempotent — second call for the same booking_item writes no extra Earn row', function (): void {
    $user = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    LoyaltyProgram::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'is_active' => true,
        'points_per_currency_unit' => 1.0,
    ]);
    bindNetReaderFake($vendor->id, 1000);

    /** @var CalculateLoyaltyPointsAction $action */
    $action = app(CalculateLoyaltyPointsAction::class);

    $first = $action->execute($user->id, 777, 1234);
    $second = $action->execute($user->id, 777, 1234);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();

    $count = LoyaltyLedgerEntry::query()
        ->where('reference_type', 'booking_item')
        ->where('reference_id', 777)
        ->where('direction', LedgerDirection::Earn->value)
        ->count();
    expect($count)->toBe(1);
})->group('loyalty', 'feature');

it('returns null and writes nothing when the program is inactive', function (): void {
    $user = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    LoyaltyProgram::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'is_active' => false,
        'points_per_currency_unit' => 1.0,
    ]);
    bindNetReaderFake($vendor->id, 1000);

    /** @var CalculateLoyaltyPointsAction $action */
    $action = app(CalculateLoyaltyPointsAction::class);
    $entry = $action->execute($user->id, 111, 222);

    // Scope to this user+vendor so the dev seeder's sample rows don't pollute.
    $writtenForUser = LoyaltyLedgerEntry::query()
        ->where('user_id', $user->id)
        ->where('vendor_profile_id', $vendor->id)
        ->count();
    expect($entry)->toBeNull()
        ->and($writtenForUser)->toBe(0);
})->group('loyalty', 'feature');

it('clamps to the configured per-day cap and refuses once exhausted', function (): void {
    config(['loyalty.max_points_per_day' => 5]);

    $user = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    LoyaltyProgram::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'is_active' => true,
        'points_per_currency_unit' => 1.0, // 10 points would normally be earned per 1000 minor
    ]);
    bindNetReaderFake($vendor->id, 1000);

    /** @var CalculateLoyaltyPointsAction $action */
    $action = app(CalculateLoyaltyPointsAction::class);

    // First call: would earn 10, clamped to remaining cap of 5.
    $first = $action->execute($user->id, 1, 100);
    expect($first)->not->toBeNull()
        ->and($first->points)->toBe(5);

    // Second call: cap exhausted, returns null.
    $second = $action->execute($user->id, 2, 101);
    expect($second)->toBeNull();

    // Third call: still exhausted.
    $third = $action->execute($user->id, 3, 102);
    expect($third)->toBeNull();

    $earnRows = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Earn->value)
        ->where('user_id', $user->id)
        ->where('vendor_profile_id', $vendor->id)
        ->count();
    expect($earnRows)->toBe(1);
})->group('loyalty', 'feature');
