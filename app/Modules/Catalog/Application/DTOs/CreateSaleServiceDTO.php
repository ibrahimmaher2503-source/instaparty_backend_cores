<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Http\Requests\CreateSaleServiceRequest;

readonly class CreateSaleServiceDTO
{
    public function __construct(
        public int $vendorProfileId,
        public int $categoryId,
        /** @var array{en: string, ar: string} */
        public array $name,
        /** @var array{en: string, ar: string} */
        public array $shortDescription,
        public int $basePriceMinor,
        public bool $isPerishable,
        public bool $isMadeToOrder,
        public ?int $leadTimeHours,
        public ?int $stockQuantity,
        public ?array $customizationFields,
    ) {}

    public static function fromRequest(CreateSaleServiceRequest $request, int $vendorProfileId): self
    {
        return new self(
            vendorProfileId: $vendorProfileId,
            categoryId: $request->integer('category_id'),
            name: $request->array('name'),
            shortDescription: $request->array('short_description'),
            basePriceMinor: $request->integer('base_price_minor'),
            isPerishable: $request->boolean('is_perishable'),
            isMadeToOrder: $request->boolean('is_made_to_order'),
            leadTimeHours: $request->filled('lead_time_hours') ? $request->integer('lead_time_hours') : null,
            stockQuantity: $request->filled('stock_quantity') ? $request->integer('stock_quantity') : null,
            customizationFields: $request->input('customization_fields'),
        );
    }
}
