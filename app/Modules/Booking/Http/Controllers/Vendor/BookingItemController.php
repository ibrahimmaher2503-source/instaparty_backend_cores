<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Vendor;

use App\Modules\Booking\Application\Actions\Fulfillment\MarkCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkInProgressAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkReadyAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Exceptions\PaymentNotCapturedException;
use App\Modules\Booking\Domain\Exceptions\RefundInProgressException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Http\Requests\VendorTransitionBookingItemRequest;
use App\Modules\Booking\Http\Resources\BookingItemResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookingItemController
{
    public function show(Request $request, string $bookingItemPublicId): JsonResponse
    {
        $item = $this->ownedItem($request, $bookingItemPublicId);

        return ApiResponse::success(new BookingItemResource($item));
    }

    public function transition(VendorTransitionBookingItemRequest $request, string $bookingItemPublicId): JsonResponse
    {
        $item = $this->ownedItem($request, $bookingItemPublicId);
        $vendor = $this->vendorProfile($request);
        $actor = $request->user();

        $evidence = new FulfillmentEvidenceDto(
            completionNote: $request->validated('completion_note'),
            completionPhoto: $request->file('completion_photo'),
        );

        try {
            $item = match ($request->validated('lane')) {
                'preparing' => app(MarkPreparingAction::class)->execute($item, $vendor, $actor),
                'ready' => app(MarkReadyAction::class)->execute($item, $vendor, $actor),
                'in_progress' => app(MarkInProgressAction::class)->execute($item, $vendor, $actor),
                'completed' => app(MarkCompletedAction::class)->execute($item, $vendor, $actor, $evidence),
            };
        } catch (LaneNotSupportedForTypeException|CouldNotPerformTransition $e) {
            return ApiResponse::error(__('booking::booking.errors.invalid_transition'), 422);
        } catch (PaymentNotCapturedException|BookingNotEligibleForFulfillmentException|RefundInProgressException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(new BookingItemResource($item));
    }

    private function ownedItem(Request $request, string $publicId): BookingItem
    {
        return BookingItem::query()
            ->where('public_id', $publicId)
            ->whereHas('bookingVendor', fn ($q) => $q->where('vendor_profile_id', $this->vendorProfile($request)->id))
            ->firstOrFail();
    }

    private function vendorProfile(Request $request): VendorProfile
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendor;
    }
}
