<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    protected $model = CustomerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asCustomer(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-50 years', '-18 years'),
            'gender' => fake()->optional()->randomElement(['male', 'female', 'prefer_not_to_say']),
            'how_heard_about_us' => fake()->optional()->randomElement(['friend', 'social_media', 'search', 'ad']),
            'children' => null,
            'accepts_marketing' => fake()->boolean(80),
        ];
    }
}
