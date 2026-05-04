<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class ResolveInboxItemAction
{
    public function execute(AdminInboxItem $item, User $actor): AdminInboxItem
    {
        return DB::transaction(function () use ($item, $actor): AdminInboxItem {
            $item->update(['status' => AdminInboxStatus::Resolved]);

            activity()
                ->causedBy($actor)
                ->performedOn($item)
                ->withProperties(['action' => 'inbox_item_resolved'])
                ->log('inbox_item_resolved');

            return $item->fresh();
        });
    }
}
