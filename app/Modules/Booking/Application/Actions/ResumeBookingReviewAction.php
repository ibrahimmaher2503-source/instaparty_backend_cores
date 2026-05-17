<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\CustomerReviewReminderSent;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResumeBookingReviewAction
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function execute(Booking $booking, int $adminId, ?string $note = null): BookingAdminIntervention
    {
        $openModificationsCount = DB::table('booking_modifications')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_modifications.booking_vendor_id')
            ->where('booking_vendors.booking_id', $booking->id)
            ->where('booking_modifications.status', 'pending')
            ->count();

        if ($openModificationsCount === 0) {
            throw new \DomainException(__('booking::booking.intervention.error.no_open_modification'));
        }

        $scope = 'admin.intervention.customer_review_reminder';
        $ttlHours = config('booking.intervention.customer_review_reminder_cooldown_hours', 4);
        $throttleKey = hash('xxh64', "{$scope}:{$booking->id}");

        $alreadyThrottled = DB::table('idempotency_keys')
            ->where('scope', 'internal')
            ->where('key', $throttleKey)
            ->where('expires_at', '>', now())
            ->exists();

        if ($alreadyThrottled) {
            throw new \DomainException(__('booking::booking.intervention.error.throttled'));
        }

        return DB::transaction(function () use ($booking, $adminId, $note, $scope, $ttlHours, $throttleKey, $openModificationsCount): BookingAdminIntervention {
            $intervention = BookingAdminIntervention::create([
                'public_id'         => Str::ulid()->toBase32(),
                'booking_id'        => $booking->id,
                'admin_id'          => $adminId,
                'intervention_type' => InterventionType::CustomerReviewReminder,
                'reason'            => $note ?? 'Customer review reminder sent by admin',
                'before_state'      => ['open_modifications_count' => $openModificationsCount],
                'after_state'       => ['open_modifications_count' => $openModificationsCount, 'note' => $note],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'      => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id'   => $booking->id,
                'user_id'        => $adminId,
                'action'         => 'booking.customer_review_reminder',
                'changes'        => json_encode(['booking_id' => $booking->id, 'note' => $note]),
                'created_at'     => now(),
            ]);

            DB::table('idempotency_keys')->insert([
                'scope'        => 'internal',
                'key'          => $throttleKey,
                'user_id'      => $adminId,
                'route'        => $scope,
                'request_hash' => hash('sha256', $throttleKey),
                'expires_at'   => now()->addHours($ttlHours),
                'created_at'   => now(),
            ]);

            DB::afterCommit(function () use ($booking, $adminId, $note): void {
                $this->dispatcher->dispatch(
                    'booking.customer_review.reminder',
                    $booking->customer_id,
                    NotificationAudience::Customer,
                    ['booking_id' => $booking->id, 'note' => $note],
                    Booking::class,
                    $booking->id,
                );
                event(new CustomerReviewReminderSent($booking->id, $adminId));
            });

            return $intervention;
        });
    }
}
