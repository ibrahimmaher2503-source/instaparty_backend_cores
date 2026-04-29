<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::active()->orderBy('sort_order');

        if ($request->filled('product_type')) {
            $query->forProductType(ProductType::from($request->string('product_type')->toString()));
        }

        return ApiResponse::success($query->get());
    }
}
