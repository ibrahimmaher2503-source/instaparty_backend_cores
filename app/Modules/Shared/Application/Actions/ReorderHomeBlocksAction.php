<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\HomeBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReorderHomeBlocksAction
{
    /**
     * @param  array<int, string>  $orderedPublicIds
     */
    public function execute(array $orderedPublicIds): void
    {
        DB::transaction(function () use ($orderedPublicIds): void {
            foreach (array_values($orderedPublicIds) as $position => $publicId) {
                HomeBlock::query()
                    ->where('public_id', $publicId)
                    ->update(['position' => $position]);
            }

            DB::afterCommit(function (): void {
                Cache::forget('theme:homepage:en');
                Cache::forget('theme:homepage:ar');
                event(new PublicThemeChanged(reason: 'home_blocks.reordered'));
            });
        });
    }
}
