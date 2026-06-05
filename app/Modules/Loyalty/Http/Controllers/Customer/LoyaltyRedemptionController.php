<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Application\Actions\ApplyRedemptionToBookingAction;
use App\Modules\Loyalty\Application\Actions\VoidRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Http\Requests\Customer\ApplyRedemptionRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyRedemptionResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Loyalty
 */
class LoyaltyRedemptionController
{
    public function __construct(
        private readonly ApplyRedemptionToBookingAction $apply,
        private readonly VoidRedemptionAction $void,
        private readonly LoyaltyRedemptionRepository $redemptions,
    ) {}

    public function store(ApplyRedemptionRequest $request, string $bookingPublicId): JsonResponse
    {
        $redemption = $this->apply->execute(
            (int) $request->user()->id,
            $bookingPublicId,
            (int) $request->validated('points'),
        );

        return ApiResponse::success(
            (new LoyaltyRedemptionResource($redemption))->resolve($request),
            status: 201,
        );
    }

    public function destroy(Request $request, string $bookingPublicId, string $redemptionPublicId): JsonResponse
    {
        $redemption = $this->redemptions->findByPublicId($redemptionPublicId);
        abort_if($redemption === null, 404, __('loyalty::loyalty.errors.redemption_not_found'));
        abort_if((int) $redemption->user_id !== (int) $request->user()->id, 403);

        $this->void->execute($redemption);

        return ApiResponse::success(['voided' => true]);
    }
}
