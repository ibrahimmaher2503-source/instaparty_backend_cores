<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Geography\Domain\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAddress>
 */
class BookingAddressFactory extends Factory
{
    protected $model = BookingAddress::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'city_id' => City::factory(),
            'address_line' => $this->faker->streetAddress(),
            'building' => $this->faker->optional()->buildingNumber(),
            'floor' => $this->faker->optional()->numerify('#'),
            'apartment' => $this->faker->optional()->numerify('##'),
            'landmark' => $this->faker->optional()->sentence(4),
            'latitude' => $this->faker->randomFloat(7, 22, 31),
            'longitude' => $this->faker->randomFloat(7, 25, 35),
            'recipient_name' => $this->faker->name(),
            'recipient_phone_e164' => '+2010'.$this->faker->unique()->numerify('########'),
        ];
    }
}
