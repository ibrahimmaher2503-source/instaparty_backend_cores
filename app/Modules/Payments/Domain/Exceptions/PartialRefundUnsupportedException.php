<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Exceptions;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PartialRefundUnsupportedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Partial refunds are Phase 2.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            ['code' => 'partial_refund_unsupported', 'message' => __('payments::refunds.errors.partial_refund_unsupported')],
            422,
            ['locale' => app()->getLocale()],
        );
    }
}
