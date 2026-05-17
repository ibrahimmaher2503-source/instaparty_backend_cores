<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('vendor rejects → booking_vendor sub_status becomes rejected', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject",
        ['rejection_reason' => ['en' => 'Not available', 'ar' => 'غير متاح']]
    );

    $response->assertOk();
    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Rejected);
    expect($bv->responded_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('single vendor rejects → booking lifecycle_status becomes cancelled', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject"
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(CancelledState::class);
    expect($booking->cancelled_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('booking_state_transitions has row to cancelled after single vendor rejects', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject"
    );

    expect(DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'cancelled')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation');

it('rental inventory reservation released when booking is cancelled via rejection', function (): void {
    ['customer' => $customer, 'booking' => $booking, 'vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    DB::table('service_inventory_reservations')->insert([
        'public_id' => (string) Str::ulid(),
        'service_id' => $item->service_id,
        'user_id' => $customer->id,
        'booking_item_id' => $item->id,
        'product_type' => 'rental',
        'hold_type' => 'payment',
        'quantity' => 1,
        'status' => 'held',
        'reserved_starts_at' => now()->addDays(30),
        'reserved_ends_at' => now()->addDays(30)->addHours(5),
        'expires_at' => now()->addHours(24),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject"
    )->assertOk();

    expect(DB::table('service_inventory_reservations')
        ->where('booking_item_id', $item->id)
        ->where('status', 'released')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation', 'rental');

it('multi-vendor: first rejects, second still pending → booking goes to customer_review', function (): void {
    ['customer' => $customer, 'booking' => $booking] = makeSubmittedBookingWithVendor();

    $vendor2 = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create();
    $service2 = Service::factory()->create([
        'product_type' => ProductType::Sale,
        'status' => PublishedState::class,
        'vendor_profile_id' => $vendor2->id,
        'category_id' => $category->id,
    ]);

    $bv2 = BookingVendor::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor2->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => now()->addHours(24),
        'subtotal_minor' => 30000,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 30000,
        'vendor_payout_currency' => 'EGP',
    ]);

    BookingItem::create([
        'public_id' => (string) Str::ulid(),
        'booking_vendor_id' => $bv2->id,
        'service_id' => $service2->id,
        'product_type' => ProductType::Sale,
        'name_snapshot' => ['en' => 'Item 2', 'ar' => 'عنصر 2'],
        'unit_price_minor' => 30000,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 30000,
        'line_total_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'quantity' => 1,
        'item_status' => 'pending',
        'commission_bps' => 0,
    ]);

    // First vendor rejects
    $bv1 = $booking->vendors()->where('vendor_profile_id', '!=', $vendor2->id)->first();
    $vendor1 = VendorProfile::find($bv1->vendor_profile_id);

    $this->actingAs($vendor1->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv1->public_id}/reject"
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(CustomerReviewState::class);
})->group('booking', 'negotiation');

it('returns 403 when wrong vendor tries to reject', function (): void {
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();
    $otherVendor = VendorProfile::factory()->approved()->create();

    $this->actingAs($otherVendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject"
    )->assertStatus(403);
})->group('booking', 'negotiation');

it('returns 409 when vendor rejects non-pending booking_vendor', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/reject"
    )->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 401 when unauthenticated on reject', function (): void {
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/reject")
        ->assertStatus(401);
})->group('booking', 'negotiation');
