<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Events\ChatFlagEscalatedToInbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatches the `chat.flag_escalated` in-app notification to the assignee admin
 * after a ChatModerationFlag is escalated to the admin inbox.
 */
class DispatchChatFlagEscalatedNotificationsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ChatFlagEscalatedToInbox $event): void
    {
        $assigneeId = (int) $event->inboxItem->admin_id;

        if ($assigneeId <= 0) {
            return;
        }

        $context = [
            'flag_public_id'         => $event->flag->public_id,
            'flag_type'              => $event->flag->flag_type?->value,
            'inbox_item_public_id'   => $event->inboxItem->public_id,
            'severity'               => $event->inboxItem->severity instanceof \BackedEnum
                ? $event->inboxItem->severity->value
                : (string) $event->inboxItem->severity,
        ];

        $this->dispatcher->execute(new DispatchNotificationDTO(
            eventKey: 'chat.flag_escalated',
            channel: NotificationChannel::InApp,
            audience: NotificationAudience::Admin,
            eventCategory: EventCategory::System,
            userId: $assigneeId,
            context: $context,
            referenceType: 'admin_inbox_item',
            referenceId: $event->inboxItem->id,
        ));
    }
}
