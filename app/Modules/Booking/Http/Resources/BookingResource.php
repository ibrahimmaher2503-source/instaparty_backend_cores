<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Application\Actions\ResolveAvailablePaymentMethodsAction;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXY...",
 *     "reference_no": "INP-2026-000123",
 *     "lifecycle_status": "customer_review",
 *     "display_status": "modification_requested",
 *     "rejection_reason": null,
 *     "vendors_summary": {"total": 3, "pending": 1, "accepted": 2, "modified": 0, "rejected": 0, "cancelled": 0, "in_progress": 0, "completed": 0, "timed_out": 0},
 *     "payment_status": "unpaid",
 *     "fulfillment_status": "not_started",
 *     "requires_customer_approval": true,
 *     "total_minor": 25000,
 *     "currency": "EGP"
 *   }
 * }
 */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tz = auth()->user()->timezone ?? 'Africa/Cairo';
        $locale = str_starts_with($request->header('Accept-Language', 'ar'), 'en') ? 'en' : 'ar';

        return [
            'public_id' => $this->public_id,
            'reference_no' => $this->reference_no,
            'lifecycle_status' => $this->lifecycle_status->getValue(),
            'display_status' => $this->displayStatus(),
            'rejection_reason' => $this->bookingRejectionReason($locale),
            'vendors_summary' => $this->vendorsSummary(),
            'payment_status' => $this->payment_status->getValue(),
            'fulfillment_status' => $this->fulfillment_status->value,
            'event_starts_at' => $this->event_starts_at?->setTimezone($tz)->toIso8601String(),
            'event_ends_at' => $this->event_ends_at?->setTimezone($tz)->toIso8601String(),
            'guest_count' => $this->guest_count,
            'subtotal_minor' => $this->subtotal_minor ?? 0,
            'delivery_total_minor' => $this->delivery_total_minor ?? 0,
            'discount_total_minor' => $this->discount_total_minor ?? 0,
            'discount_promo_minor' => $this->discount_promo_minor ?? 0,
            'discount_loyalty_minor' => $this->discount_loyalty_minor ?? 0,
            'applied_wallet_minor' => $this->applied_wallet_minor ?? 0,
            'total_minor' => $this->total_minor ?? 0,
            'due_minor' => ($this->total_minor ?? 0) - ($this->applied_wallet_minor ?? 0) - ($this->discount_loyalty_minor ?? 0),
            'currency' => $this->total_currency ?? 'EGP',
            'payment_method_options' => app(ResolveAvailablePaymentMethodsAction::class)->execute($this->resource),
            'hold_expires_at' => $this->payment_hold_expires_at?->setTimezone($tz)->toIso8601String(),
            'requires_customer_approval' => $this->requiresCustomerApproval(),
            'vendors' => BookingVendorResource::collection($this->whenLoaded('vendors')),
            'address' => $this->whenLoaded('address', function () {
                /** @var BookingAddress $address */
                $address = $this->address;

                return [
                    'city_id' => $address->city_id,
                    'address_line' => $address->address_line,
                    'building' => $address->building,
                    'floor' => $address->floor,
                    'apartment' => $address->apartment,
                    'landmark' => $address->landmark,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone_e164' => $address->recipient_phone_e164,
                ];
            }),
        ];
    }

    private function requiresCustomerApproval(): bool
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return $booking->pendingModifications()->exists();
    }

    /**
     * Derived, display-only status. The lifecycle state machine is untouched:
     * - a booking cancelled because EVERY vendor rejected reads `rejected`,
     *   not `cancelled` (the client must not conflate the two);
     * - a booking with a pending vendor modification awaiting the customer's
     *   decision reads `modification_requested`;
     * - everything else mirrors `lifecycle_status`.
     */
    private function displayStatus(): string
    {
        $lifecycle = $this->lifecycle_status->getValue();

        if ($lifecycle === LifecycleStatus::Cancelled->value && $this->allVendorsRejected()) {
            return 'rejected';
        }

        if (in_array($lifecycle, [LifecycleStatus::VendorReview->value, LifecycleStatus::CustomerReview->value], true)
            && $this->requiresCustomerApproval()) {
            return 'modification_requested';
        }

        return $lifecycle;
    }

    private function allVendorsRejected(): bool
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return $booking->vendors->isNotEmpty()
            && $booking->vendors->every(
                fn ($vendor): bool => $vendor->sub_status === VendorSubStatus::Rejected
            );
    }

    private function bookingRejectionReason(string $locale): ?string
    {
        if ($this->displayStatus() !== 'rejected') {
            return null;
        }

        /** @var Booking $booking */
        $booking = $this->resource;

        /** @var array<string,string>|null $reason */
        $reason = $booking->vendors->firstWhere('rejection_reason', '!==', null)?->rejection_reason;

        if (! is_array($reason) || $reason === []) {
            return null;
        }

        return $reason[$locale] ?? $reason['en'] ?? $reason['ar'] ?? null;
    }

    /** @return array<string, int> */
    private function vendorsSummary(): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        $counts = $booking->vendors->countBy(fn ($vendor): string => $vendor->sub_status->value);

        $summary = ['total' => $booking->vendors->count()];
        foreach (VendorSubStatus::cases() as $case) {
            $summary[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $summary;
    }
}
