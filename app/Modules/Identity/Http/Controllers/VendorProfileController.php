<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\UpdateVendorProfileRequest;
use App\Modules\Identity\Http\Resources\VendorProfileResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());

        return ApiResponse::success(new VendorProfileResource($vendorProfile->load('approvedTypes')));
    }

    public function update(UpdateVendorProfileRequest $request, UpdateVendorProfileAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());

        return ApiResponse::success(new VendorProfileResource($action->execute($vendorProfile, $request->validated())));
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
