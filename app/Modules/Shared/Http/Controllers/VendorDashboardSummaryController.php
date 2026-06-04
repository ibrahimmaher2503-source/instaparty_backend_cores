<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Services\VendorDashboardSummaryService;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorDashboardSummaryController
{
    public function __invoke(Request $request, VendorDashboardSummaryService $service): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return ApiResponse::success($service->summarize($vendorProfile));
    }
}
