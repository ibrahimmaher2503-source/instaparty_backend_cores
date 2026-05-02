<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Modules\Payments\Application\Actions\InitiatePaymentAction;
use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Shared\Http\ApiResponse;
use Brick\Money\Money;

class InitiatePaymentController
{
    public function __invoke(InitiatePaymentRequest $request, string $bookingPublicId, InitiatePaymentAction $action)
    {
        $booking = app(\App\Modules\Payments\Domain\Contracts\PaymentsBookingReader::class)->findByPublicId($bookingPublicId) ?? abort(404);
        return ApiResponse::success($action->execute(new InitiatePaymentDto($booking->id, (int) $request->user()->id, PaymentMethod::from((string) $request->string('method')), Money::ofMinor((int) $booking->totalMinor, $booking->totalCurrency), (string) $request->header('Idempotency-Key'), 'payments.initiate')), ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr'], 201);
    }
}
