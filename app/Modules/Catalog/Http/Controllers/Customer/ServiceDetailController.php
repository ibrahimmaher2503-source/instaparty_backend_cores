<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Customer;

use App\Modules\Catalog\Application\Actions\ShowPublishedServiceAction;
use App\Modules\Catalog\Http\Resources\CustomerServiceDetailResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceDetailController
{
    /**
     * @group Customer - Catalog
     *
     * Show a published customer-facing service detail by public ULID.
     */
    public function __invoke(string $servicePublicId, ShowPublishedServiceAction $action): JsonResponse
    {
        $service = $action->execute($servicePublicId);

        return ApiResponse::success(new CustomerServiceDetailResource($service), [
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        ]);
    }
}
