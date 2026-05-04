<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Factories;

use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->asCustomer(),
            'name' => 'Default',
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
