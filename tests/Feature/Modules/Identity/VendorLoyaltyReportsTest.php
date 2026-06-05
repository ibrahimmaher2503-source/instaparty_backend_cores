<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * Vendor-portal remainder B2 — loyalty members/ledger/adjust (16.3–16.5)
 * and reports (15.2–15.4).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

function b2Member(VendorProfile $vendor): array
{
    $customer = User::factory()->asCustomer()->create(['name' => 'Mona Hassan']);
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $vendor->id]);

    $entry = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $vendor->id,
        'loyalty_program_id' => $program->id,
        'direction' => 'earn',
        'points' => 200,
        'balance_after' => 200,
    ]);

    return ['customer' => $customer, 'program' => $program, 'entry' => $entry];
}

it('lists loyalty members with masked names and balances', function (): void {
    b2Member($this->vendor);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/loyalty/customers')
        ->assertStatus(200);

    expect($response->json('data.0.customer_name'))->toBe('Mona H.')
        ->and($response->json('data.0.balance'))->toBe(200)
        ->and($response->getContent())->not->toContain('Hassan"');
})->group('loyalty', 'vendor-portal', 'privacy');

it('lists the loyalty ledger and never leaks other vendors entries', function (): void {
    b2Member($this->vendor);
    $foreign = b2Member(VendorProfile::factory()->approved()->create());

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/loyalty/transactions')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->getContent())->not->toContain($foreign['entry']->public_id);
})->group('loyalty', 'vendor-portal', 'isolation');

it('manually adjusts a member balance with bilingual reason', function (): void {
    $member = b2Member($this->vendor);

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/loyalty/customers/{$member['customer']->id}/adjust", [
            'points_delta' => 50,
            'reason_en' => 'Goodwill bonus',
            'reason_ar' => 'مكافأة',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(201)
        ->assertJsonPath('data.balance_after', 250);
})->group('loyalty', 'vendor-portal');

it('cannot adjust a non-member or another vendor member — 404', function (): void {
    $stranger = User::factory()->asCustomer()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/loyalty/customers/{$stranger->id}/adjust", [
            'points_delta' => 10,
            'reason_en' => 'x',
            'reason_ar' => 'س',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(404);
})->group('loyalty', 'vendor-portal', 'isolation');

it('bookings report returns summary and daily series for own rows only', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $vendorUser = User::query()->findOrFail($data['vendor']->user_id);

    $summaryOnly = $this->actingAs($vendorUser)
        ->getJson('/api/v1/vendor/reports/bookings?granularity=summary')
        ->assertStatus(200);

    expect($summaryOnly->json('data.summary.total'))->toBe(1)
        ->and($summaryOnly->json('data'))->not->toHaveKey('series');

    $full = $this->actingAs($vendorUser)
        ->getJson('/api/v1/vendor/reports/bookings')
        ->assertStatus(200);

    expect($full->json('data.series'))->toHaveCount(1);

    // Another vendor sees zero.
    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/reports/bookings?granularity=summary')
        ->assertStatus(200)
        ->assertJsonPath('data.summary.total', 0);
})->group('booking', 'vendor-portal', 'reports', 'isolation');

it('revenue report sums completed rows in integer minor units', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['bookingVendor']->update(['sub_status' => 'completed']);
    $vendorUser = User::query()->findOrFail($data['vendor']->user_id);

    $response = $this->actingAs($vendorUser)
        ->getJson('/api/v1/vendor/reports/revenue?granularity=summary')
        ->assertStatus(200);

    expect($response->json('data.summary.gross_minor'))->toBeInt()->toBe(50000)
        ->and($response->json('data.summary.payout_minor'))->toBeInt()->toBe(50000)
        ->and($response->json('data.summary.currency'))->toBe('EGP');
})->group('booking', 'vendor-portal', 'reports', 'money');

it('services-performance ranks own services by gross', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $vendorUser = User::query()->findOrFail($data['vendor']->user_id);

    $response = $this->actingAs($vendorUser)
        ->getJson('/api/v1/vendor/reports/services-performance')
        ->assertStatus(200);

    expect($response->json('data.0.service_public_id'))->toBe($data['service']->public_id)
        ->and($response->json('data.0.booked_items'))->toBe(1)
        ->and($response->json('data.0.gross_minor'))->toBe(50000);
})->group('booking', 'vendor-portal', 'reports');

it('rejects an invalid report range', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/reports/bookings?from=2026-06-10&to=2026-06-01')
        ->assertStatus(422);
})->group('booking', 'vendor-portal', 'reports');
