<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingVendorTimedOut;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Contracts\AdminInboxWriter;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EscalateLateVendorResponseAction
{
    public function __construct(
        private readonly AdminInboxWriter $inboxWriter,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function execute(BookingVendor $bookingVendor, AdminInterventionDTO $dto): BookingAdminIntervention
    {
        return DB::transaction(function () use ($bookingVendor, $dto): BookingAdminIntervention {
            // Lock the row to prevent concurrent escalation
            $vendor = BookingVendor::query()
                ->whereKey($bookingVendor->id)
                ->lockForUpdate()
                ->firstOrFail();

            $gracePeriodMinutes = config('booking.intervention.deadline_grace_period_minutes', 0);
            $deadline = $vendor->response_deadline?->copy()->addMinutes($gracePeriodMinutes);

            if (! $deadline || $deadline->isFuture()) {
                throw new \DomainException(__('booking::booking.intervention.error.deadline_not_passed'));
            }

            if ($vendor->sub_status !== VendorSubStatus::Pending) {
                throw new \DomainException(__('booking::booking.intervention.error.vendor_not_pending'));
            }

            $beforeSubStatus = $vendor->sub_status->value;
            $vendor->sub_status = VendorSubStatus::TimedOut;
            $vendor->save();

            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $dto->bookingId,
                'admin_id' => $dto->adminId,
                'intervention_type' => InterventionType::VendorTimeout,
                'reason' => $dto->reason,
                'before_state' => ['sub_status' => $beforeSubStatus],
                'after_state' => ['sub_status' => VendorSubStatus::TimedOut->value],
            ]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $vendor->id,
                'from_state' => $beforeSubStatus,
                'to_state' => VendorSubStatus::TimedOut->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $dto->adminId,
                'context' => [
                    'intervention_id' => $intervention->id,
                    'reason' => $dto->reason,
                    'booking_id' => $dto->bookingId,
                ],
            ]);

            $this->inboxWriter->create(
                'booking_admin_intervention',
                $intervention->id,
                AdminInboxSeverity::Warning,
                ['en' => "Vendor timed out on booking #{$dto->bookingId}", 'ar' => "انتهت مهلة المورد للحجز #{$dto->bookingId}"],
                ['en' => $dto->reason, 'ar' => $dto->reason],
                $dto->adminId,
            );

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => BookingVendor::class,
                'auditable_id' => $vendor->id,
                'user_id' => $dto->adminId,
                'action' => 'booking.vendor_timeout',
                'changes' => json_encode([
                    'before' => ['sub_status' => $beforeSubStatus],
                    'after' => ['sub_status' => VendorSubStatus::TimedOut->value],
                    'booking_id' => $dto->bookingId,
                    'reason' => $dto->reason,
                ]),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new BookingVendorTimedOut(
                $vendor->id,
                $dto->bookingId,
                $dto->adminId,
                $dto->reason,
            )));

            return $intervention;
        });
    }
}
