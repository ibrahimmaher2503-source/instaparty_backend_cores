<?php

namespace App\Modules\Advertising\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdvertisementPackagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_advertisement::package');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('view_advertisement::package');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_advertisement::package');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('update_advertisement::package');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('delete_advertisement::package');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_advertisement::package');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('force_delete_advertisement::package');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_advertisement::package');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('restore_advertisement::package');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_advertisement::package');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AdvertisementPackage $advertisementPackage): bool
    {
        return $user->can('replicate_advertisement::package');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_advertisement::package');
    }
}
