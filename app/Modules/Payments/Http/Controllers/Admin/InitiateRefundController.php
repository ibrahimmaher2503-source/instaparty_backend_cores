<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Admin;

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Http\Requests\InitiateRefundRequest;
use App\Modules\Shared\Http\ApiResponse;

class InitiateRefundController
{
    public function __invoke(InitiateRefundRequest $request, string $bookingPublicId, InitiateRefundAction $action)
    {
        $booking = app(\App\Modules\Payments\Domain\Contracts\PaymentsBookingReader::class)->findByPublicId($bookingPublicId) ?? abort(404); $payment = Payment::query()->where('booking_id', $booking->id)->where('status', 'captured')->latest('id')->firstOrFail();
        return ApiResponse::success($action->execute(new InitiateRefundDto($payment->id, $booking->id, (int) $request->user()->id, RefundReasonCode::from((string) $request->string('reason_code')), (array) $request->input('reason_notes'), (string) $request->header('Idempotency-Key'), 'refunds.initiate')), ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'], 201);
    }
}
