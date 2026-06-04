<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;

/**
 * P1 — Customer API audit 2026-06-04, Step 1.6: money fields must reach the
 * client as integer minor units + CHAR(3) currency — never floats, never
 * formatted-only strings (CLAUDE.md §6, Brick\Money convention).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('booking detail returns every money field as an integer with EGP currency', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200);

    $payload = $response->json('data');

    foreach ([
        'subtotal_minor',
        'delivery_total_minor',
        'discount_total_minor',
        'discount_promo_minor',
        'discount_loyalty_minor',
        'applied_wallet_minor',
        'total_minor',
        'due_minor',
    ] as $field) {
        expect($payload[$field])->toBeInt("{$field} must be an integer minor unit");
    }

    expect($payload['currency'])->toBe('EGP');

    foreach ($payload['vendors'] as $vendorBlock) {
        expect($vendorBlock['subtotal_minor'])->toBeInt()
            ->and($vendorBlock['delivery_fee_minor'])->toBeInt()
            ->and($vendorBlock['currency'])->toBe('EGP');
    }
})->group('booking', 'money');

it('booking total and due reflect the net-of-loyalty total written by the discount writer', function (): void {
    $data = makeSubmittedBookingWithVendor();

    // Mirror EloquentBookingDiscountWriter semantics: total_minor is stored
    // NET of the loyalty redemption (total = subtotal + delivery - discounts
    // - loyalty_redeemed).
    $data['booking']->forceFill([
        'subtotal_minor' => 50000,
        'delivery_total_minor' => 0,
        'discount_total_minor' => 0,
        'loyalty_redeemed_minor' => 2500,
        'total_minor' => 47500,
    ])->save();

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.total_minor', 47500)
        ->assertJsonPath('data.due_minor', 47500);
})->group('booking', 'money');

it('booking detail surfaces the applied loyalty redemption amount')
    ->todo()
    // Audit 2026-06-04 bug (HIGH, display): BookingResource reads phantom
    // attributes — discount_loyalty_minor / discount_promo_minor /
    // applied_wallet_minor have no backing columns, so they render 0 even
    // when loyalty_redeemed_minor holds a real redemption. Map
    // discount_loyalty_minor => loyalty_redeemed_minor (display only; due
    // formula must NOT subtract it again — total is already net).
    ->group('booking', 'money');

it('service detail returns integer minor prices with currency', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $service = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'base_price_minor' => 123450,
        'base_price_currency' => 'EGP',
    ]);
    ServiceRentalDetail::factory()->create([
        'service_id' => $service->id,
        'security_deposit_minor' => 50000,
        'security_deposit_currency' => 'EGP',
    ]);

    $response = $this->getJson("/api/v1/customer/services/{$service->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.currency', 'EGP');

    expect($response->json('data.price_from_minor'))->toBeInt()->toBe(123450)
        ->and($response->json('data.rental.security_deposit_minor'))->toBeInt()->toBe(50000);

    // A float anywhere in a money field is a constitution violation.
    expect($response->json('data.price_from_minor'))->not->toBeFloat();
})->group('booking', 'money', 'catalog');
