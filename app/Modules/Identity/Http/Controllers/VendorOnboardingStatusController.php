<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 1.4 mobile parity ruling (C)-lite — the registration
 * "complete" step becomes resumable: the existing onboarding checklist
 * (spec 034) tells mobile exactly which step to resume at. No multi-step
 * registration redesign needed.
 */
class VendorOnboardingStatusController
{
    public function __invoke(Request $request, VendorOnboardingChecklistService $checklist): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $dto = $checklist->forVendor($vendor);

        return ApiResponse::success([
            'is_suspended' => $dto->isSuspended,
            'rejection_reason' => $dto->rejectionReason,
            'items' => collect($dto->items)->map(fn ($item): array => [
                'key' => $item->key->value,
                'status' => $item->status->value,
                'label' => $item->label,
                'sub_text' => $item->subText,
            ])->values()->all(),
        ]);
    }
}
