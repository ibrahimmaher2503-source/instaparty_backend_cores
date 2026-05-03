<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use Illuminate\Support\Facades\DB;

class EloquentCommissionRateResolver implements CommissionRateResolver
{
    /**
     * Resolve commission basis points using most-specific-wins logic:
     *   1. (category_id = X, product_type = Y) — most specific
     *   2. (category_id = X, product_type = NULL)
     *   3. (category_id = NULL, product_type = Y)
     *   4. (category_id = NULL, product_type = NULL) — global default
     */
    public function resolve(?int $categoryId, ProductType $type): ?int
    {
        $typeValue = $type->value;

        // Build a CASE-based query to score specificity and pick the best match
        $row = DB::table('commission_rates')
            ->where(function ($query) use ($categoryId, $typeValue): void {
                $query
                    // Level 1: exact category + type match
                    ->orWhere(function ($q) use ($categoryId, $typeValue): void {
                        $q->where('category_id', $categoryId)
                            ->where('product_type', $typeValue);
                    })
                    // Level 2: category match, any type
                    ->orWhere(function ($q) use ($categoryId): void {
                        $q->where('category_id', $categoryId)
                            ->whereNull('product_type');
                    })
                    // Level 3: any category, type match
                    ->orWhere(function ($q) use ($typeValue): void {
                        $q->whereNull('category_id')
                            ->where('product_type', $typeValue);
                    })
                    // Level 4: global default
                    ->orWhere(function ($q): void {
                        $q->whereNull('category_id')
                            ->whereNull('product_type');
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN category_id IS NOT NULL AND product_type IS NOT NULL THEN 1
                    WHEN category_id IS NOT NULL AND product_type IS NULL THEN 2
                    WHEN category_id IS NULL AND product_type IS NOT NULL THEN 3
                    ELSE 4
                END ASC'
            )
            ->orderBy('effective_from', 'desc')
            ->select('commission_bps')
            ->first();

        return $row ? (int) $row->commission_bps : null;
    }
}
