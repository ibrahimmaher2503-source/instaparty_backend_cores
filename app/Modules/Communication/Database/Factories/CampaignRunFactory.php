<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRun>
 */
class CampaignRunFactory extends Factory
{
    protected $model = CampaignRun::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'recipients_total' => $this->faker->numberBetween(10, 200),
            'recipients_sent' => $this->faker->numberBetween(5, 200),
            'recipients_failed' => $this->faker->numberBetween(0, 20),
        ];
    }
}
