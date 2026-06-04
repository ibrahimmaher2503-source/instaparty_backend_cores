<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/booking-vendors/{publicId} — detail (G5)
// ─────────────────────────────────────────────────────────────────────────────

it('returns booking-vendor detail with booking context for any product type', function (ProductType $productType): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'booking' => $booking] = makeSubmittedBookingWithVendor($productType);

    $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/booking-vendors/{$bv->public_id}")
        ->assertOk()
        ->assertJsonPath('data.public_id', $bv->public_id)
        ->assertJsonPath('data.booking.public_id', $booking->public_id)
        ->assertJsonPath('data.items.0.product_type', $productType->value)
        ->assertJsonStructure(['data' => [
            'public_id', 'sub_status', 'subtotal_minor', 'items',
            'booking' => ['reference_no', 'event_starts_at', 'customer_phone_masked', 'address'],
        ]]);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
])->group('booking', 'vendor-detail', 'rental', 'sale', 'digital');

it('masks customer phone to last 4 digits and hides street address while pending', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/booking-vendors/{$bv->public_id}")
        ->assertOk();

    // Helper seeds recipient_phone_e164 = +201234567890 → mask keeps last 4
    expect($response->json('data.booking.customer_phone_masked'))->toEndWith('7890')
        ->and($response->json('data.booking.customer_phone_masked'))->not->toContain('+2012345')
        ->and($response->json('data.booking.address.address_line'))->toBeNull()
        ->and($response->json('data.booking.address.recipient_name'))->toBeNull()
        ->and($response->json('data.booking.address.city'))->not->toBeNull();

    // No raw customer contact data anywhere in the payload
    expect(json_encode($response->json()))->not->toContain('+201234567890');
})->group('booking', 'vendor-detail', 'privacy');

it('reveals street address and recipient name once the vendor has accepted', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();
    $bv->update(['sub_status' => VendorSubStatus::Accepted, 'responded_at' => now()]);

    $response = $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/booking-vendors/{$bv->public_id}")
        ->assertOk();

    expect($response->json('data.booking.address.address_line'))->toBe('123 Test St')
        ->and($response->json('data.booking.address.recipient_name'))->toBe('Test User');
})->group('booking', 'vendor-detail', 'privacy');

it('returns 404 when another vendor requests the detail', function (): void {
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $other = VendorProfile::factory()->approved()->create();
    $other->user->assignRole('vendor');

    $this->actingAs($other->user)
        ->getJson("/api/v1/vendor/booking-vendors/{$bv->public_id}")
        ->assertStatus(404);
})->group('booking', 'vendor-detail', 'auth');

it('returns 401 when unauthenticated on detail', function (): void {
    ['bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $this->getJson("/api/v1/vendor/booking-vendors/{$bv->public_id}")
        ->assertStatus(401);
})->group('booking', 'vendor-detail', 'auth');
