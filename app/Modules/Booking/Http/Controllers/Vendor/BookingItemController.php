<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Vendor;

use App\Modules\Booking\Application\Actions\Fulfillment\MarkCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkInProgressAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkReadyAction;
use App\Modules\Booking\Application\Actions\Fulfillment\ReportFulfillmentIssueAction;
use App\Modules\Booking\Application\Actions\Fulfillment\UploadConditionPhotosAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentIssueDto;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueReason;
use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Exceptions\PaymentNotCapturedException;
use App\Modules\Booking\Domain\Exceptions\RefundInProgressException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Http\Requests\VendorTransitionBookingItemRequest;
use App\Modules\Booking\Http\Requests\VendorUploadConditionPhotosRequest;
use App\Modules\Booking\Http\Resources\BookingItemResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group Vendor - Fulfillment
 */
class BookingItemController
{
    public function show(Request $request, string $bookingItemPublicId): JsonResponse
    {
        $item = $this->ownedItem($request, $bookingItemPublicId);

        return ApiResponse::success(new BookingItemResource($item));
    }

    public function conditionPhotos(
        VendorUploadConditionPhotosRequest $request,
        string $bookingItemPublicId,
    ): JsonResponse {
        $item = $this->ownedItem($request, $bookingItemPublicId);

        try {
            $media = app(UploadConditionPhotosAction::class)
                ->execute($item, $request->user(), $request->validated('phase'), $request->file('photos', []));
        } catch (LaneNotSupportedForTypeException) {
            return ApiResponse::error(__('booking::booking.errors.condition_photos_rental_only'), 422);
        }

        return ApiResponse::success([
            'phase' => $request->validated('phase'),
            'uploaded' => count($media),
        ], status: 201);
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

    /** Vendor-portal 6.12 — escalate a fulfillment issue to admin (REST for the existing Filament Action). */
    public function reportIssue(Request $request, string $bookingItemPublicId): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'in:venue_unavailable,customer_unreachable,damaged_goods,safety_concern,other'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $item = $this->ownedItem($request, $bookingItemPublicId);

        try {
            $issue = app(ReportFulfillmentIssueAction::class)->execute(
                $item,
                $this->vendorProfile($request),
                $request->user(),
                new FulfillmentIssueDto(
                    reasonCode: FulfillmentIssueReason::from($validated['reason_code']),
                    note: $validated['note'],
                ),
            );
        } catch (PaymentNotCapturedException $e) {
            // Same contract as the transition endpoint above.
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(['public_id' => $issue->public_id, 'status' => 'reported'], status: 201);
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
