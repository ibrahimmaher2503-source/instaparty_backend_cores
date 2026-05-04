<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'payment_id' => Payment::factory(),
            'booking_id' => Booking::factory(),
            'amount_minor' => $this->faker->numberBetween(1000, 50000),
            'amount_currency' => 'EGP',
            'reason_code' => RefundReasonCode::CustomerRequest,
            'reason_notes' => [
                'en' => $this->faker->sentence(),
                'ar' => 'سبب الاسترداد.',
            ],
            'gateway_ref' => 'REF-'.$this->faker->unique()->numerify('#######'),
            'status' => RefundStatus::Pending,
            'initiated_by' => User::factory(),
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RefundStatus::Completed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => RefundStatus::Failed]);
    }
}
