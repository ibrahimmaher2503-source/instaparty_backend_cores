<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReassignInboxItemAction
{
    /** Roles that may own an inbox item. Keep in sync with Shield role seeder. */
    private const ADMIN_ROLES = ['super_admin', 'admin', 'vendor_manager', 'ops_manager'];

    public function execute(AdminInboxItem $item, User $actor, User $target): AdminInboxItem
    {
        if (! $target->hasAnyRole(self::ADMIN_ROLES)) {
            throw new InvalidArgumentException("Target user #{$target->id} does not have an admin role.");
        }

        return DB::transaction(function () use ($item, $actor, $target): AdminInboxItem {
            $item->update([
                'status' => AdminInboxStatus::Reassigned,
                'assigned_to_admin_id' => $target->id,
            ]);

            $newItem = AdminInboxItem::create([
                'public_id' => Str::ulid()->toBase32(),
                'admin_id' => $target->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'severity' => $item->severity,
                'title' => $item->getTranslations('title'),
                'body' => $item->getTranslations('body'),
                'status' => AdminInboxStatus::Unread,
                'snoozed_until' => null,
                'assigned_to_admin_id' => null,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($newItem)
                ->withProperties([
                    'from_item_id' => $item->id,
                    'reassigned_from' => $actor->id,
                    'reassigned_to' => $target->id,
                ])
                ->log('inbox_item_reassigned');

            return $newItem;
        });
    }
}
