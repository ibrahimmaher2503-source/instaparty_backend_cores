<?php

namespace App\Modules\Settlement\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class LedgerTransactionGroupPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ledger::transaction::group');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('view_ledger::transaction::group');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_ledger::transaction::group');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('update_ledger::transaction::group');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('delete_ledger::transaction::group');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ledger::transaction::group');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('force_delete_ledger::transaction::group');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ledger::transaction::group');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('restore_ledger::transaction::group');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ledger::transaction::group');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, LedgerTransactionGroup $ledgerTransactionGroup): bool
    {
        return $user->can('replicate_ledger::transaction::group');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_ledger::transaction::group');
    }
}
