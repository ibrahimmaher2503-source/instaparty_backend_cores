<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\BookingChatResumed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResumeBookingChatAction
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

            if ($thread->frozen_at === null) {
                throw new \DomainException(__('booking::booking.intervention.error.chat_not_frozen'));
            }

            $resumedAt = now();

            DB::table('chat_threads')
                ->where('id', $thread->id)
                ->update([
                    'frozen_at'  => null,
                    'frozen_by'  => null,
                    'status'     => 'open',
                    'locked_at'  => null,
                    'updated_at' => $resumedAt,
                ]);

            $intervention = BookingAdminIntervention::create([
                'public_id'         => Str::ulid()->toBase32(),
                'booking_id'        => $bookingId,
                'admin_id'          => $adminId,
                'intervention_type' => InterventionType::ChatResumed,
                'reason'            => $reason,
                'before_state'      => ['chat_status' => 'locked', 'frozen_at' => $thread->frozen_at],
                'after_state'       => ['chat_status' => 'open', 'frozen_at' => null],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'       => Str::ulid()->toBase32(),
                'auditable_type'  => Booking::class,
                'auditable_id'    => $bookingId,
                'user_id'         => $adminId,
                'action'          => 'booking.chat_resumed',
                'changes'         => json_encode([
                    'before'         => ['status' => 'locked', 'frozen_at' => $thread->frozen_at],
                    'after'          => ['status' => 'open', 'frozen_at' => null],
                    'chat_thread_id' => $thread->id,
                    'reason'         => $reason,
                ]),
                'created_at' => $resumedAt,
            ]);

            DB::afterCommit(fn () => event(new BookingChatResumed(
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
