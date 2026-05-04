<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\CustomerUnsuspended;
use App\Modules\Identity\Domain\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UnsuspendCustomerAction
{
    public function execute(User $customer): User
    {
        if ($customer->status !== 'suspended') {
            throw new DomainException('customer_not_suspended');
        }

        $actorId = (int) auth()->id();

        return DB::transaction(function () use ($customer, $actorId): User {
            $customer->update(['status' => 'active']);

            activity()
                ->on($customer)
                ->causedBy(auth()->user())
                ->withProperties(['old' => ['status' => 'suspended'], 'new' => ['status' => 'active']])
                ->log('customer.unsuspended');

            DB::afterCommit(fn () => event(new CustomerUnsuspended($customer, $actorId)));

            return $customer;
        });
    }
}
