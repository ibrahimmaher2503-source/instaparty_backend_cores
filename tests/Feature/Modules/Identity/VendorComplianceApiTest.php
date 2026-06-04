<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeComplianceVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();

    return VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/compliance (G3)
// ─────────────────────────────────────────────────────────────────────────────

it('reports per-product-type approval covering all three types', function (): void {
    $vendor = makeComplianceVendor();

    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/compliance')
        ->assertOk();

    $types = collect($response->json('data.product_types'))->keyBy('product_type');

    expect($types)->toHaveCount(3)
        ->and($types->get('rental')['approved'])->toBeTrue()
        ->and($types->get('sale')['approved'])->toBeFalse()
        ->and($types->get('digital')['approved'])->toBeFalse();
})->group('identity', 'compliance', 'rental', 'sale', 'digital');

it('flags expired and expiring documents with alert counts', function (): void {
    $vendor = makeComplianceVendor();

    VendorDocument::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'expires_at' => today()->subDay(), // expired
    ]);
    VendorDocument::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'expires_at' => today()->addDays(10), // expiring soon (≤30d)
    ]);
    VendorDocument::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'expires_at' => today()->addDays(90), // healthy
    ]);

    $response = $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/compliance')
        ->assertOk()
        ->assertJsonPath('data.alerts.expired_documents', 1)
        ->assertJsonPath('data.alerts.expiring_documents', 1);

    $docs = collect($response->json('data.documents'));
    expect($docs->where('is_expired', true))->toHaveCount(1)
        ->and($docs->where('expires_soon', true))->toHaveCount(1);
})->group('identity', 'compliance');

it('does not include another vendor documents', function (): void {
    $vendor = makeComplianceVendor();
    $other = makeComplianceVendor();

    VendorDocument::factory()->create(['vendor_profile_id' => $other->id]);

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/compliance')
        ->assertOk()
        ->assertJsonCount(0, 'data.documents');
})->group('identity', 'compliance', 'auth');

it('exposes the profile approval status', function (): void {
    $vendor = makeComplianceVendor();

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/compliance')
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved');
})->group('identity', 'compliance');

it('returns 401 when unauthenticated on compliance', function (): void {
    $this->getJson('/api/v1/vendor/compliance')->assertStatus(401);
})->group('identity', 'compliance', 'auth');

it('returns 403 when a customer requests compliance', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/compliance')
        ->assertStatus(403);
})->group('identity', 'compliance', 'auth');
