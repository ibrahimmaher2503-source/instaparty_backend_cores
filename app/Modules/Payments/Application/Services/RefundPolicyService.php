<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader;
use App\Modules\Payments\Domain\Contracts\RefundPolicyResolver;
use App\Modules\Payments\Domain\ValueObjects\RefundPolicy;
use Carbon\Carbon;

class RefundPolicyService implements RefundPolicyResolver
{
    public function __construct(private readonly PaymentsCatalogReader $catalogReader) {}

    public function policyFor(ProductType $productType, string $itemStatus, ?Carbon $eventStartsAt, ?int $serviceId = null): RefundPolicy
    {
        return match ($productType) {
            ProductType::Rental => $this->rentalPolicy($itemStatus, $eventStartsAt),
            ProductType::Sale => $this->salePolicy($itemStatus),
            ProductType::Digital => $this->digitalPolicy($itemStatus, $serviceId),
        };
    }

    private function rentalPolicy(string $itemStatus, ?Carbon $eventStartsAt): RefundPolicy
    {
        if ($itemStatus === 'setup') {
            return RefundPolicy::denied('rental_in_setup', 'payments::refunds.policy.rental_in_setup');
        }

        if ($eventStartsAt !== null && $eventStartsAt->lt(now()->addHours(24))) {
            return RefundPolicy::denied('rental_window_closed', 'payments::refunds.policy.rental_window_closed');
        }

        return RefundPolicy::allowed();
    }

    private function salePolicy(string $itemStatus): RefundPolicy
    {
        return match ($itemStatus) {
            'pending', 'confirmed' => RefundPolicy::allowed(),
            'in_preparation' => RefundPolicy::denied('sale_in_preparation', 'payments::refunds.policy.sale_in_preparation'),
            default => RefundPolicy::denied('sale_unavailable', 'payments::refunds.policy.sale_in_preparation'),
        };
    }

    private function digitalPolicy(string $itemStatus, ?int $serviceId): RefundPolicy
    {
        if ($itemStatus !== 'delivered') {
            return RefundPolicy::allowed();
        }

        if ($serviceId === null) {
            return RefundPolicy::denied('digital_post_delivery', 'payments::refunds.policy.digital_post_delivery');
        }

        return $this->catalogReader->isDigitalRefundableAfterDelivery($serviceId)
            ? RefundPolicy::allowed()
            : RefundPolicy::denied('digital_post_delivery', 'payments::refunds.policy.digital_post_delivery');
    }
}
