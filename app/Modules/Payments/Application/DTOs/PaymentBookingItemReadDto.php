<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class PaymentBookingItemReadDto extends Data
{
    public function __construct(
        public int $id,
        public int $bookingId,
        public int $serviceId,
        public ProductType $productType,
        public ?Carbon $eventStartsAt,
        public string $itemStatus,
    ) {}
}
