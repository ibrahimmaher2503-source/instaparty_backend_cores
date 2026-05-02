<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Shared\Http\ApiResponse;

class ShowPaymentController
{
    public function __invoke(string $paymentPublicId)
    {
        return ApiResponse::success(new PaymentResource(Payment::query()->where('public_id', $paymentPublicId)->firstOrFail()), ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr']);
    }
}
