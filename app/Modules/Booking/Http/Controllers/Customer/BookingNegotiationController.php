<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\DTOs\CoverageMinimumFailureDTO;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Exceptions\BelowCoverageMinimumException;
use App\Modules\Booking\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Booking\Domain\Exceptions\DeliveryCityRequiredException;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Http\Requests\CustomerModificationDecisionRequest;
use App\Modules\Booking\Http\Requests\SubmitBookingRequest;
use App\Modules\Booking\Http\Resources\BookingModificationResource;
use App\Modules\Booking\Http\Resources\BookingResource;
use App\Modules\Shared\Http\ApiResponse;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;

class BookingNegotiationController
{
    public function submit(SubmitBookingRequest $request, string $bookingPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null, 404);

        try {
            $result = app(SubmitBookingAction::class)->execute(
                new SubmitBookingDTO((int) $booking->id, (int) auth()->id(), $request->idempotencyKey(), $request->getContent())
            );
        } catch (BelowCoverageMinimumException $e) {
            return $this->respondBelowCoverageMinimum($e);
        } catch (DeliveryCityRequiredException) {
            return ApiResponse::error(
                [
                    'code' => 'delivery_city_required',
                    'message' => trans('booking::booking.errors.delivery_city_required.message'),
                ],
                422
            );
        } catch (CurrencyMismatchException) {
            return ApiResponse::error(
                [
                    'code' => 'currency_mismatch',
                    'message' => trans('booking::booking.errors.currency_mismatch.message'),
                ],
                422
            );
        }

        return ApiResponse::success(new BookingResource($result->load(['vendors', 'vendors.vendor'])));
    }

    private function respondBelowCoverageMinimum(BelowCoverageMinimumException $e): JsonResponse
    {
        $locale = app()->getLocale();

        $details = array_map(function (CoverageMinimumFailureDTO $failure) use ($locale): array {
            $businessName = $failure->vendorBusinessName[$locale]
                ?? $failure->vendorBusinessName['en']
                ?? $failure->vendorBusinessName['ar']
                ?? '';

            $fmt = fn (int $minor, string $currency): string => Money::ofMinor($minor, $currency)->formatTo($locale);

            return [
                'vendor_public_id' => $failure->vendorPublicId,
                'vendor_business_name' => $businessName,
                'min_order_minor' => $failure->minOrderMinor,
                'min_order_currency' => $failure->minOrderCurrency,
                'min_order_formatted' => $fmt($failure->minOrderMinor, $failure->minOrderCurrency),
                'current_subtotal_minor' => $failure->currentSubtotalMinor,
                'current_subtotal_formatted' => $fmt($failure->currentSubtotalMinor, $failure->minOrderCurrency),
                'shortfall_minor' => $failure->shortfallMinor,
                'shortfall_formatted' => $fmt($failure->shortfallMinor, $failure->minOrderCurrency),
            ];
        }, $e->failures());

        return ApiResponse::error(
            [
                'code' => 'below_coverage_minimum',
                'message' => trans('booking::booking.errors.below_coverage_minimum.message'),
                'details' => $details,
            ],
            422
        );
    }

    public function listModifications(string $bookingPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== (int) auth()->id(), 404);

        $modifications = BookingModification::whereHas(
            'bookingVendor',
            fn ($q) => $q->where('booking_id', $booking->id)
        )->latest()->get();

        return ApiResponse::success(BookingModificationResource::collection($modifications));
    }

    public function showModification(string $bookingPublicId, string $modificationPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== (int) auth()->id(), 404);

        $modification = BookingModification::whereHas(
            'bookingVendor',
            fn ($q) => $q->where('booking_id', $booking->id)
        )->where('public_id', $modificationPublicId)->firstOrFail();

        return ApiResponse::success(new BookingModificationResource($modification));
    }

    public function decideModification(
        CustomerModificationDecisionRequest $request,
        string $bookingPublicId,
        string $modificationPublicId,
    ): JsonResponse {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null, 404);

        $result = app(CustomerConfirmModifiedBookingAction::class)->execute(
            new CustomerModificationDecisionDTO(
                (int) $booking->id,
                (int) auth()->id(),
                $modificationPublicId,
                $request->validated('decision'),
                $request->idempotencyKey(),
            )
        );

        return ApiResponse::success(new BookingResource($result));
    }
}
