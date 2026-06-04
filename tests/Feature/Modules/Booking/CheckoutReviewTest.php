<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Phase 3 C4 — 11.1 pre-submit checkout review.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('returns checks and totals for an own draft booking', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($data['customer'])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/checkout-review")
        ->assertStatus(200);

    $payload = $response->json('data');

    expect($payload)->toHaveKeys(['ready_to_submit', 'checks', 'items_count', 'total_minor', 'currency'])
        ->and($payload['checks'])->toHaveKeys(['is_draft', 'has_items', 'has_address', 'event_in_future', 'min_order_ok'])
        ->and($payload['items_count'])->toBe(1)
        ->and($payload['checks']['has_items'])->toBeTrue()
        ->and($payload['checks']['event_in_future'])->toBeTrue()
        ->and($payload['total_minor'])->toBeInt()
        ->and($payload['currency'])->toBe('EGP');
})->group('booking', 'checkout');

it('flags a submitted booking as not draft', function (): void {
    $data = makeSubmittedBookingWithVendor(); // lifecycle = vendor_review

    $this->actingAs($data['customer'])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/checkout-review")
        ->assertStatus(200)
        ->assertJsonPath('data.checks.is_draft', false)
        ->assertJsonPath('data.ready_to_submit', false);
})->group('booking', 'checkout');

it('is 404 for another customer booking', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/checkout-review")
        ->assertStatus(404);
})->group('booking', 'checkout', 'privacy');
