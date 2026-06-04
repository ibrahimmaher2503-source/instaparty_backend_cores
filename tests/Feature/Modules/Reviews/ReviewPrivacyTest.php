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
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * P0 — Customer API audit 2026-06-04, Step 1.3 privacy assertions for
 * endpoints 7.3 (public service reviews) and 13.1 (my reviews):
 *  - reviewer identity is masked (first name only — never full last name,
 *    email, or phone);
 *  - a customer's review list contains only their own reviews.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/**
 * Self-contained equivalent of SubmitServiceReviewTest::makeCompletedBookingItem()
 * (that helper lives inside another test file and is not loaded here).
 */
function reviewPrivacyCompletedItem(ProductType $productType = ProductType::Sale): array
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
        'reference_no' => 'IP-PRIV-'.rand(1000, 9999),
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

    return compact('customer', 'vendor', 'service', 'booking', 'bookingVendor', 'bookingItem');
}

function makeApprovedReviewForNamedCustomer(string $name, string $email, string $phone): array
{
    $data = reviewPrivacyCompletedItem();

    $data['customer']->update([
        'name' => $name,
        'email' => $email,
        'phone_e164' => $phone,
    ]);

    $review = ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 5,
        'body' => 'Great service',
        'locale' => 'en',
        'moderation_status' => 'approved',
    ]);

    return [...$data, 'review' => $review];
}

it('public service reviews never expose reviewer last name, email, or phone', function (): void {
    $data = makeApprovedReviewForNamedCustomer(
        'Ahmed Elmasry',
        'reviewer-secret@example.com',
        '+201522334455',
    );

    $response = $this->getJson("/api/v1/public/services/{$data['service']->public_id}/reviews")
        ->assertStatus(200);

    $raw = $response->getContent();

    expect($raw)
        ->not->toContain('Elmasry')
        ->not->toContain('reviewer-secret@example.com')
        ->not->toContain('+201522334455');

    // Masked identity is still present.
    expect($response->json('data.0.reviewer_first_name'))->toBe('Ahmed');
})->group('reviews', 'privacy');

it('public service reviews payload exposes no email or phone keys', function (): void {
    $data = makeApprovedReviewForNamedCustomer(
        'Mona Hassan',
        'mona-secret@example.com',
        '+201533445566',
    );

    $response = $this->getJson("/api/v1/public/services/{$data['service']->public_id}/reviews")
        ->assertStatus(200);

    $row = $response->json('data.0');

    expect($row)
        ->not->toHaveKey('email')
        ->not->toHaveKey('phone_e164')
        ->not->toHaveKey('user_id')
        ->not->toHaveKey('reviewer_name');
})->group('reviews', 'privacy');

it('my-reviews list returns only the authenticated customer reviews', function (): void {
    $dataA = makeApprovedReviewForNamedCustomer(
        'Customer Aye',
        'customer-a@example.com',
        '+201544556677',
    );

    $customerB = User::factory()->asCustomer()->create();

    $response = $this->actingAs($customerB)
        ->getJson('/api/v1/customer/reviews')
        ->assertStatus(200);

    expect($response->getContent())->not->toContain($dataA['review']->public_id);
})->group('reviews', 'privacy');
