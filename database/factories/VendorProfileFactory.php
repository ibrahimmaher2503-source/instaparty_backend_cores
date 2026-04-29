<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    protected $model = VendorProfile::class;

    public function definition(): array
    {
        $businessNameEn = fake()->company();

        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->phoneVerified()->asVendor(),
            'business_name' => [
                'en' => $businessNameEn,
                'ar' => 'شركة '.$businessNameEn,
            ],
            'slug' => Str::slug($businessNameEn).'-'.Str::lower(Str::random(6)),
            'bio' => null,
            'business_type' => BusinessType::Individual->value,
            'primary_governorate_id' => 1,
            'primary_city_id' => 1,
            'approval_status' => ApprovalStatus::Pending->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => ApprovalStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => ApprovalStatus::Rejected->value,
            'rejected_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => ApprovalStatus::Suspended->value,
            'suspended_at' => now(),
        ]);
    }
}
