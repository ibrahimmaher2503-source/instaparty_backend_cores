<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Application\Actions\GetHomepageBlocksAction;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class HomepageController
{
    /**
     * @group CMS
     *
     * Get the ordered list of visible homepage blocks for the current locale.
     */
    public function index(GetHomepageBlocksAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute(app()->getLocale()))
            ->header('Cache-Control', 'public, max-age=60, must-revalidate');
    }
}
