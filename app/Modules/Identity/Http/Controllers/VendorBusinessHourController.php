<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\UpsertVendorBusinessHoursAction;
use App\Modules\Identity\Http\Requests\UpsertBusinessHoursRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorBusinessHourController extends Controller
{
    public function update(UpsertBusinessHoursRequest $request, UpsertVendorBusinessHoursAction $action): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $hours = $action->execute($vendorProfile, $request->input('hours'));

        return ApiResponse::success(['hours' => $hours]);
    }
}
