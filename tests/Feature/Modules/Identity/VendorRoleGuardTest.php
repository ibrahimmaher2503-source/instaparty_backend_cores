<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Vendor-portal audit 2026-06-04 P0 — 27 of 67 vendor routes carried only
 * auth:sanctum (no role:vendor). A CUSTOMER token must get 403 on every
 * vendor surface, reads included. These tests pin the contract; the route
 * hardening lands with them.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->customer = User::factory()->asCustomer()->phoneVerified()->create();

    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
    $this->service = Service::factory()->rental()->published()->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('customer token gets 403 on vendor read endpoints', function (string $method, string $uri): void {
    $uri = str_replace('{servicePublicId}', $this->service->public_id, $uri);

    $this->actingAs($this->customer)->json($method, $uri)->assertStatus(403);
})->with([
    ['GET', '/api/v1/vendor/services'],
    ['GET', '/api/v1/vendor/services/{servicePublicId}'],
    ['GET', '/api/v1/vendor/services/{servicePublicId}/media'],
    ['GET', '/api/v1/vendor/categories'],
    ['GET', '/api/v1/vendor/occasions'],
    ['GET', '/api/v1/vendor/loyalty/program'],
    ['GET', '/api/v1/vendor/change-requests'],
    ['GET', '/api/v1/vendor/catalog/import/template/rental'],
])->group('identity', 'vendor-guard', 'authorization');

it('customer token gets 403 on vendor mutation endpoints', function (string $method, string $uri): void {
    $uri = str_replace('{servicePublicId}', $this->service->public_id, $uri);

    $this->actingAs($this->customer)->json($method, $uri)->assertStatus(403);
})->with([
    ['POST', '/api/v1/vendor/services/rental'],
    ['POST', '/api/v1/vendor/services/sale'],
    ['POST', '/api/v1/vendor/services/digital'],
    ['PATCH', '/api/v1/vendor/services/rental/{servicePublicId}'],
    ['DELETE', '/api/v1/vendor/services/rental/{servicePublicId}'],
    ['PUT', '/api/v1/vendor/loyalty/program'],
    ['POST', '/api/v1/vendor/services/rental/import'],
])->group('identity', 'vendor-guard', 'authorization');

it('unauthenticated requests get 401 on vendor endpoints', function (): void {
    $this->getJson('/api/v1/vendor/services')->assertStatus(401);
    $this->postJson('/api/v1/vendor/services/rental')->assertStatus(401);
})->group('identity', 'vendor-guard');
