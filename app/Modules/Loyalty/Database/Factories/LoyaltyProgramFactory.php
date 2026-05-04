<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\ProgramStatus;
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
            'name' => [
                'en' => $name,
                'ar' => 'برنامج '.Str::title($this->faker->word()),
            ],
            'terms' => [
                'en' => $this->faker->sentence(),
                'ar' => 'شروط البرنامج.',
            ],
            'currency' => 'EGP',
            'status' => ProgramStatus::Active,
            'expiration_days' => $this->faker->numberBetween(30, 365),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProgramStatus::Active]);
    }

    public function paused(): static
    {
        return $this->state(['status' => ProgramStatus::Paused]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProgramStatus::Archived]);
    }
}
