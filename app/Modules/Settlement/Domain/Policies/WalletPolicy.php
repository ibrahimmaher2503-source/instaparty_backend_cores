<?php

namespace App\Modules\Settlement\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Wallet $wallet): bool
    {
        return $user->can('view_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Wallet $wallet): bool
    {
        return $user->can('update_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Wallet $wallet): bool
    {
        return $user->can('delete_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Wallet $wallet): bool
    {
        return $user->can('force_delete_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Wallet $wallet): bool
    {
        return $user->can('restore_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Wallet $wallet): bool
    {
        return $user->can('replicate_wallet::ledger::viewer');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_wallet::ledger::viewer');
    }
}
