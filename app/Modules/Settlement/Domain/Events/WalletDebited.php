<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletDebited
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $walletId,
        public int $amountMinor,
        public string $currency,
        public LedgerEntryType $entryType,
    ) {}
}
