<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class BatchResolveInboxItemsAction
{
    public function __construct(private readonly ResolveInboxItemAction $resolveAction) {}

    /** @param int[] $itemIds */
    public function execute(array $itemIds, User $actor): int
    {
        return DB::transaction(function () use ($itemIds, $actor): int {
            $items = AdminInboxItem::query()
                ->whereIn('id', $itemIds)
                ->where('admin_id', $actor->id)
                ->get();

            foreach ($items as $item) {
                $this->resolveAction->execute($item, $actor);
            }

            return $items->count();
        });
    }
}
