<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 5.3 — import status polling for web + mobile progress UI.
 */
class VendorImportStatusController
{
    public function __invoke(Request $request, string $importPublicId): JsonResponse
    {
        $vendorProfile = $request->user()?->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $import = ExcelImport::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('public_id', $importPublicId)
            ->firstOrFail();

        return ApiResponse::success([
            'public_id' => $import->public_id,
            'product_type' => $import->product_type->value,
            'status' => $import->status,
            'original_filename' => $import->original_filename,
            'total_rows' => (int) $import->total_rows,
            'imported_rows' => (int) $import->imported_rows,
            'error_rows' => (int) $import->error_rows,
            'created_at' => $import->created_at?->toIso8601String(),
        ]);
    }
}
