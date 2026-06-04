<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Support\Str;

/**
 * Computes the before/after diff snapshot for a proposed booking-vendor
 * modification. Pure computation — no persistence, no events. Shared by
 * PreviewBookingModificationAction (G6) and VendorModifyBookingAction.
 */
class BookingModificationDiffService
{
    /**
     * @param  array<int,array{change_kind:string,target_item_public_id?:string,payload:array<string,mixed>}>  $changes
     * @return array<string,mixed>
     */
    public function buildDiffSnapshot(BookingVendor $bookingVendor, array $changes): array
    {
        $currentItems = $bookingVendor->items->map(fn (BookingItem $item) => [
            'public_id' => $item->public_id,
            'unit_price_minor' => $item->unit_price_minor,
            'unit_price_currency' => $item->unit_price_currency,
            'quantity' => $item->quantity,
            'effective_starts_at' => $item->effective_starts_at?->toIso8601String(),
            'effective_ends_at' => $item->effective_ends_at?->toIso8601String(),
        ])->keyBy('public_id')->all();

        $afterItems = $currentItems;
        $subtotalBefore = $bookingVendor->subtotal_minor;
        $subtotalAfter = $subtotalBefore;

        foreach ($changes as $change) {
            match (ModificationChangeKind::from($change['change_kind'])) {
                ModificationChangeKind::Update => $this->applyDiffUpdate($change, $afterItems, $subtotalAfter),
                ModificationChangeKind::Add => $this->applyDiffAdd($change, $afterItems, $subtotalAfter),
                ModificationChangeKind::Remove => $this->applyDiffRemove($change, $afterItems, $subtotalAfter),
            };
        }

        return [
            'before' => [
                'items' => array_values($currentItems),
                'subtotal_minor' => $subtotalBefore,
            ],
            'after' => [
                'items' => array_values($afterItems),
                'subtotal_minor' => $subtotalAfter,
            ],
        ];
    }

    /**
     * Stable hash of a change set — used to detect drift between the
     * previewed changes and the submitted ones.
     *
     * @param  array<int,array<string,mixed>>  $changes
     */
    public function changesHash(int $bookingVendorId, array $changes): string
    {
        return hash('sha256', $bookingVendorId.'|'.json_encode($changes));
    }

    /** @param array<string,mixed> $change @param array<string,mixed> $afterItems */
    private function applyDiffUpdate(array $change, array &$afterItems, int &$subtotalAfter): void
    {
        if (! isset($change['target_item_public_id'])) {
            return;
        }
        $pubId = $change['target_item_public_id'];
        if (! isset($afterItems[$pubId])) {
            return;
        }
        $payload = $change['payload'];
        $oldPrice = (int) $afterItems[$pubId]['unit_price_minor'];
        $newPrice = isset($payload['unit_price_minor']) ? (int) $payload['unit_price_minor'] : $oldPrice;
        $qty = (int) $afterItems[$pubId]['quantity'];
        $afterItems[$pubId] = array_merge($afterItems[$pubId], $payload);
        $subtotalAfter += ($newPrice - $oldPrice) * $qty;
    }

    /** @param array<string,mixed> $change @param array<string,mixed> $afterItems */
    private function applyDiffAdd(array $change, array &$afterItems, int &$subtotalAfter): void
    {
        $payload = $change['payload'];
        $newPubId = (string) Str::ulid();
        $price = (int) ($payload['unit_price_minor'] ?? 0);
        $qty = (int) ($payload['quantity'] ?? 1);
        $afterItems[$newPubId] = array_merge(['public_id' => $newPubId], $payload);
        $subtotalAfter += $price * $qty;
    }

    /** @param array<string,mixed> $change @param array<string,mixed> $afterItems */
    private function applyDiffRemove(array $change, array &$afterItems, int &$subtotalAfter): void
    {
        if (! isset($change['target_item_public_id'])) {
            return;
        }
        $pubId = $change['target_item_public_id'];
        if (! isset($afterItems[$pubId])) {
            return;
        }
        $price = (int) $afterItems[$pubId]['unit_price_minor'];
        $qty = (int) $afterItems[$pubId]['quantity'];
        $subtotalAfter -= $price * $qty;
        unset($afterItems[$pubId]);
    }
}
