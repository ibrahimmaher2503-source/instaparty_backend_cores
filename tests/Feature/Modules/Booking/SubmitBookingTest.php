<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeNegotiationCustomer(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function makeNegotiationDraftBooking(User $customer): Booking
{
    $occasion = Occasion::factory()->create();
    $city     = City::factory()->create();

    $booking = Booking::create([
        'public_id'                  => (string) Str::ulid(),
        'reference_no'               => 'IP-2026-N0001',
        'customer_id'                => $customer->id,
        'occasion_id'                => $occasion->id,
        'lifecycle_status'           => LifecycleStatus::Draft,
        'payment_status'             => PaymentStatus::Unpaid,
        'fulfillment_status'         => FulfillmentStatus::NotStarted,
        'event_starts_at'            => now()->addDays(30),
        'event_ends_at'              => now()->addDays(30)->addHours(5),
        'subtotal_currency'          => 'EGP',
        'delivery_total_currency'    => 'EGP',
        'discount_total_currency'    => 'EGP',
        'loyalty_redeemed_currency'  => 'EGP',
        'total_currency'             => 'EGP',
        'amount_paid_currency'       => 'EGP',
    ]);

    \App\Modules\Booking\Domain\Models\BookingAddress::create([
        'booking_id'           => $booking->id,
        'city_id'              => $city->id,
        'address_line'         => '123 Test St',
        'recipient_name'       => 'Test User',
        'recipient_phone_e164' => '+201234567890',
    ]);

    return $booking;
}

function makeNegotiationService(?VendorProfile $vendor = null, ProductType $type = ProductType::Sale): Service
{
    $vendor   = $vendor ?? VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create();

    return Service::factory()->create([
        'product_type'      => $type,
        'status'            => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);
}

function addItemToBookingForTest(Booking $booking, ?VendorProfile $vendor = null, ProductType $type = ProductType::Sale): array
{
    $vendor  = $vendor ?? VendorProfile::factory()->approved()->create();
    $service = makeNegotiationService($vendor, $type);

    $bookingVendor = BookingVendor::create([
        'public_id'              => (string) Str::ulid(),
        'booking_id'             => $booking->id,
        'vendor_profile_id'      => $vendor->id,
        'sub_status'             => VendorSubStatus::Pending,
        'subtotal_minor'         => 50000,
        'subtotal_currency'      => 'EGP',
        'delivery_fee_minor'     => 0,
        'delivery_fee_currency'  => 'EGP',
        'commission_minor'       => 0,
        'commission_currency'    => 'EGP',
        'vendor_payout_minor'    => 50000,
        'vendor_payout_currency' => 'EGP',
    ]);

    $item = BookingItem::create([
        'public_id'           => (string) Str::ulid(),
        'booking_vendor_id'   => $bookingVendor->id,
        'service_id'          => $service->id,
        'product_type'        => $type,
        'name_snapshot'       => ['en' => 'Test Item', 'ar' => 'عنصر اختبار'],
        'unit_price_minor'    => 50000,
        'unit_price_currency' => 'EGP',
        'line_total_minor'    => 50000,
        'line_total_currency' => 'EGP',
        'commission_minor'    => 0,
        'commission_currency' => 'EGP',
        'quantity'            => 1,
        'item_status'         => 'pending',
        'commission_bps'      => 0,
    ]);

    return ['vendor' => $vendor, 'bookingVendor' => $bookingVendor, 'item' => $item, 'service' => $service];
}

it('submits a draft booking — lifecycle_status becomes vendor_review', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);

    $idempotencyKey = (string) Str::uuid();

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    );

    $response->assertOk();
    $response->assertJsonPath('data.lifecycle_status', 'vendor_review');

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::VendorReview);
    expect($booking->submitted_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('creates booking_vendor pending rows with response_deadline on submit', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    ['vendor' => $vendor, 'bookingVendor' => $bv] = addItemToBookingForTest($booking);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $response->assertOk();

    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Pending);
    expect($bv->response_deadline)->not->toBeNull();
})->group('booking', 'negotiation');

it('creates a booking_state_transitions row to vendor_review on submit', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    expect(\Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'vendor_review')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation');

it('returns 422 when submitting empty draft', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $response->assertStatus(422);
})->group('booking', 'negotiation');

it('returns 409 when submitting a non-draft booking', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);

    $booking->update(['lifecycle_status' => LifecycleStatus::VendorReview]);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $response->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 403 when submitting another customer\'s booking', function (): void {
    $customer1 = makeNegotiationCustomer();
    $customer2 = makeNegotiationCustomer();
    $booking   = makeNegotiationDraftBooking($customer1);
    addItemToBookingForTest($booking);

    $response = $this->actingAs($customer2)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $response->assertStatus(403);
})->group('booking', 'negotiation');

it('returns 401 when unauthenticated', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);

    $response = $this->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => (string) Str::uuid()]
    );

    $response->assertStatus(401);
})->group('booking', 'negotiation');

it('returns 422 when Idempotency-Key header is missing', function (): void {
    $customer = makeNegotiationCustomer();
    $booking  = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit"
    );

    $response->assertStatus(422);
})->group('booking', 'negotiation');

it('idempotency: duplicate submit key returns same response without re-submitting', function (): void {
    $customer       = makeNegotiationCustomer();
    $booking        = makeNegotiationDraftBooking($customer);
    addItemToBookingForTest($booking);
    $idempotencyKey = (string) Str::uuid();

    // First submit
    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    )->assertOk();

    $transitionCount = \Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'vendor_review')
        ->count();

    // Second submit with same key
    $response2 = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    );

    $response2->assertOk();

    $transitionCountAfter = \Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'vendor_review')
        ->count();

    expect($transitionCountAfter)->toBe($transitionCount);
})->group('booking', 'negotiation');
