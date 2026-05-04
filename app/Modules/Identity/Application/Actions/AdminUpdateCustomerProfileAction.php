<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\AdminUpdateCustomerDTO;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class AdminUpdateCustomerProfileAction
{
    public function execute(User $customer, AdminUpdateCustomerDTO $dto): User
    {
        $old = [
            'name' => $customer->name,
            'phone_e164' => $customer->phone_e164,
        ];

        $updated = DB::transaction(function () use ($customer, $dto): User {
            $customer->update($dto->toArray());

            return $customer->refresh();
        });

        activity()
            ->on($customer)
            ->causedBy(auth()->user())
            ->withProperties(['old' => $old, 'new' => $dto->toArray()])
            ->log('customer.profile_updated');

        return $updated;
    }
}
