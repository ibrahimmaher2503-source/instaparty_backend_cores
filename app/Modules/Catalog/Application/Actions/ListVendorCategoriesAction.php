<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Collection;

class ListVendorCategoriesAction
{
    /**
     * Returns active root categories (with children) whose allowed_product_types
     * intersect with the vendor's approved product types.
     *
     * @return Collection<int, Category>
     */
    public function execute(VendorProfile $vendorProfile): Collection
    {
        $approvedTypes = $vendorProfile->approvedTypes
            ->pluck('product_type')
            ->map(fn ($t) => $t->value)
            ->toArray();

        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->where(function ($query) use ($approvedTypes): void {
                foreach ($approvedTypes as $type) {
                    $query->orWhereJsonContains('allowed_product_types', $type);
                }
            })
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }
}
