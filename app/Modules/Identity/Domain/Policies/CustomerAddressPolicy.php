<?php

namespace App\Modules\Identity\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerAddressPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_customer::address');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('view_customer::address');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_customer::address');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('update_customer::address');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('delete_customer::address');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_customer::address');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('force_delete_customer::address');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_customer::address');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('restore_customer::address');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_customer::address');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can('replicate_customer::address');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_customer::address');
    }
}
