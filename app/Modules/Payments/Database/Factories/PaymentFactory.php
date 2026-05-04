<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_id' => Booking::factory(),
            'user_id' => User::factory()->asCustomer(),
            'gateway' => 'paymob',
            'gateway_ref' => 'PAY-'.$this->faker->unique()->numerify('#######'),
            'amount_minor' => $this->faker->numberBetween(10000, 500000),
            'amount_currency' => 'EGP',
            'method' => PaymentMethod::Card,
            'status' => PaymentStatus::Pending,
            'captured_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => [],
        ];
    }

    public function captured(): static
    {
        return $this->state([
            'status' => PaymentStatus::Captured,
            'captured_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => PaymentStatus::Failed,
            'failure_code' => 'card_declined',
            'failure_message' => [
                'en' => 'Card declined.',
                'ar' => 'تم رفض البطاقة.',
            ],
        ]);
    }

    public function refunded(): static
    {
        return $this->state(['status' => PaymentStatus::Refunded]);
    }
}
