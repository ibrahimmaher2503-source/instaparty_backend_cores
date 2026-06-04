<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

/**
 * 3.3 — partial update of an own address. Snapshot safety: bookings copy
 * addresses into booking_addresses at creation, so editing here never
 * mutates past bookings.
 */
class UpdateCustomerAddressAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(CustomerAddress $address, array $attributes): CustomerAddress
    {
        return DB::transaction(function () use ($address, $attributes): CustomerAddress {
            $address->fill($attributes)->save();

            return $address->refresh();
        });
    }
}
