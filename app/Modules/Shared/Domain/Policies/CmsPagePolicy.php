<?php

namespace App\Modules\Shared\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Auth\Access\HandlesAuthorization;

class CmsPagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cms::page');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('view_cms::page');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_cms::page');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('update_cms::page');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('delete_cms::page');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cms::page');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('force_delete_cms::page');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_cms::page');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('restore_cms::page');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_cms::page');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CmsPage $cmsPage): bool
    {
        return $user->can('replicate_cms::page');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_cms::page');
    }
}
