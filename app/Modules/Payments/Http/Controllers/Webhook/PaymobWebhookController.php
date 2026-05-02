<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Webhook;

use App\Modules\Payments\Application\Actions\ProcessPaymobWebhookAction;
use App\Modules\Payments\Http\Requests\PaymobWebhookRequest;
use App\Modules\Shared\Http\ApiResponse;

class PaymobWebhookController
{
    public function __invoke(PaymobWebhookRequest $request, ProcessPaymobWebhookAction $action)
    {
        $action->execute($request->validated(), (string) $request->header('X-Paymob-Signature', ''));
        return ApiResponse::success(['ok' => true]);
    }
}
