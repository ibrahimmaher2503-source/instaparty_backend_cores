<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingCustomerNote;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingCustomerNote>
 */
class BookingCustomerNoteFactory extends Factory
{
    protected $model = BookingCustomerNote::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory()->asCustomer(),
            'body' => $this->faker->sentence(),
            'detected_locale' => $this->faker->randomElement(['en', 'ar']),
            'created_at' => now(),
        ];
    }

    public function arabic(): static
    {
        return $this->state(['detected_locale' => 'ar']);
    }

    public function english(): static
    {
        return $this->state(['detected_locale' => 'en']);
    }
}
