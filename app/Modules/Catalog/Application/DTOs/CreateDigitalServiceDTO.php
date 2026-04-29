<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Http\Requests\CreateDigitalServiceRequest;

readonly class CreateDigitalServiceDTO
{
    public function __construct(
        public int $vendorProfileId,
        public int $categoryId,
        /** @var array{en: string, ar: string} */
        public array $name,
        /** @var array{en: string, ar: string} */
        public array $shortDescription,
        public int $basePriceMinor,
        public string $deliveryMethod,
        public bool $hasExpiry,
        public ?int $expiryDaysAfterPurchase,
        public bool $isRefundableAfterDelivery,
        public ?string $redemptionUrlTemplate,
    ) {}

    public static function fromRequest(CreateDigitalServiceRequest $request, int $vendorProfileId): self
    {
        return new self(
            vendorProfileId: $vendorProfileId,
            categoryId: $request->integer('category_id'),
            name: $request->array('name'),
            shortDescription: $request->array('short_description'),
            basePriceMinor: $request->integer('base_price_minor'),
            deliveryMethod: $request->string('delivery_method')->toString(),
            hasExpiry: $request->boolean('has_expiry'),
            expiryDaysAfterPurchase: $request->filled('expiry_days_after_purchase')
                ? $request->integer('expiry_days_after_purchase')
                : null,
            isRefundableAfterDelivery: $request->boolean('is_refundable_after_delivery'),
            redemptionUrlTemplate: $request->input('redemption_url_template'),
        );
    }
}
