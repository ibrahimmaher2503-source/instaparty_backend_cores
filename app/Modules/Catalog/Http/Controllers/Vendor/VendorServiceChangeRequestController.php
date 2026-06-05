<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\ReplyToServiceChangeClarificationAction;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Http\Requests\Vendor\ReplyServiceChangeClarificationRequest;
use App\Modules\Catalog\Http\Resources\ServiceChangeRequestResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Vendor - Services
 */
class VendorServiceChangeRequestController
{
    public function reply(
        ReplyServiceChangeClarificationRequest $request,
        string $publicId,
        ReplyToServiceChangeClarificationAction $action,
    ): JsonResponse {
        $vendor = $request->user()->vendorProfile()->firstOrFail();

        $cr = ServiceChangeRequest::query()
            ->where('public_id', $publicId)
            ->where('vendor_profile_id', $vendor->id)
            ->firstOrFail();

        $updated = $action->execute($cr, $request->user()->id, $request->input('body'));

        return (new ServiceChangeRequestResource($updated))->response();
    }
}
