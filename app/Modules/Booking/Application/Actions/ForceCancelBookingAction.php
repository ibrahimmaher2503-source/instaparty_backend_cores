<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForceCancelBookingAction
{
    public function execute(Booking $booking, AdminInterventionDTO $dto): BookingAdminIntervention
    {
        if ($booking->lifecycle_status instanceof CompletedState) {
            throw new \DomainException('Cannot force-cancel a completed booking.');
        }

        return DB::transaction(function () use ($booking, $dto): BookingAdminIntervention {
            $beforeState = [
                'lifecycle_status' => $booking->lifecycle_status->getValue(),
                'payment_status' => $booking->payment_status->getValue(),
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $booking->lifecycle_status = CancelledState::class;
            $booking->cancelled_by = $dto->adminId;
            $booking->cancelled_at = now();
            $booking->save();

            $afterState = [
                'lifecycle_status' => LifecycleStatus::Cancelled->value,
                'payment_status' => $booking->payment_status->getValue(),
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $booking->id,
                'admin_id' => $dto->adminId,
                'intervention_type' => InterventionType::ForceCancel,
                'reason' => $dto->reason,
                'before_state' => $beforeState,
                'after_state' => $afterState,
            ]);

            StateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $beforeState['lifecycle_status'],
                'to_state' => LifecycleStatus::Cancelled->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $dto->adminId,
                'context' => ['intervention_id' => $intervention->id, 'reason' => $dto->reason],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $dto->adminId,
                'action' => 'force_cancel_booking',
                'changes' => json_encode([
                    'before' => $beforeState,
                    'after' => $afterState,
                ]),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new BookingForceCancelled($booking, $intervention)));

            return $intervention;
        });
    }
}
