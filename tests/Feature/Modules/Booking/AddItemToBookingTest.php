<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeCustomer(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function makeDraftBooking(User $customer): Booking
{
    $occasion = Occasion::factory()->create();
    $city = City::factory()->create();

    return Booking::create([
        'public_id'               => \Illuminate\Support\Str::ulid(),
        'reference_no'            => 'IP-2026-TEST01',
        'customer_id'             => $customer->id,
        'occasion_id'             => $occasion->id,
        'lifecycle_status'        => LifecycleStatus::Draft,
        'payment_status'          => \App\Modules\Booking\Domain\Enums\PaymentStatus::Unpaid,
        'fulfillment_status'      => \App\Modules\Booking\Domain\Enums\FulfillmentStatus::NotStarted,
        'event_starts_at'         => now()->addDays(30),
        'event_ends_at'           => now()->addDays(30)->addHours(5),
        'subtotal_currency'       => 'EGP',
        'delivery_total_currency' => 'EGP',
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_currency' => 'EGP',
        'total_currency'          => 'EGP',
        'amount_paid_currency'    => 'EGP',
    ]);
}

function makePublishedService(ProductType $type, array $overrides = []): Service
{
    $category = Category::factory()->create();
    $vendor = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();

    return Service::factory()->create(array_merge([
        'product_type'      => $type,
        'status'            => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ], $overrides));
}

it('adds a rental item and creates a reservation', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $service  = makePublishedService(ProductType::Rental);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 1]
    );

    $response->assertStatus(201);
    $response->assertJsonPath('data.item_status', 'pending_delivery');

    expect(\Illuminate\Support\Facades\DB::table('service_inventory_reservations')
        ->where('service_id', $service->id)
        ->where('status', 'held')
        ->exists())->toBeTrue();
})->group('booking', 'add-item', 'rental');

it('adds a sale item and creates a stock reservation', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $service  = makePublishedService(ProductType::Sale);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 10]);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 2]
    );

    $response->assertStatus(201);
    $response->assertJsonPath('data.item_status', 'pending');

    expect(\Illuminate\Support\Facades\DB::table('service_inventory_reservations')
        ->where('service_id', $service->id)
        ->where('status', 'held')
        ->exists())->toBeTrue();
})->group('booking', 'add-item', 'sale');

it('adds a digital item without creating a reservation', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $service  = makePublishedService(ProductType::Digital);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 1]
    );

    $response->assertStatus(201);
    $response->assertJsonPath('data.item_status', 'pending');

    expect(\Illuminate\Support\Facades\DB::table('service_inventory_reservations')
        ->where('service_id', $service->id)
        ->exists())->toBeFalse();
})->group('booking', 'add-item', 'digital');

it('adds two items from same vendor into one booking_vendor row', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $vendor   = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $category = Category::factory()->create();

    $service1 = Service::factory()->create([
        'product_type'      => ProductType::Digital,
        'status'            => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);
    $service2 = Service::factory()->create([
        'product_type'      => ProductType::Digital,
        'status'            => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id'       => $category->id,
    ]);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service1->public_id, 'quantity' => 1]
    )->assertStatus(201);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service2->public_id, 'quantity' => 1]
    )->assertStatus(201);

    expect(BookingVendor::where('booking_id', $booking->id)->count())->toBe(1);
})->group('booking', 'add-item');

it('adds items from two vendors creating two booking_vendor rows', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $service1 = makePublishedService(ProductType::Digital);
    $service2 = makePublishedService(ProductType::Digital);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service1->public_id, 'quantity' => 1]
    )->assertStatus(201);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service2->public_id, 'quantity' => 1]
    )->assertStatus(201);

    expect(BookingVendor::where('booking_id', $booking->id)->count())->toBe(2);
})->group('booking', 'add-item');

it('returns 409 when adding to a non-draft booking', function (): void {
    $customer = makeCustomer();
    $booking  = makeDraftBooking($customer);
    $booking->update(['lifecycle_status' => LifecycleStatus::Submitted]);
    $service = makePublishedService(ProductType::Digital);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 1]
    )->assertStatus(409);
})->group('booking', 'add-item');

it('returns 403 when adding to another user\'s booking', function (): void {
    $customer = makeCustomer();
    $other    = makeCustomer();
    $booking  = makeDraftBooking($other);
    $service  = makePublishedService(ProductType::Digital);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 1]
    )->assertStatus(403);
})->group('booking', 'add-item');

it('returns 401 when unauthenticated', function (): void {
    $this->postJson('/api/v1/customer/bookings/FAKE/items', [])->assertStatus(401);
})->group('booking', 'add-item');
