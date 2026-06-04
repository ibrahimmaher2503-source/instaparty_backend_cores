<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;

/**
 * F17.3 — marks one in-app notification as read. Naturally idempotent:
 * a second call leaves the original read_at untouched.
 */
class MarkNotificationReadAction
{
    public function execute(int $userId, string $publicId): NotificationDispatch
    {
        return DB::transaction(function () use ($userId, $publicId): NotificationDispatch {
            /** @var NotificationDispatch $dispatch */
            $dispatch = NotificationDispatch::query()
                ->where('public_id', $publicId)
                ->where('user_id', $userId)
                ->where('channel', NotificationChannel::InApp)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispatch->read_at === null) {
                $dispatch->forceFill(['read_at' => now()])->save();
            }

            return $dispatch;
        });
    }
}
