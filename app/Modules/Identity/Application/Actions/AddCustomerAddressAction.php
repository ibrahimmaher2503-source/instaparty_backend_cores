<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class AddCustomerAddressAction
{
    public function execute(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data): CustomerAddress {
            if (($data['is_default'] ?? false) === true) {
                CustomerAddress::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->update(['is_default' => false]);
            }

            return CustomerAddress::create([
                ...$data,
                'user_id' => $user->id,
            ]);
        });
    }
}
