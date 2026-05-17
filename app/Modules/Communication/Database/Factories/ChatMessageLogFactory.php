<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessageLog>
 */
class ChatMessageLogFactory extends Factory
{
    protected $model = ChatMessageLog::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::ulid()->toBase32(),
            'chat_thread_id' => ChatThread::factory(),
            'firestore_message_id' => (string) Str::ulid(),
            'sender_id' => User::factory(),
            'message_kind' => 'text',
            'detected_locale' => $this->faker->randomElement(['ar', 'en', 'mixed']),
            'flagged' => false,
            'flag_reason' => null,
            'redacted' => false,
            'created_at' => now(),
        ];
    }

    public function flagged(?string $reason = 'phone_pattern'): static
    {
        return $this->state([
            'flagged' => true,
            'flag_reason' => $reason,
        ]);
    }

    public function redacted(): static
    {
        return $this->state([
            'flagged' => true,
            'flag_reason' => 'manual',
            'redacted' => true,
        ]);
    }
}
