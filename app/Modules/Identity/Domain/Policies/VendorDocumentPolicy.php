<?php

namespace App\Modules\Identity\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendorDocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_vendor::document::filament');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('view_vendor::document::filament');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_vendor::document::filament');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('update_vendor::document::filament');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('delete_vendor::document::filament');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_vendor::document::filament');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('force_delete_vendor::document::filament');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_vendor::document::filament');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('restore_vendor::document::filament');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_vendor::document::filament');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, VendorDocument $vendorDocument): bool
    {
        return $user->can('replicate_vendor::document::filament');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_vendor::document::filament');
    }
}
