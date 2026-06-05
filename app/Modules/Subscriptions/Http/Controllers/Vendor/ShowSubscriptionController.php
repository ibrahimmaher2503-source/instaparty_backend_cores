<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\Vendor;

use App\Modules\Subscriptions\Application\Actions\AutoEnrolFreeTierAction;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionRepository;
use App\Modules\Subscriptions\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Vendor - Account
 */
class ShowSubscriptionController
{
    public function __construct(
        private readonly SubscriptionRepository $repository,
        private readonly AutoEnrolFreeTierAction $autoEnrol,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $vendorProfileId = (int) $request->user()->vendorProfile?->id;

        abort_if($vendorProfileId === 0, 403, 'No vendor profile attached to user.');

        $subscription = $this->repository->currentForVendor($vendorProfileId)
            ?? $this->autoEnrol->execute($vendorProfileId);

        return response()->json([
            'data' => (new SubscriptionResource($subscription->load('plan.features')))->toArray($request),
            'meta' => [],
            'errors' => [],
        ]);
    }
}
