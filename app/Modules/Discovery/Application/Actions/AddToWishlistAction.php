<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddToWishlistAction
{
    public function execute(int $userId, string $servicePublicId): Wishlist
    {
        $service = Service::where('public_id', $servicePublicId)
            ->where('status', ServiceStatus::Published)
            ->firstOrFail();

        return DB::transaction(function () use ($userId, $service): Wishlist {
            $wishlist = Wishlist::firstOrCreate(
                ['user_id' => $userId],
                ['public_id' => (string) Str::ulid(), 'name' => 'Default'],
            );

            WishlistItem::firstOrCreate([
                'wishlist_id' => $wishlist->id,
                'service_id'  => $service->id,
            ]);

            return $wishlist->load('items');
        });
    }
}
