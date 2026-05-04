<?php

namespace App\Modules\Loyalty\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoyaltyLedgerEntryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_loyalty::ledger');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('view_loyalty::ledger');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_loyalty::ledger');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('update_loyalty::ledger');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('delete_loyalty::ledger');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_loyalty::ledger');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('force_delete_loyalty::ledger');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_loyalty::ledger');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('restore_loyalty::ledger');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_loyalty::ledger');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, LoyaltyLedgerEntry $loyaltyLedgerEntry): bool
    {
        return $user->can('replicate_loyalty::ledger');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_loyalty::ledger');
    }
}
