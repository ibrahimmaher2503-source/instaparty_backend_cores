<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyRedemption>
 */
class LoyaltyRedemptionFactory extends Factory
{
    protected $model = LoyaltyRedemption::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->asCustomer(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'booking_id' => Booking::factory(),
            'points_redeemed' => $this->faker->numberBetween(50, 500),
            'amount_minor' => $this->faker->numberBetween(1000, 50000),
            'amount_currency' => 'EGP',
        ];
    }
}
