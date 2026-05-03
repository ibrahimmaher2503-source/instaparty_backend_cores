<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Jobs;

use App\Modules\Communication\Application\Actions\CompleteCampaignRunAction;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchCampaignRecipientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $campaignRunId,
        public readonly int $userId,
    ) {}

    public function handle(DispatchNotificationAction $dispatchAction): void
    {
        $campaign = Campaign::find($this->campaignId);
        $run = CampaignRun::find($this->campaignRunId);
        $user = User::find($this->userId);

        if ($campaign === null || $run === null || $user === null) {
            Log::warning('DispatchCampaignRecipientJob: missing entity', [
                'campaign_id' => $this->campaignId,
                'run_id' => $this->campaignRunId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        if ($campaign->status === CampaignStatus::Cancelled) {
            return;
        }

        $recipient = CampaignRecipient::where('campaign_run_id', $run->id)
            ->where('user_id', $user->id)
            ->first();

        if ($recipient === null || $recipient->status !== CampaignRecipientStatus::Queued) {
            return;
        }

        $channel = $campaign->channel->toNotificationChannel();

        $isOptedOut = ! NotificationPreference::isEnabledFor($user->id, $channel, EventCategory::Marketing);

        if ($isOptedOut) {
            $recipient->update(['status' => CampaignRecipientStatus::Skipped->value]);
            CompleteCampaignRunAction::checkAndComplete($run);

            return;
        }

        $locale = $user->preferred_locale ?? 'en';
        $body = $campaign->getTranslation('body', $locale, false)
            ?: $campaign->getTranslation('body', 'en', false);
        $subject = $campaign->subject
            ? ($campaign->getTranslation('subject', $locale, false) ?: $campaign->getTranslation('subject', 'en', false))
            : null;

        try {
            $dispatch = $dispatchAction->execute(new DispatchNotificationDTO(
                eventKey: 'marketing.campaign.'.$campaign->public_id,
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Marketing,
                userId: $user->id,
                context: ['campaign_name' => $campaign->name],
                referenceType: Campaign::class,
                referenceId: $campaign->id,
                directBody: $body,
                directSubject: $subject,
            ));

            DB::transaction(function () use ($recipient, $dispatch, $run) {
                $recipient->update([
                    'status' => CampaignRecipientStatus::Sent->value,
                    'dispatch_id' => $dispatch?->id,
                ]);
                $run->increment('recipients_sent');
            });
        } catch (\Throwable $e) {
            Log::error('DispatchCampaignRecipientJob: dispatch failed', [
                'campaign_id' => $this->campaignId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            DB::transaction(function () use ($recipient, $run) {
                $recipient->update([
                    'status' => CampaignRecipientStatus::Failed->value,
                ]);
                $run->increment('recipients_failed');
            });
        }

        CompleteCampaignRunAction::checkAndComplete($run);
    }
}
