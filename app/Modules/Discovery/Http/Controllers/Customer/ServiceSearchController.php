<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Http\Requests\SearchServicesRequest;
use App\Modules\Discovery\Http\Resources\ServiceSearchResultResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceSearchController
{
    public function __invoke(SearchServicesRequest $request): JsonResponse
    {
        $dto     = SearchServicesDTO::fromRequest($request);
        $results = app(SearchServicesAction::class)->execute($dto);

        return ApiResponse::success(
            ServiceSearchResultResource::collection($results),
            [
                'total'           => $results->total(),
                'current_page'    => $results->currentPage(),
                'per_page'        => $results->perPage(),
                'last_page'       => $results->lastPage(),
                'query'           => $dto->query,
                'filters_applied' => array_filter([
                    'type'     => $dto->type?->value,
                    'occasion' => $dto->occasionCode,
                ]),
            ],
        );
    }
}
