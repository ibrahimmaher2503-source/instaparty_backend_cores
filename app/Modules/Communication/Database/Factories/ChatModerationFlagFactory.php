<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatModerationFlag>
 */
class ChatModerationFlagFactory extends Factory
{
    protected $model = ChatModerationFlag::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::ulid()->toBase32(),
            'chat_message_log_id' => ChatMessageLog::factory(),
            'flag_type' => $this->faker->randomElement(ChatFlagType::cases())->value,
            'matched_pattern' => '+201001234567',
            'action_taken' => ChatFlagAction::Warn->value,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => now(),
        ];
    }

    public function ofType(ChatFlagType $type): static
    {
        return $this->state(['flag_type' => $type->value]);
    }

    public function resolved(int $reviewerId, ChatFlagAction $action = ChatFlagAction::Redact): static
    {
        return $this->state([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'action_taken' => $action->value,
        ]);
    }
}
