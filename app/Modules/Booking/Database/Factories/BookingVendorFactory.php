<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingVendor>
 */
class BookingVendorFactory extends Factory
{
    protected $model = BookingVendor::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_id' => Booking::factory(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'sub_status' => VendorSubStatus::Pending,
            'response_deadline' => now()->addDay(),
            'responded_at' => null,
            'rejection_reason' => null,
            'vendor_notes' => null,
            'subtotal_minor' => 0,
            'subtotal_currency' => 'EGP',
            'delivery_fee_minor' => 0,
            'delivery_fee_currency' => 'EGP',
            'commission_minor' => 0,
            'commission_currency' => 'EGP',
            'vendor_payout_minor' => 0,
            'vendor_payout_currency' => 'EGP',
        ];
    }

    public function pending(): static
    {
        return $this->state(['sub_status' => VendorSubStatus::Pending]);
    }

    public function accepted(): static
    {
        return $this->state([
            'sub_status' => VendorSubStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'sub_status' => VendorSubStatus::Completed,
            'responded_at' => now(),
        ]);
    }
}
