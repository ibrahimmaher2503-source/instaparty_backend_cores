<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Factories;

use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'channel' => CampaignChannel::Push,
            'target_locale' => CampaignTargetLocale::Both,
            'segment_filters' => [
                'roles' => ['customer'],
            ],
            'product_type_segment' => null,
            'subject' => [
                'en' => $this->faker->sentence(4),
                'ar' => 'حملة '.Str::title($this->faker->word()),
            ],
            'body' => [
                'en' => $this->faker->sentence(),
                'ar' => 'رسالة تسويقية '.Str::title($this->faker->word()),
            ],
            'scheduled_at' => null,
            'status' => CampaignStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(['status' => CampaignStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    }

    public function running(): static
    {
        return $this->state(['status' => CampaignStatus::Running]);
    }

    public function completed(): static
    {
        return $this->state(['status' => CampaignStatus::Completed]);
    }
}
