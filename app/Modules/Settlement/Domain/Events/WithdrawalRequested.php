<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $withdrawalId,
        public string $withdrawalPublicId,
        public int $vendorProfileId,
        public int $amountMinor,
        public string $currency,
    ) {}
}
