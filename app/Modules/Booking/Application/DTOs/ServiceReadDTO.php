<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;

final readonly class ServiceReadDTO
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $vendorProfileId,
        public ProductType $productType,
        public string $nameEn,
        public string $nameAr,
        public int $basePriceMinor,
        public string $basePriceCurrency,
        public ?int $stockQuantity,
    ) {}
}
