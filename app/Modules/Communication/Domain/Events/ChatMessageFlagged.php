<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class ChatMessageFlagged implements AuditableEvent
{
    public function __construct(
        public ChatMessageLog $log,
        public ChatModerationFlag $flag,
    ) {}

    public function auditable(): Model
    {
        return $this->flag;
    }

    public function actor(): ?User
    {
        return null; // system-initiated detection job
    }

    public function action(): string
    {
        return 'chat.message.flagged';
    }

    public function changes(): array
    {
        return [
            'flag_type' => $this->flag->flag_type->value,
            'matched_pattern' => $this->flag->matched_pattern,
        ];
    }
}
