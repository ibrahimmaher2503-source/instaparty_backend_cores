<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForceCancelBookingAction
{
    public function execute(Booking $booking, AdminInterventionDTO $dto): BookingAdminIntervention
    {
        if ($booking->lifecycle_status === LifecycleStatus::Completed) {
            throw new \DomainException('Cannot force-cancel a completed booking.');
        }

        return DB::transaction(function () use ($booking, $dto): BookingAdminIntervention {
            $beforeState = [
                'lifecycle_status' => $booking->lifecycle_status->value,
                'payment_status'   => $booking->payment_status->value,
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $booking->lifecycle_status = LifecycleStatus::Cancelled;
            $booking->cancelled_by = 'admin';
            $booking->save();

            $afterState = [
                'lifecycle_status' => LifecycleStatus::Cancelled->value,
                'payment_status'   => $booking->payment_status->value,
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $intervention = BookingAdminIntervention::create([
                'public_id'         => (string) Str::ulid(),
                'booking_id'        => $booking->id,
                'admin_id'          => $dto->adminId,
                'intervention_type' => InterventionType::ForceCancel,
                'reason'            => $dto->reason,
                'before_state'      => $beforeState,
                'after_state'       => $afterState,
            ]);

            BookingStateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id'   => $booking->id,
                'from_state'          => $beforeState['lifecycle_status'],
                'to_state'            => LifecycleStatus::Cancelled->value,
                'trigger_kind'        => 'admin',
                'triggered_by'        => $dto->adminId,
                'context'             => ['intervention_id' => $intervention->id, 'reason' => $dto->reason],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'      => (string) Str::ulid(),
                'auditable_type' => Booking::class,
                'auditable_id'   => $booking->id,
                'user_id'        => $dto->adminId,
                'action'         => 'force_cancel_booking',
                'changes'        => json_encode([
                    'before' => $beforeState,
                    'after'  => $afterState,
                ]),
                'created_at'     => now(),
            ]);

            DB::afterCommit(fn () => event(new BookingForceCancelled($booking, $intervention)));

            return $intervention;
        });
    }
}
