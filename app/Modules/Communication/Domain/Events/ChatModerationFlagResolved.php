<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Application\DTOs\ResolveChatFlagDTO;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class ChatModerationFlagResolved implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public User $actor,
        public ResolveChatFlagDTO $dto,
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
        return 'chat.flag.resolved';
    }

    public function changes(): array
    {
        return [
            'decision' => $this->dto->decision->value,
            'note_en' => $this->dto->noteEn,
            'note_ar' => $this->dto->noteAr,
            'action_taken' => $this->flag->action_taken->value,
            'redacted' => $this->flag->messageLog->redacted,
        ];
    }
}
