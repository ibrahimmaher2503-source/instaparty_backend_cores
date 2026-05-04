<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Database\Factories;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewResponse>
 */
final class ReviewResponseFactory extends Factory
{
    protected $model = ReviewResponse::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ReviewType::cases());

        return [
            'review_type' => $type,
            'review_id' => $type === ReviewType::Service
                ? ServiceReview::factory()
                : VendorReview::factory(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'body' => $this->faker->paragraph(),
            'locale' => $this->faker->randomElement(ReviewLocale::cases()),
            'moderation_status' => ModerationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['moderation_status' => ModerationStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(['moderation_status' => ModerationStatus::Rejected]);
    }
}
