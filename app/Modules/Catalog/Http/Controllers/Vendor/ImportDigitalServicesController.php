<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
use App\Modules\Catalog\Http\Requests\ImportDigitalServicesRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @response 200 {"data":{"status":"completed","imported_rows":2,"total_rows":2},"meta":{},"errors":null}
 * @response 422 {"data":{"status":"failed","imported_rows":0,"total_rows":1,"errors":[{"row":2,"field":"delivery_method","message":{"en":"The selected delivery method is invalid.","ar":"قيمة طريقة التسليم غير صالحة."}}]},"meta":{},"errors":null}
 * @response 403 {"data":null,"meta":{},"errors":{"message":"store_not_owned"}}
 * @response 401 {"message":"Unauthenticated."}
 */
class ImportDigitalServicesController
{
    public function store(ImportDigitalServicesRequest $request, ImportDigitalServicesFromExcelAction $action): JsonResponse
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
