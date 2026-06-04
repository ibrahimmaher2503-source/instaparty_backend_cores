<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;

/**
 * F17.4 — bulk mark-all-read for the in-app inbox. Naturally idempotent.
 */
class MarkAllNotificationsReadAction
{
    public function execute(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            NotificationDispatch::query()
                ->where('user_id', $userId)
                ->where('channel', NotificationChannel::InApp)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        });
    }
}
