<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GatewayWebhookLog>
 */
class GatewayWebhookLogFactory extends Factory
{
    protected $model = GatewayWebhookLog::class;

    public function definition(): array
    {
        return [
            'gateway' => 'paymob',
            'event_type' => 'payment.captured',
            'signature_valid' => true,
            'payload' => [
                'payment_ref' => 'PAY-1234567',
            ],
            'processed_at' => now(),
            'processing_error' => null,
            'created_at' => now(),
        ];
    }

    public function failedSignature(): static
    {
        return $this->state([
            'signature_valid' => false,
            'processed_at' => null,
            'processing_error' => 'Invalid signature',
        ]);
    }
}
