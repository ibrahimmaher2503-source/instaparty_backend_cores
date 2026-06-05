<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\ApproveServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\RejectServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\RequestServiceChangeClarificationAction;
use App\Modules\Catalog\Application\DTOs\DecideServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Http\Requests\Admin\ApproveServiceChangeRequestRequest;
use App\Modules\Catalog\Http\Requests\Admin\RejectServiceChangeRequestRequest;
use App\Modules\Catalog\Http\Requests\Admin\RequestServiceChangeClarificationRequest;
use App\Modules\Catalog\Http\Resources\ServiceChangeRequestResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 */
class AdminServiceChangeRequestController
{
    public function approve(
        ApproveServiceChangeRequestRequest $request,
        ServiceChangeRequest $serviceChangeRequest,
        ApproveServiceChangeRequestAction $action,
    ): JsonResponse {
        $cr = $action->execute($serviceChangeRequest, new DecideServiceChangeRequestDTO(
            adminUserId: $request->user()->id,
            adminNote: $request->input('admin_note'),
            version: $request->integer('version'),
        ));

        return (new ServiceChangeRequestResource($cr))->response();
    }

    public function reject(
        RejectServiceChangeRequestRequest $request,
        ServiceChangeRequest $serviceChangeRequest,
        RejectServiceChangeRequestAction $action,
    ): JsonResponse {
        $cr = $action->execute($serviceChangeRequest, new DecideServiceChangeRequestDTO(
            adminUserId: $request->user()->id,
            adminNote: $request->input('admin_note'),
            version: $request->integer('version'),
        ));

        return (new ServiceChangeRequestResource($cr))->response();
    }

    public function requestClarification(
        RequestServiceChangeClarificationRequest $request,
        ServiceChangeRequest $serviceChangeRequest,
        RequestServiceChangeClarificationAction $action,
    ): JsonResponse {
        $cr = $action->execute($serviceChangeRequest, new DecideServiceChangeRequestDTO(
            adminUserId: $request->user()->id,
            adminNote: $request->input('admin_note'),
            version: $request->integer('version'),
        ));

        return (new ServiceChangeRequestResource($cr))->response();
    }
}
