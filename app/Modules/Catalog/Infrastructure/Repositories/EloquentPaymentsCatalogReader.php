<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader;
use Illuminate\InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class EloquentPaymentsCatalogReader implements PaymentsCatalogReader
{
    public function isDigitalRefundableAfterDelivery(int $serviceId): bool
    {
        $row = DB::table('service_digital_details')->where('service_id', $serviceId)->first(['is_refundable_after_delivery']);

        if ($row === null) {
            throw new InvalidArgumentException('Digital service details not found.');
        }

        return (bool) $row->is_refundable_after_delivery;
    }
}
