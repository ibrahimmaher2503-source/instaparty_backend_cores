<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Admin;

use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Http\Resources\RefundResource;
use App\Modules\Shared\Http\ApiResponse;

class ShowRefundController
{
    public function __invoke(string $refundPublicId)
    {
        return ApiResponse::success(new RefundResource(Refund::query()->where('public_id', $refundPublicId)->firstOrFail()), ['locale' => app()->getLocale(), 'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr']);
    }
}
