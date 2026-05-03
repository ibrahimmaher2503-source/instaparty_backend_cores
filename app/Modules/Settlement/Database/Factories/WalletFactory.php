<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
            'owner_id' => 1, // overridden in tests
            'currency' => 'EGP',
            'balance_minor' => $this->faker->numberBetween(0, 1000000),
            'pending_withdrawal_minor' => 0,
        ];
    }

    public function withBalance(int $balanceMinor): static
    {
        return $this->state(['balance_minor' => $balanceMinor]);
    }

    public function empty(): static
    {
        return $this->state(['balance_minor' => 0, 'pending_withdrawal_minor' => 0]);
    }
}
