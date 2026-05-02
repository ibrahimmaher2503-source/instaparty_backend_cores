<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Modules\Payments\Application\Actions\InitiatePaymentAction;
use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Shared\Http\ApiResponse;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;

class InitiatePaymentController
{
    public function __invoke(
        InitiatePaymentRequest $request,
        string $bookingPublicId,
        InitiatePaymentAction $action,
        PaymentsBookingReader $bookingReader,
    ): JsonResponse {
        $booking = $bookingReader->findByPublicId($bookingPublicId) ?? abort(404);
        abort_if($booking->customerId !== (int) $request->user()->id, 403, 'Booking belongs to another customer');
        $payload = $action->execute(new InitiatePaymentDto(
            bookingId: $booking->id,
            payerId: (int) $request->user()->id,
            method: PaymentMethod::from((string) $request->string('method')),
            amount: Money::ofMinor((int) $booking->totalMinor, $booking->totalCurrency),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            route: 'payments.initiate',
        ));

        return ApiResponse::success(
            $payload,
            ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'],
            201,
        );
    }
}
