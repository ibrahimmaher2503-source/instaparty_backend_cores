<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Admin;

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Http\Requests\InitiateRefundRequest;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class InitiateRefundController
{
    public function __invoke(
        InitiateRefundRequest $request,
        string $bookingPublicId,
        InitiateRefundAction $action,
        PaymentsBookingReader $bookingReader,
        EloquentPaymentRepository $payments,
    ): JsonResponse {
        $booking = $bookingReader->findByPublicId($bookingPublicId) ?? abort(404, 'Booking not found');
        $payment = $payments->findActiveCaptureForBooking($booking->id) ?? abort(409, 'No captured payment to refund');
        $refund = $action->execute(new InitiateRefundDto(
            paymentId: $payment->id,
            bookingId: $booking->id,
            initiatedBy: (int) $request->user()->id,
            reasonCode: RefundReasonCode::from((string) $request->string('reason_code')),
            reasonNotes: (array) $request->input('reason_notes'),
            requestedAmountMinor: $request->has('amount_minor') ? (int) $request->integer('amount_minor') : null,
        ));

        return ApiResponse::success(
            (new RefundResource($refund))->toArray($request),
            ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'],
            201,
        );
    }
}
