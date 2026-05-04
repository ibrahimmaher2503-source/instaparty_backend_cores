<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Application\Actions\ApplyRedemptionToBookingAction;
use App\Modules\Loyalty\Application\Actions\FinalizeRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Http\Requests\Customer\ApplyRedemptionRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyRedemptionResource;
use Illuminate\Http\JsonResponse;

class LoyaltyRedemptionController
{
    public function __construct(
        private readonly ApplyRedemptionToBookingAction $apply,
        private readonly FinalizeRedemptionAction $finalize,
        private readonly LoyaltyRedemptionRepository $redemptions,
    ) {}

    public function store(ApplyRedemptionRequest $request, string $bookingPublicId): JsonResponse
    {
        $redemption = $this->apply->execute(auth()->id(), $bookingPublicId, $request->validated('points'));

        return (new LoyaltyRedemptionResource($redemption))->response()->setStatusCode(201);
    }

    public function destroy(string $bookingPublicId, string $redemptionPublicId): JsonResponse
    {
        $redemption = $this->redemptions->findByPublicId($redemptionPublicId);
        abort_if($redemption === null, 404, __('loyalty::loyalty.errors.redemption_not_found'));
        abort_if($redemption->customer_id !== auth()->id(), 403);

        $this->finalize->voidPending($redemption);

        return response()->json(['data' => ['voided' => true]]);
    }
}
