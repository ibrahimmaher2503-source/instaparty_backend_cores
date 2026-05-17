<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class ChatThreadUnfrozen implements AuditableEvent
{
    public function __construct(
        public ChatThread $thread,
        public User $actor,
        public UnfreezeChatDTO $dto,
    ) {}

    public function auditable(): Model
    {
        return $this->thread;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function action(): string
    {
        return 'chat.unfrozen';
    }

    public function changes(): array
    {
        return [
            'before' => [
                'status' => 'locked',
                'frozen_at' => $this->thread->getOriginal('frozen_at'),
                'frozen_by' => $this->thread->getOriginal('frozen_by'),
            ],
            'after' => [
                'status' => 'open',
                'frozen_at' => null,
                'frozen_by' => null,
            ],
            'reason_en' => $this->dto->reasonEn,
            'reason_ar' => $this->dto->reasonAr,
        ];
    }
}
