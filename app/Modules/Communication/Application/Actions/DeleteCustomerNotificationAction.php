<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;

/**
 * F17.5 — removes one in-app notification from the customer's inbox.
 * Hard delete: notification_dispatches is delivery plumbing, not an
 * append-only ledger (it is absent from the CLAUDE.md §15 list).
 */
class DeleteCustomerNotificationAction
{
    public function execute(int $userId, string $publicId): void
    {
        DB::transaction(function () use ($userId, $publicId): void {
            NotificationDispatch::query()
                ->where('public_id', $publicId)
                ->where('user_id', $userId)
                ->where('channel', NotificationChannel::InApp)
                ->firstOrFail()
                ->delete();
        });
    }
}
