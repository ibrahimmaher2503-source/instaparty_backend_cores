<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'city_id' => 1,
            'label' => fake()->randomElement(['Home', 'Work', 'Other']),
            'address_line' => fake()->streetAddress(),
            'building' => null,
            'floor' => null,
            'apartment' => null,
            'landmark' => null,
            'latitude' => null,
            'longitude' => null,
            'recipient_name' => fake()->name(),
            'recipient_phone_e164' => '+201001234567',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
