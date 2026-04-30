<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Resources;

use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale   = $request->header('Accept-Language', 'en') === 'ar' ? 'ar' : 'en';
        $resource = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'public_id'           => $resource['public_id'] ?? null,
            'name'                => $resource["name_{$locale}"] ?? $resource['name_en'] ?? null,
            'short_description'   => $resource["short_description_{$locale}"] ?? $resource['short_description_en'] ?? null,
            'product_type'        => $resource['product_type'] ?? null,
            'base_price_minor'    => $resource['price_minor'] ?? null,
            'base_price_currency' => $resource['currency'] ?? 'EGP',
            'rating_avg'          => $resource['rating_avg'] ?? 0,
            'vendor'              => [
                'id'         => $resource['vendor_id'] ?? null,
                'rating_avg' => $resource['vendor_rating'] ?? 0,
            ],
            'is_wishlisted' => $this->resolveIsWishlisted($resource['id'] ?? null),
        ];
    }

    private function resolveIsWishlisted(mixed $serviceId): bool
    {
        if (! auth()->check() || $serviceId === null) {
            return false;
        }

        /** @var Wishlist|null $wishlist */
        $wishlist = Wishlist::where('user_id', auth()->id())->first();
        if ($wishlist === null) {
            return false;
        }

        return WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('service_id', $serviceId)
            ->exists();
    }
}
