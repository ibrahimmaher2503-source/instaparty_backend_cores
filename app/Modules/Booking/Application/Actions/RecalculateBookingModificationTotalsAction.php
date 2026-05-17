<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use Illuminate\Support\Facades\DB;

class RecalculateBookingModificationTotalsAction
{
    public function execute(BookingModification $modification): BookingModification
    {
        return DB::transaction(function () use ($modification): BookingModification {
            $modification->loadMissing(['items', 'bookingVendor']);

            $currency = $modification->bookingVendor->subtotal_currency;

            $priceDeltaTotal = 0;
            $byChangeKind = [];
            $itemSnapshots = [];
            $proposalKinds = [];

            /** @var BookingModificationItem $item */
            foreach ($modification->items as $item) {
                $payload = $item->payload;

                $priceDeltaTotal += (int) ($payload['price_delta_minor'] ?? 0);

                $kind = $item->change_kind->value;
                $byChangeKind[$kind] = ($byChangeKind[$kind] ?? 0) + 1;

                if (isset($payload['change_type']) && is_string($payload['change_type'])) {
                    $proposalKinds[] = $payload['change_type'];
                }

                $itemSnapshots[] = [
                    'modification_item_id' => $item->id,
                    'target_booking_item_id' => $item->target_booking_item_id,
                    'change_kind' => $kind,
                    'change_type' => $payload['change_type'] ?? null,
                    'price_delta_minor' => (int) ($payload['price_delta_minor'] ?? 0),
                    'quantity_delta' => (int) ($payload['quantity_delta'] ?? 0),
                    'time_delta' => $payload['time_delta'] ?? null,
                ];
            }

            $modification->update([
                'proposal_kind' => $this->resolveProposalKind($proposalKinds),
                'diff_snapshot' => [
                    'totals' => [
                        'price_delta_minor' => $priceDeltaTotal,
                        'currency' => $currency,
                        'item_count' => $modification->items->count(),
                        'by_change_kind' => $byChangeKind,
                    ],
                    'items' => $itemSnapshots,
                ],
            ]);

            return $modification->fresh(['items', 'bookingVendor']);
        });
    }

    /**
     * Most-specific match wins; multi-kind drafts fall back to AddSurcharge
     * (a deliberate non-info default that signals the customer "this is a
     * compound proposal, not a single-axis change").
     *
     * @param  list<string>  $proposalKinds
     */
    private function resolveProposalKind(array $proposalKinds): ModificationProposalKind
    {
        $unique = array_values(array_unique($proposalKinds));

        if (count($unique) === 0) {
            return ModificationProposalKind::AddNote;
        }

        if (count($unique) === 1) {
            return ModificationProposalKind::tryFrom($unique[0]) ?? ModificationProposalKind::AddNote;
        }

        return ModificationProposalKind::AddSurcharge;
    }
}
