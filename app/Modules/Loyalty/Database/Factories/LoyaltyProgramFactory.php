<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'is_active' => true,
            'points_per_currency_unit' => 1.0000,
            'points_value_minor' => 100, // 1 point = 1 EGP (100 piastres)
            'points_value_currency' => 'EGP',
            'min_points_to_redeem' => 100,
            'max_redeem_pct' => 50,
            'points_expire_after_days' => $this->faker->numberBetween(30, 365),
            'name' => [
                'en' => $name,
                'ar' => 'برنامج '.Str::title($this->faker->word()),
            ],
            'terms' => [
                'en' => $this->faker->sentence(),
                'ar' => 'شروط البرنامج.',
            ],
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
