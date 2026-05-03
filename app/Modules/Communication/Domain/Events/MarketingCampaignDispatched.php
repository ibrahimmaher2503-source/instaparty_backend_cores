<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketingCampaignDispatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $campaignRunId,
        public readonly int $recipientsTotal,
        public readonly int $recipientsSent,
        public readonly int $recipientsFailed,
    ) {}
}
