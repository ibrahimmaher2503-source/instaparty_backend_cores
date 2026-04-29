<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\AddVendorCoverageAreaAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\AddVendorCoverageAreaRequest;
use App\Modules\Identity\Http\Resources\VendorCoverageAreaResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorCoverageAreaController extends Controller
{
    public function store(AddVendorCoverageAreaRequest $request, AddVendorCoverageAreaAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        $area = $action->execute($vendorProfile, $request->validated());

        return ApiResponse::success(new VendorCoverageAreaResource($area), [], 201);
    }

    private function vendorProfileForUser(?User $user): VendorProfile
    {
        if ($user === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $vendorProfile = $user->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendorProfile;
    }
}
