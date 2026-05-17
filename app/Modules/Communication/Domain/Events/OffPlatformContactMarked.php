<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Application\DTOs\MarkOffPlatformContactDTO;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class OffPlatformContactMarked implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public User $actor,
        public MarkOffPlatformContactDTO $dto,
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
        return 'chat.flag.marked_off_platform';
    }

    public function changes(): array
    {
        return [
            'after' => [
                'chat_message_log_id' => $this->flag->chat_message_log_id,
                'flag_type' => $this->flag->flag_type->value,
                'action_taken' => $this->flag->action_taken->value,
            ],
            'reason_en' => $this->dto->reasonEn,
            'reason_ar' => $this->dto->reasonAr,
        ];
    }
}
