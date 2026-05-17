<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'reference_no' => 'BK-'.$this->faker->unique()->numerify('#######'),
            'customer_id' => User::factory()->phoneVerified()->asCustomer(),
            'occasion_id' => Occasion::factory(),
            'lifecycle_status' => DraftState::class,
            'payment_status' => UnpaidState::class,
            'fulfillment_status' => FulfillmentStatus::NotStarted,
            'event_starts_at' => now()->addDays(14),
            'event_ends_at' => now()->addDays(14)->addHours(4),
            'guest_count' => $this->faker->numberBetween(10, 120),
            'theme' => [
                'code' => 'classic-party',
                'accent' => 'gold',
            ],
            'celebrant_name' => $this->faker->name(),
            'celebrant_dob' => $this->faker->date(),
            'celebrant_gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'subtotal_minor' => 0,
            'subtotal_currency' => 'EGP',
            'delivery_total_minor' => 0,
            'delivery_total_currency' => 'EGP',
            'discount_total_minor' => 0,
            'discount_total_currency' => 'EGP',
            'loyalty_redeemed_minor' => 0,
            'loyalty_redeemed_currency' => 'EGP',
            'total_minor' => 0,
            'total_currency' => 'EGP',
            'amount_paid_minor' => 0,
            'amount_paid_currency' => 'EGP',
            'submitted_at' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'payment_hold_expires_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['lifecycle_status' => DraftState::class]);
    }

    public function submitted(): static
    {
        return $this->state([
            'lifecycle_status' => SubmittedState::class,
            'submitted_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'lifecycle_status' => ConfirmedState::class,
            'payment_status' => PaidState::class,
            'confirmed_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(['lifecycle_status' => ActiveState::class]);
    }

    public function completed(): static
    {
        return $this->state([
            'lifecycle_status' => CompletedState::class,
            'fulfillment_status' => FulfillmentStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'lifecycle_status' => CancelledState::class,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Booking in VendorReview with at least one vendor whose response_deadline
     * is already past — triggers the "late vendor response" trouble bucket.
     */
    public function stalledWithLateVendor(): static
    {
        return $this->state([
            'lifecycle_status' => VendorReviewState::class,
            'submitted_at' => now()->subHours(50),
        ])->afterCreating(function (Booking $booking): void {
            BookingVendorFactory::new()->create([
                'booking_id'        => $booking->id,
                'sub_status'        => VendorSubStatus::Pending,
                'response_deadline' => now()->subHours(2),
            ]);
        });
    }

    /**
     * Booking in VendorReview where every attached vendor has rejected —
     * triggers the "all vendors rejected" trouble bucket.
     */
    public function withAllVendorsRejected(): static
    {
        return $this->state([
            'lifecycle_status' => VendorReviewState::class,
            'submitted_at' => now()->subHours(10),
        ])->afterCreating(function (Booking $booking): void {
            BookingVendorFactory::new()->create([
                'booking_id' => $booking->id,
                'sub_status' => VendorSubStatus::Rejected,
            ]);
        });
    }

    /**
     * Booking sitting in CustomerReview (open modification awaiting customer).
     */
    public function withCustomerReviewPending(): static
    {
        return $this->state([
            'lifecycle_status' => CustomerReviewState::class,
            'submitted_at' => now()->subHours(10),
        ]);
    }

    /**
     * Generic stalled booking — submitted/vendor_review but older than the
     * configured stalled-hours threshold.
     */
    public function stalled(): static
    {
        return $this->state([
            'lifecycle_status' => SubmittedState::class,
            'submitted_at' => now()->subHours(50),
        ]);
    }
}
