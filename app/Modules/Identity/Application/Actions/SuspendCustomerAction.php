<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\CustomerSuspended;
use App\Modules\Identity\Domain\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SuspendCustomerAction
{
    public function execute(User $customer): User
    {
        if ($customer->id === auth()->id()) {
            throw new DomainException('admin_cannot_self_suspend');
        }

        if ($customer->status === 'suspended') {
            throw new DomainException('customer_already_suspended');
        }

        $actorId = (int) auth()->id();

        return DB::transaction(function () use ($customer, $actorId): User {
            $customer->update(['status' => 'suspended']);
            $customer->tokens()->delete();

            activity()
                ->on($customer)
                ->causedBy(auth()->user())
                ->withProperties(['old' => ['status' => 'active'], 'new' => ['status' => 'suspended']])
                ->log('customer.suspended');

            DB::afterCommit(fn () => event(new CustomerSuspended($customer, $actorId)));

            return $customer;
        });
    }
}
