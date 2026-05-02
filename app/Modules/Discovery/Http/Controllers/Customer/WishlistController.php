<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Application\Actions\AddToWishlistAction;
use App\Modules\Discovery\Application\Actions\RemoveFromWishlistAction;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use App\Modules\Discovery\Http\Requests\AddToWishlistRequest;
use App\Modules\Discovery\Http\Resources\WishlistItemResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class WishlistController
{
    public function index(): JsonResponse
    {
        $wishlist = Wishlist::with('items.service')->where('user_id', auth()->id())->first();
        $items = $wishlist?->items ?? collect();

        return ApiResponse::success(
            WishlistItemResource::collection($items),
            ['total' => $items->count()],
        );
    }

    public function add(AddToWishlistRequest $request): JsonResponse
    {
        $servicePublicId = $request->validated('service_id');
        $wishlist = app(AddToWishlistAction::class)->execute(auth()->id(), $servicePublicId);
        $item = WishlistItem::with('service')
            ->where('wishlist_id', $wishlist->id)
            ->whereHas('service', fn ($q) => $q->where('public_id', $servicePublicId))
            ->first();

        return ApiResponse::success(
            new WishlistItemResource($item),
            ['wishlist_count' => $wishlist->items()->count()],
            201,
        );
    }

    public function remove(string $servicePublicId): JsonResponse
    {
        app(RemoveFromWishlistAction::class)->execute(auth()->id(), $servicePublicId);

        return response()->json(null, 204);
    }
}
