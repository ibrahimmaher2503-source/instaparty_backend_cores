<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyLedgerEntry>
 */
class LoyaltyLedgerEntryFactory extends Factory
{
    protected $model = LoyaltyLedgerEntry::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'customer_id' => User::factory()->asCustomer(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'entry_type' => LedgerEntryType::Earn,
            'points' => $this->faker->numberBetween(10, 200),
            'booking_id' => Booking::factory(),
            'booking_item_id' => BookingItem::factory(),
            'redemption_id' => LoyaltyRedemption::factory(),
            'reversed_from_ledger_id' => null,
            'product_type' => $this->faker->randomElement(ProductType::cases()),
            'reason' => [
                'en' => $this->faker->sentence(),
                'ar' => 'سبب السجل.',
            ],
        ];
    }

    public function earn(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::Earn]);
    }

    public function redeem(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::Redeem]);
    }

    public function reversal(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::Reversal]);
    }

    public function voidRelease(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::VoidRelease]);
    }
}
