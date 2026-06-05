<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Http\Resources\VendorProfileResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Vendor Browsing
 */
class VendorProfileIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 6), 24);

        $vendors = VendorProfile::with(['primaryCity', 'approvedTypes', 'user'])
            ->withCount(['services' => function ($q): void {
                $q->whereNull('deleted_at');
            }])
            ->whereState('approval_status', ApprovedState::class)
            ->orderByDesc('rating_avg')
            ->limit($perPage)
            ->get();

        return ApiResponse::success(VendorProfileResource::collection($vendors));
    }
}
