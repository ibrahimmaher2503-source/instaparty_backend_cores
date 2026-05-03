<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Models\Campaign;
use Illuminate\Support\Facades\DB;

class CampaignCannotBeCancelledException extends \RuntimeException {}

class CancelCampaignAction
{
    public function execute(Campaign $campaign): void
    {
        match ($campaign->status) {
            CampaignStatus::Draft => DB::transaction(fn () => $campaign->delete()),
            CampaignStatus::Running => DB::transaction(fn () => $campaign->update(['status' => CampaignStatus::Cancelled->value])),
            default => throw new CampaignCannotBeCancelledException(
                "Campaign [{$campaign->id}] cannot be cancelled in status [{$campaign->status->value}]."
            ),
        };
    }
}
