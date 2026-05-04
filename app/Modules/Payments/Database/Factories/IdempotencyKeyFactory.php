<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Payments\Domain\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'key' => 'idem:'.$this->faker->unique()->uuid(),
            'user_id' => null,
            'route' => '/api/v1/customer/bookings',
            'request_hash' => hash('sha256', (string) $this->faker->uuid()),
            'response_status' => 201,
            'response_body' => [
                'message' => 'cached response',
            ],
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }
}
