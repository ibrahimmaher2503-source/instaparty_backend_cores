<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\ResolveChatFlagDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagResolution;
use App\Modules\Communication\Domain\Events\ChatModerationFlagResolved;
use App\Modules\Communication\Domain\Exceptions\ChatFlagAlreadyResolved;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an open chat moderation flag with a 4-decision enum.
 *
 * When the decision is `upheld_redact`, this is the ONLY allowed mutation
 * path that flips `chat_message_log.redacted=true`. No other content fields
 * may ever be touched — enforced by ChatMessageLog::updating() guard.
 *
 * See `specs/036-admin-chat-moderation/contracts/resolve-flag.md`.
 */
final readonly class ResolveChatFlagAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(ChatModerationFlag $flag, ResolveChatFlagDTO $dto, User $actor): ChatModerationFlag
    {
        return DB::transaction(function () use ($flag, $dto, $actor): ChatModerationFlag {
            /** @var ChatModerationFlag $flag */
            $flag = ChatModerationFlag::query()->lockForUpdate()->findOrFail($flag->id);

            if ($flag->reviewed_at !== null) {
                throw new ChatFlagAlreadyResolved('chat_flag_already_resolved');
            }

            $flag->fill([
                'reviewed_by'  => $actor->id,
                'reviewed_at'  => now(),
                'action_taken' => $dto->decision->actionTaken(),
            ])->save();

            if ($dto->decision === ChatFlagResolution::UpheldRedact) {
                // Whitelisted UPDATE — only `redacted` is dirty, enforced by booted() guard.
                $flag->messageLog->update(['redacted' => true]);
                $flag->setRelation('messageLog', $flag->messageLog->fresh());
            }

            DB::afterCommit(fn () => $this->events->dispatch(
                new ChatModerationFlagResolved($flag, $actor, $dto),
            ));

            return $flag;
        });
    }
}
