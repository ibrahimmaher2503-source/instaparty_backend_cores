<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone_e164' => '+2010'.fake()->unique()->numerify('########'),
            'preferred_locale' => fake()->randomElement(['ar', 'en']),
            'timezone' => 'Africa/Cairo',
            'numeral_system' => 'western',
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function phoneVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => now(),
        ]);
    }

    public function asCustomer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('customer'));
    }

    public function asVendor(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('vendor'));
    }

    public function asAdmin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('admin'));
    }
}
