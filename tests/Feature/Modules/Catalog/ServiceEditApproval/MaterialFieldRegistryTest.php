<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceFieldClassification;
use App\Modules\Catalog\Domain\Policies\MaterialFieldRegistry;

uses()->group('catalog', 'service-edit-approval', 'unit');

beforeEach(function (): void {
    $this->registry = app(MaterialFieldRegistry::class);
});

// ── materialFieldsFor ─────────────────────────────────────────────────────────

it('shared fields are always included regardless of product type', function (ProductType $type): void {
    $fields = $this->registry->materialFieldsFor($type);

    expect($fields)->toContain('base_price_minor')
        ->and($fields)->toContain('category_id')
        ->and($fields)->toContain('name.en')
        ->and($fields)->toContain('name.ar');
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('rental-specific fields appear for rental type only', function (): void {
    $rentalFields = $this->registry->materialFieldsFor(ProductType::Rental);
    $saleFields = $this->registry->materialFieldsFor(ProductType::Sale);

    expect($rentalFields)->toContain('service_rental_details.security_deposit_minor')
        ->and($rentalFields)->toContain('service_rental_details.default_rental_duration_hours')
        ->and($saleFields)->not->toContain('service_rental_details.security_deposit_minor');
});

it('sale-specific fields appear for sale type only', function (): void {
    $saleFields = $this->registry->materialFieldsFor(ProductType::Sale);
    $digitalFields = $this->registry->materialFieldsFor(ProductType::Digital);

    expect($saleFields)->toContain('service_sale_details.is_perishable')
        ->and($saleFields)->toContain('service_sale_details.stock_quantity')
        ->and($digitalFields)->not->toContain('service_sale_details.is_perishable');
});

it('digital-specific fields appear for digital type only', function (): void {
    $digitalFields = $this->registry->materialFieldsFor(ProductType::Digital);
    $rentalFields = $this->registry->materialFieldsFor(ProductType::Rental);

    expect($digitalFields)->toContain('service_digital_details.delivery_method')
        ->and($digitalFields)->toContain('service_digital_details.has_expiry')
        ->and($rentalFields)->not->toContain('service_digital_details.delivery_method');
});

// ── classifyField ─────────────────────────────────────────────────────────────

it('classifies gallery_ops as media', function (ProductType $type): void {
    expect($this->registry->classifyField('gallery_ops', $type))
        ->toBe(ServiceFieldClassification::Media);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('classifies availability_windows and excluded_dates as availability', function (ProductType $type): void {
    expect($this->registry->classifyField('availability_windows', $type))
        ->toBe(ServiceFieldClassification::Availability)
        ->and($this->registry->classifyField('excluded_dates', $type))
        ->toBe(ServiceFieldClassification::Availability);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('classifies pricing_tiers as pricing_tier', function (ProductType $type): void {
    expect($this->registry->classifyField('pricing_tiers', $type))
        ->toBe(ServiceFieldClassification::PricingTier);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('classifies base_price_minor as shared', function (ProductType $type): void {
    expect($this->registry->classifyField('base_price_minor', $type))
        ->toBe(ServiceFieldClassification::Shared);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('classifies type-specific prefix correctly per type', function (): void {
    expect($this->registry->classifyField('service_rental_details.security_deposit_minor', ProductType::Rental))
        ->toBe(ServiceFieldClassification::Rental);

    expect($this->registry->classifyField('service_sale_details.is_perishable', ProductType::Sale))
        ->toBe(ServiceFieldClassification::Sale);

    expect($this->registry->classifyField('service_digital_details.delivery_method', ProductType::Digital))
        ->toBe(ServiceFieldClassification::Digital);
});

// ── isMaterial ────────────────────────────────────────────────────────────────

it('base_price_minor is material for all types', function (ProductType $type): void {
    expect($this->registry->isMaterial('base_price_minor', $type))->toBeTrue();
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('gallery_ops prefix is always material', function (ProductType $type): void {
    expect($this->registry->isMaterial('gallery_ops', $type))->toBeTrue()
        ->and($this->registry->isMaterial('gallery_ops.add', $type))->toBeTrue();
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('type-specific fields are material only for their own type', function (): void {
    // rental field is material for rental, not for sale
    expect($this->registry->isMaterial('service_rental_details.security_deposit_minor', ProductType::Rental))
        ->toBeTrue()
        ->and($this->registry->isMaterial('service_rental_details.security_deposit_minor', ProductType::Sale))
        ->toBeFalse();
});
