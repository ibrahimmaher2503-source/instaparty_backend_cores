<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\BookingChatFrozen;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FreezeBookingChatAction
{
    public function execute(int $bookingId, int $adminId, string $reason): BookingAdminIntervention
    {
        return DB::transaction(function () use ($bookingId, $adminId, $reason): BookingAdminIntervention {
            $thread = DB::table('chat_threads')
                ->where('booking_id', $bookingId)
                ->lockForUpdate()
                ->first();

            if (! $thread) {
                throw new \DomainException(__('booking::booking.intervention.error.chat_thread_not_found'));
            }

            if ($thread->frozen_at !== null) {
                throw new \DomainException(__('booking::booking.intervention.error.chat_already_frozen'));
            }

            $frozenAt = now();

            DB::table('chat_threads')
                ->where('id', $thread->id)
                ->update([
                    'frozen_at'  => $frozenAt,
                    'frozen_by'  => $adminId,
                    'status'     => 'locked',
                    'locked_at'  => $frozenAt,
                    'updated_at' => $frozenAt,
                ]);

            $intervention = BookingAdminIntervention::create([
                'public_id'         => Str::ulid()->toBase32(),
                'booking_id'        => $bookingId,
                'admin_id'          => $adminId,
                'intervention_type' => InterventionType::ChatFrozen,
                'reason'            => $reason,
                'before_state'      => ['chat_status' => $thread->status, 'frozen_at' => null],
                'after_state'       => ['chat_status' => 'locked', 'frozen_at' => $frozenAt->toIso8601String()],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'       => Str::ulid()->toBase32(),
                'auditable_type'  => Booking::class,
                'auditable_id'    => $bookingId,
                'user_id'         => $adminId,
                'action'          => 'booking.chat_frozen',
                'changes'         => json_encode([
                    'before'         => ['status' => $thread->status, 'frozen_at' => null],
                    'after'          => ['status' => 'locked', 'frozen_at' => $frozenAt->toIso8601String()],
                    'chat_thread_id' => $thread->id,
                    'reason'         => $reason,
                ]),
                'created_at' => $frozenAt,
            ]);

            DB::afterCommit(fn () => event(new BookingChatFrozen(
                $bookingId,
                $thread->id,
                $thread->firestore_thread_id,
                $adminId,
                $reason,
            )));

            return $intervention;
        });
    }
}
