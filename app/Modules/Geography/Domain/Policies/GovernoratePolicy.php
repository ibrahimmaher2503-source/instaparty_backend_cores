<?php

namespace App\Modules\Geography\Domain\Policies;

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GovernoratePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_governorate');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Governorate $governorate): bool
    {
        return $user->can('view_governorate');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_governorate');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Governorate $governorate): bool
    {
        return $user->can('update_governorate');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Governorate $governorate): bool
    {
        return $user->can('delete_governorate');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_governorate');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Governorate $governorate): bool
    {
        return $user->can('force_delete_governorate');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_governorate');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Governorate $governorate): bool
    {
        return $user->can('restore_governorate');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_governorate');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Governorate $governorate): bool
    {
        return $user->can('replicate_governorate');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_governorate');
    }
}
