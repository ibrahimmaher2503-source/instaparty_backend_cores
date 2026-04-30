<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('vendor accepts → booking_vendor sub_status becomes accepted', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    );

    $response->assertOk();
    $response->assertJsonPath('data.sub_status', 'accepted');

    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Accepted);
    expect($bv->responded_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('single vendor accepts → booking lifecycle_status becomes confirmed', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::Confirmed);
    expect($booking->confirmed_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('booking_state_transitions has row to confirmed after single vendor accepts', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    );

    expect(\Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'confirmed')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation');

it('multi-vendor: first vendor accepts but second still pending → booking stays vendor_review', function (): void {
    ['customer' => $customer, 'booking' => $booking] = makeSubmittedBookingWithVendor();

    // Second vendor
    $vendor2  = VendorProfile::factory()->approved()->create();
    $category = \App\Modules\Catalog\Domain\Models\Category::factory()->create();
    $service2 = \App\Modules\Catalog\Domain\Models\Service::factory()->create([
        'product_type'      => ProductType::Digital,
        'status'            => \App\Modules\Catalog\Domain\Enums\ServiceStatus::Published,
        'vendor_profile_id' => $vendor2->id,
        'category_id'       => $category->id,
    ]);

    $bv2 = BookingVendor::create([
        'public_id'              => (string) Str::ulid(),
        'booking_id'             => $booking->id,
        'vendor_profile_id'      => $vendor2->id,
        'sub_status'             => VendorSubStatus::Pending,
        'response_deadline'      => now()->addHours(24),
        'subtotal_minor'         => 30000,
        'subtotal_currency'      => 'EGP',
        'delivery_fee_minor'     => 0,
        'delivery_fee_currency'  => 'EGP',
        'commission_minor'       => 0,
        'commission_currency'    => 'EGP',
        'vendor_payout_minor'    => 30000,
        'vendor_payout_currency' => 'EGP',
    ]);

    \App\Modules\Booking\Domain\Models\BookingItem::create([
        'public_id'           => (string) Str::ulid(),
        'booking_vendor_id'   => $bv2->id,
        'service_id'          => $service2->id,
        'product_type'        => ProductType::Digital,
        'name_snapshot'       => ['en' => 'Digital Item', 'ar' => 'رقمي'],
        'unit_price_minor'    => 30000,
        'unit_price_currency' => 'EGP',
        'line_total_minor'    => 30000,
        'line_total_currency' => 'EGP',
        'commission_minor'    => 0,
        'commission_currency' => 'EGP',
        'quantity'            => 1,
        'item_status'         => 'pending',
        'commission_bps'      => 0,
    ]);

    // First vendor accepts
    $bv1 = $booking->vendors()->where('vendor_profile_id', '!=', $vendor2->id)->first();
    $vendor1 = VendorProfile::find($bv1->vendor_profile_id);
    $this->actingAs($vendor1->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv1->public_id}/accept"
    )->assertOk();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::VendorReview);
})->group('booking', 'negotiation');

it('Rental inventory reservation upgrades to confirmed when booking confirms', function (): void {
    $customer = \App\Modules\Identity\Domain\Models\User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
    $occasion = \App\Modules\Catalog\Domain\Models\Occasion::factory()->create();
    $city     = \App\Modules\Geography\Domain\Models\City::factory()->create();

    $booking = Booking::create([
        'public_id'                 => (string) Str::ulid(),
        'reference_no'              => 'IP-2026-V002',
        'customer_id'               => $customer->id,
        'occasion_id'               => $occasion->id,
        'lifecycle_status'          => LifecycleStatus::VendorReview,
        'payment_status'            => \App\Modules\Booking\Domain\Enums\PaymentStatus::Unpaid,
        'fulfillment_status'        => \App\Modules\Booking\Domain\Enums\FulfillmentStatus::NotStarted,
        'event_starts_at'           => now()->addDays(30),
        'event_ends_at'             => now()->addDays(30)->addHours(5),
        'subtotal_currency'         => 'EGP',
        'delivery_total_currency'   => 'EGP',
        'discount_total_currency'   => 'EGP',
        'loyalty_redeemed_currency' => 'EGP',
        'total_currency'            => 'EGP',
        'amount_paid_currency'      => 'EGP',
    ]);

    \App\Modules\Booking\Domain\Models\BookingAddress::create([
        'booking_id' => $booking->id, 'city_id' => $city->id,
        'address_line' => '1 Rental St', 'recipient_name' => 'Test', 'recipient_phone_e164' => '+201111111111',
    ]);

    $vendor  = VendorProfile::factory()->approved()->create();
    $category = \App\Modules\Catalog\Domain\Models\Category::factory()->create();
    $service = \App\Modules\Catalog\Domain\Models\Service::factory()->create([
        'product_type'      => ProductType::Rental,
        'status'            => \App\Modules\Catalog\Domain\Enums\ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);

    $bv = BookingVendor::create([
        'public_id'              => (string) Str::ulid(),
        'booking_id'             => $booking->id,
        'vendor_profile_id'      => $vendor->id,
        'sub_status'             => VendorSubStatus::Pending,
        'response_deadline'      => now()->addHours(24),
        'subtotal_minor'         => 100000,
        'subtotal_currency'      => 'EGP',
        'delivery_fee_minor'     => 0,
        'delivery_fee_currency'  => 'EGP',
        'commission_minor'       => 0,
        'commission_currency'    => 'EGP',
        'vendor_payout_minor'    => 100000,
        'vendor_payout_currency' => 'EGP',
    ]);

    $item = \App\Modules\Booking\Domain\Models\BookingItem::create([
        'public_id'           => (string) Str::ulid(),
        'booking_vendor_id'   => $bv->id,
        'service_id'          => $service->id,
        'product_type'        => ProductType::Rental,
        'name_snapshot'       => ['en' => 'Rental Item', 'ar' => 'إيجار'],
        'unit_price_minor'    => 100000,
        'unit_price_currency' => 'EGP',
        'line_total_minor'    => 100000,
        'line_total_currency' => 'EGP',
        'commission_minor'    => 0,
        'commission_currency' => 'EGP',
        'quantity'            => 1,
        'item_status'         => 'pending_delivery',
        'commission_bps'      => 0,
    ]);

    // Create a held reservation
    \Illuminate\Support\Facades\DB::table('service_inventory_reservations')->insert([
        'public_id'             => (string) Str::ulid(),
        'service_id'            => $service->id,
        'user_id'               => $customer->id,
        'booking_item_id'       => $item->id,
        'product_type'          => 'rental',
        'hold_type'             => 'payment',
        'quantity'              => 1,
        'status'                => 'held',
        'reserved_starts_at'    => now()->addDays(30),
        'reserved_ends_at'      => now()->addDays(30)->addHours(5),
        'expires_at'            => now()->addHours(24),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    )->assertOk();

    expect(\Illuminate\Support\Facades\DB::table('service_inventory_reservations')
        ->where('booking_item_id', $item->id)
        ->where('status', 'confirmed')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation', 'rental');

it('returns 403 when vendor accepts wrong booking_vendor', function (): void {
    $anotherVendor = VendorProfile::factory()->approved()->create();
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($anotherVendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    );

    $response->assertStatus(403);
})->group('booking', 'negotiation');

it('returns 409 when vendor accepts non-pending booking_vendor', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $response = $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/accept"
    );

    $response->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 401 when unauthenticated on accept', function (): void {
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/accept")
        ->assertStatus(401);
})->group('booking', 'negotiation');
