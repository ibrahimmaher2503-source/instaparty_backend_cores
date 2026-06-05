<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Str;

/**
 * Gap-closure Phase 2.2 — distinct display statuses on the customer booking
 * payload. The lifecycle state machine is untouched; `display_status`,
 * `rejection_reason`, and `vendors_summary` are additive derived fields so the
 * client stops conflating rejected with cancelled.
 */
it('reads rejected, not cancelled, when every vendor rejected', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $data['bookingVendor']->update([
        'sub_status' => VendorSubStatus::Rejected,
        'rejection_reason' => ['en' => 'Fully booked that day', 'ar' => 'محجوز بالكامل في هذا اليوم'],
        'responded_at' => now(),
    ]);
    $data['booking']->update([
        'lifecycle_status' => CancelledState::class,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id, ['Accept-Language' => 'en'])
        ->assertStatus(200);

    expect($response->json('data.lifecycle_status'))->toBe('cancelled')
        ->and($response->json('data.display_status'))->toBe('rejected')
        ->and($response->json('data.rejection_reason'))->toBe('Fully booked that day')
        ->and($response->json('data.vendors.0.rejection_reason'))->toBe('Fully booked that day')
        ->and($response->json('data.vendors_summary.rejected'))->toBe(1);
})->group('booking', 'status-exposure');

it('localizes the rejection reason to Arabic', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $data['bookingVendor']->update([
        'sub_status' => VendorSubStatus::Rejected,
        'rejection_reason' => ['en' => 'Fully booked that day', 'ar' => 'محجوز بالكامل في هذا اليوم'],
    ]);
    $data['booking']->update(['lifecycle_status' => CancelledState::class, 'cancelled_at' => now()]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id, ['Accept-Language' => 'ar'])
        ->assertStatus(200);

    expect($response->json('data.rejection_reason'))->toBe('محجوز بالكامل في هذا اليوم');
})->group('booking', 'status-exposure');

it('keeps cancelled as cancelled when the cancellation was not vendor rejection', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $data['bookingVendor']->update(['sub_status' => VendorSubStatus::Cancelled]);
    $data['booking']->update(['lifecycle_status' => CancelledState::class, 'cancelled_at' => now()]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id)
        ->assertStatus(200);

    expect($response->json('data.display_status'))->toBe('cancelled')
        ->and($response->json('data.rejection_reason'))->toBeNull();
})->group('booking', 'status-exposure');

it('reads modification_requested while a vendor proposal awaits the customer', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $data['booking']->update(['lifecycle_status' => CustomerReviewState::class]);

    BookingModification::create([
        'public_id' => (string) Str::ulid(),
        'booking_vendor_id' => $data['bookingVendor']->id,
        'proposed_by' => $data['vendor']->user->id,
        'proposal_kind' => ModificationProposalKind::ChangePrice,
        'status' => ModificationStatus::Pending,
        'vendor_explanation' => ['en' => 'Price update', 'ar' => 'تحديث السعر'],
        'diff_snapshot' => ['totals' => ['price_delta_minor' => 1000]],
    ]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id)
        ->assertStatus(200);

    expect($response->json('data.lifecycle_status'))->toBe('customer_review')
        ->and($response->json('data.display_status'))->toBe('modification_requested');
})->group('booking', 'status-exposure');

it('summarizes per-vendor statuses for multi-vendor bookings', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $secondVendor = VendorProfile::factory()->approved()->create();
    BookingVendor::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'vendor_profile_id' => $secondVendor->id,
        'sub_status' => VendorSubStatus::Accepted,
        'responded_at' => now(),
        'subtotal_minor' => 30000,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 30000,
        'vendor_payout_currency' => 'EGP',
    ]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id)
        ->assertStatus(200);

    expect($response->json('data.vendors_summary'))->toMatchArray([
        'total' => 2,
        'pending' => 1,
        'accepted' => 1,
        'rejected' => 0,
    ])->and($response->json('data.display_status'))->toBe('vendor_review');
})->group('booking', 'status-exposure');
