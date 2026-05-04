<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionLimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly string $featureKey,
        public readonly int $currentCount,
        public readonly int $limit,
        public readonly string $unblockingPlanCode,
        public readonly string $currentPlanCode,
    ) {
        parent::__construct(
            "Subscription limit reached for feature [{$featureKey}]: {$currentCount}/{$limit}. Upgrade to [{$unblockingPlanCode}]."
        );
    }

    public function render(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language', 'en');
        app()->setLocale(in_array($locale, ['en', 'ar']) ? $locale : 'en');

        return response()->json([
            'data'   => null,
            'meta'   => [],
            'errors' => [
                [
                    'code'    => 'subscription_limit_reached',
                    'message' => trans('subscriptions::subscription.errors.limit_reached', [
                        'feature'          => $this->featureKey,
                        'plan'             => $this->currentPlanCode,
                        'unblocking_plan'  => $this->unblockingPlanCode,
                    ]),
                ],
            ],
        ], 422);
    }
}
