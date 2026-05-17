<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
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

function removeCustomer(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function removeDraftBooking(User $customer): Booking
{
    $occasion = Occasion::factory()->create();
    $city = City::factory()->create();

    return Booking::create([
        'public_id' => (string) Str::ulid(),
        'reference_no' => 'IP-2026-REM01',
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'lifecycle_status' => DraftState::class,
        'payment_status' => UnpaidState::class,
        'fulfillment_status' => FulfillmentStatus::NotStarted,
        'event_starts_at' => now()->addDays(30),
        'event_ends_at' => now()->addDays(30)->addHours(5),
        'subtotal_currency' => 'EGP',
        'delivery_total_currency' => 'EGP',
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_currency' => 'EGP',
        'total_currency' => 'EGP',
        'amount_paid_currency' => 'EGP',
    ]);
}

function addItemToBooking(Booking $booking): array
{
    $vendor = VendorProfile::factory()->create();
    $category = Category::factory()->create();
    $service = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => PublishedState::class,
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'base_price_minor' => 50000,
    ]);

    $bv = BookingVendor::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => 'pending',
        'subtotal_minor' => 50000,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 0,
        'vendor_payout_currency' => 'EGP',
    ]);

    $item = BookingItem::create([
        'public_id' => (string) Str::ulid(),
        'booking_vendor_id' => $bv->id,
        'service_id' => $service->id,
        'product_type' => ProductType::Digital->value,
        'name_snapshot' => ['en' => 'Test', 'ar' => 'اختبار'],
        'unit_price_minor' => 50000,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 50000,
        'line_total_currency' => 'EGP',
        'commission_currency' => 'EGP',
        'quantity' => 1,
        'item_status' => 'pending',
        'commission_bps' => 0,
    ]);

    return ['booking_vendor' => $bv, 'item' => $item];
}

it('removes an item and returns 200', function (): void {
    $customer = removeCustomer();
    $booking = removeDraftBooking($customer);
    $data = addItemToBooking($booking);
    $item = $data['item'];

    $response = $this->actingAs($customer)->deleteJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items/{$item->public_id}"
    );

    $response->assertStatus(200);
    expect(BookingItem::where('id', $item->id)->exists())->toBeFalse();
})->group('booking', 'remove-item');

it('removes last item for a vendor and deletes booking_vendor row', function (): void {
    $customer = removeCustomer();
    $booking = removeDraftBooking($customer);
    $data = addItemToBooking($booking);
    $bv = $data['booking_vendor'];
    $item = $data['item'];

    $this->actingAs($customer)->deleteJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items/{$item->public_id}"
    )->assertStatus(200);

    expect(BookingVendor::where('id', $bv->id)->exists())->toBeFalse();
})->group('booking', 'remove-item');

it('returns 409 when removing from a non-draft booking', function (): void {
    $customer = removeCustomer();
    $booking = removeDraftBooking($customer);
    $data = addItemToBooking($booking);
    $booking->update(['lifecycle_status' => SubmittedState::class]);

    $this->actingAs($customer)->deleteJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items/{$data['item']->public_id}"
    )->assertStatus(409);
})->group('booking', 'remove-item');

it('returns 403 when removing from another user\'s booking', function (): void {
    $customer = removeCustomer();
    $other = removeCustomer();
    $booking = removeDraftBooking($other);
    $data = addItemToBooking($booking);

    $this->actingAs($customer)->deleteJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items/{$data['item']->public_id}"
    )->assertStatus(403);
})->group('booking', 'remove-item');

it('returns 401 when unauthenticated', function (): void {
    $this->deleteJson('/api/v1/customer/bookings/FAKE/items/FAKE')->assertStatus(401);
})->group('booking', 'remove-item');
