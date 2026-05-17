<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceChangeRequest>
 */
class ServiceChangeRequestFactory extends Factory
{
    protected $model = ServiceChangeRequest::class;

    public function definition(): array
    {
        $productType = $this->faker->randomElement(ProductType::cases());

        return [
            'public_id'           => (string) Str::ulid(),
            'service_id'          => Service::factory(),
            'product_type'        => $productType,
            'vendor_profile_id'   => VendorProfile::factory(),
            'submitted_by'        => User::factory(),
            'status'              => ServiceChangeRequestStatus::Pending,
            'proposed_changes'    => [
                'shared'               => ['base_price_minor' => 12500],
                'type_specific'        => [],
                'gallery_ops'          => [],
                'availability_windows' => [],
                'excluded_dates'       => [],
                'pricing_tiers'        => [],
            ],
            'before_snapshot'     => [
                'base_price_minor'    => 10000,
                'base_price_currency' => 'EGP',
            ],
            'vendor_note'         => ['en' => 'Cost increase', 'ar' => 'زيادة التكلفة'],
            'admin_note'          => null,
            'clarification_round' => 0,
            'decided_by'          => null,
            'decided_at'          => null,
            'version'             => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ServiceChangeRequestStatus::Pending]);
    }

    public function awaitingClarification(): static
    {
        return $this->state([
            'status'              => ServiceChangeRequestStatus::AwaitingClarification,
            'clarification_round' => 1,
            'admin_note'          => ['en' => 'Please clarify', 'ar' => 'يرجى التوضيح'],
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status'     => ServiceChangeRequestStatus::Approved,
            'decided_by' => User::factory(),
            'decided_at' => now(),
            'admin_note' => ['en' => 'Approved', 'ar' => 'تمت الموافقة'],
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status'     => ServiceChangeRequestStatus::Rejected,
            'decided_by' => User::factory(),
            'decided_at' => now(),
            'admin_note' => ['en' => 'Rejected: content policy', 'ar' => 'مرفوض: سياسة المحتوى'],
        ]);
    }
}
