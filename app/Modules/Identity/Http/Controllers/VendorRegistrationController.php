<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\RegisterVendorAction;
use App\Modules\Identity\Http\Requests\RegisterVendorRequest;
use App\Modules\Identity\Http\Resources\VendorProfileResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class VendorRegistrationController extends Controller
{
    public function register(RegisterVendorRequest $request, RegisterVendorAction $action): JsonResponse
    {
        $vendorProfile = $action->execute($request->toDTO());

        return ApiResponse::success(new VendorProfileResource($vendorProfile), [], 201);
    }
}
