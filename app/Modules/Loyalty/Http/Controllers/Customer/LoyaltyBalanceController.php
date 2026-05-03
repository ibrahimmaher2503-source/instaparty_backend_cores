<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use App\Modules\Loyalty\Http\Resources\LoyaltyBalanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyBalanceController
{
    public function __construct(
        private readonly BalanceCalculator $balance,
        private readonly LoyaltyProgramRepository $programs,
    ) {}

    public function show(Request $request, string $vendorPublicId): JsonResponse
    {
        $program = $this->programs->findByPublicId($vendorPublicId);
        abort_if($program === null, 404, __('loyalty::loyalty.errors.program_not_found'));

        $customerId = auth()->id();
        $vendorProfileId = $program->vendor_profile_id;
        $available = $this->balance->availableFor($customerId, $vendorProfileId);
        $total = \App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry::where('customer_id', $customerId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->sum('points');
        $held = $total - $available;

        return (new LoyaltyBalanceResource([
            'vendor_public_id' => $program->public_id,
            'vendor_name'      => $program->name,
            'available_points' => $available,
            'held_points'      => max(0, $held),
            'total_points'     => max(0, $total),
        ]))->response();
    }
}
