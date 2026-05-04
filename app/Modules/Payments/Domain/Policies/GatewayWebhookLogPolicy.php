<?php

namespace App\Modules\Payments\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class GatewayWebhookLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_gateway::webhook::log');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('view_gateway::webhook::log');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_gateway::webhook::log');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('update_gateway::webhook::log');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('delete_gateway::webhook::log');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_gateway::webhook::log');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('force_delete_gateway::webhook::log');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_gateway::webhook::log');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('restore_gateway::webhook::log');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_gateway::webhook::log');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, GatewayWebhookLog $gatewayWebhookLog): bool
    {
        return $user->can('replicate_gateway::webhook::log');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_gateway::webhook::log');
    }
}
