<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Webhook;

use App\Modules\Payments\Application\Actions\ProcessPaymobWebhookAction;
use App\Modules\Payments\Http\Requests\PaymobWebhookRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymobWebhookController
{
    public function __invoke(PaymobWebhookRequest $request, ProcessPaymobWebhookAction $action): JsonResponse
    {
        try {
            $action->execute($request->json()->all(), (string) $request->header('HMAC', ''));
        } catch (Throwable $e) {
            if ($e->getCode() === 401 || str_contains($e->getMessage(), 'Invalid signature')) {
                return ApiResponse::error(['message' => 'Invalid signature'], 401);
            }
            throw $e;
        }

        return ApiResponse::success(['ok' => true]);
    }
}
