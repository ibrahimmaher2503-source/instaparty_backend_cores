<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    public function index(Request $request, ListPublicCategoriesAction $action): JsonResponse
    {
        $type = $request->filled('product_type')
            ? ProductType::from($request->string('product_type')->toString())
            : null;

        return ApiResponse::success(CategoryResource::collection($action->execute($type)));
    }

    public function show(string $publicId): JsonResponse
    {
        $category = Category::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->firstOrFail();

        return ApiResponse::success(new CategoryResource($category));
    }

    /**
     * FR-20 — dynamic filter schemas per (category × product_type) for the
     * customer filter sheet. Only filterable fields are exposed.
     */
    public function fieldSchemas(Request $request, string $publicId): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'string', 'in:rental,sale,digital']]);

        $category = Category::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->firstOrFail();

        $locale = app()->getLocale();

        $schemas = CategoryFieldSchema::query()
            ->where('category_id', $category->id)
            ->when($request->filled('type'), fn ($q) => $q->where('product_type', ProductType::from((string) $request->string('type'))))
            ->where('is_filterable', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($schema): array => [
                'field_key' => $schema->field_key,
                'field_label' => $schema->getTranslation('field_label', $locale, useFallbackLocale: true),
                'field_type' => $schema->field_type,
                'options' => $schema->options,
                'is_filterable' => (bool) $schema->is_filterable,
                'applies_to_product_types' => [$schema->product_type->value],
            ])
            ->values();

        return ApiResponse::success($schemas);
    }
}
