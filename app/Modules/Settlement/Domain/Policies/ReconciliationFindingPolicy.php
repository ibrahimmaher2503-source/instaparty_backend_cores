<?php

namespace App\Modules\Settlement\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReconciliationFindingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_reconciliation::finding');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('view_reconciliation::finding');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_reconciliation::finding');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('update_reconciliation::finding');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('delete_reconciliation::finding');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_reconciliation::finding');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('force_delete_reconciliation::finding');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_reconciliation::finding');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('restore_reconciliation::finding');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_reconciliation::finding');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ReconciliationFinding $reconciliationFinding): bool
    {
        return $user->can('replicate_reconciliation::finding');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_reconciliation::finding');
    }
}
