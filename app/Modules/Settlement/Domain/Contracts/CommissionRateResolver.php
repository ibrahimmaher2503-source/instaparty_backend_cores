<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Catalog\Domain\Enums\ProductType;

interface CommissionRateResolver
{
    /**
     * Resolve the commission basis points for the given category and product type.
     *
     * Resolution order (most specific wins):
     *   1. (category_id = X, product_type = Y)
     *   2. (category_id = X, product_type = NULL)
     *   3. (category_id = NULL, product_type = Y)
     *   4. (category_id = NULL, product_type = NULL) — global default
     *
     * Returns null if no rate is configured at any specificity level.
     */
    public function resolve(?int $categoryId, ProductType $type): ?int;
}
