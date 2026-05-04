<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorBusinessHour>
 */
class VendorBusinessHourFactory extends Factory
{
    protected $model = VendorBusinessHour::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'day_of_week' => $this->faker->randomElement(DayOfWeek::cases()),
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
