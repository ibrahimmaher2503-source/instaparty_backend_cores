<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\VendorResubmitAfterChangesAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\VendorResubmitFormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VendorProfileResubmitController
{
    public function __construct(private VendorResubmitAfterChangesAction $action) {}

    public function store(VendorResubmitFormRequest $request, string $publicId): JsonResponse
    {
        $profile = VendorProfile::wherePublicId($publicId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $this->action->execute(
            $profile,
            $request->input('addressed_item_ids', []),
            $request->input('waived_item_ids', []),
            $request->input('resubmit_notes'),
            $request->header('Idempotency-Key'),
        );

        return response()->json(null, Response::HTTP_OK);
    }
}
