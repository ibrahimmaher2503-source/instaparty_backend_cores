<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;

final readonly class BookingItemSnapshotDto
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $bookingId,
        public int $vendorProfileId,
        public ?int $categoryId,
        public ProductType $productType,
        public int $totalMinor,
        public string $totalCurrency,
        public ?int $commissionBps,
    ) {}
}
