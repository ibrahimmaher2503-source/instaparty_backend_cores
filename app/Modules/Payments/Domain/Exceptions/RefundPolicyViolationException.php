<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Exceptions;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RefundPolicyViolationException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly string $reasonMessageKey,
    ) {
        parent::__construct($reasonCode);
    }

    public function render(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        return ApiResponse::error(
            ['code' => $this->reasonCode, 'message' => __($this->reasonMessageKey, [], $locale)],
            422,
            ['locale' => $locale],
        );
    }

    private function resolveLocale(Request $request): string
    {
        $accept = (string) $request->header('Accept-Language', '');
        if (str_starts_with(strtolower($accept), 'ar')) {
            return 'ar';
        }

        return app()->getLocale();
    }
}
