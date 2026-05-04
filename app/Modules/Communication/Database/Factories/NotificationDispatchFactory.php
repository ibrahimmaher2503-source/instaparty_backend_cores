<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationDispatch>
 */
class NotificationDispatchFactory extends Factory
{
    protected $model = NotificationDispatch::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'notification_template_id' => NotificationTemplate::factory(),
            'user_id' => User::factory(),
            'channel' => NotificationChannel::Push,
            'locale' => 'en',
            'status' => DispatchStatus::Queued,
            'context' => [],
            'provider' => null,
            'provider_ref' => null,
            'reference_type' => null,
            'reference_id' => null,
            'error_message' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'created_at' => now(),
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => DispatchStatus::Sent, 'sent_at' => now()]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => DispatchStatus::Delivered, 'sent_at' => now(), 'delivered_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DispatchStatus::Failed,
            'error_message' => 'Delivery failed.',
        ]);
    }
}
