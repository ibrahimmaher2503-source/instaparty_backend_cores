<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function idemCustomer(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function idemDraftBookingWithItem(User $customer): Booking
{
    $occasion = Occasion::factory()->create();
    $city     = City::factory()->create();

    $booking = Booking::create([
        'public_id'               => (string) Str::ulid(),
        'reference_no'            => 'IP-2026-IDM' . rand(100, 999),
        'customer_id'             => $customer->id,
        'occasion_id'             => $occasion->id,
        'lifecycle_status'        => DraftState::class,
        'payment_status'          => UnpaidState::class,
        'fulfillment_status'      => FulfillmentStatus::NotStarted,
        'event_starts_at'         => now()->addDays(30),
        'event_ends_at'           => now()->addDays(30)->addHours(5),
        'subtotal_currency'       => 'EGP',
        'delivery_total_currency' => 'EGP',
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_currency' => 'EGP',
        'total_currency'          => 'EGP',
        'amount_paid_currency'    => 'EGP',
    ]);

    BookingAddress::create([
        'booking_id'           => $booking->id,
        'city_id'              => $city->id,
        'address_line'         => '5 Hassan St',
        'recipient_name'       => 'Idem User',
        'recipient_phone_e164' => '+201098765432',
    ]);

    $vendor   = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create();
    $service  = Service::factory()->create([
        'product_type'      => ProductType::Sale,
        'status'            => PublishedState::class,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
        'base_price_minor'  => 60000,
    ]);

    $bookingVendor = BookingVendor::create([
        'public_id'              => (string) Str::ulid(),
        'booking_id'             => $booking->id,
        'vendor_profile_id'      => $vendor->id,
        'sub_status'             => VendorSubStatus::Pending,
        'subtotal_minor'         => 60000,
        'subtotal_currency'      => 'EGP',
        'delivery_fee_minor'     => 0,
        'delivery_fee_currency'  => 'EGP',
        'commission_minor'       => 0,
        'commission_currency'    => 'EGP',
        'vendor_payout_minor'    => 60000,
        'vendor_payout_currency' => 'EGP',
    ]);

    BookingItem::create([
        'public_id'           => (string) Str::ulid(),
        'booking_vendor_id'   => $bookingVendor->id,
        'service_id'          => $service->id,
        'product_type'        => ProductType::Sale,
        'name_snapshot'       => ['en' => 'Idem Item', 'ar' => 'عنصر'],
        'unit_price_minor'    => 60000,
        'unit_price_currency' => 'EGP',
        'line_total_minor'    => 60000,
        'line_total_currency' => 'EGP',
        'commission_minor'    => 0,
        'commission_currency' => 'EGP',
        'quantity'            => 1,
        'item_status'         => 'pending',
        'commission_bps'      => 0,
    ]);

    return $booking;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('same Idempotency-Key replays the response without creating a second state transition', function (): void {
    $customer     = idemCustomer();
    $booking      = idemDraftBookingWithItem($customer);
    $idempotencyKey = (string) Str::uuid();

    // First submit
    $first = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    );
    $first->assertOk();

    $transitionsAfterFirst = DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'vendor_review')
        ->count();

    // Second submit — same key, same body
    $second = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    );
    $second->assertOk();

    // Response shape is identical
    expect($second->json('data.lifecycle_status'))->toBe('vendor_review');

    // No additional state transition was created
    $transitionsAfterSecond = DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'vendor_review')
        ->count();

    expect($transitionsAfterSecond)->toBe($transitionsAfterFirst);
})->group('booking', 'idempotency');

it('same Idempotency-Key with different request body returns 409 conflict', function (): void {
    $customer1    = idemCustomer();
    $customer2    = idemCustomer();
    $booking1     = idemDraftBookingWithItem($customer1);
    $booking2     = idemDraftBookingWithItem($customer2);
    $idempotencyKey = (string) Str::uuid();

    // First submit for customer1 / booking1
    $this->actingAs($customer1)->postJson(
        "/api/v1/customer/bookings/{$booking1->public_id}/submit",
        [],
        ['Idempotency-Key' => $idempotencyKey]
    )->assertOk();

    // Second submit with same key but different route/body (different booking public_id acts as different content)
    // We re-use the same customer so the user_id scope matches, but different URL = different request hash
    $second = $this->actingAs($customer1)->postJson(
        "/api/v1/customer/bookings/{$booking1->public_id}/submit",
        ['_different' => true],   // different body → different request hash
        ['Idempotency-Key' => $idempotencyKey]
    );

    $second->assertStatus(409);
})->group('booking', 'idempotency');

it('missing Idempotency-Key header returns 422', function (): void {
    $customer = idemCustomer();
    $booking  = idemDraftBookingWithItem($customer);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/submit"
    )->assertStatus(422);
})->group('booking', 'idempotency');
