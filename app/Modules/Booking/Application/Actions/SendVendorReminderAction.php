<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorReminderSent;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendVendorReminderAction
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function execute(BookingVendor $bookingVendor, int $adminId, ?string $note = null): BookingAdminIntervention
    {
        if ($bookingVendor->sub_status !== VendorSubStatus::Pending) {
            throw new \DomainException(__('booking::booking.intervention.error.vendor_not_pending'));
        }

        $scope = 'admin.intervention.vendor_reminder';
        $ttlSeconds = config('booking.intervention.vendor_reminder_cooldown_minutes', 5) * 60;
        $throttleKey = hash('xxh64', "{$scope}:{$bookingVendor->id}");

        $alreadyThrottled = DB::table('idempotency_keys')
            ->where('scope', 'internal')
            ->where('key', $throttleKey)
            ->where('expires_at', '>', now())
            ->exists();

        if ($alreadyThrottled) {
            throw new \DomainException(__('booking::booking.intervention.error.throttled'));
        }

        return DB::transaction(function () use ($bookingVendor, $adminId, $note, $scope, $ttlSeconds, $throttleKey): BookingAdminIntervention {
            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $bookingVendor->booking_id,
                'admin_id' => $adminId,
                'intervention_type' => InterventionType::VendorReminder,
                'reason' => $note ?? 'Vendor reminder sent by admin',
                'before_state' => ['sub_status' => $bookingVendor->sub_status->value],
                'after_state' => ['sub_status' => $bookingVendor->sub_status->value, 'reminder_sent' => true],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $bookingVendor->booking_id,
                'user_id' => $adminId,
                'action' => 'booking.vendor_reminder',
                'changes' => json_encode(['booking_vendor_id' => $bookingVendor->id, 'note' => $note]),
                'created_at' => now(),
            ]);

            DB::table('idempotency_keys')->insert([
                'scope' => 'internal',
                'key' => $throttleKey,
                'user_id' => $adminId,
                'route' => $scope,
                'request_hash' => hash('sha256', $throttleKey),
                'expires_at' => now()->addSeconds($ttlSeconds),
                'created_at' => now(),
            ]);

            $vendorUserId = $bookingVendor->vendor?->user_id;

            DB::afterCommit(function () use ($bookingVendor, $adminId, $note, $vendorUserId): void {
                if ($vendorUserId) {
                    $this->dispatcher->dispatch(
                        'booking.vendor.reminder',
                        $vendorUserId,
                        NotificationAudience::Vendor,
                        ['booking_vendor_id' => $bookingVendor->id, 'note' => $note],
                        BookingVendor::class,
                        $bookingVendor->id,
                    );
                }
                event(new VendorReminderSent($bookingVendor->id, $adminId));
            });

            return $intervention;
        });
    }
}
