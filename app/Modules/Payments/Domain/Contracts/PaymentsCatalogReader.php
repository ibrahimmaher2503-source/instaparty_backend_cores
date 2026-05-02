<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

interface PaymentsCatalogReader
{
    public function isDigitalRefundableAfterDelivery(int $serviceId): bool;
}
