<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\DownloadFailedRowsAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Vendor - Excel Import
 */
class DownloadFailedRowsController
{
    public function __invoke(string $importPublicId, DownloadFailedRowsAction $action): BinaryFileResponse|JsonResponse
    {
        $import = ExcelImport::where('public_id', $importPublicId)->firstOrFail();

        abort_unless($import->vendor_profile_id === auth()->user()?->vendorProfile?->id, 403);

        try {
            return $action->execute($import);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
