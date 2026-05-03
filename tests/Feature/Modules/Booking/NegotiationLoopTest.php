<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('full loop: draft → submit → vendor accepts → confirmed', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    // Vendor accepts
    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::Confirmed);

    // Verify full state transition chain exists
    $transitions = DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->orderBy('id')
        ->pluck('to_state')
        ->all();

    expect($transitions)->toContain('confirmed');
})->group('booking', 'negotiation', 'loop');

it('full loop: submit → vendor modifies → customer accepts → confirmed', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    // Vendor modifies
    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes' => [
                [
                    'change_kind' => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload' => ['unit_price_minor' => 70000],
                ],
            ],
        ]
    )->assertCreated();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::CustomerReview);

    $modification = BookingModification::where('booking_vendor_id', $bv->id)->latest('id')->first();

    // Customer accepts
    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$modification->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::Confirmed);

    $item->refresh();
    expect($item->unit_price_minor)->toBe(70000);
})->group('booking', 'negotiation', 'loop');

it('full loop: submit → vendor modifies → customer rejects → vendor modifies again → customer accepts → confirmed', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    // Round 1: vendor modifies
    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes' => [
                [
                    'change_kind' => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload' => ['unit_price_minor' => 70000],
                ],
            ],
        ]
    )->assertCreated();

    $mod1 = BookingModification::where('booking_vendor_id', $bv->id)->latest('id')->first();

    // Customer rejects round 1
    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod1->public_id}/decide",
        ['decision' => 'rejected'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Pending);

    // Round 2: vendor modifies again with new price
    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes' => [
                [
                    'change_kind' => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload' => ['unit_price_minor' => 65000],
                ],
            ],
        ]
    )->assertCreated();

    $mod2 = BookingModification::where('booking_vendor_id', $bv->id)->latest('id')->first();

    // Customer accepts round 2
    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod2->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::Confirmed);

    $item->refresh();
    expect($item->unit_price_minor)->toBe(65000);

    // Two modifications exist: first rejected, second accepted
    $mods = BookingModification::where('booking_vendor_id', $bv->id)->orderBy('id')->get();
    expect($mods->count())->toBe(2);
    expect($mods[0]->status)->toBe(ModificationStatus::CustomerRejected);
    expect($mods[1]->status)->toBe(ModificationStatus::CustomerAccepted);
})->group('booking', 'negotiation', 'loop');

it('full loop: submit → vendor rejects → booking cancelled', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject",
        ['rejection_reason' => ['en' => 'Fully booked', 'ar' => 'محجوز بالكامل']]
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::Cancelled);
})->group('booking', 'negotiation', 'loop');

it('booking snapshots are written at each negotiation event', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $snapshotsBefore = BookingSnapshot::where('booking_id', $booking->id)->count();

    // Vendor modifies → snapshot written
    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes' => [
                [
                    'change_kind' => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload' => ['unit_price_minor' => 70000],
                ],
            ],
        ]
    )->assertCreated();

    $modification = BookingModification::where('booking_vendor_id', $bv->id)->latest('id')->first();

    // Customer accepts → snapshot written (CustomerModificationDecided + BookingConfirmed = 2)
    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$modification->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $snapshotsAfter = BookingSnapshot::where('booking_id', $booking->id)->count();
    // At minimum 2 new snapshots were written (vendor_responded + booking_confirmed)
    expect($snapshotsAfter)->toBeGreaterThan($snapshotsBefore + 1);
})->group('booking', 'negotiation', 'loop');
