<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\Services\RefundPolicyService;
use App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader;
use Carbon\Carbon;

function refundPolicyService(?bool $digitalRefundFlag = null): RefundPolicyService
{
    $reader = new class($digitalRefundFlag) implements PaymentsCatalogReader
    {
        public function __construct(private readonly ?bool $flag) {}

        public function isDigitalRefundableAfterDelivery(int $serviceId): bool
        {
            if ($this->flag === null) {
                throw new InvalidArgumentException('Digital service not found');
            }

            return $this->flag;
        }
    };

    return new RefundPolicyService($reader);
}

// === Rental ===
it('rental: allowed when event is more than 24h away and item not in setup', function (): void {
    $policy = refundPolicyService()->policyFor(ProductType::Rental, 'confirmed', now()->addHours(25));

    expect($policy->allowed)->toBeTrue();
})->group('rental', 'payments');

it('rental: allowed at the exact 24h boundary', function (): void {
    Carbon::setTestNow('2026-05-02 10:00:00');
    $policy = refundPolicyService()->policyFor(ProductType::Rental, 'confirmed', Carbon::parse('2026-05-03 10:00:00'));

    expect($policy->allowed)->toBeTrue();
    Carbon::setTestNow();
})->group('rental', 'payments');

it('rental: denied when event is less than 24h away (rental_window_closed)', function (): void {
    $policy = refundPolicyService()->policyFor(ProductType::Rental, 'confirmed', now()->addHours(23));

    expect($policy->allowed)->toBeFalse();
    expect($policy->reasonCode)->toBe('rental_window_closed');
})->group('rental', 'payments');

it('rental: denied when item is in setup state regardless of timing', function (): void {
    $policy = refundPolicyService()->policyFor(ProductType::Rental, 'setup', now()->addHours(48));

    expect($policy->allowed)->toBeFalse();
    expect($policy->reasonCode)->toBe('rental_in_setup');
})->group('rental', 'payments');

// === Sale ===
it('sale: allowed when item_status is pending', function (): void {
    expect(refundPolicyService()->policyFor(ProductType::Sale, 'pending', null)->allowed)->toBeTrue();
})->group('sale', 'payments');

it('sale: allowed when item_status is confirmed', function (): void {
    expect(refundPolicyService()->policyFor(ProductType::Sale, 'confirmed', null)->allowed)->toBeTrue();
})->group('sale', 'payments');

it('sale: denied when item_status is in_preparation', function (): void {
    $policy = refundPolicyService()->policyFor(ProductType::Sale, 'in_preparation', null);

    expect($policy->allowed)->toBeFalse();
    expect($policy->reasonCode)->toBe('sale_in_preparation');
})->group('sale', 'payments');

it('sale: denied for downstream states (out_for_delivery, delivered)', function (): void {
    foreach (['out_for_delivery', 'delivered'] as $status) {
        $policy = refundPolicyService()->policyFor(ProductType::Sale, $status, null);
        expect($policy->allowed)->toBeFalse("Expected $status to be denied");
    }
})->group('sale', 'payments');

// === Digital ===
it('digital: allowed pre-delivery regardless of refundable flag', function (): void {
    expect(refundPolicyService(false)->policyFor(ProductType::Digital, 'pending', null, 1)->allowed)->toBeTrue();
    expect(refundPolicyService(true)->policyFor(ProductType::Digital, 'pending', null, 1)->allowed)->toBeTrue();
})->group('digital', 'payments');

it('digital: allowed post-delivery when service flag is true', function (): void {
    $policy = refundPolicyService(true)->policyFor(ProductType::Digital, 'delivered', null, 42);

    expect($policy->allowed)->toBeTrue();
})->group('digital', 'payments');

it('digital: denied post-delivery when service flag is false', function (): void {
    $policy = refundPolicyService(false)->policyFor(ProductType::Digital, 'delivered', null, 42);

    expect($policy->allowed)->toBeFalse();
    expect($policy->reasonCode)->toBe('digital_post_delivery');
})->group('digital', 'payments');
