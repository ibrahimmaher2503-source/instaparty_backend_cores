<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Domain\Models\Commission;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vendor-portal remainder B1 — settlements (12.4/12.5), booking timeline
 * (6.11), review stats (14.4), compliance audit-log (3.6).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
    $this->user->givePermissionTo('settlement.view_wallet.own');
});

function b1Commission(VendorProfile $vendor, array $overrides = []): Commission
{
    $data = makeSubmittedBookingWithVendor();

    $payment = Payment::factory()->create([
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'status' => 'pending',
    ]);

    return Commission::query()->create(array_merge([
        'public_id' => (string) Str::ulid(),
        'booking_item_id' => $data['item']->id,
        'payment_id' => $payment->id,
        'vendor_profile_id' => $vendor->id,
        'category_id' => null,
        'product_type' => ProductType::Rental,
        'gross_amount_minor' => 100000,
        'gross_amount_currency' => 'EGP',
        'commission_bps' => 1500,
        'commission_minor' => 15000,
        'commission_currency' => 'EGP',
        'vendor_share_minor' => 85000,
        'vendor_share_currency' => 'EGP',
        'status' => 'accrued',
    ], $overrides));
}

it('lists own settlement records with integer minor money', function (): void {
    b1Commission($this->vendor);
    b1Commission(VendorProfile::factory()->approved()->create()); // foreign

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/wallet/settlements')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.gross_amount_minor'))->toBeInt()->toBe(100000)
        ->and($response->json('data.0.vendor_share_minor'))->toBe(85000)
        ->and($response->json('data.0.currency'))->toBe('EGP');
})->group('settlement', 'vendor-portal', 'isolation', 'money');

it('settlement detail is 404 for a foreign record', function (): void {
    $foreign = b1Commission(VendorProfile::factory()->approved()->create());

    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/wallet/settlements/{$foreign->public_id}")
        ->assertStatus(404);
})->group('settlement', 'vendor-portal', 'isolation');

it('returns the booking timeline scoped to the vendor', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $vendorUser = User::query()->findOrFail($data['vendor']->user_id);

    DB::table('state_transitions')->insert([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $data['booking']->id,
        'from_state' => 'draft',
        'to_state' => 'vendor_review',
        'trigger_kind' => 'customer',
        'triggered_by' => $data['customer']->id,
        'created_at' => now(), // append-only table — no updated_at
    ]);

    $response = $this->actingAs($vendorUser)
        ->getJson("/api/v1/vendor/booking-vendors/{$data['bookingVendor']->public_id}/timeline")
        ->assertStatus(200);

    expect($response->json('data.0'))->toMatchArray([
        'scope' => 'booking',
        'from_state' => 'draft',
        'to_state' => 'vendor_review',
    ]);
})->group('booking', 'vendor-portal');

it('review stats aggregate approved reviews per own service', function (): void {
    $service = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $this->vendor->id,
        'rating_avg' => 4.5,
        'rating_count' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/reviews/stats')
        ->assertStatus(200);

    $row = collect($response->json('data.services'))->firstWhere('service_public_id', $service->public_id);

    expect($row)->not->toBeNull()
        ->and($response->json('data.vendor'))->toHaveKeys(['rating_avg', 'rating_count']);
})->group('reviews', 'vendor-portal');

it('compliance audit-log exposes status movement but never raw changes payloads', function (): void {
    DB::table('audit_logs')->insert([
        'public_id' => Str::ulid()->toBase32(),
        'auditable_type' => VendorProfile::class,
        'auditable_id' => $this->vendor->id,
        'user_id' => null,
        'action' => 'vendor_approved',
        'changes' => json_encode([
            'from' => ['approval_status' => 'pending_review'],
            'to' => ['approval_status' => 'approved'],
            'admin_note' => 'internal-secret-note',
        ]),
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/compliance/audit-log')
        ->assertStatus(200)
        ->assertJsonPath('data.0.action', 'vendor_approved')
        ->assertJsonPath('data.0.to_status', 'approved');

    expect($response->getContent())->not->toContain('internal-secret-note');
})->group('identity', 'vendor-portal', 'privacy');
