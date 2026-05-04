<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'reference_no' => 'BK-'.$this->faker->unique()->numerify('#######'),
            'customer_id' => User::factory()->phoneVerified()->asCustomer(),
            'occasion_id' => Occasion::factory(),
            'lifecycle_status' => LifecycleStatus::Draft,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::NotStarted,
            'event_starts_at' => now()->addDays(14),
            'event_ends_at' => now()->addDays(14)->addHours(4),
            'guest_count' => $this->faker->numberBetween(10, 120),
            'theme' => [
                'code' => 'classic-party',
                'accent' => 'gold',
            ],
            'celebrant_name' => $this->faker->name(),
            'celebrant_dob' => $this->faker->date(),
            'celebrant_gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'subtotal_minor' => 0,
            'subtotal_currency' => 'EGP',
            'delivery_total_minor' => 0,
            'delivery_total_currency' => 'EGP',
            'discount_total_minor' => 0,
            'discount_total_currency' => 'EGP',
            'loyalty_redeemed_minor' => 0,
            'loyalty_redeemed_currency' => 'EGP',
            'total_minor' => 0,
            'total_currency' => 'EGP',
            'amount_paid_minor' => 0,
            'amount_paid_currency' => 'EGP',
            'submitted_at' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'payment_hold_expires_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['lifecycle_status' => LifecycleStatus::Draft]);
    }

    public function submitted(): static
    {
        return $this->state([
            'lifecycle_status' => LifecycleStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'lifecycle_status' => LifecycleStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(['lifecycle_status' => LifecycleStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state([
            'lifecycle_status' => LifecycleStatus::Completed,
            'fulfillment_status' => FulfillmentStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'lifecycle_status' => LifecycleStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
