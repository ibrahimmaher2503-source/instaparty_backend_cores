<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function totalsCustomer(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function totalsDraftBooking(User $customer): Booking
{
    $occasion = Occasion::factory()->create();

    return Booking::create([
        'public_id' => (string) Str::ulid(),
        'reference_no' => 'IP-2026-TOT01',
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'lifecycle_status' => LifecycleStatus::Draft,
        'payment_status' => PaymentStatus::Unpaid,
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

it('line_total_minor equals unit_price_minor times quantity', function (): void {
    $customer = totalsCustomer();
    $booking = totalsDraftBooking($customer);
    $category = Category::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $service = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'base_price_minor' => 30000,
    ]);

    $response = $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service->public_id, 'quantity' => 2]
    );

    $response->assertStatus(201);
    expect($response->json('data.line_total_minor'))->toBe(60000);
})->group('booking', 'totals');

it('booking GET returns per-vendor breakdown', function (): void {
    $customer = totalsCustomer();
    $booking = totalsDraftBooking($customer);
    $category = Category::factory()->create();

    $vendor1 = VendorProfile::factory()->create();
    $vendor2 = VendorProfile::factory()->create();

    $service1 = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => ServiceStatus::Published,
        'vendor_profile_id' => $vendor1->id,
        'category_id' => $category->id,
        'base_price_minor' => 50000,
    ]);
    $service2 = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => ServiceStatus::Published,
        'vendor_profile_id' => $vendor2->id,
        'category_id' => $category->id,
        'base_price_minor' => 70000,
    ]);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service1->public_id, 'quantity' => 1]
    )->assertStatus(201);

    $this->actingAs($customer)->postJson(
        "/api/v1/customer/bookings/{$booking->public_id}/items",
        ['service_id' => $service2->public_id, 'quantity' => 1]
    )->assertStatus(201);

    $response = $this->actingAs($customer)->getJson(
        "/api/v1/customer/bookings/{$booking->public_id}"
    );

    $response->assertStatus(200);
    $vendors = $response->json('data.vendors');
    expect(count($vendors))->toBe(2);
    expect($response->json('data.total_minor'))->toBe(120000);
})->group('booking', 'totals');

it('locale AR response returns Arabic names', function (): void {
    $customer = totalsCustomer();
    $booking = totalsDraftBooking($customer);
    $category = Category::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $service = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($customer)
        ->withHeader('Accept-Language', 'ar')
        ->postJson(
            "/api/v1/customer/bookings/{$booking->public_id}/items",
            ['service_id' => $service->public_id, 'quantity' => 1]
        );

    $response->assertStatus(201);
    // name_snapshot['ar'] should be returned
    $name = $service->getTranslation('name', 'ar');
    expect($response->json('data.name'))->toBe($name);
})->group('booking', 'totals', 'locale');

it('locale EN response returns English names', function (): void {
    $customer = totalsCustomer();
    $booking = totalsDraftBooking($customer);
    $category = Category::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $service = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => ServiceStatus::Published,
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($customer)
        ->withHeader('Accept-Language', 'en')
        ->postJson(
            "/api/v1/customer/bookings/{$booking->public_id}/items",
            ['service_id' => $service->public_id, 'quantity' => 1]
        );

    $response->assertStatus(201);
    $name = $service->getTranslation('name', 'en');
    expect($response->json('data.name'))->toBe($name);
})->group('booking', 'totals', 'locale');
