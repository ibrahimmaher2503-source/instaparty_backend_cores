<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Resources;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\Concerns\BuildsServiceContract;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Search hit. Emits the canonical `ServiceSummary` contract (shared with the
 * customer detail and vendor resources via BuildsServiceContract) plus the
 * authenticated customer's wishlist flag.
 *
 * @mixin Service
 */
class ServiceSearchResultResource extends JsonResource
{
    use BuildsServiceContract;

    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;

        return array_merge(
            $this->serviceSummary($service, app()->getLocale()),
            ['is_wishlisted' => $this->resolveIsWishlisted($request, $service->id)],
        );
    }

    /**
     * Whether the service is in the customer's wishlist. The set of
     * wishlisted service-ids is loaded once and memoized on the REQUEST
     * (not a static — that would leak across requests under Octane/tests).
     * Previously this ran two queries PER card: 40 on a 20-card page.
     */
    private function resolveIsWishlisted(Request $request, mixed $serviceId): bool
    {
        if (! auth()->check() || $serviceId === null) {
            return false;
        }

        if (! $request->attributes->has('wishlisted_service_ids')) {
            $wishlistId = Wishlist::where('user_id', auth()->id())->value('id');
            $ids = $wishlistId === null
                ? []
                : WishlistItem::where('wishlist_id', $wishlistId)->pluck('service_id')
                    ->mapWithKeys(fn ($id): array => [(int) $id => true])->all();
            $request->attributes->set('wishlisted_service_ids', $ids);
        }

        return isset($request->attributes->get('wishlisted_service_ids')[(int) $serviceId]);
    }
}
