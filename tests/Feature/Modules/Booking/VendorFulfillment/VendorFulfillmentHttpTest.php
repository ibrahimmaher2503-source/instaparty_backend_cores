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
// GET /api/v1/vendor/booking-items/{publicId} (G7)
// ─────────────────────────────────────────────────────────────────────────────

it('shows a booking item for any product type', function (ProductType $type): void {
    $data = makePaidBookingForFulfillment($type);

    $this->actingAs($data['vendorUser'])
        ->getJson("/api/v1/vendor/booking-items/{$data['item']->public_id}")
        ->assertOk()
        ->assertJsonPath('data.public_id', $data['item']->public_id)
        ->assertJsonPath('data.product_type', $type->value);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
])->group('booking', 'fulfillment-http', 'rental', 'sale', 'digital');

it('returns 404 when another vendor requests the item', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);

    $other = VendorProfile::factory()->approved()->create();
    $other->user->assignRole('vendor');

    $this->actingAs($other->user)
        ->getJson("/api/v1/vendor/booking-items/{$data['item']->public_id}")
        ->assertStatus(404);
})->group('booking', 'fulfillment-http', 'auth');

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/v1/vendor/booking-items/{publicId}/transition (G7)
// Per-type state machines: rental/sale advance through all four lanes,
// digital jumps straight to completed.
// ─────────────────────────────────────────────────────────────────────────────

it('walks a rental item through its full fulfillment lane sequence', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);
    $url = "/api/v1/vendor/booking-items/{$data['item']->public_id}/transition";

    foreach (['preparing', 'ready', 'in_progress', 'completed'] as $lane) {
        $this->actingAs($data['vendorUser'])
            ->postJson($url, ['lane' => $lane])
            ->assertOk();
    }

    expect($data['item']->fresh()->completed_at)->not->toBeNull();
})->group('booking', 'fulfillment-http', 'rental');

it('walks a sale item through its full fulfillment lane sequence', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);
    $url = "/api/v1/vendor/booking-items/{$data['item']->public_id}/transition";

    foreach (['preparing', 'ready', 'in_progress', 'completed'] as $lane) {
        $this->actingAs($data['vendorUser'])
            ->postJson($url, ['lane' => $lane])
            ->assertOk();
    }
})->group('booking', 'fulfillment-http', 'sale');

it('completes a digital item directly', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Digital);

    $this->actingAs($data['vendorUser'])
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", [
            'lane' => 'completed',
            'completion_note' => 'Delivered by email.',
        ])
        ->assertOk();

    expect($data['item']->fresh()->completion_note)->toBe('Delivered by email.');
})->group('booking', 'fulfillment-http', 'digital');

it('returns 422 when a digital item is sent to the preparing lane', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Digital);

    $this->actingAs($data['vendorUser'])
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", [
            'lane' => 'preparing',
        ])
        ->assertStatus(422);
})->group('booking', 'fulfillment-http', 'digital');

it('returns 422 for an unknown lane', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);

    $this->actingAs($data['vendorUser'])
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", [
            'lane' => 'teleporting',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lane']);
})->group('booking', 'fulfillment-http', 'validation');

it('returns a localized Arabic message for an invalid transition', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Digital);

    $response = $this->actingAs($data['vendorUser'])
        ->withHeader('Accept-Language', 'ar')
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", [
            'lane' => 'preparing',
        ])
        ->assertStatus(422);

    expect(json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE))->toContain('خطوة التنفيذ');
})->group('booking', 'fulfillment-http', 'locale');

it('returns 404 when another vendor transitions the item', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);

    $other = VendorProfile::factory()->approved()->create();
    $other->user->assignRole('vendor');

    $this->actingAs($other->user)
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", [
            'lane' => 'preparing',
        ])
        ->assertStatus(404);
})->group('booking', 'fulfillment-http', 'auth');

it('writes a state transition row on lane advance', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);

    $this->actingAs($data['vendorUser'])
        ->postJson("/api/v1/vendor/booking-items/{$data['item']->public_id}/transition", ['lane' => 'preparing'])
        ->assertOk();

    expect($data['item']->fresh()->item_status)->not->toBe('pending');
})->group('booking', 'fulfillment-http');

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/schedule (G7)
// ─────────────────────────────────────────────────────────────────────────────

it('lists only the vendor own items inside the requested window', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);
    $data['booking']->update([
        'event_starts_at' => now()->addHours(2),
        'event_ends_at' => now()->addHours(6),
    ]);

    // Another vendor's event today must not appear
    $otherData = makePaidBookingForFulfillment(ProductType::Sale);
    $otherData['booking']->update(['event_starts_at' => now()->addHours(3)]);

    $response = $this->actingAs($data['vendorUser'])
        ->getJson('/api/v1/vendor/schedule?window=today')
        ->assertOk()
        ->assertJsonPath('meta.window', 'today');

    $allItems = collect($response->json('data'))->flatMap(fn (array $day) => $day['items']);
    expect($allItems)->toHaveCount(1)
        ->and($allItems->first()['public_id'])->toBe($data['item']->public_id);
})->group('booking', 'schedule');

it('window=week includes events later this week but window=today excludes them', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);
    $data['booking']->update(['event_starts_at' => now()->addDays(3)]);

    $today = $this->actingAs($data['vendorUser'])
        ->getJson('/api/v1/vendor/schedule?window=today')
        ->assertOk();
    expect(collect($today->json('data'))->flatMap(fn (array $d) => $d['items']))->toHaveCount(0);

    $week = $this->actingAs($data['vendorUser'])
        ->getJson('/api/v1/vendor/schedule?window=week')
        ->assertOk();
    expect(collect($week->json('data'))->flatMap(fn (array $d) => $d['items']))->toHaveCount(1);
})->group('booking', 'schedule');

it('excludes pending (unaccepted) bookings from the schedule', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);
    $data['booking']->update(['event_starts_at' => now()->addHours(2)]);
    $data['bookingVendor']->update(['sub_status' => VendorSubStatus::Pending]);

    $response = $this->actingAs($data['vendorUser'])
        ->getJson('/api/v1/vendor/schedule?window=today')
        ->assertOk();

    expect(collect($response->json('data'))->flatMap(fn (array $d) => $d['items']))->toHaveCount(0);
})->group('booking', 'schedule');

it('returns 422 for an unknown schedule window', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Sale);

    $this->actingAs($data['vendorUser'])
        ->getJson('/api/v1/vendor/schedule?window=fortnight')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['window']);
})->group('booking', 'schedule', 'validation');

it('returns 401 when unauthenticated on schedule', function (): void {
    $this->getJson('/api/v1/vendor/schedule')->assertStatus(401);
})->group('booking', 'schedule', 'auth');
