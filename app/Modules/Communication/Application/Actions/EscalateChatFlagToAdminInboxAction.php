<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\EscalateChatFlagDTO;
use App\Modules\Communication\Domain\Events\ChatFlagEscalatedToInbox;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Infrastructure\Services\ChatModerationRoutingHelper;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escalates a chat moderation flag to the Phase 6.1 admin inbox.
 *
 * Idempotent on UNIQUE `(source_type, source_id, admin_id)` of admin_inbox_items —
 * re-escalating returns the existing inbox item silently.
 *
 * See `specs/036-admin-chat-moderation/contracts/escalate-to-inbox.md`.
 */
final readonly class EscalateChatFlagToAdminInboxAction
{
    private const SOURCE_TYPE = ChatModerationFlag::class;

    public function __construct(
        private ChatModerationRoutingHelper $router,
        private Dispatcher $events,
    ) {}

    public function execute(ChatModerationFlag $flag, EscalateChatFlagDTO $dto, User $actor): AdminInboxItem
    {
        return DB::transaction(function () use ($flag, $dto, $actor): AdminInboxItem {
            $assigneeId = $this->router->resolveAssignee($flag);

            $existing = AdminInboxItem::query()
                ->where('source_type', self::SOURCE_TYPE)
                ->where('source_id', $flag->id)
                ->where('admin_id', $assigneeId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $item = AdminInboxItem::create([
                'public_id'   => Str::ulid()->toBase32(),
                'admin_id'    => $assigneeId,
                'source_type' => self::SOURCE_TYPE,
                'source_id'   => $flag->id,
                'severity'    => $dto->severity,
                'title'       => [
                    'en' => __('chat_moderation.escalation_title', [], 'en'),
                    'ar' => __('chat_moderation.escalation_title', [], 'ar'),
                ],
                'body'        => [
                    'en' => $dto->summaryEn,
                    'ar' => $dto->summaryAr,
                ],
                'status'      => 'unread',
            ]);

            DB::afterCommit(fn () => $this->events->dispatch(
                new ChatFlagEscalatedToInbox($flag, $item, $actor),
            ));

            return $item;
        });
    }
}
