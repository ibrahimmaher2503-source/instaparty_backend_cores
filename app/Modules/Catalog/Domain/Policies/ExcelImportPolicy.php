<?php

namespace App\Modules\Catalog\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExcelImportPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_excel::import');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('view_excel::import');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_excel::import');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('update_excel::import');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('delete_excel::import');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_excel::import');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('force_delete_excel::import');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_excel::import');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('restore_excel::import');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_excel::import');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ExcelImport $excelImport): bool
    {
        return $user->can('replicate_excel::import');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_excel::import');
    }
}
