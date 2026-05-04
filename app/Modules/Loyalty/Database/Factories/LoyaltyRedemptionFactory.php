<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
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
            'customer_id' => User::factory()->asCustomer(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'loyalty_rule_id' => LoyaltyRule::factory(),
            'booking_id' => Booking::factory(),
            'points_held' => $this->faker->numberBetween(50, 500),
            'discount_minor' => $this->faker->numberBetween(1000, 50000),
            'discount_currency' => 'EGP',
            'status' => 'pending',
            'applied_at' => null,
            'voided_at' => null,
            'reversed_at' => null,
        ];
    }

    public function applied(): static
    {
        return $this->state([
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function reversed(): static
    {
        return $this->state([
            'status' => 'reversed',
            'reversed_at' => now(),
        ]);
    }
}
