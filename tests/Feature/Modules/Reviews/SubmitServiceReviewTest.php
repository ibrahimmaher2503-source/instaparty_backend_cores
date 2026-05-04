<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeCompletedBookingItem(ProductType $productType = ProductType::Sale): array
{
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create();
    $service = Service::factory()->create([
        'product_type' => $productType,
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
    ]);

    $booking = Booking::create([
        'public_id' => (string) Str::ulid(),
        'reference_no' => 'IP-TEST-'.rand(1000, 9999),
        'customer_id' => $customer->id,
        'occasion_id' => Occasion::factory()->create()->id,
        'lifecycle_status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'completed',
        'event_starts_at' => now()->subDays(3),
        'event_ends_at' => now()->subDays(3)->addHours(5),
        'subtotal_currency' => 'EGP',
        'delivery_total_currency' => 'EGP',
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_currency' => 'EGP',
        'total_currency' => 'EGP',
        'amount_paid_currency' => 'EGP',
    ]);

    $bookingVendor = BookingVendor::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => 'accepted',
        'response_deadline' => now()->addHours(24),
        'subtotal_minor' => 50000,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 50000,
        'vendor_payout_currency' => 'EGP',
    ]);

    $bookingItem = BookingItem::create([
        'public_id' => (string) Str::ulid(),
        'booking_vendor_id' => $bookingVendor->id,
        'service_id' => $service->id,
        'product_type' => $productType->value,
        'name_snapshot' => ['en' => 'Test', 'ar' => 'اختبار'],
        'unit_price_minor' => 50000,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 50000,
        'line_total_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'quantity' => 1,
        'item_status' => 'completed',
        'commission_bps' => 0,
    ]);

    return compact('customer', 'booking', 'bookingVendor', 'bookingItem', 'service', 'vendor');
}

it('creates a service review for a completed rental booking item', function (): void {
    $data = makeCompletedBookingItem(ProductType::Rental);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 5,
            'body' => 'Excellent rental!',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.moderation_status', ModerationStatus::Pending->value);

    $this->assertDatabaseHas('service_reviews', [
        'booking_item_id' => $data['bookingItem']->id,
        'moderation_status' => 'pending',
        'rating' => 5,
    ]);
})->group('reviews', 'rental');

it('creates a service review for a completed sale booking item', function (): void {
    $data = makeCompletedBookingItem(ProductType::Sale);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 4,
        ])
        ->assertStatus(201);
})->group('reviews', 'sale');

it('creates a service review for a completed digital booking item', function (): void {
    $data = makeCompletedBookingItem(ProductType::Digital);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 3,
        ])
        ->assertStatus(201);
})->group('reviews', 'digital');

it('returns 401 when unauthenticated', function (): void {
    $data = makeCompletedBookingItem();

    $this->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
        'rating' => 5,
    ])->assertStatus(401);
})->group('reviews');

it('returns 422 when booking item is not completed', function (): void {
    $data = makeCompletedBookingItem();
    $data['bookingItem']->update(['item_status' => 'pending']);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'booking_item_not_completed');
})->group('reviews');

it('returns 409 when review already exists', function (): void {
    $data = makeCompletedBookingItem();

    // First submission succeeds
    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 5,
        ])
        ->assertStatus(201);

    // Second submission returns conflict
    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 4,
        ])
        ->assertStatus(409)
        ->assertJsonPath('errors.0.code', 'review_already_exists');
})->group('reviews');

it('accepts review with null body', function (): void {
    $data = makeCompletedBookingItem();

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 5,
            'body' => null,
        ])
        ->assertStatus(201);
})->group('reviews');

it('returns 422 for invalid rating', function (): void {
    $data = makeCompletedBookingItem();

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-items/{$data['bookingItem']->public_id}/review", [
            'rating' => 6,
        ])
        ->assertStatus(422);
})->group('reviews');
