<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\VendorProfile;

/**
 * P1 — Customer API audit 2026-06-04, Step 1.4: the customer service-detail
 * endpoint is type-aware and MUST be covered for all three product types
 * (product-types.md). ServiceContractShapeTest covers rental only; this file
 * completes sale + digital and pins block exclusivity per type.
 */
function typeVariantService(string $type): Service
{
    $vendor = VendorProfile::factory()->approved()->create();

    $service = Service::factory()->{$type}()->published()->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    match ($type) {
        'rental' => ServiceRentalDetail::factory()->create([
            'service_id' => $service->id,
            'requires_electricity' => true,
            'default_rental_duration_hours' => 4,
            'security_deposit_minor' => 50000,
            'security_deposit_currency' => 'EGP',
        ]),
        'sale' => ServiceSaleDetail::factory()->create([
            'service_id' => $service->id,
            'is_perishable' => true,
            'is_made_to_order' => true,
            'lead_time_hours' => 48,
            'stock_quantity' => 7,
        ]),
        'digital' => ServiceDigitalDetail::factory()->create([
            'service_id' => $service->id,
            'delivery_method' => 'link',
            'has_expiry' => true,
            'expiry_days_after_purchase' => 30,
            'is_refundable_after_delivery' => false,
        ]),
    };

    return $service;
}

it('service detail exposes exactly one per-type block', function (string $type): void {
    $service = typeVariantService($type);

    $response = $this->getJson("/api/v1/customer/services/{$service->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.product_type', $type);

    $data = $response->json('data');
    $otherTypes = array_diff(['rental', 'sale', 'digital'], [$type]);

    expect($data)->toHaveKey($type);
    foreach ($otherTypes as $other) {
        expect($data)->not->toHaveKey($other);
    }
})->with(['rental', 'sale', 'digital'])->group('catalog', 'rental', 'sale', 'digital');

it('sale detail block carries the sale-specific fields', function (): void {
    $service = typeVariantService('sale');

    $this->getJson("/api/v1/customer/services/{$service->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.sale.is_perishable', true)
        ->assertJsonPath('data.sale.is_made_to_order', true)
        ->assertJsonPath('data.sale.lead_time_hours', 48)
        ->assertJsonPath('data.sale.stock_quantity', 7);
})->group('catalog', 'sale');

it('digital detail block carries the digital-specific fields', function (): void {
    $service = typeVariantService('digital');

    $this->getJson("/api/v1/customer/services/{$service->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.digital.delivery_method', 'link')
        ->assertJsonPath('data.digital.has_expiry', true)
        ->assertJsonPath('data.digital.expiry_days_after_purchase', 30)
        ->assertJsonPath('data.digital.is_refundable_after_delivery', false);
})->group('catalog', 'digital');
