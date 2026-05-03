<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Repositories;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\CursorPaginator;

class EloquentNotificationTemplateRepository
{
    public function findByEventChannelAudience(
        string $eventKey,
        NotificationChannel $channel,
        NotificationAudience $audience
    ): ?NotificationTemplate {
        return NotificationTemplate::query()
            ->active()
            ->forEventChannelAudience($eventKey, $channel, $audience)
            ->first();
    }

    public function paginateActive(int $perPage = 20): CursorPaginator
    {
        return NotificationTemplate::query()
            ->active()
            ->orderBy('event_key')
            ->cursorPaginate($perPage);
    }
}
