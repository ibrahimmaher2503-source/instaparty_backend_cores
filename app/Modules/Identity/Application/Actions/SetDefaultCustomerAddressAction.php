<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

/**
 * 3.5 — marks one address as default, clearing the flag on the user's
 * other addresses inside the same transaction (single-default invariant).
 */
class SetDefaultCustomerAddressAction
{
    public function execute(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address): CustomerAddress {
            CustomerAddress::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);

            $address->forceFill(['is_default' => true])->save();

            return $address;
        });
    }
}
