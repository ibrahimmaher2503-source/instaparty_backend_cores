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
        public readonly ?string $categoryPublicId,
        public readonly ?string $occasionCode,
        public readonly ?string $vendorPublicId,
        public readonly ?int $priceMax,
        public readonly string $locale,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $sort,
    ) {}

    public static function fromRequest(SearchServicesRequest $request): self
    {
        return new self(
            query: $request->string('q')->toString() ?: null,
            type: $request->filled('type') ? ProductType::from($request->string('type')->toString()) : null,
            categoryPublicId: $request->filled('category') ? $request->string('category')->toString() : null,
            occasionCode: $request->filled('occasion') ? $request->string('occasion')->toString() : null,
            vendorPublicId: $request->filled('vendor') ? $request->string('vendor')->toString() : null,
            priceMax: $request->filled('price_max') ? $request->integer('price_max') : null,
            locale: $request->header('Accept-Language', 'en') === 'ar' ? 'ar' : 'en',
            page: max(1, $request->integer('page', 1)),
            perPage: min(50, max(1, $request->integer('per_page', 20))),
            sort: $request->filled('sort') ? $request->string('sort')->toString() : null,
        );
    }
}
