<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('returns 200 and the vendor\'s pending booking vendors', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids)->toContain($bv->public_id);
})->group('booking', 'vendor-incoming');

it('includes booking vendor items in the response', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();

    $bvData = collect($response->json('data'))
        ->firstWhere('public_id', $bv->public_id);

    expect($bvData)->not->toBeNull()
        ->and($bvData['items'])->not->toBeEmpty()
        ->and($bvData['items'][0]['public_id'])->toBe($item->public_id);
})->group('booking', 'vendor-incoming');

it('response contains expected booking vendor fields', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();

    $bvData = collect($response->json('data'))
        ->firstWhere('public_id', $bv->public_id);

    expect($bvData)->toHaveKeys([
        'public_id',
        'sub_status',
        'response_deadline',
        'subtotal_minor',
        'currency',
        'items',
    ]);
    expect($bvData['sub_status'])->toBe(VendorSubStatus::Pending->value);
})->group('booking', 'vendor-incoming');

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

it('returns 401 when unauthenticated', function (): void {
    $this->getJson('/api/v1/vendor/booking-vendors')
        ->assertStatus(401);
})->group('booking', 'vendor-incoming');

// ---------------------------------------------------------------------------
// Authorization — wrong role
// ---------------------------------------------------------------------------

it('returns 403 when a customer calls the endpoint', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/booking-vendors')
        ->assertStatus(403);
})->group('booking', 'vendor-incoming');

it('returns 403 when an admin calls the endpoint', function (): void {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/vendor/booking-vendors')
        ->assertStatus(403);
})->group('booking', 'vendor-incoming');

// ---------------------------------------------------------------------------
// Cross-vendor isolation
// ---------------------------------------------------------------------------

it('vendor does not see another vendor\'s booking vendors', function (): void {
    ['bookingVendor' => $bvOther] = makeSubmittedBookingWithVendor();

    // A second, separate vendor with their own booking
    ['vendor' => $vendor2] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor2->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids)->not->toContain($bvOther->public_id);
})->group('booking', 'vendor-incoming');

it('vendor only sees their own pending booking vendors, not another vendor\'s', function (): void {
    // Vendor A has one pending booking vendor
    ['vendor' => $vendorA, 'bookingVendor' => $bvA] = makeSubmittedBookingWithVendor();

    // Vendor B has one pending booking vendor
    ['vendor' => $vendorB, 'bookingVendor' => $bvB] = makeSubmittedBookingWithVendor();

    $responseA = $this->actingAs($vendorA->user)
        ->getJson('/api/v1/vendor/booking-vendors');
    $responseA->assertOk();
    $idsA = collect($responseA->json('data'))->pluck('public_id');
    expect($idsA)->toContain($bvA->public_id)
        ->and($idsA)->not->toContain($bvB->public_id);

    $responseB = $this->actingAs($vendorB->user)
        ->getJson('/api/v1/vendor/booking-vendors');
    $responseB->assertOk();
    $idsB = collect($responseB->json('data'))->pluck('public_id');
    expect($idsB)->toContain($bvB->public_id)
        ->and($idsB)->not->toContain($bvA->public_id);
})->group('booking', 'vendor-incoming');

// ---------------------------------------------------------------------------
// Empty list
// ---------------------------------------------------------------------------

it('returns an empty data array when vendor has no pending booking vendors', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();
    expect($response->json('data'))->toBeArray()->toBeEmpty();
})->group('booking', 'vendor-incoming');

it('does not return non-pending booking vendors', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv] = makeSubmittedBookingWithVendor();

    // Mark it accepted — no longer pending
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/booking-vendors');

    $response->assertOk();
    expect($response->json('data'))->toBeArray()->toBeEmpty();
})->group('booking', 'vendor-incoming');
