<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('customer accepts modification → booking_vendor sub_status becomes accepted', function (): void {
    ['customer' => $customer, 'bookingVendor' => $bv, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$bv->booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Accepted);
})->group('booking', 'negotiation');

it('customer accepts modification → booking becomes confirmed (single vendor)', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'bookingVendor' => $bv, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(ConfirmedState::class);
})->group('booking', 'negotiation');

it('customer accepts modification → unit_price_minor updated on booking_item', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $item->refresh();
    expect($item->unit_price_minor)->toBe(60000);
})->group('booking', 'negotiation');

it('customer accepts modification → modification status becomes customer_accepted', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $mod->refresh();
    expect($mod->status)->toBe(ModificationStatus::CustomerAccepted);
    expect($mod->customer_decision_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('customer rejects modification → booking_vendor goes back to pending', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'bookingVendor' => $bv, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'rejected'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Pending);
})->group('booking', 'negotiation');

it('customer rejects modification → booking returns to vendor_review', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'rejected'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(VendorReviewState::class);
})->group('booking', 'negotiation');

it('customer rejects modification → modification status becomes customer_rejected', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'rejected'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertOk();

    $mod->refresh();
    expect($mod->status)->toBe(ModificationStatus::CustomerRejected);
})->group('booking', 'negotiation');

it('booking_state_transitions has row to confirmed after customer accepts', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    expect(DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'confirmed')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation');

it('idempotency: duplicate decision key returns same response', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();
    $idempotencyKey = (string) Str::uuid();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => $idempotencyKey]
    )->assertOk();

    $transitionCount = DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'confirmed')
        ->count();

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => $idempotencyKey]
    )->assertOk();

    $transitionCountAfter = DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'confirmed')
        ->count();

    expect($transitionCountAfter)->toBe($transitionCount);
})->group('booking', 'negotiation');

it('returns 403 when another customer tries to decide', function (): void {
    ['booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();
    $otherCustomer = User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);

    $this->actingAs($otherCustomer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertStatus(403);
})->group('booking', 'negotiation');

it('returns 409 when modification is not pending', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();
    $mod->update(['status' => ModificationStatus::CustomerAccepted]);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 401 when unauthenticated on decide', function (): void {
    ['booking' => $booking, 'modification' => $mod] = makeBookingWithPendingModification();

    $this->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$mod->public_id}/decide",
        ['decision' => 'accepted'],
        ['Idempotency-Key' => (string) Str::uuid()]
    )->assertStatus(401);
})->group('booking', 'negotiation');
