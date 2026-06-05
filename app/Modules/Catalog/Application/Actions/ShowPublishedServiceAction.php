<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;

class ShowPublishedServiceAction
{
    /**
     * Resolve a published service by its public ULID with every relationship
     * the customer detail contract needs eager-loaded (vendor location for the
     * area hint, category, and all three detail tables).
     */
    public function execute(string $publicId): Service
    {
        return Service::query()
            ->published()
            ->with([
                'media',
                'category',
                'vendor.primaryCity',
                'vendor.primaryGovernorate',
                'rentalDetail',
                'saleDetail',
                'digitalDetail',
            ])
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
