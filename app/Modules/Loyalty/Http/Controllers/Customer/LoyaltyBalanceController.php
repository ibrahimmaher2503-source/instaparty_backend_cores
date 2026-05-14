<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use App\Modules\Loyalty\Http\Resources\LoyaltyBalanceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyBalanceController
{
    public function __construct(
        private readonly BalanceCalculator $balance,
        private readonly LoyaltyProgramRepository $programs,
        private readonly VendorLookup $vendors,
    ) {}

    public function show(Request $request, string $vendorPublicId): JsonResponse
    {
        $program = $this->programs->findByPublicId($vendorPublicId);
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));
        abort_unless($this->vendors->isApproved((int) $program->vendor_profile_id), 404);

        $available = $this->balance->availableFor((int) $request->user()->id, (int) $program->vendor_profile_id);

        return ApiResponse::success((new LoyaltyBalanceResource([
            'vendor_public_id' => (string) $program->public_id,
            'vendor_name' => $program->getTranslations('name'),
            'available_points' => $available,
            'held_points' => 0,
            'total_points' => $available,
        ]))->resolve($request));
    }
}
