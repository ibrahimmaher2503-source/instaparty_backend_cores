<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class ForceLogoutCustomerAction
{
    public function execute(User $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $customer->tokens()->delete();

            activity()
                ->on($customer)
                ->causedBy(auth()->user())
                ->log('customer.force_logout');
        });
    }
}
