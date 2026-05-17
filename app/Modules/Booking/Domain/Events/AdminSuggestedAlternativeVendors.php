<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AdminSuggestedAlternativeVendors
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int> $vendorProfileIds
     */
    public function __construct(
        public readonly int $bookingId,
        public readonly int $adminId,
        public readonly array $vendorProfileIds,
        public readonly string $reason,
    ) {}
}
