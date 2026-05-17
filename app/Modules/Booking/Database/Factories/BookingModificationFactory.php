<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingModification>
 */
class BookingModificationFactory extends Factory
{
    protected $model = BookingModification::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_vendor_id' => BookingVendor::factory(),
            'proposed_by' => User::factory(),
            'proposal_kind' => ModificationProposalKind::ChangePrice,
            'status' => ModificationStatus::Pending,
            'customer_decision_at' => null,
            'expires_at' => now()->addDay(),
            'vendor_explanation' => [
                'en' => $this->faker->sentence(),
                'ar' => 'اقتراح تعديل من البائع.',
            ],
            'diff_snapshot' => [
                'before' => ['subtotal_minor' => 10000],
                'after' => ['subtotal_minor' => 12000],
            ],
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => ModificationStatus::CustomerAccepted,
            'customer_decision_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => ModificationStatus::CustomerRejected,
            'customer_decision_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state([
            'status' => ModificationStatus::Draft,
            'customer_decision_at' => null,
            'expires_at' => null,
            'diff_snapshot' => [
                'totals' => [
                    'price_delta_minor' => 0,
                    'currency' => 'EGP',
                    'item_count' => 0,
                    'by_change_kind' => [],
                ],
                'items' => [],
            ],
        ]);
    }
}
