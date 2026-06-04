<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;

/**
 * P0 — Customer API audit 2026-06-04, Step 1.3 privacy assertions for
 * endpoint 12.2 (GET /api/v1/customer/bookings/{publicId}):
 *  - vendor financials (commission, payout) must never reach the customer;
 *  - vendor bank details must never reach the customer;
 *  - customer A must not see customer B's booking (404, not 403 — no
 *    existence leak).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/** Recursively collect every key present anywhere in the payload. */
function bookingPrivacyAllKeys(mixed $payload): array
{
    if (! is_array($payload)) {
        return [];
    }

    $keys = [];
    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }
        $keys = array_merge($keys, bookingPrivacyAllKeys($value));
    }

    return $keys;
}

it('booking detail hides vendor commission and payout from the customer', function (): void {
    $data = makeSubmittedBookingWithVendor();

    // Make the hidden columns non-zero so a leak would be visible.
    $data['bookingVendor']->update([
        'commission_minor' => 7500,
        'vendor_payout_minor' => 42500,
    ]);

    $response = $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200);

    $keys = bookingPrivacyAllKeys($response->json('data'));

    expect($keys)
        ->not->toContain('commission_minor')
        ->not->toContain('commission_bps')
        ->not->toContain('vendor_payout_minor')
        ->not->toContain('vendor_payout_currency');
})->group('booking', 'privacy');

it('booking detail hides vendor bank details and owner PII', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $data['vendor']->update([
        'bank_name' => 'Secret Bank',
        'bank_iban' => 'EG380019000500000000263180002',
    ]);

    $response = $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200);

    $keys = bookingPrivacyAllKeys($response->json('data'));

    expect($keys)
        ->not->toContain('bank_name')
        ->not->toContain('bank_iban')
        ->not->toContain('bank_account_holder')
        ->not->toContain('email')
        ->not->toContain('phone_e164');

    expect($response->getContent())
        ->not->toContain('EG380019000500000000263180002')
        ->not->toContain('Secret Bank');
})->group('booking', 'privacy');

it('customer A cannot read customer B booking — 404, not 403', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $otherCustomer = User::factory()->asCustomer()->create();

    $this->actingAs($otherCustomer)
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(404);
})->group('booking', 'privacy');

it('booking list never includes another customer bookings', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $otherCustomer = User::factory()->asCustomer()->create();

    $response = $this->actingAs($otherCustomer)
        ->getJson('/api/v1/customer/bookings')
        ->assertStatus(200);

    expect($response->getContent())->not->toContain($data['booking']->public_id);
})->group('booking', 'privacy');
