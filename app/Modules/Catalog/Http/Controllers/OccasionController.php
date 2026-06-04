<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Http\Resources\OccasionResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class OccasionController
{
    public function index(ListPublicOccasionsAction $action): JsonResponse
    {
        return ApiResponse::success(OccasionResource::collection($action->execute()));
    }

    public function show(string $slug): JsonResponse
    {
        $occasion = Occasion::query()
            ->where('code', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return ApiResponse::success(new OccasionResource($occasion));
    }
}
