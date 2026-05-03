<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Application\Services\SegmentResolver;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use Illuminate\Support\Facades\DB;

class CampaignAlreadyDispatchedException extends \RuntimeException {}

class DispatchCampaignAction
{
    public function __construct(
        private readonly SegmentResolver $segmentResolver,
    ) {}

    public function execute(Campaign $campaign): CampaignRun
    {
        if (! $campaign->isDraft()) {
            throw new CampaignAlreadyDispatchedException(
                "Campaign [{$campaign->id}] is not in draft state (current: {$campaign->status->value})."
            );
        }

        $userIds = $this->segmentResolver->resolve(
            $campaign->segment_filters ?? [],
            $campaign->target_locale->value,
        );

        $run = DB::transaction(function () use ($campaign, $userIds): CampaignRun {
            $campaign->update(['status' => CampaignStatus::Running->value]);

            $run = CampaignRun::create([
                'campaign_id' => $campaign->id,
                'started_at' => now(),
                'recipients_total' => $userIds->count(),
                'recipients_sent' => 0,
                'recipients_failed' => 0,
            ]);

            if ($userIds->isEmpty()) {
                $campaign->update(['status' => CampaignStatus::Completed->value]);
                $run->update(['completed_at' => now()]);

                return $run;
            }

            $now = now()->toDateTimeString();
            $recipientRows = $userIds->map(fn (int $userId) => [
                'campaign_run_id' => $run->id,
                'user_id' => $userId,
                'status' => CampaignRecipientStatus::Queued->value,
                'created_at' => $now,
            ])->all();

            CampaignRecipient::insertOrIgnore($recipientRows);

            DB::afterCommit(function () use ($run, $userIds, $campaign) {
                foreach ($userIds as $userId) {
                    DispatchCampaignRecipientJob::dispatch($campaign->id, $run->id, $userId);
                }
            });

            return $run;
        });

        return $run;
    }
}
