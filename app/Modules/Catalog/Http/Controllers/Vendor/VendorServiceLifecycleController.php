<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 4.10 + 4.12 — exposes the existing Filament Actions as REST
 * routes (spec 12 §12 parity table). "Publish" for a vendor means submitting
 * for admin moderation; taking a service offline is the archive (DELETE)
 * flow — there is no separate unpublish state in the locked state machine.
 *
 * @group Vendor - Services
 */
class VendorServiceLifecycleController
{
    public function submitReview(Request $request, string $publicId, SubmitServiceForReviewAction $action): JsonResponse
    {
        $service = $action->execute($this->ownedService($request, $publicId), $request->user()->vendorProfile);

        return ApiResponse::success(['public_id' => $service->public_id, 'status' => $service->status->getValue()]);
    }

    public function clone(Request $request, string $publicId, CloneServiceAction $action): JsonResponse
    {
        $clone = $action->execute($this->ownedService($request, $publicId), $request->user()->vendorProfile);

        return ApiResponse::success(['public_id' => $clone->public_id, 'status' => $clone->status->getValue()], [], 201);
    }

    private function ownedService(Request $request, string $publicId): Service
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return Service::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
