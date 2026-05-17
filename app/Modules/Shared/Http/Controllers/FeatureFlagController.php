<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Actions\GetPublicFeatureFlagsAction;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class FeatureFlagController
{
    /**
     * @group Feature Flags
     *
     * Returns the public-facing feature flags (frontend.* namespace only).
     * Internal flags are never exposed by this endpoint.
     */
    public function index(GetPublicFeatureFlagsAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute())
            ->header('Cache-Control', 'public, max-age=60, must-revalidate');
    }
}
