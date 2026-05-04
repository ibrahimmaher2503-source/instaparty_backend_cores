<?php

namespace App\Modules\Reviews\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceReviewPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_service::review');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('view_service::review');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_service::review');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('update_service::review');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('delete_service::review');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_service::review');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('force_delete_service::review');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_service::review');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('restore_service::review');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_service::review');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ServiceReview $serviceReview): bool
    {
        return $user->can('replicate_service::review');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_service::review');
    }
}
