<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
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
        $points = $this->faker->numberBetween(10, 200);

        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->asCustomer(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'direction' => LedgerDirection::Earn,
            'points' => $points,
            'balance_after' => $points,
            'reference_type' => null,
            'reference_id' => null,
            'reason' => [
                'en' => $this->faker->sentence(),
                'ar' => 'سبب السجل.',
            ],
            'expires_at' => null,
        ];
    }

    public function earn(): static
    {
        return $this->state(['direction' => LedgerDirection::Earn]);
    }

    public function redeem(): static
    {
        return $this->state(['direction' => LedgerDirection::Redeem]);
    }

    public function expire(): static
    {
        return $this->state(['direction' => LedgerDirection::Expire]);
    }

    public function adjust(): static
    {
        return $this->state(['direction' => LedgerDirection::Adjust]);
    }
}
