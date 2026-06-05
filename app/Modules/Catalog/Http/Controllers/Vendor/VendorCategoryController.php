<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Http\Resources\VendorCategoryResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Vendor - Services
 */
class VendorCategoryController
{
    public function index(Request $request, ListPublicCategoriesAction $action): JsonResponse
    {
        $type = $request->filled('product_type')
            ? ProductType::from($request->string('product_type')->toString())
            : null;

        return ApiResponse::success(VendorCategoryResource::collection($action->execute($type)->load('parent')));
    }

    /**
     * Vendor-portal 10.2 / 4.18 (FR-20) — ALL field schemas for the create/
     * edit form (the customer variant exposes filterable fields only).
     */
    public function fieldSchemas(Request $request, string $categoryPublicId): JsonResponse
    {
        $request->validate(['type' => ['required', 'string', 'in:rental,sale,digital']]);

        $category = Category::query()
            ->where('public_id', $categoryPublicId)
            ->where('is_active', true)
            ->firstOrFail();

        $locale = app()->getLocale();

        $schemas = CategoryFieldSchema::query()
            ->where('category_id', $category->id)
            ->where('product_type', ProductType::from((string) $request->string('type')))
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($schema): array => [
                'field_key' => $schema->field_key,
                'field_label' => $schema->getTranslation('field_label', $locale, useFallbackLocale: true),
                'field_type' => $schema->field_type,
                'options' => $schema->options,
                'is_required' => (bool) $schema->is_required,
                'is_filterable' => (bool) $schema->is_filterable,
                'validation_rules' => $schema->validation_rules,
            ])
            ->values();

        return ApiResponse::success($schemas);
    }
}
