<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'attempt_no' => 1,
            'request_payload' => [
                'amount_minor' => 10000,
                'currency' => 'EGP',
            ],
            'response_payload' => [
                'status' => 'ok',
            ],
            'http_status' => 200,
            'created_at' => now(),
        ];
    }
}
