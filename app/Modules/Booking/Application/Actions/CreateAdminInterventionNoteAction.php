<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateAdminInterventionNoteAction
{
    public function execute(Booking $booking, int $adminId, string $note): BookingAdminIntervention
    {
        if (strlen($note) < 1 || strlen($note) > 2000) {
            throw new \InvalidArgumentException('Note must be between 1 and 2000 characters.');
        }

        return DB::transaction(function () use ($booking, $adminId, $note): BookingAdminIntervention {
            $intervention = BookingAdminIntervention::create([
                'public_id'         => Str::ulid()->toBase32(),
                'booking_id'        => $booking->id,
                'admin_id'          => $adminId,
                'intervention_type' => InterventionType::AdminNote,
                'reason'            => $note,
                'before_state'      => [],
                'after_state'       => [],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'      => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id'   => $booking->id,
                'user_id'        => $adminId,
                'action'         => 'booking.admin_note',
                'changes'        => json_encode(['note_length' => strlen($note)]),
                'created_at'     => now(),
            ]);

            // NO events, NO dispatch, NO state mutation per spec

            return $intervention;
        });
    }
}
