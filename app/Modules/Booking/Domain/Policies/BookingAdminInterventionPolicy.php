<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Policies;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Identity\Domain\Models\User;

final class BookingAdminInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('booking.intervene.access');
    }

    public function view(User $user, BookingAdminIntervention $intervention): bool
    {
        return $user->can('booking.intervene.access');
    }

    /**
     * Hard boundary (FR-EXT-012): admin cannot create a vendor_proposal intervention
     * with a non-null proposed_vendor_id. The customer retains final choice.
     */
    public function create(User $user, BookingAdminIntervention $intervention): bool
    {
        if ($intervention->intervention_type === InterventionType::VendorProposal
            && $intervention->proposed_vendor_id !== null) {
            return false;
        }

        return match ($intervention->intervention_type) {
            InterventionType::VendorReminder         => $user->can('booking.intervene.send_vendor_reminder'),
            InterventionType::VendorTimeout          => $user->can('booking.intervene.escalate_vendor_timeout'),
            InterventionType::VendorProposal         => $user->can('booking.intervene.suggest_alternative_vendors'),
            InterventionType::ChatFrozen,
            InterventionType::ChatResumed            => $user->can('booking.intervene.freeze_chat'),
            InterventionType::CustomerReviewReminder => $user->can('booking.intervene.resume_customer_review'),
            InterventionType::AdminNote              => $user->can('booking.intervene.create_note'),
            InterventionType::ForceCancel            => $user->can('force_cancel_booking'),
        };
    }
}
