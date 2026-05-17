<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Events\ChatThreadUnfrozen;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires `chat.thread_unfrozen.customer` and `chat.thread_unfrozen.vendor`
 * bilingual notification templates when an admin unfreezes a chat thread.
 */
class DispatchChatThreadUnfrozenNotificationsListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ChatThreadUnfrozen $event): void
    {
        $thread = $event->thread;
        $thread->loadMissing(['vendorProfile' => fn ($q) => $q->with('user')]);

        $context = [
            'thread_public_id' => $thread->public_id,
            'reason_en'        => $event->dto->reasonEn,
            'reason_ar'        => $event->dto->reasonAr,
        ];

        if ($thread->customer_id !== null) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey:      'chat.thread_unfrozen.customer',
                channel:       NotificationChannel::Push,
                audience:      NotificationAudience::Customer,
                eventCategory: EventCategory::Chat,
                userId:        $thread->customer_id,
                context:       $context,
                referenceType: 'chat_thread',
                referenceId:   $thread->id,
            ));

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey:      'chat.thread_unfrozen.customer',
                channel:       NotificationChannel::Email,
                audience:      NotificationAudience::Customer,
                eventCategory: EventCategory::Chat,
                userId:        $thread->customer_id,
                context:       $context,
                referenceType: 'chat_thread',
                referenceId:   $thread->id,
            ));
        }

        $vendorUser = $thread->vendorProfile?->user;
        if ($vendorUser !== null) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey:      'chat.thread_unfrozen.vendor',
                channel:       NotificationChannel::Push,
                audience:      NotificationAudience::Vendor,
                eventCategory: EventCategory::Chat,
                userId:        $vendorUser->id,
                context:       $context,
                referenceType: 'chat_thread',
                referenceId:   $thread->id,
            ));

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey:      'chat.thread_unfrozen.vendor',
                channel:       NotificationChannel::Email,
                audience:      NotificationAudience::Vendor,
                eventCategory: EventCategory::Chat,
                userId:        $vendorUser->id,
                context:       $context,
                referenceType: 'chat_thread',
                referenceId:   $thread->id,
            ));
        }
    }
}
