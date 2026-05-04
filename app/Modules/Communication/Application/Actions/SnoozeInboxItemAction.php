<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SnoozeInboxItemAction
{
    private const ALLOWED_HOURS = [1, 4, 24];

    public function execute(AdminInboxItem $item, User $actor, int $hours): AdminInboxItem
    {
        if (! in_array($hours, self::ALLOWED_HOURS, strict: true)) {
            throw new InvalidArgumentException('Snooze duration must be one of: '.implode(', ', self::ALLOWED_HOURS).'h.');
        }

        return DB::transaction(function () use ($item, $actor, $hours): AdminInboxItem {
            $item->update([
                'status' => AdminInboxStatus::Snoozed,
                'snoozed_until' => now()->addHours($hours),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($item)
                ->withProperties(['hours' => $hours, 'snoozed_until' => $item->snoozed_until->toIso8601String()])
                ->log('inbox_item_snoozed');

            return $item->fresh();
        });
    }
}
