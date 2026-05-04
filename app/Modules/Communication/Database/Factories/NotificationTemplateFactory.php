<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $eventKey = $this->faker->unique()->slug(3, '.');

        return [
            'public_id' => (string) Str::ulid(),
            'event_key' => $eventKey,
            'channel' => NotificationChannel::Push,
            'audience' => NotificationAudience::Customer,
            'body' => [
                'en' => $this->faker->sentence(),
                'ar' => 'رسالة '.Str::title($this->faker->word()),
            ],
            'subject' => [
                'en' => $this->faker->sentence(4),
                'ar' => 'إشعار '.Str::title($this->faker->word()),
            ],
            'variables' => [],
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
