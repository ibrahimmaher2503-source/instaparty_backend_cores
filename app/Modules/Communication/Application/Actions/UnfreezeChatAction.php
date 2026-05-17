<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use App\Modules\Communication\Domain\Events\ChatThreadUnfrozen;
use App\Modules\Communication\Domain\Exceptions\ChatThreadUnfreezeForbidden;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Unfreezes a chat thread previously locked by admin moderation.
 *
 * Refuses with `ChatThreadUnfreezeForbidden` (rendered as 409) when the
 * underlying booking_vendor.sub_status is outside ('pending','modified').
 * Idempotent on already-open threads.
 *
 * See `specs/036-admin-chat-moderation/contracts/unfreeze-thread.md`.
 */
final readonly class UnfreezeChatAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(ChatThread $thread, UnfreezeChatDTO $dto, User $actor): ChatThread
    {
        return DB::transaction(function () use ($thread, $dto, $actor): ChatThread {
            /** @var ChatThread $thread */
            $thread = ChatThread::query()->lockForUpdate()->findOrFail($thread->id);

            // Idempotent short-circuit — already open.
            if ($thread->status === 'open' && $thread->frozen_at === null) {
                return $thread;
            }

            $bookingVendor = $thread->bookingVendor()->lockForUpdate()->first();
            $allowed = [VendorSubStatus::Pending, VendorSubStatus::Modified];

            if ($bookingVendor === null || ! in_array($bookingVendor->sub_status, $allowed, true)) {
                throw new ChatThreadUnfreezeForbidden(
                    'Cannot reopen chat after review window closed.',
                );
            }

            $thread->fill([
                'status'    => 'open',
                'frozen_at' => null,
                'frozen_by' => null,
            ])->save();

            DB::afterCommit(fn () => $this->events->dispatch(
                new ChatThreadUnfrozen($thread, $actor, $dto),
            ));

            return $thread;
        });
    }
}
