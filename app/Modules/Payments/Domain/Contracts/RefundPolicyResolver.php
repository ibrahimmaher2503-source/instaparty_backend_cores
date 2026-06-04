<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Domain\ValueObjects\RefundPolicy;
use Carbon\Carbon;

/**
 * Public contract so other modules (Booking cancellation flow) can resolve
 * per-product-type refund policies without importing the Payments
 * Application service directly (modules.md cross-module rule).
 */
interface RefundPolicyResolver
{
    public function policyFor(ProductType $productType, string $itemStatus, ?Carbon $eventStartsAt, ?int $serviceId = null): RefundPolicy;
}
