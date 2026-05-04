<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingLock;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingLock>
 */
class BookingLockFactory extends Factory
{
    protected $model = BookingLock::class;

    public function definition(): array
    {
        return [
            'resource_type' => Booking::class,
            'resource_id' => Booking::factory(),
            'lock_token' => (string) Str::uuid(),
            'locked_by_user_id' => User::factory(),
            'lock_purpose' => 'payment',
            'acquired_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'released_at' => null,
            'created_at' => now(),
        ];
    }
}
