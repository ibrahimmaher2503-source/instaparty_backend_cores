<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\MarkOffPlatformContactDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Events\OffPlatformContactMarked;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manually marks a chat message as an off-platform contact attempt.
 *
 * Idempotent on UNIQUE `(chat_message_log_id, flag_type)` — re-marking the
 * same (log, type) returns the existing flag silently.
 *
 * See `specs/036-admin-chat-moderation/contracts/mark-off-platform.md`.
 */
final readonly class MarkOffPlatformContactAttemptAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(ChatMessageLog $log, MarkOffPlatformContactDTO $dto, User $actor): ChatModerationFlag
    {
        return DB::transaction(function () use ($log, $dto, $actor): ChatModerationFlag {
            /** @var ChatMessageLog $log */
            $log = ChatMessageLog::query()->lockForUpdate()->findOrFail($log->id);

            $existing = ChatModerationFlag::query()
                ->where('chat_message_log_id', $log->id)
                ->where('flag_type', $dto->flagType->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $flag = ChatModerationFlag::create([
                'public_id'           => Str::ulid()->toBase32(),
                'chat_message_log_id' => $log->id,
                'flag_type'           => $dto->flagType,
                'matched_pattern'     => null,
                'action_taken'        => ChatFlagAction::Block,
            ]);

            // Whitelisted UPDATE — only flagged + flag_reason are dirty.
            $log->update([
                'flagged'     => true,
                'flag_reason' => 'manual',
            ]);

            DB::afterCommit(fn () => $this->events->dispatch(
                new OffPlatformContactMarked($flag, $actor, $dto),
            ));

            return $flag;
        });
    }
}
