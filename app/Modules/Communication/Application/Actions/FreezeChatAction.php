<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Domain\Events\ChatThreadFrozen;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Freezes a chat thread for admin moderation review.
 *
 * Idempotent: re-freezing an already-frozen thread returns the thread without
 * mutating state, firing events, or writing audit rows.
 *
 * See `specs/036-admin-chat-moderation/contracts/freeze-thread.md`.
 */
final readonly class FreezeChatAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(ChatThread $thread, FreezeChatDTO $dto, User $actor): ChatThread
    {
        return DB::transaction(function () use ($thread, $dto, $actor): ChatThread {
            /** @var ChatThread $thread */
            $thread = ChatThread::query()->lockForUpdate()->findOrFail($thread->id);

            // Idempotent short-circuit — already frozen.
            if ($thread->status === 'locked' && $thread->frozen_at !== null) {
                return $thread;
            }

            $thread->fill([
                'status'    => 'locked',
                'frozen_at' => now(),
                'frozen_by' => $actor->id,
            ])->save();

            DB::afterCommit(fn () => $this->events->dispatch(
                new ChatThreadFrozen($thread, $actor, $dto),
            ));

            return $thread;
        });
    }
}
