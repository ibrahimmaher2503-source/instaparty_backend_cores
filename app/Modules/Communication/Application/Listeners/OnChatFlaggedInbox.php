<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Events\ChatFlagged;

class OnChatFlaggedInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(ChatFlagged $event): void
    {
        $this->router->execute(
            eventKey: 'chat.flagged',
            severity: AdminInboxSeverity::Warning,
            sourceType: 'chat_thread',
            sourceId: $event->chatThreadId,
            title: ['en' => 'Chat flagged for moderation', 'ar' => 'محادثة مُبلَّغ عنها للمراجعة'],
            body: ['en' => "Chat thread #{$event->chatThreadId} has been flagged.", 'ar' => "تمت الإشارة إلى المحادثة #{$event->chatThreadId}."],
        );
    }
}
