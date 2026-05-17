<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class ChatFlagEscalatedToInbox implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public AdminInboxItem $inboxItem,
        public User $actor,
    ) {}

    public function auditable(): Model
    {
        return $this->flag;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function action(): string
    {
        return 'chat.flag.escalated';
    }

    public function changes(): array
    {
        return [
            'admin_inbox_item_id' => $this->inboxItem->id,
            'severity' => $this->inboxItem->severity,
        ];
    }
}
