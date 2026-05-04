<?php

namespace App\Modules\Identity\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\UserDevice;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserDevicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user::device');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserDevice $userDevice): bool
    {
        return $user->can('view_user::device');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_user::device');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserDevice $userDevice): bool
    {
        return $user->can('update_user::device');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserDevice $userDevice): bool
    {
        return $user->can('delete_user::device');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_user::device');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, UserDevice $userDevice): bool
    {
        return $user->can('force_delete_user::device');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_user::device');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, UserDevice $userDevice): bool
    {
        return $user->can('restore_user::device');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_user::device');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, UserDevice $userDevice): bool
    {
        return $user->can('replicate_user::device');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_user::device');
    }
}
