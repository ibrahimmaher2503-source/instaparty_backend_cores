<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3 B1 — audit endpoints 12.3 (cancellation-preview) + 12.4 (cancel).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ---------------------------------------------------------------------------
// 12.3 — preview
// ---------------------------------------------------------------------------

it('previews a refundable cancellation for each product type', function (ProductType $type): void {
    $data = makeSubmittedBookingWithVendor($type);

    $response = $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancellation-preview")
        ->assertStatus(200)
        ->assertJsonPath('data.cancellable', true)
        ->assertJsonPath('data.items.0.product_type', $type->value)
        ->assertJsonPath('data.items.0.refundable', true);

    expect($response->json('data.refundable_minor'))->toBeInt()
        ->and($response->json('data.currency'))->toBe('EGP');
})->with([ProductType::Rental, ProductType::Sale, ProductType::Digital])
    ->group('booking', 'cancellation', 'rental', 'sale', 'digital');

it('preview denies rental refund inside the 24h window', function (): void {
    $data = makeSubmittedBookingWithVendor(ProductType::Rental);
    $data['booking']->update([
        'event_starts_at' => now()->addHours(6),
        'event_ends_at' => now()->addHours(11),
    ]);

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancellation-preview")
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.refundable', false)
        ->assertJsonPath('data.items.0.reason_code', 'rental_window_closed');
})->group('booking', 'cancellation', 'rental');

it('preview denies sale refund once item is in preparation', function (): void {
    $data = makeSubmittedBookingWithVendor(ProductType::Sale);
    $data['item']->update(['item_status' => 'in_preparation']);

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancellation-preview")
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.refundable', false)
        ->assertJsonPath('data.items.0.reason_code', 'sale_in_preparation');
})->group('booking', 'cancellation', 'sale');

it('preview is 404 for another customer booking', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancellation-preview")
        ->assertStatus(404);
})->group('booking', 'cancellation', 'privacy');

// ---------------------------------------------------------------------------
// 12.4 — cancel
// ---------------------------------------------------------------------------

it('customer cancels an unpaid booking — state, transition, audit row', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $this->actingAs($data['customer'])
        ->postJson(
            "/api/v1/customer/bookings/{$data['booking']->public_id}/cancel",
            ['reason' => 'Plans changed'],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(200)
        ->assertJsonPath('data.cancellable', true);

    $booking = $data['booking']->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(CancelledState::class)
        ->and((int) $booking->cancelled_by)->toBe($data['customer']->id)
        ->and($booking->cancelled_at)->not->toBeNull();

    expect(DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'cancelled')
        ->where('trigger_kind', 'customer')
        ->exists())->toBeTrue();

    expect(DB::table('audit_logs')
        ->where('auditable_type', Booking::class)
        ->where('auditable_id', $booking->id)
        ->where('action', 'customer_cancel_booking')
        ->exists())->toBeTrue();
})->group('booking', 'cancellation');

it('cancel requires an Idempotency-Key header', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $this->actingAs($data['customer'])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancel")
        ->assertStatus(422);
})->group('booking', 'cancellation', 'idempotency');

it('replaying the same Idempotency-Key does not double-transition', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $key = (string) Str::uuid();

    $this->actingAs($data['customer'])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancel", [], ['Idempotency-Key' => $key])
        ->assertStatus(200);
    $this->actingAs($data['customer'])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancel", [], ['Idempotency-Key' => $key])
        ->assertStatus(200);

    expect(DB::table('state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $data['booking']->id)
        ->where('to_state', 'cancelled')
        ->count())->toBe(1);
})->group('booking', 'cancellation', 'idempotency');

it('cannot cancel a completed booking — 422 localized', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['booking']->update(['lifecycle_status' => CompletedState::class]);

    $this->actingAs($data['customer'])
        ->postJson(
            "/api/v1/customer/bookings/{$data['booking']->public_id}/cancel",
            [],
            ['Idempotency-Key' => (string) Str::uuid(), 'Accept-Language' => 'ar'],
        )
        ->assertStatus(422);
})->group('booking', 'cancellation');

it('cancel is 404 for another customer booking', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->postJson(
            "/api/v1/customer/bookings/{$data['booking']->public_id}/cancel",
            [],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(404);
})->group('booking', 'cancellation', 'privacy');

it('unauthenticated cancel returns 401', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $this->postJson(
        "/api/v1/customer/bookings/{$data['booking']->public_id}/cancel",
        [],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertStatus(401);
})->group('booking', 'cancellation');
