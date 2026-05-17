<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\AdminSuggestedAlternativeVendors;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SuggestAlternativeVendorsAction
{
    public function __construct(
        private readonly AlternativeVendorFinder $finder,
    ) {}

    public function execute(Booking $booking, SuggestedAlternativeVendorsDTO $dto): BookingAdminIntervention
    {
        $max = config('booking.intervention.suggest_max_candidates', 5);

        if (empty($dto->vendorProfileIds)) {
            throw new \InvalidArgumentException('At least one vendor must be suggested.');
        }

        if (count($dto->vendorProfileIds) > $max) {
            throw new \InvalidArgumentException("Cannot suggest more than {$max} vendors.");
        }

        // Validate all candidates against the filter (throws InvalidArgumentException on failure)
        $this->finder->validateCandidates($booking, $dto->vendorProfileIds);

        return DB::transaction(function () use ($booking, $dto): BookingAdminIntervention {
            $intervention = BookingAdminIntervention::create([
                'public_id'          => Str::ulid()->toBase32(),
                'booking_id'         => $booking->id,
                'admin_id'           => $dto->adminId,
                'intervention_type'  => InterventionType::VendorProposal,
                'reason'             => $dto->reason,
                'proposed_vendor_id' => null, // MUST always be null — FR-EXT-012
                'before_state'       => [],
                'after_state'        => [
                    'suggested_vendor_ids' => $dto->vendorProfileIds,
                    'reason'               => $dto->reason,
                ],
            ]);

            DB::table('audit_logs')->insert([
                'public_id'      => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id'   => $booking->id,
                'user_id'        => $dto->adminId,
                'action'         => 'booking.suggest_alternatives',
                'changes'        => json_encode([
                    'suggested_vendor_ids' => $dto->vendorProfileIds,
                    'reason'               => $dto->reason,
                ]),
                'created_at'     => now(),
            ]);

            DB::afterCommit(fn () => event(new AdminSuggestedAlternativeVendors(
                $booking->id,
                $dto->adminId,
                $dto->vendorProfileIds,
                $dto->reason,
            )));

            return $intervention;
        });
    }
}
