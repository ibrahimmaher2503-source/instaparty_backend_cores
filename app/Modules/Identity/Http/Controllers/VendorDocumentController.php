<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\UploadVendorDocumentRequest;
use App\Modules\Identity\Http\Resources\VendorDocumentResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group Vendor - Compliance
 */
class VendorDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());

        return ApiResponse::success(VendorDocumentResource::collection($vendorProfile->documents));
    }

    public function store(UploadVendorDocumentRequest $request, UploadVendorDocumentAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        $document = $action->execute(
            $vendorProfile,
            $request->file('file'),
            DocumentType::from($request->validated('doc_type')),
        );

        return ApiResponse::success(new VendorDocumentResource($document), [], 201);
    }

    /** Vendor-portal 3.4 — delete a document (rejected-only per ADR-0021; existing action). */
    public function destroy(Request $request, string $publicId, DeleteVendorDocumentAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        /** @var VendorDocument|null $document */
        $document = $vendorProfile->documents()->where('public_id', $publicId)->first();

        if ($document === null) {
            throw new NotFoundHttpException('Document not found.');
        }

        $action->execute($vendorProfile, $document);

        return ApiResponse::success(null);
    }

    public function signedUrl(Request $request, string $publicId, GenerateDocumentSignedUrlAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        /** @var VendorDocument|null $document */
        $document = $vendorProfile->documents()->where('public_id', $publicId)->first();

        if ($document === null) {
            throw new NotFoundHttpException('Document not found.');
        }

        return ApiResponse::success(['url' => $action->execute($document), 'expires_in_seconds' => 900]);
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
