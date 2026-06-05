<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Vendor;

use App\Modules\Booking\Application\Actions\PreviewBookingModificationAction;
use App\Modules\Booking\Application\Actions\VendorAcceptBookingAction;
use App\Modules\Booking\Application\Actions\VendorModifyBookingAction;
use App\Modules\Booking\Application\Actions\VendorRejectBookingAction;
use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Application\DTOs\VendorModifyDTO;
use App\Modules\Booking\Application\DTOs\VendorRejectDTO;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Http\Requests\VendorModifyRequest;
use App\Modules\Booking\Http\Requests\VendorRejectRequest;
use App\Modules\Booking\Http\Resources\BookingModificationResource;
use App\Modules\Booking\Http\Resources\BookingVendorResource;
use App\Modules\Booking\Http\Resources\VendorBookingVendorDetailResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Models\StateTransition;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Vendor - Bookings
 */
class BookingController
{
    private function vendorProfileId(): int
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var VendorProfile $profile */
        $profile = $user->vendorProfile;

        return $profile->id;
    }

    private function optionalIdempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return null;
        }

        return $key;
    }

    public function index(): JsonResponse
    {
        $vendorProfileId = $this->vendorProfileId();

        $bookingVendors = BookingVendor::with('items')
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('sub_status', VendorSubStatus::Pending)
            ->get();

        return ApiResponse::success(BookingVendorResource::collection($bookingVendors));
    }

    public function show(string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::with(['items', 'booking.address.city', 'booking.occasion', 'booking.customer'])
            ->where('public_id', $bookingVendorPublicId)
            ->where('vendor_profile_id', $this->vendorProfileId())
            ->firstOrFail();

        return ApiResponse::success(new VendorBookingVendorDetailResource($bookingVendor));
    }

    /**
     * Vendor-portal 6.11 — lifecycle timeline: booking-level transitions plus
     * this vendor's own item transitions. Other vendors' items are excluded.
     */
    public function timeline(string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::with('items')
            ->where('public_id', $bookingVendorPublicId)
            ->where('vendor_profile_id', $this->vendorProfileId())
            ->firstOrFail();

        $entries = StateTransition::query()
            ->where(fn ($q) => $q
                ->where(fn ($b) => $b
                    ->where('transitionable_type', Booking::class)
                    ->where('transitionable_id', $bookingVendor->booking_id))
                ->orWhere(fn ($i) => $i
                    ->where('transitionable_type', BookingItem::class)
                    ->whereIn('transitionable_id', $bookingVendor->items->pluck('id'))))
            ->orderBy('id')
            ->get()
            ->map(fn ($t): array => [
                'scope' => str_contains((string) $t->transitionable_type, 'BookingItem') ? 'item' : 'booking',
                'from_state' => $t->from_state,
                'to_state' => $t->to_state,
                'trigger_kind' => $t->trigger_kind,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->values();

        return ApiResponse::success($entries);
    }

    public function accept(Request $request, string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::where('public_id', $bookingVendorPublicId)->firstOrFail();

        $result = app(VendorAcceptBookingAction::class)->execute(new VendorAcceptDTO(
            bookingVendorId: (int) $bookingVendor->id,
            vendorProfileId: $this->vendorProfileId(),
            proposedByUserId: (int) auth()->id(),
            idempotencyKey: $this->optionalIdempotencyKey($request),
        ));

        return ApiResponse::success(new BookingVendorResource($result));
    }

    public function modify(VendorModifyRequest $request, string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::where('public_id', $bookingVendorPublicId)->firstOrFail();

        $result = app(VendorModifyBookingAction::class)->execute(new VendorModifyDTO(
            bookingVendorId: (int) $bookingVendor->id,
            vendorProfileId: $this->vendorProfileId(),
            proposedByUserId: (int) auth()->id(),
            proposalKind: ModificationProposalKind::from($request->validated('proposal_kind')),
            changes: $request->validated('changes'),
            vendorExplanation: $request->validated('vendor_explanation'),
            idempotencyKey: $this->optionalIdempotencyKey($request),
            previewToken: $request->input('preview_token'),
        ));

        return ApiResponse::success(new BookingModificationResource($result), [], 201);
    }

    public function previewModification(VendorModifyRequest $request, string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::where('public_id', $bookingVendorPublicId)->firstOrFail();

        $preview = app(PreviewBookingModificationAction::class)->execute(new VendorModifyDTO(
            bookingVendorId: (int) $bookingVendor->id,
            vendorProfileId: $this->vendorProfileId(),
            proposedByUserId: (int) auth()->id(),
            proposalKind: ModificationProposalKind::from($request->validated('proposal_kind')),
            changes: $request->validated('changes'),
            vendorExplanation: $request->validated('vendor_explanation'),
        ));

        return ApiResponse::success($preview);
    }

    public function modifications(string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::where('public_id', $bookingVendorPublicId)
            ->where('vendor_profile_id', $this->vendorProfileId())
            ->firstOrFail();

        $modifications = BookingModification::where('booking_vendor_id', $bookingVendor->id)->latest()->get();

        return ApiResponse::success(BookingModificationResource::collection($modifications));
    }

    public function reject(VendorRejectRequest $request, string $bookingVendorPublicId): JsonResponse
    {
        $bookingVendor = BookingVendor::where('public_id', $bookingVendorPublicId)->firstOrFail();

        $result = app(VendorRejectBookingAction::class)->execute(new VendorRejectDTO(
            bookingVendorId: (int) $bookingVendor->id,
            vendorProfileId: $this->vendorProfileId(),
            proposedByUserId: (int) auth()->id(),
            rejectionReason: $request->validated('rejection_reason'),
            idempotencyKey: $this->optionalIdempotencyKey($request),
        ));

        return ApiResponse::success(new BookingVendorResource($result));
    }
}
