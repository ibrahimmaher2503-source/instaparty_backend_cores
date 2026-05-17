<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Actions\GetBrandingAction;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Shared\Http\Resources\BrandingResource;
use Illuminate\Http\JsonResponse;

class BrandingController
{
    /**
     * @group Theme
     *
     * Get current branding (logo URLs, site name, contact, social).
     *
     * Returns translatable fields resolved against Accept-Language.
     */
    public function show(GetBrandingAction $action): JsonResponse
    {
        $payload = $action->execute(app()->getLocale());

        return ApiResponse::success(new BrandingResource($payload))
            ->header('Cache-Control', 'public, max-age=300, must-revalidate');
    }
}
