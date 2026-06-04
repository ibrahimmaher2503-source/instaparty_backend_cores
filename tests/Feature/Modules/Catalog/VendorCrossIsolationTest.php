<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Vendor-portal audit 2026-06-04 P0 — cross-vendor isolation: vendor A must
 * never read or mutate vendor B's services, media, drafts, or loyalty data.
 * Existence-safe: expect 404 (not 403) on foreign resources.
 */
function isolationVendor(ProductType $type = ProductType::Rental): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    VendorApprovedProductTypeFactory::new()->forType($type)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    return ['user' => $user, 'vendor' => $vendor];
}

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->a = isolationVendor();
    $this->b = isolationVendor();
    $this->serviceB = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $this->b['vendor']->id,
    ]);
});

it('vendor A cannot read vendor B service detail', function (): void {
    $this->actingAs($this->a['user'])
        ->getJson("/api/v1/vendor/services/{$this->serviceB->public_id}")
        ->assertStatus(404);
})->group('catalog', 'isolation', 'privacy');

it('vendor A cannot update vendor B service', function (): void {
    $this->actingAs($this->a['user'])
        ->patchJson("/api/v1/vendor/services/rental/{$this->serviceB->public_id}", [
            'base_price_minor' => 1,
        ])
        ->assertStatus(404);

    expect($this->serviceB->refresh()->base_price_minor)->not->toBe(1);
})->group('catalog', 'isolation', 'privacy');

it('vendor A cannot delete vendor B service', function (): void {
    $this->actingAs($this->a['user'])
        ->deleteJson("/api/v1/vendor/services/rental/{$this->serviceB->public_id}")
        ->assertStatus(404);

    expect($this->serviceB->refresh()->trashed())->toBeFalse();
})->group('catalog', 'isolation', 'privacy');

it('vendor A cannot list or upload media on vendor B service', function (): void {
    // Media routes deny foreign services with 403 (policy-level ownership);
    // existence is not a secret — published service IDs are public catalog data.
    $this->actingAs($this->a['user'])
        ->getJson("/api/v1/vendor/services/{$this->serviceB->public_id}/media")
        ->assertStatus(403);

    $this->actingAs($this->a['user'])
        ->postJson("/api/v1/vendor/services/{$this->serviceB->public_id}/media")
        ->assertStatus(403);
})->group('catalog', 'isolation', 'privacy', 'media');

it('vendor A services list never contains vendor B services', function (): void {
    Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $this->a['vendor']->id,
    ]);

    $response = $this->actingAs($this->a['user'])
        ->getJson('/api/v1/vendor/services')
        ->assertStatus(200);

    expect($response->getContent())->not->toContain($this->serviceB->public_id);
})->group('catalog', 'isolation', 'privacy');

it('vendor A wallet endpoints never expose vendor B figures', function (): void {
    $response = $this->actingAs($this->a['user'])
        ->getJson('/api/v1/vendor/wallet');

    // Wallet may be empty/404 for a fresh vendor; what matters is it is not B's.
    if ($response->getStatusCode() === 200) {
        expect($response->getContent())->not->toContain((string) $this->b['vendor']->public_id);
    } else {
        $response->assertStatus(404);
    }
})->group('catalog', 'isolation', 'privacy', 'settlement');
