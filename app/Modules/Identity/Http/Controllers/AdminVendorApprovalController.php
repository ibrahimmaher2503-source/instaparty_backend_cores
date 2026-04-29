<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\ApproveVendorForTypeRequest;
use App\Modules\Identity\Http\Requests\RejectVendorProfileRequest;
use App\Modules\Identity\Http\Requests\RevokeVendorTypeRequest;
use App\Modules\Identity\Http\Resources\VendorProfileResource;
use App\Modules\Shared\Domain\Enums\ProductType;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVendorApprovalController
{
    public function index(Request $request): JsonResponse
    {
        $vendors = VendorProfile::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('approval_status', $status))
            ->with(['user', 'approvedTypes'])
            ->latest()
            ->paginate(20);

        return ApiResponse::success(VendorProfileResource::collection($vendors), $vendors->toArray()['links'] ?? []);
    }

    public function approve(VendorProfile $vendorProfile, ApproveVendorProfileAction $action): JsonResponse
    {
        return ApiResponse::success(new VendorProfileResource($action->execute($vendorProfile)));
    }

    public function reject(VendorProfile $vendorProfile, RejectVendorProfileRequest $request, RejectVendorProfileAction $action): JsonResponse
    {
        return ApiResponse::success(new VendorProfileResource($action->execute($vendorProfile, $request->input('rejection_reason', []))));
    }

    public function approveForType(VendorProfile $vendorProfile, ApproveVendorForTypeRequest $request, ApproveVendorForTypeAction $action): JsonResponse
    {
        $row = $action->execute($vendorProfile, ProductType::from($request->input('product_type')));

        return ApiResponse::success(['product_type' => $row->product_type->value, 'approved_at' => $row->approved_at], status: 200);
    }

    public function revokeType(VendorProfile $vendorProfile, RevokeVendorTypeRequest $request, RevokeVendorTypeAction $action): JsonResponse
    {
        $action->execute($vendorProfile, ProductType::from($request->input('product_type')), $request->input('revoke_reason', []));

        return ApiResponse::success([], status: 204);
    }

    public function suspend(VendorProfile $vendorProfile, SuspendVendorAction $action): JsonResponse
    {
        return ApiResponse::success(new VendorProfileResource($action->execute($vendorProfile)));
    }
}
