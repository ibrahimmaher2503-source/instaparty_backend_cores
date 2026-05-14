<?php

namespace App\Modules\Communication\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminInboxRoutingRulePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_inbox::routing::rules');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('view_inbox::routing::rules');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_inbox::routing::rules');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('update_inbox::routing::rules');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('delete_inbox::routing::rules');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_inbox::routing::rules');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('force_delete_inbox::routing::rules');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_inbox::routing::rules');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('restore_inbox::routing::rules');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_inbox::routing::rules');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AdminInboxRoutingRule $adminInboxRoutingRule): bool
    {
        return $user->can('replicate_inbox::routing::rules');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_inbox::routing::rules');
    }
}
