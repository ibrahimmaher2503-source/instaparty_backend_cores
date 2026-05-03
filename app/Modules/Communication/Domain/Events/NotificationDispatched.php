<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDispatched
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $dispatchId;

    public readonly int $userId;

    public readonly NotificationChannel $channel;

    public readonly string $eventKey;

    public readonly DispatchStatus $status;

    public function __construct(NotificationDispatch $dispatch)
    {
        $this->dispatchId = $dispatch->id;
        $this->userId     = $dispatch->user_id;
        $this->channel    = $dispatch->channel;
        $this->status     = $dispatch->status;

        /** @var \App\Modules\Communication\Domain\Models\NotificationTemplate|null $template */
        $template         = $dispatch->template;
        $this->eventKey   = $template !== null ? $template->event_key : '';
    }
}
