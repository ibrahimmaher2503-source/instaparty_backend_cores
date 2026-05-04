<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\ImportSaleServicesFromExcelAction;
use App\Modules\Catalog\Http\Requests\ImportSaleServicesRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @response 200 {"data":{"status":"completed","imported_rows":3,"total_rows":3},"meta":{},"errors":null}
 * @response 422 {"data":{"status":"failed","imported_rows":0,"total_rows":2,"errors":[{"row":3,"field":"name_en","message":{"en":"The name en field is required.","ar":"حقل الاسم بالإنجليزية مطلوب."}}]},"meta":{},"errors":null}
 * @response 403 {"data":null,"meta":{},"errors":{"message":"store_not_owned"}}
 * @response 401 {"message":"Unauthenticated."}
 */
class ImportSaleServicesController
{
    public function store(ImportSaleServicesRequest $request, ImportSaleServicesFromExcelAction $action): JsonResponse
    {
        $vendor = $request->vendorProfile();
        $import = $action->execute($request->file('file'), $vendor->id, app()->getLocale());

        if ($import->status === 'completed') {
            return ApiResponse::success([
                'status' => 'completed',
                'imported_rows' => $import->imported_rows,
                'total_rows' => $import->total_rows,
            ]);
        }

        return ApiResponse::success([
            'status' => 'failed',
            'imported_rows' => $import->imported_rows,
            'total_rows' => $import->total_rows,
            'errors' => $import->errors()->get()->map(fn ($e) => [
                'row' => $e->row_number,
                'field' => $e->field,
                'message' => $e->message,
            ])->values()->all(),
        ], [], 422);
    }
}
