<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Database\Factories;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorReview>
 */
final class VendorReviewFactory extends Factory
{
    protected $model = VendorReview::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'booking_vendor_id' => BookingVendor::factory(),
            'user_id' => User::factory()->asCustomer(),
            'rating' => $this->faker->numberBetween(3, 5),
            'body' => $this->faker->optional(85)->paragraph(),
            'locale' => $this->faker->randomElement(ReviewLocale::cases()),
            'moderation_status' => ModerationStatus::Pending,
            'moderated_by' => null,
            'moderated_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['moderation_status' => ModerationStatus::Pending]);
    }

    public function approved(): static
    {
        return $this->state([
            'moderation_status' => ModerationStatus::Approved,
            'moderated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'moderation_status' => ModerationStatus::Rejected,
            'moderated_at' => now(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state([
            'moderation_status' => ModerationStatus::Hidden,
            'moderated_at' => now(),
        ]);
    }
}
