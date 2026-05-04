<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_run_id' => CampaignRun::factory(),
            'user_id' => User::factory(),
            'dispatch_id' => null,
            'status' => CampaignRecipientStatus::Queued,
            'created_at' => now(),
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => CampaignRecipientStatus::Sent]);
    }

    public function failed(): static
    {
        return $this->state(['status' => CampaignRecipientStatus::Failed]);
    }

    public function skipped(): static
    {
        return $this->state(['status' => CampaignRecipientStatus::Skipped]);
    }
}
