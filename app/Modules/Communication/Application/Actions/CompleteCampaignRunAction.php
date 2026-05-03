<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Events\MarketingCampaignDispatched;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRun;
use Illuminate\Support\Facades\DB;

class CompleteCampaignRunAction
{
    public static function checkAndComplete(CampaignRun $run): void
    {
        $stillQueued = $run->recipients()
            ->where('status', CampaignRecipientStatus::Queued->value)
            ->exists();

        if ($stillQueued) {
            return;
        }

        DB::transaction(function () use ($run) {
            $run->refresh();

            if ($run->completed_at !== null) {
                return;
            }

            $run->update(['completed_at' => now()]);

            Campaign::whereKey($run->campaign_id)->update(['status' => CampaignStatus::Completed->value]);

            DB::afterCommit(function () use ($run) {
                MarketingCampaignDispatched::dispatch(
                    $run->campaign_id,
                    $run->id,
                    $run->recipients_total,
                    $run->recipients_sent,
                    $run->recipients_failed,
                );
            });
        });
    }
}
