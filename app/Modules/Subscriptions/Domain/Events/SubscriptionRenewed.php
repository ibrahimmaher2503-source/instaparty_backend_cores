<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Events;

use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionRenewed
{
    use Dispatchable;

    public function __construct(
        public readonly VendorSubscription $subscription,
        public readonly string $mode,
        public readonly ?string $invoicePublicId = null,
    ) {}
}
