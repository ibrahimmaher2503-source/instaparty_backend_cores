<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingItem
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXY...",
 *     "product_type": "rental",
 *     "name": "Bouncy castle",
 *     "unit_price_minor": 25000,
 *     "quantity": 1,
 *     "is_modified": true,
 *     "change_badges": ["change_price", "change_slot"]
 *   }
 * }
 */
class BookingItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = str_starts_with($request->header('Accept-Language', 'ar'), 'en') ? 'en' : 'ar';
        $name = $this->name_snapshot ?? [];

        $changeBadges = $this->changeBadges();

        return [
            'public_id' => $this->public_id,
            'product_type' => $this->product_type->value,
            'name' => $name[$locale] ?? $name['en'] ?? '',
            'unit_price_minor' => $this->unit_price_minor,
            'quantity' => $this->quantity,
            'line_total_minor' => $this->line_total_minor,
            'currency' => $this->unit_price_currency ?? 'EGP',
            'item_status' => $this->item_status,
            'effective_starts_at' => $this->effective_starts_at?->toIso8601String(),
            'effective_ends_at' => $this->effective_ends_at?->toIso8601String(),
            'is_modified' => count($changeBadges) > 0,
            'change_badges' => $changeBadges,
        ];
    }

    /** @return list<string> */
    private function changeBadges(): array
    {
        /** @var BookingItem $item */
        $item = $this->resource;

        return BookingModificationItem::query()
            ->where('target_booking_item_id', $item->id)
            ->whereHas('modification', fn ($q) => $q->where('status', ModificationStatus::Pending->value))
            ->get()
            ->map(fn (BookingModificationItem $row) => (string) ($row->payload['change_type'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
