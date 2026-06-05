<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\RequestVendorChangesAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\RequestVendorChangesFormRequest;
use App\Modules\Shared\Http\Resources\ChangeRequestResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 */
class VendorChangeRequestController
{
    public function __construct(private RequestVendorChangesAction $action) {}

    public function store(RequestVendorChangesFormRequest $request, string $publicId): JsonResponse
    {
        $profile = VendorProfile::wherePublicId($publicId)->firstOrFail();
        $changeRequest = $this->action->execute($profile, $request->input('items'), $request->user(), $request->header('Idempotency-Key'));

        return response()->json(new ChangeRequestResource($changeRequest), 201);
    }
}
