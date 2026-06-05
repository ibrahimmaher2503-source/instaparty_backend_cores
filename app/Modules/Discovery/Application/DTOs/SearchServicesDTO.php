<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Http\Requests\SearchServicesRequest;

final class SearchServicesDTO
{
    public function __construct(
        public readonly ?string $query,
        public readonly ?ProductType $type,
        public readonly ?string $categorySlug,
        public readonly ?string $occasionCode,
        public readonly ?string $vendorPublicId,
        public readonly ?string $cityPublicId,
        public readonly ?int $priceMin,
        public readonly ?int $priceMax,
        public readonly ?float $minRating,
        public readonly string $locale,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $sort,
    ) {}

    public static function fromRequest(SearchServicesRequest $request): self
    {
        return new self(
            query: $request->string('q')->toString() ?: null,
            type: $request->filled('product_type') ? ProductType::from($request->string('product_type')->toString()) : null,
            categorySlug: $request->filled('category_slug') ? $request->string('category_slug')->toString() : null,
            // `occasion` is canonical; `occasion_slug` kept as a back-compat alias.
            // Both match occasions.code — OccasionResource exposes `code` as `slug`.
            occasionCode: $request->filled('occasion')
                ? $request->string('occasion')->toString()
                : ($request->filled('occasion_slug') ? $request->string('occasion_slug')->toString() : null),
            vendorPublicId: $request->filled('vendor_public_id') ? $request->string('vendor_public_id')->toString() : null,
            cityPublicId: $request->filled('city_public_id') ? $request->string('city_public_id')->toString() : null,
            priceMin: $request->filled('price_min') ? $request->integer('price_min') : null,
            priceMax: $request->filled('price_max') ? $request->integer('price_max') : null,
            minRating: $request->filled('min_rating') ? (float) $request->input('min_rating') : null,
            locale: $request->header('Accept-Language', 'en') === 'ar' ? 'ar' : 'en',
            page: max(1, $request->integer('page', 1)),
            perPage: min(50, max(1, $request->integer('per_page', 20))),
            sort: $request->filled('sort') ? $request->string('sort')->toString() : null,
        );
    }
}
