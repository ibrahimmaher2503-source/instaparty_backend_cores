<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Policies;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingPolicy
{
    use HandlesAuthorization;

    /**
     * Hard refusal: admin cannot assign a replacement vendor on behalf of the customer.
     * Enforces FR-17 / FR-18 / BR-4 / FR-EXT-021. The customer remains the sole decider
     * of vendor alternatives.
     *
     * @return false Always returns false. The literal return type makes accidental
     *               `return true` a fatal type error.
     */
    public function assignReplacementVendor(?User $user, Booking $booking): false
    {
        $this->logBlockedReplacementAttempt($user, $booking);

        return false;
    }

    private function logBlockedReplacementAttempt(?User $user, Booking $booking): void
    {
        try {
            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $user?->id,
                'action' => 'booking.replacement_vendor_assignment_blocked',
                'changes' => json_encode([
                    'attempted_at' => now()->toIso8601String(),
                    'role' => $user?->getRoleNames()->toArray() ?? [],
                    'source' => request()?->route()?->getName() ?? 'cli',
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never throw from a policy — refusal is unconditional regardless of DB availability
        }
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_bookings::monitor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->can('view_bookings::monitor');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_bookings::monitor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->can('update_bookings::monitor');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $user->can('delete_bookings::monitor');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_bookings::monitor');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->can('force_delete_bookings::monitor');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_bookings::monitor');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return $user->can('restore_bookings::monitor');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_bookings::monitor');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Booking $booking): bool
    {
        return $user->can('replicate_bookings::monitor');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_bookings::monitor');
    }
}
