<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Database\Factories;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewModerationLog>
 */
final class ReviewModerationLogFactory extends Factory
{
    protected $model = ReviewModerationLog::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ReviewType::cases());

        return [
            'review_type' => $type->value,
            'review_id' => $type === ReviewType::Service
                ? ServiceReview::factory()
                : VendorReview::factory(),
            'from_status' => ModerationStatus::Pending->value,
            'to_status' => ModerationStatus::Approved->value,
            'moderator_id' => User::factory()->asAdmin(),
            'reason' => [
                'en' => $this->faker->sentence(),
                'ar' => 'تمت مراجعة التقييم.',
            ],
            'created_at' => now(),
        ];
    }
}
