<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RemoveFromWishlistAction
{
    public function execute(int $userId, string $servicePublicId): void
    {
        $service  = Service::where('public_id', $servicePublicId)->firstOrFail();
        $wishlist = Wishlist::where('user_id', $userId)->firstOrFail();

        $deleted = WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('service_id', $service->id)
            ->delete();

        if ($deleted === 0) {
            throw new ModelNotFoundException('Wishlist item not found.');
        }
    }
}
