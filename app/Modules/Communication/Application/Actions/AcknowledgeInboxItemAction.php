<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeInboxItemAction
{
    public function execute(AdminInboxItem $item, User $actor): AdminInboxItem
    {
        return DB::transaction(function () use ($item, $actor): AdminInboxItem {
            $item->update(['status' => AdminInboxStatus::Read]);

            activity()
                ->causedBy($actor)
                ->performedOn($item)
                ->withProperties(['from' => AdminInboxStatus::Unread->value, 'to' => AdminInboxStatus::Read->value])
                ->log('inbox_item_read');

            return $item->fresh();
        });
    }
}
